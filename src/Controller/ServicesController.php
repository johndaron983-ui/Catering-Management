<?php

namespace App\Controller;

use App\Entity\Services;
use App\Form\ServicesType;
use App\Repository\ServicesRepository;
use App\Repository\InventoryRepository;
use App\Service\InventoryStockManager;
use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\String\Slugger\SluggerInterface;
use Symfony\Component\HttpFoundation\File\Exception\FileException;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/admin/services')]
#[IsGranted(['ROLE_ADMIN', 'ROLE_STAFF'])]
final class ServicesController extends AbstractController
{
    #[Route(name: 'app_services_index', methods: ['GET'])]
    public function index(Request $request, ServicesRepository $servicesRepository): Response
    {
        $search = $request->query->get('search');
        $eventType = $request->query->get('event_type');
        $minPrice = $request->query->get('min_price');
        $maxPrice = $request->query->get('max_price');

        $queryBuilder = $servicesRepository->createQueryBuilder('s');

        if ($search) {
            $queryBuilder->andWhere('s.name LIKE :search OR s.description LIKE :search')
                ->setParameter('search', '%' . $search . '%');
        }

        if ($eventType) {
            $queryBuilder->andWhere('s.eventType = :eventType')
                ->setParameter('eventType', $eventType);
        }

        if ($minPrice !== null && $minPrice !== '') {
            $queryBuilder->andWhere('s.basePrice >= :minPrice')
                ->setParameter('minPrice', (float) $minPrice);
        }

        if ($maxPrice !== null && $maxPrice !== '') {
            $queryBuilder->andWhere('s.basePrice <= :maxPrice')
                ->setParameter('maxPrice', (float) $maxPrice);
        }

        $services = $queryBuilder
            ->orderBy('s.name', 'ASC')
            ->getQuery()
            ->getResult();

        // Get unique event types for filter
        $eventTypes = $servicesRepository->createQueryBuilder('s')
            ->select('DISTINCT s.eventType')
            ->orderBy('s.eventType', 'ASC')
            ->getQuery()
            ->getScalarResult();

        $eventTypeChoices = array_map(fn($row) => $row['eventType'], $eventTypes);

        return $this->render('services/index.html.twig', [
            'services' => $services,
            'event_types' => $eventTypeChoices,
            'current_search' => $search,
            'current_event_type' => $eventType,
            'current_min_price' => $minPrice,
            'current_max_price' => $maxPrice,
        ]);
    }

    #[Route('/new', name: 'app_services_new', methods: ['GET', 'POST'])]
    public function new(Request $request, EntityManagerInterface $entityManager, SluggerInterface $slugger, InventoryStockManager $stockManager, InventoryRepository $inventoryRepository): Response
    {
        $service = new Services();
        $form = $this->createForm(ServicesType::class, $service);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            try {
                $imageFile = $form->get('image')->getData();
                
                if ($imageFile) {
                    $originalFilename = pathinfo($imageFile->getClientOriginalName(), PATHINFO_FILENAME);
                    $safeFilename = $slugger->slug($originalFilename);
                    $newFilename = $safeFilename.'-'.uniqid().'.'.$imageFile->guessExtension();
                    
                    try {
                        $imageFile->move(
                            $this->getParameter('kernel.project_dir').'/public/image',
                            $newFilename
                        );
                    } catch (FileException $e) {
                        // HANDLE THE EXCEPTION IF SOMETHING HAPPENS DURING FILE UPLOAD
                    }
                    
                    $service->setImage($newFilename);
                }
                
                // Persist service first so it has an ID for ServiceInventory relationships
                $service->setCreatedBy($this->getUser());
                $entityManager->persist($service);
                $entityManager->flush();
                
                // Get inventory data from request
                $inventoryData = [];
                $requestData = $request->request->all();
                
                // Parse inventory items and quantities from the request
                foreach ($requestData as $key => $value) {
                    if (strpos($key, 'inventory_quantity_') === 0) {
                        $inventoryId = str_replace('inventory_quantity_', '', $key);
                        if (is_numeric($value) && (float)$value > 0) {
                            $inventoryData[$inventoryId] = (float)$value;
                        }
                    }
                }
                
                // Deduct inventory stock if items were selected
                if (!empty($inventoryData)) {
                    $stockManager->deductStockForService($service, $inventoryData);
                    $entityManager->flush();
                }

                $this->addFlash('success', 'Service created successfully!');
                return $this->redirectToRoute('app_services_index', [], Response::HTTP_SEE_OTHER);
            } catch (\Exception $e) {
                $this->addFlash('error', 'Error creating service: ' . $e->getMessage());
                // Fall through to re-render the form with the error message
            }
        }

