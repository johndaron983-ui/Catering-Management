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
) {}

#[Route('/register', name: 'api_register', methods: ['POST'])]
public function register(Request $request): JsonResponse
{
$data = $request->toArray();

// Validate required fields
if (!isset($data['username']) || !isset($data['email']) || !isset($data['password'])) {
return $this->json([
'success' => false,
'message' => 'Username, email, and password are required'
], 400);
}

// Basic validation
if (strlen($data['username']) < 3) {
return $this->json([
'success' => false,
'message' => 'Username must be at least 3 characters long'
], 400);
}

if (!filter_var($data['email'], FILTER_VALIDATE_EMAIL)) {
return $this->json([

'success' => false,
'message' => 'Invalid email address'
], 400);
}

if (strlen($data['password']) < 6) {
return $this->json([
'success' => false,
'message' => 'Password must be at least 6 characters long'
], 400);
}

// Check if username already exists
$existingUser = $this->entityManager
->getRepository(User::class)
->findOneBy(['username' => $data['username']]);

if ($existingUser) {
return $this->json([
'success' => false,
'message' => 'Username already exists'
], 409);
}

// Check if email already exists
$existingEmail = $this->entityManager
->getRepository(User::class)
->findOneBy(['email' => $data['email']]);

if ($existingEmail) {
return $this->json([
'success' => false,
'message' => 'Email already registered'
], 409);
}

// Create new user
$user = new User();
$user->setUsername($data['username']);
$user->setEmail($data['email']);

// Hash password
$hashedPassword = $this->passwordHasher->hashPassword($user, $data['password']);
$user->setPassword($hashedPassword);

// Set default role
$user->setRoles(['ROLE_USER']);

// Generate verification token
$verificationToken = $this->emailVerificationService->generateVerificationToken();
$user->setVerificationToken($verificationToken);
$user->setIsVerified(false);

// Validate entity
$errors = $this->validator->validate($user);
if (count($errors) > 0) {
$errorMessages = [];
foreach ($errors as $error) {

$errorMessages[] = $error->getMessage();
}
return $this->json([
'success' => false,
'message' => 'Validation failed',
'errors' => $errorMessages
], 400);
}

// Save user
$this->entityManager->persist($user);
$this->entityManager->flush();

// Queue verification email — never block the HTTP response on SMTP
$this->messageBus->dispatch(new SendVerificationEmailMessage($user->getId()));

return $this->json([

'success' => true,
'message' => 'Registration successful. Please check your email to verify your account.',
'user' => [
'id' => $user->getId(),
'username' => $user->getUsername(),
'email' => $user->getEmail(),
'isVerified' => $user->isVerified(),
'roles' => $user->getRoles()
]
], 201);
}
}
