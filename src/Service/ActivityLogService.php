<?php

namespace App\Service;

use App\Entity\ActivityLog;
use App\Entity\User;
use Doctrine\Persistence\ManagerRegistry;

class ActivityLogService
{
    public function __construct(private ManagerRegistry $managerRegistry)
    {
    }

    /**
     * Log a user activity. Returns null if logging fails (does not break the request).
     */
    public function log(User $user, string $action, ?string $targetData = null, ?string $recordType = null, ?int $recordId = null): ?ActivityLog
    {
        $entityManager = $this->managerRegistry->getManager();

        try {
            $log = new ActivityLog();
            $log->setUser($user);
            $log->setUsername($user->getUsername());

            $roles = $user->getRoles();
            $role = 'ROLE_USER';
            foreach ($roles as $r) {
                if ($r !== 'ROLE_USER') {
                    $role = $r;
                    break;
                }
            }
            $log->setRole($role);
            $log->setAction($action);
            $log->setTargetData($targetData);
            $log->setRecordType($recordType);
            $log->setRecordId($recordId);
            $log->setCreatedAt(new \DateTime());

            $entityManager->persist($log);
            $entityManager->flush();

            return $log;
        } catch (\Throwable) {
            if (!$entityManager->isOpen()) {
                $this->managerRegistry->resetManager();
            }

            return null;
        }
    }

    /**
     * Log login activity
     */
    public function logLogin(User $user): ?ActivityLog
    {
        return $this->log($user, 'LOGIN', null);
    }

    /**
     * Log logout activity
     */
    public function logLogout(User $user): ?ActivityLog
    {
        return $this->log($user, 'LOGOUT', null);
    }

    /**
     * Log user creation
     */
    public function logUserCreation(User $admin, User $newUser): ActivityLog
    {
        $targetData = sprintf(
            'User: %s (ID: %d, Email: %s, Role: %s)',
            $newUser->getUsername(),
            $newUser->getId(),
            $newUser->getEmail(),
            implode(', ', $newUser->getRoles())
        );
        return $this->log($admin, 'CREATE_USER', $targetData);
    }

    /**
     * Log user deletion
     */
    public function logUserDeletion(User $admin, User $deletedUser): ActivityLog
    {
        $targetData = sprintf(
            'User: %s (ID: %d, Email: %s)',
            $deletedUser->getUsername(),
            $deletedUser->getId(),
            $deletedUser->getEmail()
        );
        return $this->log($admin, 'DELETE_USER', $targetData);
    }

    /**
     * Log user edit
     */
    public function logUserEdit(User $admin, User $editedUser, array $changes): ActivityLog
    {
        $targetData = sprintf(
            'User: %s (ID: %d) - Changes: %s',
            $editedUser->getUsername(),
            $editedUser->getId(),
            json_encode($changes)
        );
        return $this->log($admin, 'UPDATE_USER', $targetData);
    }

    /**
     * Log password change
     */
    public function logPasswordChange(User $user, string $targetUser = null): ActivityLog
    {
        $targetData = $targetUser ?? $user->getUsername();
        return $this->log($user, 'CHANGE_PASSWORD', 'User: ' . $targetData);
    }

    /**
     * Log record creation (booking, service, inventory, product, supplier)
     */
    public function logRecordCreation(User $user, string $recordType, ?int $recordId, ?string $recordName = null): ActivityLog
    {
        $targetData = sprintf(
            '%s: %s (ID: %s)',
            $recordType,
            $recordName ?? 'N/A',
            $recordId ?? 'N/A'
        );
        return $this->log($user, 'CREATE', $targetData, $recordType, $recordId);
    }

    /**
     * Log record update
     */
    public function logRecordUpdate(User $user, string $recordType, ?int $recordId, ?string $recordName = null, ?array $changes = null): ActivityLog
    {
        $targetData = sprintf(
            '%s: %s (ID: %s)',
            $recordType,
            $recordName ?? 'N/A',
            $recordId ?? 'N/A'
        );
        
        if ($changes) {
            $targetData .= ' - Changes: ' . json_encode($changes);
        }

        return $this->log($user, 'UPDATE', $targetData, $recordType, $recordId);
    }

