<?php

namespace App\Services\Scan;

use App\Models\Attendee;
use App\Models\Event;
use App\Services\Qr\PairingQr;
use App\Services\Qr\TicketQr;

/**
 * Pure decision logic for a scan against the local SQLite copy: no writes,
 * no network. Callers (scan screen / CheckinService) apply side effects.
 *
 * Decision table (see PLAN.md):
 *   GREEN  attendee found for the active event, security code matches,
 *          order completed, not yet checked in
 *   AMBER  same, but already checked in
 *   RED    everything else, with a specific reason
 *
 * Order of checks matters: unknown → wrong event → code mismatch → order
 * status → duplicate. A code mismatch on a checked-in attendee must read as
 * RED (potential forgery), not AMBER.
 */
class ScanValidator
{
    public function validate(TicketQr|PairingQr|null $parsed, Event $activeEvent): ScanResult
    {
        if (! $parsed instanceof TicketQr) {
            return new ScanResult(ScanOutcome::Red, ScanReason::NotATicket);
        }

        $attendee = Attendee::query()
            ->where('site_id', $activeEvent->site_id)
            ->where('wp_attendee_id', $parsed->attendeeId)
            ->first();

        if (! $attendee) {
            return new ScanResult(ScanOutcome::Red, ScanReason::UnknownAttendee);
        }

        if ($attendee->wp_event_id !== $activeEvent->wp_event_id
            || $parsed->eventId !== $activeEvent->wp_event_id) {
            return new ScanResult(ScanOutcome::Red, ScanReason::WrongEvent, $attendee);
        }

        if (! hash_equals($attendee->security_code, $parsed->securityCode)) {
            return new ScanResult(ScanOutcome::Red, ScanReason::SecurityCodeMismatch, $attendee);
        }

        if (! $attendee->isEligibleForCheckin()) {
            return new ScanResult(ScanOutcome::Red, ScanReason::OrderNotComplete, $attendee);
        }

        if ($attendee->checked_in) {
            return new ScanResult(ScanOutcome::Amber, ScanReason::AlreadyCheckedIn, $attendee);
        }

        return new ScanResult(ScanOutcome::Green, ScanReason::Valid, $attendee);
    }
}
