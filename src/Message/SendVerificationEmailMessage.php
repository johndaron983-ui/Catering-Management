<?php

namespace App\Message;

/**
 * Queued after API registration so HTTP responses are not blocked by SMTP.
 */
final readonly class SendVerificationEmailMessage
{
    public function __construct(
        public int $userId,
    ) {
    }
}
