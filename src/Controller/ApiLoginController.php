<?php

namespace App\Controller;

use App\Entity\User;
use App\Repository\UserRepository;
use App\Service\ActivityLogService;
use Lexik\Bundle\JWTAuthenticationBundle\Services\JWTTokenManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\Routing\Attribute\Route;

class ApiLoginController extends AbstractController
{
    public function __construct(
        private UserRepository $userRepository,
        private UserPasswordHasherInterface $passwordHasher,
        private JWTTokenManagerInterface $jwtManager,
        private ActivityLogService $activityLogService,
    ) {
    }

    #[Route('/api/login', name: 'api_login', methods: ['POST'])]
    public function login(Request $request): Response
    {
        $data = $request->toArray();

        $identifier = $data['username'] ?? $data['email'] ?? null;
        $password = $data['password'] ?? null;

        if (!$identifier || !$password) {
            return new JsonResponse([
                'message' => 'Missing credentials.',
            ], Response::HTTP_BAD_REQUEST);
        }

        // Allow login via username or email
        if (filter_var($identifier, FILTER_VALIDATE_EMAIL)) {
            $user = $this->userRepository->findOneBy(['email' => $identifier]);
        } else {
            $user = $this->userRepository->findOneBy(['username' => $identifier]);
        }

        if (
            !$user instanceof User ||
            !$user->isActive() ||
            !$user->isVerified() ||
            !$this->passwordHasher->isPasswordValid($user, $password)
        ) {
            $message = 'Invalid credentials.';
            if ($user instanceof User && $user->isActive() && !$user->isVerified()) {
                $message = 'Please verify your email address before logging in.';
            }
            return new JsonResponse([
                'message' => $message,
            ], Response::HTTP_UNAUTHORIZED);
        }

        $token = $this->jwtManager->create($user);

        // Log successful API login
        $this->activityLogService->logLogin($user);

        return new JsonResponse([
            'token' => $token,
        ]);
    }
}