    /**
     * Log record deletion
     */
    public function logRecordDeletion(User $user, string $recordType, ?int $recordId, ?string $recordName = null): ActivityLog
    {
        $targetData = sprintf(
            '%s: %s (ID: %s)',
            $recordType,
            $recordName ?? 'N/A',
            $recordId ?? 'N/A'
        );
        return $this->log($user, 'DELETE', $targetData, $recordType, $recordId);
    }

    /**
     * Log account status change
     */
    public function logStatusChange(User $admin, User $targetUser, string $oldStatus, string $newStatus): ActivityLog
    {
        $targetData = sprintf(
            'User: %s (ID: %d) - Status changed from %s to %s',
            $targetUser->getUsername(),
            $targetUser->getId(),
            $oldStatus,
            $newStatus
        );
        return $this->log($admin, 'UPDATE_STATUS', $targetData);
    }

    /**
     * Log booking creation
     */
    public function logBookingCreation(User $user, int $bookingId, ?string $customerName = null, ?string $eventDate = null): ActivityLog
    {
        $targetData = sprintf(
            'Booking: %s (ID: %d, Date: %s)',
            $customerName ?? 'N/A',
            $bookingId,
            $eventDate ?? 'N/A'
        );
        return $this->log($user, 'CREATE', $targetData, 'Booking', $bookingId);
    }

    /**
     * Log booking update
     */
    public function logBookingUpdate(User $user, int $bookingId, ?string $customerName = null, ?array $changes = null): ActivityLog
    {
        $targetData = sprintf(
            'Booking: %s (ID: %d)',
            $customerName ?? 'N/A',
            $bookingId
        );
        if ($changes) {
            $targetData .= ' - Changes: ' . json_encode($changes);
        }
        return $this->log($user, 'UPDATE', $targetData, 'Booking', $bookingId);
    }

    /**
     * Log booking deletion
     */
    public function logBookingDeletion(User $user, int $bookingId, ?string $customerName = null): ActivityLog
    {
        $targetData = sprintf(
            'Booking: %s (ID: %d)',
            $customerName ?? 'N/A',
            $bookingId
        );
        return $this->log($user, 'DELETE', $targetData, 'Booking', $bookingId);
    }

    /**
     * Log inventory creation
     */
    public function logInventoryCreation(User $user, int $inventoryId, ?string $itemName = null, ?string $category = null): ActivityLog
    {
        $targetData = sprintf(
            'Inventory: %s (ID: %d, Category: %s)',
            $itemName ?? 'N/A',
            $inventoryId,
            $category ?? 'N/A'
        );
        return $this->log($user, 'CREATE', $targetData, 'Inventory', $inventoryId);
    }

    /**
     * Log inventory update
     */
    public function logInventoryUpdate(User $user, int $inventoryId, ?string $itemName = null, ?array $changes = null): ActivityLog
    {
        $targetData = sprintf(
            'Inventory: %s (ID: %d)',
            $itemName ?? 'N/A',
            $inventoryId
        );
        if ($changes) {
            $targetData .= ' - Changes: ' . json_encode($changes);
        }
        return $this->log($user, 'UPDATE', $targetData, 'Inventory', $inventoryId);
    }

    /**
     * Log inventory deletion
     */
    public function logInventoryDeletion(User $user, int $inventoryId, ?string $itemName = null): ActivityLog
    {
        $targetData = sprintf(
            'Inventory: %s (ID: %d)',
            $itemName ?? 'N/A',
            $inventoryId
        );
        return $this->log($user, 'DELETE', $targetData, 'Inventory', $inventoryId);
    }

