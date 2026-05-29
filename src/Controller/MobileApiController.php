<?php

namespace App\Controller;

use App\Entity\Booking;
use App\Entity\Services;
use App\Entity\User;
use App\Repository\BookingRepository;
use App\Repository\ServicesRepository;
use App\Trait\ApiResponseTrait;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use App\Service\AdminRealtimePublisher;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;
#[Route('/api/mobile')]
class MobileApiController extends AbstractController
{
    use ApiResponseTrait;

    private ServicesRepository $servicesRepository;
    private BookingRepository $bookingRepository;
    private EntityManagerInterface $entityManager;
    private AdminRealtimePublisher $adminRealtimePublisher;

    public function __construct(
        ServicesRepository $servicesRepository,
        BookingRepository $bookingRepository,
        EntityManagerInterface $entityManager,
        AdminRealtimePublisher $adminRealtimePublisher,
    ) {
        $this->servicesRepository = $servicesRepository;
        $this->bookingRepository = $bookingRepository;
        $this->entityManager = $entityManager;
        $this->adminRealtimePublisher = $adminRealtimePublisher;
    }

    /**
     * Public health check endpoint.
     * 
     * Returns API status and version information. No authentication required.
     */
    #[Route('/health', name: 'api_mobile_health', methods: ['GET'])]
    public function healthCheck(): JsonResponse
    {
        return $this->successResponse([
            'status' => 'healthy',
            'version' => '1.0.0',
            'timestamp' => (new \DateTimeImmutable())->format('c'),
        ], 'Mobile API is operational');
    }

    /**
     * Get all available catering services.
     * 
     * Returns a list of all active services with their details.
     * Supports pagination via query parameters.
     */
    #[Route('/services', name: 'api_mobile_services', methods: ['GET'])]
    #[IsGranted('ROLE_USER')]
    public function getServices(Request $request): JsonResponse
    {
        $page = (int) $request->query->get('page', 1);
        $limit = (int) $request->query->get('limit', 20);

        // Ensure valid pagination values
        $page = max(1, $page);
        $limit = max(1, min(100, $limit));

        $offset = ($page - 1) * $limit;

        // Get total count for pagination
        $total = $this->servicesRepository->count([]);

        // Get paginated services
        $services = $this->servicesRepository->findBy(
            [],
            ['createdAt' => 'DESC'],
            $limit,
            $offset
        );

        $data = array_map(function (Services $service) {
            return $this->serializeService($service);
        }, $services);

        return $this->paginatedResponse($data, $page, $limit, $total, 'Services retrieved successfully');
    }

    /**
     * Get a single service by ID.
     */
    #[Route('/services/{id}', name: 'api_mobile_service_detail', methods: ['GET'])]
    #[IsGranted('ROLE_USER')]
    public function getServiceDetail(int $id): JsonResponse
    {
        $service = $this->servicesRepository->find($id);

        if (!$service) {
            return $this->errorResponse('Service not found', 404);
        }

        return $this->successResponse(
            $this->serializeService($service, true),
            'Service retrieved successfully'
        );
    }

    /**
     * Get current user's bookings.
     * 
     * Returns a list of bookings for the authenticated user.
     * Supports status filtering and pagination.
     */
    #[Route('/bookings', name: 'api_mobile_bookings', methods: ['GET'])]
    #[IsGranted('ROLE_USER')]
    public function getBookings(Request $request): JsonResponse
    {
        /** @var User $user */
        $user = $this->getUser();

        $page = (int) $request->query->get('page', 1);
        $limit = (int) $request->query->get('limit', 20);
        $status = $request->query->get('status');

        // Ensure valid pagination values
        $page = max(1, $page);
        $limit = max(1, min(100, $limit));

        $queryBuilder = $this->bookingRepository->createQueryBuilder('b')
            ->where('b.createdBy = :user')
            ->setParameter('user', $user);

        if ($status && in_array($status, ['pending', 'confirmed', 'completed', 'cancelled'])) {
            $queryBuilder->andWhere('b.status = :status')
                ->setParameter('status', $status);
        }

        // Get total count for pagination
        $totalQuery = clone $queryBuilder;
        $total = (int) $totalQuery->select('COUNT(b.id)')->getQuery()->getSingleScalarResult();

        // Get paginated bookings
        $bookings = $queryBuilder
            ->orderBy('b.eventDate', 'DESC')
            ->setFirstResult(($page - 1) * $limit)
            ->setMaxResults($limit)
            ->getQuery()
            ->getResult();

        $data = array_map(function (Booking $booking) {
            return $this->serializeBooking($booking);
        }, $bookings);

        return $this->paginatedResponse($data, $page, $limit, $total, 'Bookings retrieved successfully');
    }

