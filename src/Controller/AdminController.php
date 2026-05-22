<?php

namespace App\Controller;

use App\Repository\BookingRepository;
use App\Repository\ServicesRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/admin')]
#[IsGranted('ROLE_ADMIN')]
final class AdminController extends AbstractController
{
    public function __construct(
        private BookingRepository $bookingRepository,
        private ServicesRepository $servicesRepository
    ) {}

    #[Route(name: 'app_admin_dashboard', methods: ['GET'])]
    public function dashboard(): Response
    {
        // TO GET ALL BOOKINGS FOR CALCULATIONS
        $allBookings = $this->bookingRepository->findAll();
        
        // TO CALCULATE TOTAL REVENUE
        $totalRevenue = array_sum(array_map(fn($booking) => $booking->getTotalPrice(), $allBookings));
        
        // GET ACTIVE BOOKINGS CONFIRMED AND PENDING
        $activeBookings = $this->bookingRepository->createQueryBuilder('b')
            ->where('b.status IN (:statuses)')
            ->setParameter('statuses', ['confirmed', 'pending', 'processing'])
            ->getQuery()
            ->getResult();
        
        // GET PENDING REQUESTS
        $pendingRequests = $this->bookingRepository->findBy(['status' => 'pending']);
        
        // GET RECENT BOOKINGS LAST 5
        $recentBookings = $this->bookingRepository->createQueryBuilder('b')
            ->orderBy('b.eventDate', 'DESC')
            ->setMaxResults(5)
            ->getQuery()
            ->getResult();
        
        // GET TOP SERVICES WITH BOOKING COUNTS AND REVENUE
        $topServices = $this->getTopServices();
        
        // CALCULATE MONTHLY REVENUE (LAST 4 WEEKS)
        $monthlyRevenue = $this->getMonthlyRevenue();
        
        // GET BOOKING TRENDS FOR THE WEEK
        $weeklyBookings = $this->getWeeklyBookingTrends();
        
        // CALCULATE GROWTH PERCENTAGES
        $revenueGrowth = $this->calculateRevenueGrowth();
        $bookingGrowth = $this->calculateBookingGrowth();

        return $this->render('admin/dashboard.html.twig', [
            'totalRevenue' => $totalRevenue,
            'activeBookings' => count($activeBookings),
            'pendingRequests' => count($pendingRequests),
            'recentBookings' => $recentBookings,
            'topServices' => $topServices,
            'monthlyRevenue' => $monthlyRevenue,
            'weeklyBookings' => $weeklyBookings,
            'revenueGrowth' => $revenueGrowth,
            'bookingGrowth' => $bookingGrowth,
        ]);
    }

    private function getTopServices(): array
    {
        $query = $this->servicesRepository->createQueryBuilder('s')
            ->select('s.id, s.name, s.image, s.basePrice, COUNT(b.id) as bookingCount, SUM(b.totalPrice) as totalRevenue')
            ->leftJoin('s.bookings', 'b')
            ->groupBy('s.id')
            ->orderBy('bookingCount', 'DESC')
            ->setMaxResults(5)
            ->getQuery();

        return $query->getResult();
    }

    private function getMonthlyRevenue(): array
    {
        // GET REVENUE FOR LAST 7 DAYS
        $dailyRevenue = [];
        
        for ($i = 6; $i >= 0; $i--) {
            $date = new \DateTime("-$i days");
            $startDate = clone $date;
            $startDate->setTime(0, 0, 0);
            $endDate = clone $date;
            $endDate->setTime(23, 59, 59);
            
            $revenue = $this->bookingRepository->createQueryBuilder('b')
                ->select('SUM(b.totalPrice)')
                ->where('b.eventDate >= :start')
                ->andWhere('b.eventDate <= :end')
                ->andWhere('b.status IN (:statuses)')
                ->setParameter('start', $startDate->format('Y-m-d H:i:s'))
                ->setParameter('end', $endDate->format('Y-m-d H:i:s'))
                ->setParameter('statuses', ['confirmed', 'pending', 'processing'])
                ->getQuery()
                ->getSingleScalarResult() ?? 0;
            
            $dailyRevenue[] = round($revenue, 2);
        }
        
        return $dailyRevenue;
    }

    private function getWeeklyBookingTrends(): array
    {
        // GET BOOKING COUNTS FOR LAST 7 DAYS
        $dailyBookings = [];
        
        for ($i = 6; $i >= 0; $i--) {
            $date = new \DateTime("-$i days");
            $startDate = clone $date;
            $startDate->setTime(0, 0, 0);
            $endDate = clone $date;
            $endDate->setTime(23, 59, 59);
            
            $count = $this->bookingRepository->createQueryBuilder('b')
                ->select('COUNT(b.id)')
                ->where('b.eventDate >= :start')
                ->andWhere('b.eventDate <= :end')
                ->setParameter('start', $startDate->format('Y-m-d H:i:s'))
                ->setParameter('end', $endDate->format('Y-m-d H:i:s'))
                ->getQuery()
                ->getSingleScalarResult() ?? 0;
            
            $dailyBookings[] = (int) $count;
        }
        
        return $dailyBookings;
    }

