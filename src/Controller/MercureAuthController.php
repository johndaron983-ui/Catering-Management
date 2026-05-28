<?php

namespace App\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;
use App\Entity\User;

/**
 * Provides the Mercure Hub discovery URL and user topic for authenticated users.
 * The client-side JavaScript uses this to know where to subscribe for SSE events.
 */
class MercureAuthController extends AbstractController
{
    #[Route('/api/mercure/config', name: 'app_mercure_config', methods: ['GET'])]
    #[IsGranted('ROLE_USER')]
    public function getMercureConfig(): JsonResponse
    {
        /** @var User $user */
        $user = $this->getUser();

        if (!$user) {
            return new JsonResponse(['error' => 'Not authenticated'], 401);
        }

        return new JsonResponse([
            'mercure_url' => $_ENV['MERCURE_PUBLIC_URL'] ?? 'http://localhost:3000/.well-known/mercure',
            'topic' => '/notifications/user/' . $user->getId(),
            'user_id' => $user->getId(),
        ]);
    }
}

