<?php

namespace App\EventListener;

use App\Entity\User;
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;
use Symfony\Component\Security\Core\Exception\CustomUserMessageAuthenticationException;
use Lexik\Bundle\JWTAuthenticationBundle\Event\JWTCreatedEvent;
use Lexik\Bundle\JWTAuthenticationBundle\Event\JWTAuthenticatedEvent;

class JwtVerificationListener
{
    #[AsEventListener(event: 'lexik_jwt_authentication.on_jwt_created')]
    public function onJwtCreated(JWTCreatedEvent $event): void
    {
        $user = $event->getUser();

        if (!$user instanceof User) {
            return;
        }

        // Don't issue JWT tokens to unverified users
        if (!$user->isVerified()) {
            throw new CustomUserMessageAuthenticationException('Please verify your email address before accessing the API.');
        }

        // Add isVerified to the JWT payload for verification on subsequent requests
        $payload = $event->getData();
        $payload['isVerified'] = $user->isVerified();
        $event->setData($payload);
    }

    #[AsEventListener(event: 'lexik_jwt_authentication.on_jwt_authenticated')]
    public function onJwtAuthenticated(JWTAuthenticatedEvent $event): void
    {
        $payload = $event->getPayload();

        // Check if user is verified from the payload
        // The isVerified flag should be included in the JWT payload
        if (isset($payload['isVerified']) && !$payload['isVerified']) {
            throw new CustomUserMessageAuthenticationException('Please verify your email address before accessing the API.');
        }
    }
}
