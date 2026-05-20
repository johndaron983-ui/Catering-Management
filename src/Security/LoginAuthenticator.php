<?php

namespace App\Security;

use App\Entity\User;
use App\Repository\UserRepository;
use App\Service\ActivityLogService;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Exception\CustomUserMessageAuthenticationException;
use Symfony\Component\Security\Http\Authenticator\AbstractLoginFormAuthenticator;
use Symfony\Component\Security\Http\Authenticator\Passport\Badge\CsrfTokenBadge;
use Symfony\Component\Security\Http\Authenticator\Passport\Badge\UserBadge;
use Symfony\Component\Security\Http\Authenticator\Passport\Credentials\PasswordCredentials;
use Symfony\Component\Security\Http\Authenticator\Passport\Passport;
use Symfony\Component\Security\Http\SecurityRequestAttributes;
use Symfony\Component\Security\Http\Util\TargetPathTrait;

class LoginAuthenticator extends AbstractLoginFormAuthenticator
{
    use TargetPathTrait;

    public const LOGIN_ROUTE = 'app_login';

    public function __construct(
        private UrlGeneratorInterface $urlGenerator,
        private ActivityLogService $activityLogService,
        private UserRepository $userRepository
    ) {
    }

    public function authenticate(Request $request): Passport
    {
        $username = $request->getPayload()->getString('username');

        $request->getSession()->set(SecurityRequestAttributes::LAST_USERNAME, $username);

        return new Passport(
            new UserBadge($username, function (string $userIdentifier) {
                // Load user by username only
                $user = $this->userRepository->findOneBy(['username' => $userIdentifier]);

                if (!$user) {
                    throw new CustomUserMessageAuthenticationException('Invalid credentials.');
                }
                
                // Check if user account is active
                if (!$user instanceof User || !$user->isActive()) {
                    throw new CustomUserMessageAuthenticationException('Your account has been disabled. Please contact an administrator.');
                }

                // Check if email is verified
                if (!$user->isVerified()) {
                    throw new CustomUserMessageAuthenticationException('Please verify your email address before logging in.');
                }
                
                return $user;
            }),
            new PasswordCredentials($request->getPayload()->getString('password')),
            [
                new CsrfTokenBadge('authenticate', $request->getPayload()->getString('_csrf_token')),
            ]
        );
    }

    public function onAuthenticationSuccess(Request $request, TokenInterface $token, string $firewallName): ?Response
    {
    
        $user = $token->getUser();
        if ($user instanceof User) {
            // Check if account is inactive
            if (!$user->isActive()) {
                // Force logout if account became inactive
                $request->getSession()->invalidate();
                throw new CustomUserMessageAuthenticationException('Your account has been disabled. Please contact an administrator.');
            }

            // Check if email is verified
            if (!$user->isVerified()) {
                // Force logout if email is not verified
                $request->getSession()->invalidate();
                throw new CustomUserMessageAuthenticationException('Please verify your email address before logging in.');
            }
        }

        // Log the login activity
        if ($user) {
            $this->activityLogService->logLogin($user);
        }

        if ($targetPath = $this->getTargetPath($request->getSession(), $firewallName)) {
            return new RedirectResponse($targetPath);
        }

        // Redirect based on user role
        $roles = $user->getRoles();
        
        if (in_array('ROLE_ADMIN', $roles)) {
            return new RedirectResponse($this->urlGenerator->generate('app_admin_dashboard'));
        }
        
        if (in_array('ROLE_STAFF', $roles)) {
            return new RedirectResponse($this->urlGenerator->generate('app_bookings_index'));
        }

        return new RedirectResponse($this->urlGenerator->generate('app_home'));
    }

    protected function getLoginUrl(Request $request): string
    {
        return $this->urlGenerator->generate(self::LOGIN_ROUTE);
    }
}
