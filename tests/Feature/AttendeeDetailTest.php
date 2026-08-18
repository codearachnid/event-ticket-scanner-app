<?php

use App\Models\Attendee;
use App\Models\CheckinOperation;
use App\Models\Event;
use App\Models\Site;
use App\NativeComponents\AttendeeDetail;
use App\Services\Qr\TicketQr;
use App\Services\Scan\ScanOutcome;
use App\Services\Scan\ScanValidator;
use Native\Mobile\Events\Alert\ButtonPressed;
use Native\Mobile\Testing\Native;

covers(AttendeeDetail::class);

beforeEach(function () {
    $this->bridge = Native::fakeBridge();
    $this->site = Site::factory()->create();
    $this->event = Event::factory()->create(['site_id' => $this->site->id]);
});

function detailFor(Attendee $attendee)
{
    return Native::test(AttendeeDetail::class, params: ['attendee' => $attendee->id]);
}

function makeDetailAttendee(Site $site, Event $event, array $attributes = []): Attendee
{
    return Attendee::factory()->create([
        'site_id' => $site->id,
        'wp_event_id' => $event->wp_event_id,
        ...$attributes,
    ]);
}

it('redirects for an unknown attendee', function () {
    Native::test(AttendeeDetail::class, params: ['attendee' => 424242])
        ->assertReplacedWith('/events');
});

it('shows attendee info with a check-in button when eligible', function () {
    $attendee = makeDetailAttendee($this->site, $this->event, ['holder_name' => 'Ada Lovelace']);

    detailFor($attendee)
        ->assertSee('Ada Lovelace')
        ->assertSee('Check in')
        ->assertSet('checkedIn', false)
        ->assertSet('eligible', true);
});

it('manually checks in through the same path as a scan', function () {
    $attendee = makeDetailAttendee($this->site, $this->event);

    detailFor($attendee)
        ->tap('checkin-btn')
        ->assertSet('checkedIn', true)
        ->assertSee('Undo check-in');

    expect($attendee->refresh()->checked_in)->toBeTrue()
        ->and(CheckinOperation::where('action', 'checkin')->count())->toBe(1);
});

it('blocks manual check-in for incomplete orders', function () {
    $attendee = makeDetailAttendee($this->site, $this->event, ['order_status' => 'refunded']);

    detailFor($attendee)
        ->assertSee('not eligible for check-in')
        ->call('checkin')
        ->assertSet('checkedIn', false);

    expect(CheckinOperation::count())->toBe(0);
});

it('undo asks for confirmation and undoes only on the confirm button', function () {
    $attendee = makeDetailAttendee($this->site, $this->event, ['checked_in' => true, 'checked_in_at' => '2026-08-11T15:11:00Z']);

    $screen = detailFor($attendee)
        ->assertSee('Undo check-in')
        ->tap('undo-btn')
        ->assertNativeCalled('Dialog.Alert');

    // Cancel (index 0) does nothing.
    $screen->emitNative(ButtonPressed::class, ['index' => 0, 'label' => 'Cancel', 'id' => 'undo-checkin-confirm'])
        ->assertSet('checkedIn', true);

    // A confirm from some OTHER dialog id does nothing.
    $screen->emitNative(ButtonPressed::class, ['index' => 1, 'label' => 'OK', 'id' => 'other-dialog'])
        ->assertSet('checkedIn', true);

    // Confirm undoes.
    $screen->emitNative(ButtonPressed::class, ['index' => 1, 'label' => 'Undo check-in', 'id' => 'undo-checkin-confirm'])
        ->assertSet('checkedIn', false)
        ->assertSee('Check in');

    expect($attendee->refresh()->checked_in)->toBeFalse()
        ->and(CheckinOperation::where('action', 'uncheckin')->count())->toBe(1);
});

it('after undo, a rescan would be GREEN again (state round-trip)', function () {
    $attendee = makeDetailAttendee($this->site, $this->event);

    $screen = detailFor($attendee)->tap('checkin-btn')->assertSet('checkedIn', true);

    $screen->tap('undo-btn');
    $screen->emitNative(ButtonPressed::class, ['index' => 1, 'label' => 'Undo check-in', 'id' => 'undo-checkin-confirm'])
        ->assertSet('checkedIn', false);

    $validator = app(ScanValidator::class);
    $result = $validator->validate(
        new TicketQr($attendee->wp_attendee_id, $attendee->wp_event_id, $attendee->security_code),
        $this->event,
    );

    expect($result->outcome)->toBe(ScanOutcome::Green);
});
