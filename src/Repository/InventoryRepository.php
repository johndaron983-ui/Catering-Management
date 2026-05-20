<?php

namespace App\Repository;

use App\Entity\Inventory;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Inventory>
 */
class InventoryRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Inventory::class);
    }

    public function save(Inventory $entity, bool $flush = false): void
    {
        $this->getEntityManager()->persist($entity);

        if ($flush) {
            $this->getEntityManager()->flush();
        }
    }

    public function remove(Inventory $entity, bool $flush = false): void
    {
        $this->getEntityManager()->remove($entity);

        if ($flush) {
            $this->getEntityManager()->flush();
        }
    }

    /**
     * Find inventory item by product
     */
    public function findOneByProduct($product): ?Inventory
    {
        return $this->createQueryBuilder('i')
            ->where('i.product = :product')
            ->setParameter('product', $product)
            ->getQuery()
            ->getOneOrNullResult();
    }

    /**
     * Find items that are low on stock
     */
    public function findLowStockItems(): array
    {
        return $this->createQueryBuilder('i')
            ->where('i.currentStock <= i.minimumStock')
            ->andWhere('i.status = :status')
            ->setParameter('status', 'active')
            ->orderBy('i.currentStock', 'ASC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Find items that are out of stock
     */
    public function findOutOfStockItems(): array
    {
        return $this->createQueryBuilder('i')
            ->where('i.currentStock <= 0')
            ->andWhere('i.status = :status')
            ->setParameter('status', 'active')
            ->orderBy('i.name', 'ASC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Find items that are expired or expiring soon
     */
    public function findExpiredOrExpiringItems(int $daysAhead = 7): array
    {
        $expiryDate = new \DateTime();
        $expiryDate->modify("+{$daysAhead} days");

        return $this->createQueryBuilder('i')
            ->where('i.expiryDate IS NOT NULL')
            ->andWhere('i.expiryDate <= :expiryDate')
            ->andWhere('i.status = :status')
            ->setParameter('expiryDate', $expiryDate)
            ->setParameter('status', 'active')
            ->orderBy('i.expiryDate', 'ASC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Find items by category
     */
    public function findByCategory(string $category): array
    {
        return $this->createQueryBuilder('i')
            ->where('i.category = :category')
            ->andWhere('i.status = :status')
            ->setParameter('category', $category)
            ->setParameter('status', 'active')
            ->orderBy('i.name', 'ASC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Get all unique categories
     */
    public function findAllCategories(): array
    {
        return $this->createQueryBuilder('i')
            ->select('DISTINCT i.category')
            ->where('i.status = :status')
            ->setParameter('status', 'active')
            ->orderBy('i.category', 'ASC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Search items by name or description
     */
    public function searchItems(string $searchTerm): array
    {
        return $this->createQueryBuilder('i')
            ->where('i.name LIKE :searchTerm OR i.description LIKE :searchTerm')
            ->andWhere('i.status = :status')
            ->setParameter('searchTerm', '%' . $searchTerm . '%')
            ->setParameter('status', 'active')
            ->orderBy('i.name', 'ASC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Get inventory statistics
     */
    public function getInventoryStats(): array
    {
        $qb = $this->createQueryBuilder('i')
            ->where('i.status = :status')
            ->setParameter('status', 'active');

        $totalItems = $qb->select('COUNT(i.id)')
            ->getQuery()
            ->getSingleScalarResult();

        $lowStockCount = $this->createQueryBuilder('i')
            ->select('COUNT(i.id)')
            ->where('i.currentStock <= i.minimumStock')
            ->andWhere('i.status = :status')
            ->setParameter('status', 'active')
            ->getQuery()
            ->getSingleScalarResult();

        $outOfStockCount = $this->createQueryBuilder('i')
            ->select('COUNT(i.id)')
            ->where('i.currentStock <= 0')
            ->andWhere('i.status = :status')
            ->setParameter('status', 'active')
            ->getQuery()
            ->getSingleScalarResult();

        $totalValue = $this->createQueryBuilder('i')
            ->select('SUM(i.currentStock * i.unitPrice)')
            ->where('i.status = :status')
            ->setParameter('status', 'active')
            ->getQuery()
            ->getSingleScalarResult();

        return [
            'total_items' => (int) $totalItems,
            'low_stock_count' => (int) $lowStockCount,
            'out_of_stock_count' => (int) $outOfStockCount,
            'total_value' => (float) $totalValue,
        ];
    }

    /**
     * Get items that need restocking
     */
    public function findItemsNeedingRestock(): array
    {
        return $this->createQueryBuilder('i')
            ->where('i.currentStock <= i.minimumStock')
            ->andWhere('i.status = :status')
            ->setParameter('status', 'active')
            ->orderBy('i.currentStock', 'ASC')
            ->addOrderBy('i.name', 'ASC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Get recent items (created in last 30 days)
     */
    public function findRecentItems(int $days = 30): array
    {
        $date = new \DateTime();
        $date->modify("-{$days} days");

        return $this->createQueryBuilder('i')
            ->where('i.createdAt >= :date')
            ->andWhere('i.status = :status')
            ->setParameter('date', $date)
            ->setParameter('status', 'active')
            ->orderBy('i.createdAt', 'DESC')
            ->getQuery()
            ->getResult();
    }
}
