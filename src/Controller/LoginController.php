<?php

namespace App\Controller;

use App\Service\ActivityLogService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Authentication\AuthenticationUtils;

class LoginController extends AbstractController
{
    public function __construct(private ActivityLogService $activityLogService)
    {
    }

    #[Route(path: '/login', name: 'app_login')]
    public function login(AuthenticationUtils $authenticationUtils): Response
    {
        $user = $this->getUser();
        
        if ($user) {
            // Check if user is verified before allowing access
            if ($user instanceof \App\Entity\User && $user->hasEmail() && $user->isVerified() !== true) {
                $this->addFlash('warning', 'Please verify your email address before accessing the application.');
                return $this->redirectToRoute('app_login');
            }
            
            // Redirect based on user role
            $roles = $user->getRoles();
            
            if (in_array('ROLE_ADMIN', $roles)) {
                return $this->redirectToRoute('app_admin_dashboard');
            }
            
            return $this->redirectToRoute('app_bookings_index');
        }

        // get the login error if there is one
        $error = $authenticationUtils->getLastAuthenticationError();
        // last username entered by the user
        $lastUsername = $authenticationUtils->getLastUsername();

        return $this->render('security/login.html.twig', ['last_username' => $lastUsername, 'error' => $error]);
    }

    #[Route(path: '/logout', name: 'app_logout')]
    public function logout(Request $request): void
    {
        // Log the logout activity before session is destroyed
        $user = $this->getUser();
        if ($user) {
            $this->activityLogService->logLogout($user);
        }

        throw new \LogicException('This method can be blank - it will be intercepted by the logout key on your firewall.');
    }
}
