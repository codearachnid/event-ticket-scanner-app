<?php

use App\Models\Attendee;
use App\Models\Event;
use App\Services\Qr\PairingQr;
use App\Services\Qr\TicketQr;
use App\Services\Scan\ScanOutcome;
use App\Services\Scan\ScanReason;
use App\Services\Scan\ScanValidator;

covers(ScanValidator::class);

beforeEach(function () {
    $this->event = Event::factory()->create(); // wp_event_id 501
    $this->validator = new ScanValidator;
});

function makeAttendee(Event $event, array $attributes = []): Attendee
{
    return Attendee::factory()->create([
        'site_id' => $event->site_id,
        'wp_event_id' => $event->wp_event_id,
        ...$attributes,
    ]);
}

function qrFor(Attendee $attendee, array $overrides = []): TicketQr
{
    return new TicketQr(
        $overrides['attendee_id'] ?? $attendee->wp_attendee_id,
        $overrides['event_id'] ?? $attendee->wp_event_id,
        $overrides['security_code'] ?? $attendee->security_code,
    );
}

it('returns GREEN for a valid, unchecked, completed attendee', function () {
    $attendee = makeAttendee($this->event);

    $result = $this->validator->validate(qrFor($attendee), $this->event);

    expect($result->outcome)->toBe(ScanOutcome::Green)
        ->and($result->reason)->toBe(ScanReason::Valid)
        ->and($result->attendee->id)->toBe($attendee->id);
});

it('returns AMBER when the attendee is already checked in', function () {
    $attendee = makeAttendee($this->event, [
        'checked_in' => true,
        'checked_in_at' => '2026-08-11T15:11:00Z',
        'checked_in_by' => 'front-door-ipad',
    ]);

    $result = $this->validator->validate(qrFor($attendee), $this->event);

    expect($result->outcome)->toBe(ScanOutcome::Amber)
        ->and($result->reason)->toBe(ScanReason::AlreadyCheckedIn)
        ->and($result->attendee->checked_in_by)->toBe('front-door-ipad');
});

it('returns RED for an unknown attendee id', function () {
    $result = $this->validator->validate(new TicketQr(424242, 501, 'aaaa1111'), $this->event);

    expect($result->outcome)->toBe(ScanOutcome::Red)
        ->and($result->reason)->toBe(ScanReason::UnknownAttendee);
});

it('returns RED when the ticket belongs to a different event', function () {
    $attendee = makeAttendee($this->event, ['wp_event_id' => 999]);

    $result = $this->validator->validate(qrFor($attendee, ['event_id' => 999]), $this->event);

    expect($result->outcome)->toBe(ScanOutcome::Red)
        ->and($result->reason)->toBe(ScanReason::WrongEvent);
});

it('returns RED when the QR claims a different event than the attendee', function () {
    $attendee = makeAttendee($this->event);

    $result = $this->validator->validate(qrFor($attendee, ['event_id' => 999]), $this->event);

    expect($result->outcome)->toBe(ScanOutcome::Red)
        ->and($result->reason)->toBe(ScanReason::WrongEvent);
});

it('returns RED on a security code mismatch', function () {
    $attendee = makeAttendee($this->event);

    $result = $this->validator->validate(qrFor($attendee, ['security_code' => 'deadbeef']), $this->event);

    expect($result->outcome)->toBe(ScanOutcome::Red)
        ->and($result->reason)->toBe(ScanReason::SecurityCodeMismatch);
});

it('returns RED for incomplete orders', function (string $status) {
    $attendee = makeAttendee($this->event, ['order_status' => $status]);

    $result = $this->validator->validate(qrFor($attendee), $this->event);

    expect($result->outcome)->toBe(ScanOutcome::Red)
        ->and($result->reason)->toBe(ScanReason::OrderNotComplete);
})->with(['refunded', 'pending', 'cancelled', 'denied']);

it('returns RED (not AMBER) for a code mismatch on an already-checked-in attendee', function () {
    $attendee = makeAttendee($this->event, ['checked_in' => true]);

    $result = $this->validator->validate(qrFor($attendee, ['security_code' => 'deadbeef']), $this->event);

    expect($result->outcome)->toBe(ScanOutcome::Red)
        ->and($result->reason)->toBe(ScanReason::SecurityCodeMismatch);
});

it('returns RED for non-ticket scans', function () {
    expect($this->validator->validate(null, $this->event)->reason)->toBe(ScanReason::NotATicket)
        ->and($this->validator->validate(new PairingQr('https://example.test'), $this->event)->reason)->toBe(ScanReason::NotATicket);
});

it('ignores attendees of other sites with the same wp ids', function () {
    $otherSiteEvent = Event::factory()->create(); // different site, same wp_event_id 501
    $attendee = makeAttendee($otherSiteEvent);

    $result = $this->validator->validate(qrFor($attendee), $this->event);

    expect($result->outcome)->toBe(ScanOutcome::Red)
        ->and($result->reason)->toBe(ScanReason::UnknownAttendee);
});