    /**
     * Get a single booking by ID.
     * Users can only view their own bookings unless they are admin.
     */
    #[Route('/bookings/{id}', name: 'api_mobile_booking_detail', methods: ['GET'])]
    #[IsGranted('ROLE_USER')]
    public function getBookingDetail(int $id): JsonResponse
    {
        /** @var User $user */
        $user = $this->getUser();

        $booking = $this->bookingRepository->find($id);

        if (!$booking) {
            return $this->errorResponse('Booking not found', 404);
        }

        // Check if user owns this booking or is admin
        if ($booking->getCreatedBy()->getId() !== $user->getId() && !$this->isGranted('ROLE_ADMIN')) {
            return $this->errorResponse('Access denied', 403);
        }

        return $this->successResponse(
            $this->serializeBooking($booking, true),
            'Booking retrieved successfully'
        );
    }

    /**
     * Create a new booking from mobile app.
     */
    #[Route('/bookings', name: 'api_mobile_bookings_create', methods: ['POST'])]
    #[IsGranted('ROLE_USER')]
    public function createBooking(Request $request): JsonResponse
    {
        /** @var User $user */
        $user = $this->getUser();

        $data = json_decode($request->getContent(), true);

        if (!$data) {
            return $this->errorResponse('Invalid JSON data', 400);
        }

        // Validate required fields
        $required = ['service_id', 'customer_name', 'event_date', 'guest_count'];
        $missing = [];
        foreach ($required as $field) {
            if (!isset($data[$field]) || empty($data[$field])) {
                $missing[] = $field;
            }
        }

        if (!empty($missing)) {
            return $this->errorResponse(
                'Missing required fields',
                400,
                ['missing_fields' => $missing]
            );
        }

        // Validate service exists
        $service = $this->servicesRepository->find($data['service_id']);
        if (!$service) {
            return $this->errorResponse('Service not found', 404);
        }

        // Create booking
        $booking = new Booking();
        $booking->setService($service);
        $booking->setCustomerName($data['customer_name']);
        $booking->setCreatedBy($user);

        // Parse event date
        try {
            $eventDate = new \DateTimeImmutable($data['event_date']);
            if ($eventDate < new \DateTimeImmutable('today')) {
                return $this->errorResponse('Event date must be today or in the future', 400);
            }
            $booking->setEventDate($eventDate);
        } catch (\Exception $e) {
            return $this->errorResponse('Invalid date format. Use YYYY-MM-DD', 400);
        }

        // Validate and set guest count
        $guestCount = (int) $data['guest_count'];
        if ($guestCount < $service->getMinGuests() || $guestCount > $service->getMaxGuests()) {
            return $this->errorResponse(
                "Guest count must be between {$service->getMinGuests()} and {$service->getMaxGuests()}",
                400
            );
        }
        $booking->setGuestCount($guestCount);

        // Calculate total price
        $totalPrice = $service->getBasePrice() * $guestCount;
        $booking->setTotalPrice($totalPrice);

        // Set optional notes if provided
        if (isset($data['notes'])) {
            // Notes field doesn't exist on Booking entity, skip for now
            // Could be added in future migration
        }

        $this->entityManager->persist($booking);
        $this->entityManager->flush();

        $serviceImage = $service->getImage();
        $this->adminRealtimePublisher->publishBookingCreated([
            'id' => $booking->getId(),
            'service_name' => $service->getName(),
            'service_image' => $serviceImage ? '/image/' . $serviceImage : null,
            'customer_name' => $booking->getCustomerName(),
            'event_date' => $booking->getEventDate()?->format('Y-m-d H:i'),
            'status' => $booking->getStatus(),
            'guest_count' => $booking->getGuestCount(),
            'total_price' => $booking->getTotalPrice(),
            'created_by' => $user->getUsername(),
            'created_at' => (new \DateTimeImmutable())->format('c'),
        ]);

        return $this->successResponse(
            $this->serializeBooking($booking, true),
            'Booking created successfully',
            201
        );
    }

