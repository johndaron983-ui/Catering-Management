<?php

namespace App\Controller;

use App\Entity\Booking;
use App\Form\BookingType;
use App\Repository\BookingRepository;
use App\Repository\InventoryRepository;
use App\Repository\ServicesRepository;
use App\Service\InventoryStockManager;
use App\Service\ActivityLogService;
use App\Service\NotificationService;
use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\Form\FormError;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\ExpressionLanguage\Expression;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/bookings')]
#[IsGranted(new Expression('is_granted("ROLE_ADMIN") or is_granted("ROLE_STAFF")'))]
final class BookingController extends AbstractController
{
    #[Route(name: 'app_bookings_index', methods: ['GET'])]
    public function index(Request $request, BookingRepository $bookingRepository, ServicesRepository $servicesRepository): Response
    {
        $search = $request->query->get('search');
        $serviceId = $request->query->get('service');
        $status = $request->query->get('status');
        $startDate = $request->query->get('start_date');
        $endDate = $request->query->get('end_date');

        $queryBuilder = $bookingRepository->createQueryBuilder('b');

        if ($search) {
            $queryBuilder->andWhere('b.customerName LIKE :search')
                ->setParameter('search', '%' . $search . '%');
        }

        if ($serviceId) {
            $queryBuilder->andWhere('b.service = :service')
                ->setParameter('service', $serviceId);
        }

        if ($status && $status !== '') {
            $queryBuilder->andWhere('b.status = :status')
                ->setParameter('status', $status);
        }

        if ($startDate) {
            $queryBuilder->andWhere('b.eventDate >= :startDate')
                ->setParameter('startDate', new \DateTime($startDate));
        }

        if ($endDate) {
            $queryBuilder->andWhere('b.eventDate <= :endDate')
                ->setParameter('endDate', new \DateTime($endDate . ' 23:59:59'));
        }

        $bookings = $queryBuilder
            ->orderBy('b.eventDate', 'DESC')
            ->getQuery()
            ->getResult();

        $services = $servicesRepository->findAll();

        $initialBookingIds = array_map(static fn (Booking $b) => $b->getId(), $bookings);

        return $this->render('bookings/index.html.twig', [
            'bookings' => $bookings,
            'initial_booking_ids' => $initialBookingIds,
            'services' => $services,
            'current_search' => $search,
            'current_service' => $serviceId,
            'current_status' => $status,
            'current_start_date' => $startDate,
            'current_end_date' => $endDate,
        ]);
    }

    #[Route('/new', name: 'app_bookings_new', methods: ['GET', 'POST'])]
    public function new(Request $request, EntityManagerInterface $entityManager, ServicesRepository $servicesRepository, InventoryRepository $inventoryRepository, InventoryStockManager $stockManager, ActivityLogService $activityLogService): Response
    {
        $booking = new Booking();
        $form = $this->createForm(BookingType::class, $booking, ['isEdit' => false]);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            // Collect inventory selections
            $inventoryData = [];
            $requestData = $request->request->all();
            foreach ($requestData as $key => $value) {
                if (strpos($key, 'inventory_quantity_') === 0) {
                    $inventoryId = str_replace('inventory_quantity_', '', $key);
                    if (is_numeric($value) && (float) $value > 0) {
                        $inventoryData[$inventoryId] = (float) $value;
                    }
                }
            }

            // For NEW bookings, inventory is REQUIRED
            if (empty($inventoryData)) {
                $form->addError(new FormError('Please select at least one inventory item for this booking.'));
            } else {
                try {
                    $booking->setCreatedBy($this->getUser());

            // Calculate total price based on service base price and guest count
            if ($booking->getService() && $booking->getGuestCount()) {
                $totalPrice = $booking->getService()->getBasePrice() * $booking->getGuestCount();
                $booking->setTotalPrice($totalPrice);
            }
            
            $entityManager->persist($booking);
                    $stockManager->deductStockForBooking($booking, $inventoryData);
            $entityManager->flush();

            // Log booking creation
            $user = $this->getUser();
            if ($user) {
                $activityLogService->logBookingCreation(
                    $user,
                    $booking->getId(),
                    $booking->getCustomerName(),
                    $booking->getEventDate()?->format('Y-m-d')
                );
            }

            $this->addFlash('success', 'Booking created successfully!');
            return $this->redirectToRoute('app_bookings_index', [], Response::HTTP_SEE_OTHER);
                } catch (\Exception $e) {
                    $form->addError(new FormError($e->getMessage()));
                }
            }
        }

