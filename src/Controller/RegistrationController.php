<?php

namespace App\Controller;

use App\Entity\User;
use App\Form\RegistrationFormType;
use App\Repository\UserRepository;
use App\Service\EmailVerificationService; // NEW - Inject email service
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface; // NEW - For generating absolute URLs
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Contracts\Translation\TranslatorInterface;

class RegistrationController extends AbstractController
{
    #[Route('/register', name: 'app_register')]
    public function register(Request $request, UserPasswordHasherInterface $userPasswordHasher, EntityManagerInterface $entityManager, EmailVerificationService $emailVerificationService, UserRepository $userRepository, LoggerInterface $logger // ADDED
    ): Response {

        $user = new User();
        $form = $this->createForm(RegistrationFormType::class, $user);
        $form->handleRequest($request);

        if ($form->isSubmitted()) {
            $logger->info('Registration form submitted', ['email' => $form->get('email')->getData() ?? 'N/A']);
            
            if ($form->isValid()) {
                try {
                    // Check if email already exists
                    $existingUser = $userRepository->findOneBy(['email' => $user->getEmail()]);
                    if ($existingUser) {
                        $this->addFlash('danger', 'An account with this email already exists.');
                        return $this->redirectToRoute('app_register');
                    }

                    /** @var string $plainPassword */
                    $plainPassword = $form->get('plainPassword')->getData();

                    // encode the plain password
                    $user->setPassword($userPasswordHasher->hashPassword($user, $plainPassword));

                    // Generate verification token
                    $verificationToken = $emailVerificationService->generateVerificationToken();
                    $user->setVerificationToken($verificationToken);
                    $user->setIsVerified(false);

                    $entityManager->persist($user);
                    $entityManager->flush();

                    $logger->info('User registered successfully', ['email' => $user->getEmail(), 'userId' => $user->getId()]);

                    // Generate verification URL
                    $verificationUrl = $this->generateUrl(
                        'app_verify_email',
                        ['token' => $verificationToken],
                        UrlGeneratorInterface::ABSOLUTE_URL
                    );

                    // Send verification email
                    $emailVerificationService->sendVerificationEmail($user, $verificationUrl);
                    $this->addFlash('success', 'Registration successful! Please check your email to verify your account.');
                    return $this->redirectToRoute('app_login');
                } catch (\Exception $e) {
                    $logger->error('Registration error: ' . $e->getMessage(), ['exception' => $e]);
                    $this->addFlash('danger', 'An error occurred during registration. Please try again.');
                    return $this->redirectToRoute('app_register');
                }
            } else {
                // Log form errors
                $errors = [];
                foreach ($form->getErrors(true) as $error) {
                    $errors[] = $error->getMessage();
                }
                $logger->warning('Registration form validation failed', ['errors' => $errors]);
                $this->addFlash('danger', 'Please fix the errors below.');
            }
        }

        return $this->render('registration/register.html.twig', [
            'registrationForm' => $form,
        ]);
    }
}