    /**
     * Get current user profile.
     * 
     * Returns the authenticated user's profile information.
     */
    #[Route('/profile', name: 'api_mobile_profile', methods: ['GET'])]
    #[IsGranted('ROLE_USER')]
    public function getProfile(): JsonResponse
    {
        /** @var User $user */
        $user = $this->getUser();

        if (!$user instanceof User) {
            return $this->errorResponse('User not found', 404);
        }

        $data = [
            'id' => $user->getId(),
            'username' => $user->getUsername(),
            'email' => $user->getEmail(),
            'roles' => $user->getRoles(),
            'status' => $user->getStatus(),
            'is_verified' => $user->isVerified(),
            'created_at' => $user->getCreatedAt()?->format('Y-m-d H:i:s'),
        ];

        return $this->successResponse($data, 'Profile retrieved successfully');
    }

    /**
     * Get dashboard statistics for the current user.
     * 
     * Returns summary data like total bookings, upcoming events, etc.
     */
    #[Route('/dashboard', name: 'api_mobile_dashboard', methods: ['GET'])]
    #[IsGranted('ROLE_USER')]
    public function getDashboard(): JsonResponse
    {
        /** @var User $user */
        $user = $this->getUser();

        // Count total bookings
        $totalBookings = $this->bookingRepository->count(['createdBy' => $user]);

        // Count upcoming bookings (event date >= today)
        $upcomingBookings = $this->bookingRepository->createQueryBuilder('b')
            ->select('COUNT(b.id)')
            ->where('b.createdBy = :user')
            ->andWhere('b.eventDate >= :today')
            ->setParameter('user', $user)
            ->setParameter('today', new \DateTimeImmutable('today'))
            ->getQuery()
            ->getSingleScalarResult();

        // Count pending bookings
        $pendingBookings = $this->bookingRepository->count([
            'createdBy' => $user,
            'status' => 'pending'
        ]);

        // Count confirmed bookings
        $confirmedBookings = $this->bookingRepository->count([
            'createdBy' => $user,
            'status' => 'confirmed'
        ]);

        // Get next upcoming booking
        $nextBooking = $this->bookingRepository->createQueryBuilder('b')
            ->where('b.createdBy = :user')
            ->andWhere('b.eventDate >= :today')
            ->setParameter('user', $user)
            ->setParameter('today', new \DateTimeImmutable('today'))
            ->orderBy('b.eventDate', 'ASC')
            ->setMaxResults(1)
            ->getQuery()
            ->getOneOrNullResult();

        $data = [
            'statistics' => [
                'total_bookings' => $totalBookings,
                'upcoming_bookings' => $upcomingBookings,
                'pending_bookings' => $pendingBookings,
                'confirmed_bookings' => $confirmedBookings,
            ],
            'next_booking' => $nextBooking ? $this->serializeBooking($nextBooking) : null,
        ];

        return $this->successResponse($data, 'Dashboard data retrieved successfully');
    }

    /**
     * Serialize a Service entity to array.
     */
    private function serializeService(Services $service, bool $detailed = false): array
    {
        $data = [
            'id' => $service->getId(),
            'name' => $service->getName(),
            'description' => $service->getDescription(),
            'event_type' => $service->getEventType(),
            'base_price' => $service->getBasePrice(),
            'min_guests' => $service->getMinGuests(),
            'max_guests' => $service->getMaxGuests(),
            'image' => $service->getImage(),
        ];

        if ($detailed) {
            $data['created_at'] = $service->getCreatedAt()?->format('Y-m-d H:i:s');
            $data['updated_at'] = $service->getUpdatedAt()?->format('Y-m-d H:i:s');
        }

        return $data;
    }

    /**
     * Serialize a Booking entity to array.
     */
    private function serializeBooking(Booking $booking, bool $detailed = false): array
    {
        $service = $booking->getService();

        $data = [
            'id' => $booking->getId(),
            'customer_name' => $booking->getCustomerName(),
            'event_date' => $booking->getEventDate()?->format('Y-m-d'),
            'status' => $booking->getStatus(),
            'guest_count' => $booking->getGuestCount(),
            'total_price' => $booking->getTotalPrice(),
            'service' => $service ? [
                'id' => $service->getId(),
                'name' => $service->getName(),
                'event_type' => $service->getEventType(),
            ] : null,
        ];

        if ($detailed) {
            $data['service_details'] = $service ? $this->serializeService($service) : null;
            $data['created_at'] = $booking->getEventDate()?->format('Y-m-d H:i:s');
        }

        return $data;
    }
}