    /**
     * Log inventory stock adjustment
     */
    public function logInventoryStockAdjustment(User $user, int $inventoryId, string $itemName, int $quantityChange, string $reason = ''): ActivityLog
    {
        $action = $quantityChange > 0 ? 'RESTOCK' : 'DESTOCK';
        $targetData = sprintf(
            'Inventory: %s (ID: %d) - %s by %d units. Reason: %s',
            $itemName,
            $inventoryId,
            $action,
            abs($quantityChange),
            $reason ?: 'Manual adjustment'
        );
        return $this->log($user, $action, $targetData, 'Inventory', $inventoryId);
    }

    /**
     * Log product creation
     */
    public function logProductCreation(User $user, int $productId, ?string $productName = null): ActivityLog
    {
        $targetData = sprintf(
            'Product: %s (ID: %d)',
            $productName ?? 'N/A',
            $productId
        );
        return $this->log($user, 'CREATE', $targetData, 'Product', $productId);
    }

    /**
     * Log product update
     */
    public function logProductUpdate(User $user, int $productId, ?string $productName = null, ?array $changes = null): ActivityLog
    {
        $targetData = sprintf(
            'Product: %s (ID: %d)',
            $productName ?? 'N/A',
            $productId
        );
        if ($changes) {
            $targetData .= ' - Changes: ' . json_encode($changes);
        }
        return $this->log($user, 'UPDATE', $targetData, 'Product', $productId);
    }

    /**
     * Log product deletion
     */
    public function logProductDeletion(User $user, int $productId, ?string $productName = null): ActivityLog
    {
        $targetData = sprintf(
            'Product: %s (ID: %d)',
            $productName ?? 'N/A',
            $productId
        );
        return $this->log($user, 'DELETE', $targetData, 'Product', $productId);
    }

    /**
     * Log service creation
     */
    public function logServiceCreation(User $user, int $serviceId, ?string $serviceName = null): ActivityLog
    {
        $targetData = sprintf(
            'Service: %s (ID: %d)',
            $serviceName ?? 'N/A',
            $serviceId
        );
        return $this->log($user, 'CREATE', $targetData, 'Service', $serviceId);
    }

    /**
     * Log service update
     */
    public function logServiceUpdate(User $user, int $serviceId, ?string $serviceName = null, ?array $changes = null): ActivityLog
    {
        $targetData = sprintf(
            'Service: %s (ID: %d)',
            $serviceName ?? 'N/A',
            $serviceId
        );
        if ($changes) {
            $targetData .= ' - Changes: ' . json_encode($changes);
        }
        return $this->log($user, 'UPDATE', $targetData, 'Service', $serviceId);
    }

    /**
     * Log service deletion
     */
    public function logServiceDeletion(User $user, int $serviceId, ?string $serviceName = null): ActivityLog
    {
        $targetData = sprintf(
            'Service: %s (ID: %d)',
            $serviceName ?? 'N/A',
            $serviceId
        );
        return $this->log($user, 'DELETE', $targetData, 'Service', $serviceId);
    }

    /**
     * Log supplier creation
     */
    public function logSupplierCreation(User $user, int $supplierId, ?string $supplierName = null): ActivityLog
    {
        $targetData = sprintf(
            'Supplier: %s (ID: %d)',
            $supplierName ?? 'N/A',
            $supplierId
        );
        return $this->log($user, 'CREATE', $targetData, 'Supplier', $supplierId);
    }

    /**
     * Log supplier update
     */
    public function logSupplierUpdate(User $user, int $supplierId, ?string $supplierName = null, ?array $changes = null): ActivityLog
    {
        $targetData = sprintf(
            'Supplier: %s (ID: %d)',
            $supplierName ?? 'N/A',
            $supplierId
        );
        if ($changes) {
            $targetData .= ' - Changes: ' . json_encode($changes);
        }
        return $this->log($user, 'UPDATE', $targetData, 'Supplier', $supplierId);
    }

    /**
     * Log supplier deletion
     */
    public function logSupplierDeletion(User $user, int $supplierId, ?string $supplierName = null): ActivityLog
    {
        $targetData = sprintf(
            'Supplier: %s (ID: %d)',
            $supplierName ?? 'N/A',
            $supplierId
        );
        return $this->log($user, 'DELETE', $targetData, 'Supplier', $supplierId);
    }
}