        return $this->render('services/new.html.twig', [
            'service' => $service,
            'form' => $form->createView(),
            'availableInventory' => $inventoryRepository->findBy(['status' => 'active']),
        ]);
    }

    #[Route('/{id}', name: 'app_services_show', methods: ['GET'])]
    public function show(Services $service): Response
    {
        return $this->render('services/show.html.twig', [
            'service' => $service,
        ]);
    }

    #[Route('/{id}/edit', name: 'app_services_edit', methods: ['GET', 'POST'])]
    public function edit(Request $request, Services $service, EntityManagerInterface $entityManager, SluggerInterface $slugger, InventoryRepository $inventoryRepository): Response
    {
        $this->assertCanManageService($service);

        $form = $this->createForm(ServicesType::class, $service);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $imageFile = $form->get('image')->getData();

            if ($imageFile) {
                $originalFilename = pathinfo($imageFile->getClientOriginalName(), PATHINFO_FILENAME);
                $safeFilename = $slugger->slug($originalFilename);
                $newFilename = $safeFilename.'-'.uniqid().'.'.$imageFile->guessExtension();

                try {
                    $imageFile->move(
                        $this->getParameter('kernel.project_dir').'/public/image',
                        $newFilename
                    );
                } catch (FileException $e) {
                    // HANDLE THE EXCEPTION IF SOMETHING HAPPENS DURING FILE UPLOAD
                }

                $service->setImage($newFilename);
            }

            $entityManager->flush();

            return $this->redirectToRoute('app_services_index', [], Response::HTTP_SEE_OTHER);
        }

        return $this->render('services/edit.html.twig', [
            'service' => $service,
            'form' => $form->createView(),
            'availableInventory' => $inventoryRepository->findBy(['status' => 'active']),
        ]);
    }

    #[Route('/{id}', name: 'app_services_delete', methods: ['POST'])]
    public function delete(Request $request, Services $service, EntityManagerInterface $entityManager, InventoryStockManager $stockManager): Response
    {
        $this->assertCanManageService($service);

        if ($this->isCsrfTokenValid('delete'.$service->getId(), $request->getPayload()->getString('_token'))) {
            try {
                // Restore inventory stock before deleting
                $stockManager->restoreStockForService($service);
                
                $entityManager->remove($service);
                $entityManager->flush();
                
                $this->addFlash('success', 'Service deleted successfully and inventory stock restored!');
            } catch (\Exception $e) {
                $this->addFlash('error', 'Error deleting service: ' . $e->getMessage());
            }
        }

        return $this->redirectToRoute('app_services_index', [], Response::HTTP_SEE_OTHER);
    }

    private function assertCanManageService(Services $service): void
    {
        $user = $this->getUser();
        if ($this->isGranted('ROLE_ADMIN')) {
            return;
        }

        if (!$user instanceof User) {
            throw $this->createAccessDeniedException();
        }

        $owner = $service->getCreatedBy();
        if (!$owner || $owner->getId() !== $user->getId()) {
            throw $this->createAccessDeniedException('You can only manage your own services.');
        }

        if (in_array('ROLE_ADMIN', $owner->getRoles(), true)) {
            throw $this->createAccessDeniedException('You cannot manage admin records.');
        }
    }
}
