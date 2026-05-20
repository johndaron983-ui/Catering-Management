<?php

namespace App\Security;

use App\Entity\User;
use App\Repository\UserRepository;
use App\Service\ActivityLogService;
use Doctrine\ORM\EntityManagerInterface;
use KnpU\OAuth2ClientBundle\Client\ClientRegistry;
use KnpU\OAuth2ClientBundle\Client\OAuth2ClientInterface;
use KnpU\OAuth2ClientBundle\Security\Authenticator\OAuth2Authenticator;
use League\OAuth2\Client\Provider\GoogleUser;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Exception\AuthenticationException;
use Symfony\Component\Security\Core\Exception\CustomUserMessageAuthenticationException;
use Symfony\Component\Security\Http\Authenticator\Passport\Badge\UserBadge;
use Symfony\Component\Security\Http\Authenticator\Passport\Passport;
use Symfony\Component\Security\Http\Authenticator\Passport\SelfValidatingPassport;

class GoogleAuthenticator extends OAuth2Authenticator
{
    public function __construct(
        private ClientRegistry $clientRegistry,
        private EntityManagerInterface $entityManager,
        private UserRepository $userRepository,
        private UrlGeneratorInterface $urlGenerator,
        private ActivityLogService $activityLogService
    ) {
    }

    public function supports(Request $request): ?bool
    {
        return $request->attributes->get('_route') === 'connect_google_check';
    }

    public function authenticate(Request $request): Passport
    {
        /** @var OAuth2ClientInterface $client */
        $client = $this->clientRegistry->getClient('google');

        /** @var GoogleUser $googleUser */
        $googleUser = $client->fetchUser();

        // Convert Google user to array
        $googleUserData = [
            'id' => $googleUser->getId(),
            'email' => $googleUser->getEmail(),
            'name' => $googleUser->getName(),
            'firstName' => $googleUser->getFirstName(),
            'lastName' => $googleUser->getLastName(),
            'avatar' => $googleUser->getAvatar(),
        ];

        return new SelfValidatingPassport(
            new UserBadge($googleUserData['email'], function () use ($googleUserData) {
                // Find or create user
                $user = $this->userRepository->findOneBy(['email' => $googleUserData['email']]);

                if (!$user) {
                    // Create new user
                    $user = new User();
                    $user->setEmail($googleUserData['email']);
                    $user->setUsername($googleUserData['email']);
                    $user->setRoles(['ROLE_STAFF']);
                    $user->setPassword(''); // No password for OAuth users
                    $user->setIsVerified(true); // OAuth users are considered verified
                    $user->setStatus('active');

                    $this->entityManager->persist($user);
                    $this->entityManager->flush();
                }

                // Check if user account is active
                if (!$user->isActive()) {
                    throw new CustomUserMessageAuthenticationException('Your account has been disabled. Please contact an administrator.');
                }

                return $user;
            })
        );
    }

    public function onAuthenticationSuccess(Request $request, TokenInterface $token, string $firewallName): ?Response
    {
        $user = $token->getUser();

        // Log the login activity
        if ($user instanceof User) {
            $this->activityLogService->logLogin($user);
        }

        // Redirect based on user role
        $roles = $user->getRoles();

        if (in_array('ROLE_ADMIN', $roles)) {
            return new RedirectResponse($this->urlGenerator->generate('app_admin_dashboard'));
        }

        if (in_array('ROLE_STAFF', $roles)) {
            return new RedirectResponse($this->urlGenerator->generate('app_staff_bookings'));
        }

        return new RedirectResponse($this->urlGenerator->generate('app_home'));
    }

    public function onAuthenticationFailure(Request $request, AuthenticationException $exception): ?Response
    {
        $message = strtr($exception->getMessageKey(), $exception->getMessageData());

        return new RedirectResponse(
            $this->urlGenerator->generate('app_login', ['error' => $message])
        );
    }
}
