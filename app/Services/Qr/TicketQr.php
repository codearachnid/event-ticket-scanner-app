<?php

namespace App\Services\Qr;

/** Parsed contents of an Event Tickets ticket QR (see docs/api/qr-format.md). */
final readonly class TicketQr
{
    public function __construct(
        public int $attendeeId,   // the QR's `ticket_id` arg — actually the attendee post ID
        public int $eventId,
        public string $securityCode,
    ) {}
}