        return $this->render('bookings/new.html.twig', [
            'booking' => $booking,
            'form' => $form,
            'isEdit' => false,
            'services' => $servicesRepository->findAll(),
            'availableInventory' => $inventoryRepository->findBy(['status' => 'active']),
        ]);
    }

    #[Route('/{id}', name: 'app_bookings_show', methods: ['GET'])]

    public function show(Booking $booking): Response
    {
        return $this->render('bookings/show.html.twig', [
            'booking' => $booking,
        ]);
    }

    #[Route('/{id}/edit', name: 'app_bookings_edit', methods: ['GET', 'POST'])]
    public function edit(Request $request, Booking $booking, EntityManagerInterface $entityManager, ServicesRepository $servicesRepository, InventoryRepository $inventoryRepository, InventoryStockManager $stockManager, ActivityLogService $activityLogService, NotificationService $notificationService): Response
    {
        $this->assertCanManageBooking($booking);

        $form = $this->createForm(BookingType::class, $booking, ['isEdit' => true]);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $inventoryData = [];
            $requestData = $request->request->all();
            foreach ($requestData as $key => $value) {
                if (strpos($key, 'inventory_quantity_') === 0) {
                    $inventoryId = str_replace('inventory_quantity_', '', $key);
                    if (is_numeric($value) && (float) $value > 0) {
                        $inventoryData[$inventoryId] = (float) $value;
                    }
                }
            }

            // For UPDATE: inventory is OPTIONAL (user can update without modifying inventory)
            // Only update inventory if new inventory items were selected
            if (!empty($inventoryData)) {
                try {
                    $stockManager->restoreStockForBooking($booking);
                    $stockManager->deductStockForBooking($booking, $inventoryData);
                } catch (\Exception $e) {
                    $form->addError(new FormError($e->getMessage()));
                    return $this->render('bookings/edit.html.twig', [
                        'booking' => $booking,
                        'form' => $form,
                        'services' => $servicesRepository->findAll(),
                        'availableInventory' => $inventoryRepository->findBy(['status' => 'active']),
                    ]);
                }
            }
            // If no inventory data provided during update, simply skip inventory operations

            try {
                // Recalculate total price
                if ($booking->getService() && $booking->getGuestCount()) {
                    $totalPrice = $booking->getService()->getBasePrice() * $booking->getGuestCount();
                    $booking->setTotalPrice($totalPrice);
                }

                // Capture the old status before flush using Doctrine's UnitOfWork
                $uow = $entityManager->getUnitOfWork();
                $uow->computeChangeSets();
                $changeSet = $uow->getEntityChangeSet($booking);
                $oldStatus = isset($changeSet['status']) ? $changeSet['status'][0] : $booking->getStatus();
                $newStatus = $booking->getStatus();
                
                $entityManager->flush();

                // Publish real-time notification if status changed
                if (isset($changeSet['status']) && $oldStatus !== $newStatus) {
                    $notificationService->publishBookingStatusUpdate($booking, $oldStatus, $newStatus);
                }

                // Log booking update
                $user = $this->getUser();
                if ($user) {
                    $activityLogService->logBookingUpdate(
                        $user,
                        $booking->getId(),
                        $booking->getCustomerName()
                    );
                }

                $this->addFlash('success', 'Booking updated successfully!');
                return $this->redirectToRoute('app_bookings_index', [], Response::HTTP_SEE_OTHER);
            } catch (\Exception $e) {
                $form->addError(new FormError($e->getMessage()));
            }
        }

        return $this->render('bookings/edit.html.twig', [
            'booking' => $booking,
            'form' => $form,
            'isEdit' => true,
            'services' => $servicesRepository->findAll(),
            'availableInventory' => $inventoryRepository->findBy(['status' => 'active']),
        ]);
    }

    #[Route('/{id}', name: 'app_bookings_delete', methods: ['POST'])]
    public function delete(Request $request, Booking $booking, EntityManagerInterface $entityManager): Response
    {
        $this->assertCanManageBooking($booking);

        if ($this->isCsrfTokenValid('delete'.$booking->getId(), $request->getPayload()->getString('_token'))) {
            // Deletion logging is handled automatically by DoctrineEventListener via postRemove event
            $entityManager->remove($booking);
            $entityManager->flush();

            $this->addFlash('success', 'Booking deleted successfully!');
        }

        return $this->redirectToRoute('app_bookings_index', [], Response::HTTP_SEE_OTHER);
    }

    private function assertCanManageBooking(Booking $booking): void
    {
        $user = $this->getUser();
        if ($this->isGranted('ROLE_ADMIN')) {
            return;
        }

        if (!$user instanceof User) {
            throw $this->createAccessDeniedException();
        }

        $owner = $booking->getCreatedBy();
        if (!$owner || $owner->getId() !== $user->getId()) {
            throw $this->createAccessDeniedException('You can only manage your own bookings.');
        }

        if (in_array('ROLE_ADMIN', $owner->getRoles(), true)) {
            throw $this->createAccessDeniedException('You cannot manage admin records.');
        }
    }
}


