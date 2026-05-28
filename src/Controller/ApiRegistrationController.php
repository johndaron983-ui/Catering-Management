<?php

namespace App\Controller;

use App\Entity\User;
use App\Message\SendVerificationEmailMessage;
use App\Service\EmailVerificationService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Validator\Validator\ValidatorInterface;

#[Route('/api')]
class ApiRegistrationController extends AbstractController
{
    public function __construct(
        private EntityManagerInterface $entityManager,
        private UserPasswordHasherInterface $passwordHasher,
        private EmailVerificationService $emailVerificationService,
        private ValidatorInterface $validator,
        private MessageBusInterface $messageBus,
    ) {
    }

    #[Route('/register', name: 'api_register', methods: ['POST'])]
    public function register(Request $request): JsonResponse
    {
        $data = $request->toArray();

        if (!isset($data['username']) || !isset($data['password'])) {
            return $this->json([
                'success' => false,
                'message' => 'Username and password are required',
            ], 400);
        }

        $username = trim((string) $data['username']);
        $password = (string) $data['password'];
        $email = isset($data['email']) ? trim((string) $data['email']) : null;
        $email = ($email === '' || $email === null) ? null : $email;

        if (strlen($username) < 3) {
            return $this->json([
                'success' => false,
                'message' => 'Username must be at least 3 characters long',
            ], 400);
        }

        if ($email !== null && !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            return $this->json([
                'success' => false,
                'message' => 'Invalid email address',
            ], 400);
        }

        if (strlen($password) < 6) {
            return $this->json([
                'success' => false,
                'message' => 'Password must be at least 6 characters long',
            ], 400);
        }

        $existingUser = $this->entityManager
            ->getRepository(User::class)
            ->findOneBy(['username' => $username]);

        if ($existingUser) {
            return $this->json([
                'success' => false,
                'message' => 'Username already exists',
            ], 409);
        }

        if ($email !== null) {
            $existingEmail = $this->entityManager
                ->getRepository(User::class)
                ->findOneBy(['email' => $email]);

            if ($existingEmail) {
                return $this->json([
                    'success' => false,
                    'message' => 'Email already registered',
                ], 409);
            }
        }

        $user = new User();
        $user->setUsername($username);
        $user->setEmail($email);

        $hashedPassword = $this->passwordHasher->hashPassword($user, $password);
        $user->setPassword($hashedPassword);
        $user->setRoles(['ROLE_USER']);

        if ($email !== null) {
            $verificationToken = $this->emailVerificationService->generateVerificationToken();
            $user->setVerificationToken($verificationToken);
            $user->setIsVerified(false);
        } else {
            $user->setVerificationToken(null);
            $user->setIsVerified(true);
        }

        $errors = $this->validator->validate($user);
        if (count($errors) > 0) {
            $errorMessages = [];
            foreach ($errors as $error) {
                $errorMessages[] = $error->getMessage();
            }

            return $this->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $errorMessages,
            ], 400);
        }

        $this->entityManager->persist($user);
        $this->entityManager->flush();

        if ($user->hasEmail()) {
            $this->messageBus->dispatch(new SendVerificationEmailMessage($user->getId()));
        }

        $message = $user->hasEmail()
            ? 'Registration successful. Please check your email to verify your account.'
            : 'Registration successful. You can sign in with your username and password.';

        return $this->json([
            'success' => true,
            'message' => $message,
            'user' => [
                'id' => $user->getId(),
                'username' => $user->getUsername(),
                'email' => $user->getEmail(),
                'isVerified' => $user->isVerified(),
                'roles' => $user->getRoles(),
            ],
        ], 201);
    }
}
