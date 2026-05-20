<?php

namespace App\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\HttpFoundation\Request;

#[Route('/staff')]
#[isGranted('ROLE_STAFF')]
class StaffController extends AbstractController
{
    #[Route('/bookings', name: 'app_staff_bookings', methods: ['GET'])]
    public function bookings(Request $request): Response
    {
        // Redirect to the main bookings page
        return $this->redirectToRoute('app_bookings_index', $request->query->all());
    }
}
