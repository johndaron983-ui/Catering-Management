<?php

namespace App\Service;

use App\Entity\Booking;
use Psr\Log\LoggerInterface;
use Symfony\Component\Mercure\HubInterface;
use Symfony\Component\Mercure\Update;

/**
 * Service responsible for publishing real-time notifications
 * to customers when their booking status changes.
 */
class NotificationService
{
    public function __construct(
        private HubInterface $hub,
        private LoggerInterface $logger
    ) {}

    /**
     * Publish a booking status change notification to the Mercure Hub.
     *
     * The notification is scoped to the user who created the booking,
     * so only that user's browser receives the SSE event.
     */
    public function publishBookingStatusUpdate(Booking $booking, string $oldStatus, string $newStatus): void
    {
        $user = $booking->getCreatedBy();

        if (!$user) {
            $this->logger->warning('Cannot publish notification: booking has no createdBy user.', [
                'booking_id' => $booking->getId(),
            ]);
            return;
        }

        $topic = '/notifications/user/' . $user->getId();

        $serviceName = $booking->getService() ? $booking->getService()->getName() : 'Unknown Service';
        $eventDate = $booking->getEventDate() ? $booking->getEventDate()->format('M d, Y') : 'N/A';

        $payload = json_encode([
            'type' => 'booking_status_update',
            'booking_id' => $booking->getId(),
            'customer_name' => $booking->getCustomerName(),
            'service_name' => $serviceName,
            'event_date' => $eventDate,
            'old_status' => $oldStatus,
            'new_status' => $newStatus,
            'guest_count' => $booking->getGuestCount(),
            'total_price' => $booking->getTotalPrice(),
            'timestamp' => (new \DateTimeImmutable())->format('c'),
        ]);

        try {
            $update = new Update(
                $topic,
                $payload,
                false // public update (subscribers don't need auth for this topic)
            );

            $this->hub->publish($update);

            $this->logger->info('Published booking status notification.', [
                'booking_id' => $booking->getId(),
                'user_id' => $user->getId(),
                'old_status' => $oldStatus,
                'new_status' => $newStatus,
                'topic' => $topic,
            ]);
        } catch (\Exception $e) {
            $this->logger->error('Failed to publish Mercure notification.', [
                'booking_id' => $booking->getId(),
                'error' => $e->getMessage(),
            ]);
        }
    }
}
