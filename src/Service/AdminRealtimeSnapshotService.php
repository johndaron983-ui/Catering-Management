<?php

namespace App\Service;

use App\Entity\ActivityLog;
use App\Entity\Inventory;
use App\Entity\Product;
use App\Entity\Supplier;
use App\Entity\User;
use App\Repository\ActivityLogRepository;
use App\Repository\BookingRepository;
use App\Repository\InventoryRepository;
use App\Repository\ProductRepository;
use App\Repository\SupplierRepository;
use App\Repository\UserRepository;
use Symfony\Component\HttpFoundation\Request;

/**
 * Builds list payloads for admin module realtime polling.
 */
final class AdminRealtimeSnapshotService
{
    public const MODULE_INVENTORY = 'inventory';
    public const MODULE_PRODUCTS = 'products';
    public const MODULE_SUPPLIERS = 'suppliers';
    public const MODULE_USERS = 'users';
    public const MODULE_ACTIVITY_LOGS = 'activity_logs';

  private const ALLOWED = [
        self::MODULE_INVENTORY,
        self::MODULE_PRODUCTS,
        self::MODULE_SUPPLIERS,
        self::MODULE_USERS,
        self::MODULE_ACTIVITY_LOGS,
    ];

    public function __construct(
        private InventoryRepository $inventoryRepository,
        private ProductRepository $productRepository,
        private SupplierRepository $supplierRepository,
        private UserRepository $userRepository,
        private ActivityLogRepository $activityLogRepository,
        private BookingRepository $bookingRepository,
    ) {
    }

    public function isAllowedModule(string $module): bool
    {
        return \in_array($module, self::ALLOWED, true);
    }

    public function requiresAdmin(string $module): bool
    {
        return \in_array($module, [self::MODULE_USERS, self::MODULE_ACTIVITY_LOGS], true);
    }

    /**
     * @return array<string, mixed>
     */
    public function build(string $module, Request $request): array
    {
        return match ($module) {
            self::MODULE_INVENTORY => $this->buildInventory($request),
            self::MODULE_PRODUCTS => $this->buildProducts($request),
            self::MODULE_SUPPLIERS => $this->buildSuppliers($request),
            self::MODULE_USERS => $this->buildUsers($request),
            self::MODULE_ACTIVITY_LOGS => $this->buildActivityLogs($request),
            default => throw new \InvalidArgumentException('Unknown module: ' . $module),
        };
    }