    private function calculateRevenueGrowth(): float
    {
        // GET CURRENT MONTH START AND END DATES
        $currentMonthStart = new \DateTime('first day of this month');
        $currentMonthEnd = new \DateTime('last day of this month');
        
        // GET PREVIOUS MONTH START AND END DATES
        $previousMonthStart = new \DateTime('first day of last month');
        $previousMonthEnd = new \DateTime('last day of last month');

        // CALCULATE CURRENT MONTH REVENUE
        $currentMonth = $this->bookingRepository->createQueryBuilder('b')
            ->select('SUM(b.totalPrice)')
            ->where('b.eventDate >= :start')
            ->andWhere('b.eventDate <= :end')
            ->andWhere('b.status = :status')
            ->setParameter('start', $currentMonthStart->format('Y-m-d'))
            ->setParameter('end', $currentMonthEnd->format('Y-m-d'))
            ->setParameter('status', 'confirmed')
            ->getQuery()
            ->getSingleScalarResult() ?? 0;

        // CALCULATE PREVIOUS MONTH REVENUE
        $previousMonth = $this->bookingRepository->createQueryBuilder('b')
            ->select('SUM(b.totalPrice)')
            ->where('b.eventDate >= :start')
            ->andWhere('b.eventDate <= :end')
            ->andWhere('b.status = :status')
            ->setParameter('start', $previousMonthStart->format('Y-m-d'))
            ->setParameter('end', $previousMonthEnd->format('Y-m-d'))
            ->setParameter('status', 'confirmed')
            ->getQuery()
            ->getSingleScalarResult() ?? 0;

        if ($previousMonth == 0) {
            return $currentMonth > 0 ? 100 : 0;
        }

        return round((($currentMonth - $previousMonth) / $previousMonth) * 100, 1);
    }

    private function calculateBookingGrowth(): int
    {
        // GET CURRENT WEEK START AND END DATES
        $currentWeekStart = new \DateTime('monday this week');
        $currentWeekEnd = new \DateTime('sunday this week');
        
        // GET PREVIOUS WEEK START AND END DATES
        $previousWeekStart = new \DateTime('monday last week');
        $previousWeekEnd = new \DateTime('sunday last week');

        // COUNT CURRENT WEEK BOOKINGS
        $currentWeek = $this->bookingRepository->createQueryBuilder('b')
            ->select('COUNT(b.id)')
            ->where('b.eventDate >= :start')
            ->andWhere('b.eventDate <= :end')
            ->setParameter('start', $currentWeekStart->format('Y-m-d'))
            ->setParameter('end', $currentWeekEnd->format('Y-m-d'))
            ->getQuery()
            ->getSingleScalarResult() ?? 0;

        // COUNT PREVIOUS WEEK BOOKINGS
        $previousWeek = $this->bookingRepository->createQueryBuilder('b')
            ->select('COUNT(b.id)')
            ->where('b.eventDate >= :start')
            ->andWhere('b.eventDate <= :end')
            ->setParameter('start', $previousWeekStart->format('Y-m-d'))
            ->setParameter('end', $previousWeekEnd->format('Y-m-d'))
            ->getQuery()
            ->getSingleScalarResult() ?? 0;

        return $currentWeek - $previousWeek;
    }

    #[Route('/dashboard/chart-data', name: 'app_admin_chart_data', methods: ['GET'])]
    public function getChartData(Request $request): JsonResponse
    {
        try {
            $period = $request->query->get('period', '30D');
            
            // VALIDATE PERIOD PARAMETER
            if (!in_array($period, ['7D', '30D', '90D'])) {
                $period = '30D';
            }
            
            $revenueData = $this->getRevenueDataForPeriod($period);
            $bookingData = $this->getBookingDataForPeriod($period);
            
            return new JsonResponse([
                'revenue' => $revenueData,
                'bookings' => $bookingData,
                'period' => $period,
                'labels' => $this->getLabelsForPeriod($period),
                'success' => true
            ]);
        } catch (\Exception $e) {
            return new JsonResponse([
                'error' => 'Failed to fetch chart data',
                'message' => $e->getMessage(),
                'success' => false
            ], 500);
        }
    }

