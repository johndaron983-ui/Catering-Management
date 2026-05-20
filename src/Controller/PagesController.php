<?php

namespace App\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

/**
 * Public Pages Controller
 * Handles all public-facing pages without authentication
 */
final class PagesController extends AbstractController
{
    #[Route('/about', name: 'app_about')]
    public function about(): Response
    {
        return $this->render('about.html.twig');
    }

    #[Route('/services/page', name: 'app_services')]
    #[IsGranted('ROLE_USER')]
    public function servicesPage(): Response
    {
        return $this->render('services.html.twig');
    }

    #[Route('/menus', name: 'app_menus')]
    public function menus(): Response
    {
        return $this->render('menus.html.twig');
    }

    #[Route('/gallery', name: 'app_gallery')]
    public function gallery(): Response
    {
        return $this->render('gallery.html.twig');
    }

    #[Route('/contact', name: 'app_contact')]
    public function contact(): Response
    {
        return $this->render('contact.html.twig');
    }
}
