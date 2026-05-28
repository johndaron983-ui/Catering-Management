<?php

namespace App\MessageHandler;

use App\Entity\User;
use App\Message\SendVerificationEmailMessage;
use App\Service\EmailVerificationService;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Component\Routing\RouterInterface;

#[AsMessageHandler]
final class SendVerificationEmailMessageHandler
{
    public function __construct(
        private EntityManagerInterface $entityManager,
        private EmailVerificationService $emailVerificationService,
        private RouterInterface $router,
        private LoggerInterface $logger,
    ) {
    }

    public function __invoke(SendVerificationEmailMessage $message): void
    {
        $user = $this->entityManager->find(User::class, $message->userId);
        if (!$user instanceof User || !$user->hasEmail() || $user->isVerified()) {
            return;
        }

        $token = $user->getVerificationToken();
        if (!$token) {
            $this->logger->warning('Verification email skipped: user has no token', [
                'userId' => $message->userId,
            ]);

            return;
        }

        $verificationUrl = $this->router->generate(
            'app_verify_email',
            ['token' => $token],
            UrlGeneratorInterface::ABSOLUTE_URL,
        );

        try {
            $this->emailVerificationService->sendVerificationEmail($user, $verificationUrl);
        } catch (\Throwable $e) {
            $this->logger->error('Failed to send verification email', [
                'userId' => $user->getId(),
                'error' => $e->getMessage(),
            ]);
        }
    }
}