    /**
     * @return array<string, mixed>
     */
    private function buildInventory(Request $request): array
    {
        $qb = $this->inventoryRepository->createQueryBuilder('i')
            ->leftJoin('i.createdBy', 'cb')
            ->addSelect('cb');

        $status = $request->query->get('status', 'active');
        if ($status) {
            $qb->andWhere('i.status = :status')->setParameter('status', $status);
        }

        $inventories = $qb->orderBy('i.name', 'ASC')->getQuery()->getResult();

        $latestId = 0;
        foreach ($inventories as $item) {
            if ($item instanceof Inventory && $item->getId() !== null) {
                $latestId = max($latestId, $item->getId());
            }
        }

        return [
            'module' => self::MODULE_INVENTORY,
            'latestId' => $latestId,
            'stats' => $this->inventoryRepository->getInventoryStats(),
            'low_stock_count' => \count($this->inventoryRepository->findLowStockItems()),
            'expiring_count' => \count($this->inventoryRepository->findExpiredOrExpiringItems()),
            'inventories' => $inventories,
            'row_count' => \count($inventories),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function buildProducts(Request $request): array
    {
        $qb = $this->productRepository->createQueryBuilder('p')
            ->leftJoin('p.createdBy', 'cb')
            ->addSelect('cb');

        $search = $request->query->get('search');
        if ($search) {
            $qb->andWhere('p.name LIKE :search')->setParameter('search', '%' . $search . '%');
        }

        $category = $request->query->get('category');
        if ($category) {
            $qb->andWhere('p.category = :category')->setParameter('category', $category);
        }

        $products = $qb->orderBy('p.name', 'ASC')->getQuery()->getResult();

        $latestId = 0;
        foreach ($products as $product) {
            if ($product instanceof Product && $product->getId() !== null) {
                $latestId = max($latestId, $product->getId());
            }
        }

        return [
            'module' => self::MODULE_PRODUCTS,
            'latestId' => $latestId,
            'products' => $products,
            'row_count' => \count($products),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function buildSuppliers(Request $request): array
    {
        $qb = $this->supplierRepository->createQueryBuilder('s')
            ->leftJoin('s.createdBy', 'cb')
            ->addSelect('cb');

        $search = $request->query->get('search');
        if ($search) {
            $qb->andWhere('s.name LIKE :search OR s.contact LIKE :search')
                ->setParameter('search', '%' . $search . '%');
        }

        $product = $request->query->get('product');
        if ($product) {
            $qb->andWhere('s.product = :product')->setParameter('product', $product);
        }

        $suppliers = $qb->orderBy('s.name', 'ASC')->getQuery()->getResult();

        $latestId = 0;
        foreach ($suppliers as $supplier) {
            if ($supplier instanceof Supplier && $supplier->getId() !== null) {
                $latestId = max($latestId, $supplier->getId());
            }
        }

        return [
            'module' => self::MODULE_SUPPLIERS,
            'latestId' => $latestId,
            'suppliers' => $suppliers,
            'row_count' => \count($suppliers),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function buildUsers(Request $request): array
    {
        $page = max(1, $request->query->getInt('page', 1));
        $limit = 25;
        $offset = ($page - 1) * $limit;

        $qb = $this->userRepository->createQueryBuilder('u');

        $search = $request->query->get('search');
        if ($search) {
            $qb->andWhere('u.username LIKE :search OR u.email LIKE :search')
                ->setParameter('search', '%' . $search . '%');
        }

        $role = $request->query->get('role');
        if ($role) {
            $qb->andWhere('u.roles LIKE :role')->setParameter('role', '%' . $role . '%');
        }

        $status = $request->query->get('status');
        if ($status) {
            $qb->andWhere('u.status = :status')->setParameter('status', $status);
        }

        $totalUsers = (int) (clone $qb)
            ->select('COUNT(u.id)')
            ->getQuery()
            ->getSingleScalarResult();

        $users = $qb
            ->orderBy('u.id', 'DESC')
            ->setFirstResult($offset)
            ->setMaxResults($limit)
            ->getQuery()
            ->getResult();

        $latestId = 0;
        foreach ($users as $user) {
            if ($user instanceof User && $user->getId() !== null) {
                $latestId = max($latestId, $user->getId());
            }
        }

        return [
            'module' => self::MODULE_USERS,
            'latestId' => $latestId,
            'users' => $users,
            'row_count' => \count($users),
            'total_users' => $totalUsers,
            'page' => $page,
        ];
    }

    /**
     * Activity logs: always newest first (DESC by createdAt).
     *
     * @return array<string, mixed>
     */
    private function buildActivityLogs(Request $request): array
    {
        $page = max(1, $request->query->getInt('page', 1));
        $limit = 50;
        $offset = ($page - 1) * $limit;

        $qb = $this->activityLogRepository->createQueryBuilder('a')
            ->orderBy('a.createdAt', 'DESC');

        $username = $request->query->get('username');
        if ($username) {
            $qb->andWhere('a.username LIKE :username')
                ->setParameter('username', '%' . $username . '%');
        }

        $action = $request->query->get('action');
        if ($action) {
            $qb->andWhere('a.action = :action')->setParameter('action', $action);
        }

        $recordType = $request->query->get('record_type');
        if ($recordType) {
            $qb->andWhere('a.recordType = :recordType')->setParameter('recordType', $recordType);
        }

        $role = $request->query->get('role');
        if ($role) {
            $qb->andWhere('a.role = :role')->setParameter('role', $role);
        }

        $totalCount = (int) (clone $qb)
            ->select('COUNT(a.id)')
            ->getQuery()
            ->getSingleScalarResult();

        $logs = $qb
            ->setFirstResult($offset)
            ->setMaxResults($limit)
            ->getQuery()
            ->getResult();

        $latestLogId = 0;
        foreach ($logs as $log) {
            if ($log instanceof ActivityLog && $log->getId() !== null) {
                $latestLogId = max($latestLogId, $log->getId());
            }
        }

        return [
            'module' => self::MODULE_ACTIVITY_LOGS,
            'latestLogId' => $latestLogId,
            'logs' => $logs,
            'total_count' => $totalCount,
            'row_count' => \count($logs),
            'page' => $page,
        ];
    }

    /**
     * @return array<string, int>
     */
    public function sidebarCounts(): array
    {
        $pendingBookings = $this->bookingRepository->count(['status' => 'pending']);
        $lowStock = \count($this->inventoryRepository->findLowStockItems());

        return [
            'pending_bookings' => $pendingBookings,
            'low_stock_inventory' => $lowStock,
        ];
    }
}
