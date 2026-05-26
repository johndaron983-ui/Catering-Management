<?php

namespace App\Controller;

use App\Service\EmailVerificationService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Component\Security\Http\Attribute\CurrentUser;
use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;

#[Route('/api')]
class ApiEmailVerificationController extends AbstractController
{
public function __construct(
private EmailVerificationService $emailVerificationService,
private EntityManagerInterface $entityManager
) {}

/**
* Verify email with token
*/
#[Route('/verify-email', name: 'api_verify_email', methods: ['POST'])]
public function verifyEmail(Request $request): JsonResponse

{
$data = json_decode($request->getContent(), true);
$token = $data['token'] ?? null;

if (!$token) {
return $this->json([
'success' => false,
'message' => 'Verification token is required'
], 400);
}

$user = $this->emailVerificationService->verifyToken($token);

if (!$user) {
return $this->json([
'success' => false,
'message' => 'Invalid or expired verification token'
], 400);
}

return $this->json([
'success' => true,
'message' => 'Email verified successfully',
'user' => [
'id' => $user->getId(),
'email' => $user->getEmail(),
'isVerified' => $user->isVerified()
]
], 200);

}

/**
 * Resend verification email (public — for users who registered but cannot log in yet).
 */
#[Route('/resend-verification-email', name: 'api_resend_verification_email', methods: ['POST'])]
public function resendVerificationByEmail(Request $request): JsonResponse
{
    $data = json_decode($request->getContent(), true);
    $email = is_array($data) ? ($data['email'] ?? null) : null;

    if (!$email || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
        return $this->json([
            'success' => false,
            'message' => 'A valid email address is required',
        ], 400);
    }

    $user = $this->entityManager->getRepository(User::class)->findOneBy(['email' => $email]);

    if (!$user || $user->isVerified()) {
        return $this->json([
            'success' => true,
            'message' => 'If that email is registered and unverified, a verification link was sent.',
        ], 200);
    }

    $verificationToken = $this->emailVerificationService->generateVerificationToken();
    $user->setVerificationToken($verificationToken);
    $this->entityManager->flush();

    $verificationUrl = $this->generateUrl(
        'app_verify_email',
        ['token' => $verificationToken],
        UrlGeneratorInterface::ABSOLUTE_URL
    );

    try {
        $this->emailVerificationService->sendVerificationEmail($user, $verificationUrl);
    } catch (\Exception) {
        // Email is queued async; ignore transport errors here.
    }

    return $this->json([
        'success' => true,
        'message' => 'If that email is registered and unverified, a verification link was sent.',
    ], 200);
}

/**
* Resend verification email (authenticated)
*/
#[Route('/resend-verification', name: 'api_resend_verification', methods: ['POST'])]
public function resendVerification(#[CurrentUser] ?User $user): JsonResponse
{
if (!$user) {
return $this->json([
'success' => false,
'message' => 'Authentication required'
], 401);
}

if ($user->isVerified()) {
return $this->json([
'success' => false,
'message' => 'Email is already verified'
], 400);
}

// Generate new token
$verificationToken = $this->emailVerificationService->generateVerificationToken();
$user->setVerificationToken($verificationToken);
$this->entityManager->flush();

// Create verification URL (for web verification or deep link)
$verificationUrl = $this->generateUrl(

'app_verify_email',
['token' => $verificationToken],
UrlGeneratorInterface::ABSOLUTE_URL
);

// Send email
$this->emailVerificationService->sendVerificationEmail($user, $verificationUrl);

return $this->json([
'success' => true,
'message' => 'Verification email sent successfully'
], 200);
}

/**
* Check verification status
*/
#[Route('/verification-status', name: 'api_verification_status', methods: ['GET'])]
public function verificationStatus(#[CurrentUser] ?User $user): JsonResponse
{
if (!$user) {
return $this->json([
'success' => false,
'message' => 'Authentication required'
], 401);
}

return $this->json([
'success' => true,

'isVerified' => $user->isVerified(),
'email' => $user->getEmail()
], 200);
}
}