    private function getRevenueDataForPeriod(string $period): array
    {
        switch ($period) {
            case '7D':
                return $this->getWeeklyRevenueData();
            case '30D':
                return $this->getMonthlyRevenueData();
            case '90D':
                return $this->getQuarterlyRevenueData();
            default:
                return $this->getMonthlyRevenueData();
        }
    }

    private function getBookingDataForPeriod(string $period): array
    {
        switch ($period) {
            case '7D':
                return $this->getWeeklyBookingData();
            case '30D':
                return $this->getMonthlyBookingData();
            case '90D':
                return $this->getQuarterlyBookingData();
            default:
                return $this->getWeeklyBookingTrends();
        }
    }

    private function getLabelsForPeriod(string $period): array
    {
        switch ($period) {
            case '7D':
                return ['Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat', 'Sun'];
            case '30D':
                return ['Week 1', 'Week 2', 'Week 3', 'Week 4'];
            case '90D':
                return ['Month 1', 'Month 2', 'Month 3'];
            default:
                return ['Week 1', 'Week 2', 'Week 3', 'Week 4'];
        }
    }

    private function getWeeklyRevenueData(): array
    {
        $weeklyRevenue = [];
        for ($i = 6; $i >= 0; $i--) {
            $date = new \DateTime("-$i days");
            $startDate = clone $date;
            $startDate->setTime(0, 0, 0);
            $endDate = clone $date;
            $endDate->setTime(23, 59, 59);
            
            $revenue = $this->bookingRepository->createQueryBuilder('b')
                ->select('SUM(b.totalPrice)')
                ->where('b.eventDate >= :start')
                ->andWhere('b.eventDate <= :end')
                ->andWhere('b.status IN (:statuses)')
                ->setParameter('start', $startDate->format('Y-m-d H:i:s'))
                ->setParameter('end', $endDate->format('Y-m-d H:i:s'))
                ->setParameter('statuses', ['confirmed', 'pending', 'processing'])
                ->getQuery()
                ->getSingleScalarResult() ?? 0;
                
            $weeklyRevenue[] = round($revenue, 2);
        }
        
        return $weeklyRevenue;
    }

    private function getMonthlyRevenueData(): array
    {
        // Use the existing method
        return $this->getMonthlyRevenue();
    }

    private function getQuarterlyRevenueData(): array
    {
        $monthlyRevenue = [];
        for ($i = 2; $i >= 0; $i--) {
            $startDate = new \DateTime("-$i months first day of this month");
            $endDate = new \DateTime("-$i months last day of this month");
            
            $revenue = $this->bookingRepository->createQueryBuilder('b')
                ->select('SUM(b.totalPrice)')
                ->where('b.eventDate BETWEEN :start AND :end')
                ->andWhere('b.status IN (:statuses)')
                ->setParameter('start', $startDate->format('Y-m-d'))
                ->setParameter('end', $endDate->format('Y-m-d'))
                ->setParameter('statuses', ['confirmed', 'pending', 'processing'])
                ->getQuery()
                ->getSingleScalarResult() ?? 0;
                
            $monthlyRevenue[] = round($revenue, 2);
        }
        
        return $monthlyRevenue;
    }

    private function getWeeklyBookingData(): array
    {
        
        return $this->getWeeklyBookingTrends();
    }

    private function getMonthlyBookingData(): array
    {
        $weeklyBookings = [];
        for ($i = 3; $i >= 0; $i--) {
            $startDate = new \DateTime("-$i weeks monday");
            $endDate = new \DateTime("-$i weeks sunday");
            
            $count = $this->bookingRepository->createQueryBuilder('b')
                ->select('COUNT(b.id)')
                ->where('b.eventDate BETWEEN :start AND :end')
                ->setParameter('start', $startDate->format('Y-m-d'))
                ->setParameter('end', $endDate->format('Y-m-d'))
                ->getQuery()
                ->getSingleScalarResult() ?? 0;
                
            $weeklyBookings[] = $count;
        }
        
        return $weeklyBookings;
    }

    private function getQuarterlyBookingData(): array
    {
        $monthlyBookings = [];
        for ($i = 2; $i >= 0; $i--) {
            $startDate = new \DateTime("-$i months first day of this month");
            $endDate = new \DateTime("-$i months last day of this month");
            
            $count = $this->bookingRepository->createQueryBuilder('b')
                ->select('COUNT(b.id)')
                ->where('b.eventDate BETWEEN :start AND :end')
                ->setParameter('start', $startDate->format('Y-m-d'))
                ->setParameter('end', $endDate->format('Y-m-d'))
                ->getQuery()
                ->getSingleScalarResult() ?? 0;
                
            $monthlyBookings[] = $count;
        }
        
        return $monthlyBookings;
    }

}


