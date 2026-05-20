<?php

namespace App\Controller;

use App\Entity\User;
use App\Repository\UserRepository;
use App\Service\ActivityLogService;
use App\Service\GoogleTokenVerifierService;
use Doctrine\ORM\EntityManagerInterface;
use Lexik\Bundle\JWTAuthenticationBundle\Services\JWTTokenManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\Routing\Attribute\Route;

class ApiGoogleLoginController extends AbstractController
{
    public function __construct(
        private GoogleTokenVerifierService $tokenVerifier,
        private UserRepository $userRepository,
        private JWTTokenManagerInterface $jwtManager,
        private EntityManagerInterface $entityManager,
        private UserPasswordHasherInterface $passwordHasher,
        private ActivityLogService $activityLogService,
    ) {
    }

    #[Route('/api/google-login', name: 'api_google_login', methods: ['POST'])]
    public function googleLogin(Request $request): Response
    {
        try {
            $data = $request->toArray();

            // Validate request
            if (!isset($data['token'])) {
                return new JsonResponse([
                    'message' => 'Google ID token is required.',
                ], Response::HTTP_BAD_REQUEST);
            }

            $googleToken = $data['token'];

            // Verify the Google token
            $tokenData = $this->tokenVerifier->verifyToken($googleToken);

            if (!$tokenData) {
                return new JsonResponse([
                    'message' => 'Invalid Google token. Use a fresh ID token (not the access token), request openid+email+profile scopes, and sign in with the same OAuth client ID as OAUTH_GOOGLE_CLIENT_ID in .env.',
                ], Response::HTTP_UNAUTHORIZED);
            }

            // Extract Google user data
            $googleId = $tokenData['sub'];
            $email = $tokenData['email'] ?? $tokenData['email_verified'] ?? null;
            $name = $tokenData['name'] ?? '';
            $givenName = $tokenData['given_name'] ?? '';
            $familyName = $tokenData['family_name'] ?? '';
            $picture = $tokenData['picture'] ?? null;
            
            // If no single name, try to construct from given/family
            if (empty($name) && (!empty($givenName) || !empty($familyName))) {
                $name = trim($givenName . ' ' . $familyName);
            }

            // Email is required for user creation
            if (!$email) {
                return new JsonResponse([
                    'message' => 'Google token does not contain email information.',
                ], Response::HTTP_BAD_REQUEST);
            }

            // Check if user exists
            $user = $this->userRepository->findOneBy(['email' => $email]);

            // Create new user if doesn't exist
            if (!$user instanceof User) {
                $user = new User();
                $user->setEmail($email);
                
                // Generate a unique username from email
                $baseUsername = explode('@', $email)[0];
                $username = $baseUsername;
                $counter = 1;
                
                while ($this->userRepository->findOneBy(['username' => $username])) {
                    $username = $baseUsername . $counter;
                    $counter++;
                }
                
                $user->setUsername($username);
                
                // Set a random password (users won't login with password via Google)
                $randomPassword = bin2hex(random_bytes(16));
                $hashedPassword = $this->passwordHasher->hashPassword($user, $randomPassword);
                $user->setPassword($hashedPassword);
                
                // Set user as verified (Google verified the email)
                $user->setIsVerified(true);
                
                // Set default role
                $user->setRoles(['ROLE_USER']);
                
                $this->entityManager->persist($user);
                $this->entityManager->flush();
            } elseif (!$user->isActive()) {
                return new JsonResponse([
                    'message' => 'User account is inactive.',
                ], Response::HTTP_FORBIDDEN);
            }

            // Generate JWT token
            $token = $this->jwtManager->create($user);

            // Log successful Google login
            $this->activityLogService->logLogin($user, 'Google OAuth');

            return new JsonResponse([
                'token' => $token,
                'user' => [
                    'id' => $user->getId(),
                    'username' => $user->getUsername(),
                    'name' => $name,
                    'email' => $user->getEmail(),
                ],
            ], Response::HTTP_OK);

        } catch (\Exception $e) {
            return new JsonResponse([
                'message' => 'Google login failed: ' . $e->getMessage(),
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }
}
