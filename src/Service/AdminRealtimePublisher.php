<?php

namespace App\Service;

use App\Entity\ActivityLog;
use Psr\Log\LoggerInterface;
use Symfony\Component\Mercure\HubInterface;
use Symfony\Component\Mercure\Update;

/**
 * Publishes Mercure events for admin realtime UI.
 * Must never block HTTP requests — publishing is optional and uses short HTTP timeouts.
 */
final class AdminRealtimePublisher
{
    public const TOPIC_MODULES = '/admin/modules';
    public const TOPIC_SIDEBAR = '/admin/sidebar';
    public const TOPIC_BOOKINGS = '/admin/bookings';

    public function __construct(
        private HubInterface $hub,
        private LoggerInterface $logger,
        private bool $publishEnabled = false,
    ) {
    }

    public function isEnabled(): bool
    {
        return $this->publishEnabled;
    }

    public function publishModuleUpdate(
        string $module,
        string $action = 'updated',
        ?int $recordId = null,
        bool $refreshSidebar = false,
    ): void {
        if (!$this->publishEnabled) {
            return;
        }

        $this->publish(self::TOPIC_MODULES, [
            'type' => 'module_updated',
            'module' => $module,
            'action' => $action,
            'record_id' => $recordId,
            'timestamp' => (new \DateTimeImmutable())->format('c'),
        ]);

        if ($refreshSidebar) {
            $this->publishSidebarRefresh();
        }
    }

    /**
     * One HTTP call max — avoids login/request timeouts when Mercure hub is down.
     */
    public function notifyActivityLog(ActivityLog $log): void
    {
        if (!$this->publishEnabled) {
            return;
        }

        $action = strtoupper($log->getAction() ?? '');
        // Auth events are high-frequency; admin lists use 2s polling instead.
        if ($action === 'LOGIN' || $action === 'LOGOUT') {
            return;
        }

        $modules = [AdminRealtimeSnapshotService::MODULE_ACTIVITY_LOGS];
        $related = $this->mapRecordTypeToModule($log->getRecordType());
        if ($related !== null) {
            $modules[] = $related;
        }

        $this->publish(self::TOPIC_MODULES, [
            'type' => 'modules_updated',
            'modules' => array_values(array_unique($modules)),
            'action' => strtolower($log->getAction() ?? 'updated'),
            'record_id' => $log->getId(),
            'timestamp' => (new \DateTimeImmutable())->format('c'),
        ]);
    }

    /**
     * @param array<string, mixed> $booking
     */
    public function publishBookingCreated(array $booking): void
    {
        if (!$this->publishEnabled) {
            return;
        }

        $this->publish(self::TOPIC_BOOKINGS, [
            'type' => 'booking_created',
            'booking' => $booking,
        ]);
    }

    public function publishSidebarRefresh(): void
    {
        if (!$this->publishEnabled) {
            return;
        }

        $this->publish(self::TOPIC_SIDEBAR, [
            'type' => 'sidebar_updated',
            'timestamp' => (new \DateTimeImmutable())->format('c'),
        ]);
    }

    private function mapRecordTypeToModule(?string $recordType): ?string
    {
        return match ($recordType) {
            'Inventory' => AdminRealtimeSnapshotService::MODULE_INVENTORY,
            'Product' => AdminRealtimeSnapshotService::MODULE_PRODUCTS,
            'Supplier' => AdminRealtimeSnapshotService::MODULE_SUPPLIERS,
            'User' => AdminRealtimeSnapshotService::MODULE_USERS,
            default => null,
        };
    }

    /**
     * @param array<string, mixed> $payload
     */
    private function publish(string $topic, array $payload): void
    {
        try {
            $this->hub->publish(new Update(
                $topic,
                json_encode($payload, JSON_THROW_ON_ERROR),
            ));
        } catch (\Throwable $e) {
            $this->logger->warning('[Mercure] Publish skipped or failed (polling fallback remains active)', [
                'topic' => $topic,
                'error' => $e->getMessage(),
            ]);
        }
    }
}
