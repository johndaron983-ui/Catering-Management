<?php

namespace App\Service;

use App\Entity\ActivityLog;
use Psr\Log\LoggerInterface;
use Symfony\Component\Mercure\HubInterface;
use Symfony\Component\Mercure\Update;

/**
 * Publishes Mercure events so admin module pages refresh without waiting for the next poll.
 */
final class AdminRealtimePublisher
{
    public const TOPIC_MODULES = '/admin/modules';
    public const TOPIC_SIDEBAR = '/admin/sidebar';
    public const TOPIC_BOOKINGS = '/admin/bookings';

    public function __construct(
        private HubInterface $hub,
        private LoggerInterface $logger,
    ) {
    }

    public function publishModuleUpdate(
        string $module,
        string $action = 'updated',
        ?int $recordId = null,
        bool $refreshSidebar = true,
    ): void {
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

    public function notifyActivityLog(ActivityLog $log): void
    {
        $action = strtolower($log->getAction() ?? 'updated');

        $this->publishModuleUpdate(
            AdminRealtimeSnapshotService::MODULE_ACTIVITY_LOGS,
            $action,
            $log->getId(),
            false,
        );

        $related = $this->mapRecordTypeToModule($log->getRecordType());
        if ($related !== null) {
            $this->publishModuleUpdate($related, $action, $log->getRecordId(), false);
        }

        $this->publishSidebarRefresh();
    }

    public function publishSidebarRefresh(): void
    {
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
            $this->logger->error('[Mercure] Admin realtime publish failed', [
                'topic' => $topic,
                'error' => $e->getMessage(),
            ]);
        }
    }
}
