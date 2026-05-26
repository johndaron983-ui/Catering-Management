<?php

namespace App\Service;

use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bridge\Twig\Mime\TemplatedEmail;
use Psr\Log\LoggerInterface;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Mime\Address;

class EmailVerificationService
{
public function __construct(
private EntityManagerInterface $entityManager,
private MailerInterface $mailer,
private LoggerInterface $logger,
private string $mailerFromEmail,
private string $mailerFromName,
) {}

/**
* Generate a unique verification token
*/
public function generateVerificationToken(): string
{
return bin2hex(random_bytes(32));

}

/**
* Send verification email to user
*/
public function sendVerificationEmail(User $user, string $verificationUrl): void
{
$email = (new TemplatedEmail())
->from(new Address($this->mailerFromEmail, $this->mailerFromName))
->to(new Address($user->getEmail()))
->subject('Please verify your email address')
->htmlTemplate('emails/verification.html.twig')
->context([
'user' => $user,
'verificationUrl' => $verificationUrl,
]);

// Queued via messenger (async transport) — must not block HTTP responses.
$this->mailer->send($email);
$this->logger->info('Verification email sent', ['email' => $user->getEmail(), 'from' => $this->mailerFromEmail]);
}

/**
* Verify a token and mark user as verified
*/
public function verifyToken(string $token): ?User
{
$user = $this->entityManager
->getRepository(User::class)
->findOneBy(['verificationToken' => $token]);

if (!$user) {
return null;
}

// Mark user as verified
$user->setIsVerified(true);
$user->setVerificationToken(null); // Clear the token

$this->entityManager->flush();

return $user;
}

/**
* Check if a user needs verification
*/
public function needsVerification(User $user): bool
{
return !$user->isVerified();
}
}