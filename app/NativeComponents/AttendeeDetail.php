<?php

namespace App\NativeComponents;

use App\Models\Attendee;
use App\Models\Event;
use App\Services\CheckinService;
use Illuminate\View\View;
use Native\Mobile\Attributes\On;
use Native\Mobile\Edge\NativeComponent;
use Native\Mobile\Events\Alert\ButtonPressed;
use Native\Mobile\Facades\Dialog;
use Throwable;

class AttendeeDetail extends NativeComponent
{
    private const UNDO_DIALOG_ID = 'undo-checkin-confirm';

    public string $name = '';

    public string $email = '';

    public string $ticket = '';

    public string $provider = '';

    public string $orderStatus = '';

    public bool $checkedIn = false;

    public string $checkedInInfo = '';

    public bool $eligible = false;

    public string $error = '';

    protected ?Attendee $attendee = null;

    protected ?Event $event = null;

    public function mount(): void
    {
        $this->attendee = Attendee::find((int) $this->param('attendee', 0));

        $this->event = $this->attendee
            ? Event::where('site_id', $this->attendee->site_id)
                ->where('wp_event_id', $this->attendee->wp_event_id)
                ->first()
            : null;

        if (! $this->attendee || ! $this->event) {
            $this->replace('/events');

            return;
        }

        $this->refreshState();
    }

    /** Manual check-in: same code path as a GREEN scan (PLAN.md Stage 5). */
    public function checkin(): void
    {
        if (! $this->attendee->isEligibleForCheckin()) {
            $this->error = 'Order is not complete — cannot check in.';

            return;
        }

        if ($this->attendee->checked_in) {
            return;
        }

        app(CheckinService::class)->checkin($this->attendee, $this->event);
        $this->refreshState();
    }

    public function confirmUndo(): void
    {
        try {
            Dialog::alert(
                'Undo check-in?',
                "{$this->name} will be marked as not checked in, here and on the site.",
                ['Cancel', 'Undo check-in'],
            )->id(self::UNDO_DIALOG_ID)->show();
        } catch (Throwable) {
            // No native dialog available (e.g. dev without bridge): fall through
            // to immediate undo rather than leaving the button dead.
            $this->undo();
        }
    }

    #[On(ButtonPressed::class)]
    public function onDialogButton(int $index, string $label = '', ?string $id = null): void
    {
        if ($id === self::UNDO_DIALOG_ID && $index === 1) {
            $this->undo();
        }
    }

    private function undo(): void
    {
        if (! $this->attendee->checked_in) {
            return;
        }

        app(CheckinService::class)->undo($this->attendee, $this->event);
        $this->refreshState();
    }

    private function refreshState(): void
    {
        $this->attendee->refresh();

        $this->name = $this->attendee->holder_name;
        $this->email = $this->attendee->holder_email;
        $this->ticket = (string) $this->attendee->ticket_name;
        $this->provider = (string) $this->attendee->provider;
        $this->orderStatus = $this->attendee->order_status;
        $this->checkedIn = $this->attendee->checked_in;
        $this->eligible = $this->attendee->isEligibleForCheckin();
        $this->checkedInInfo = $this->checkedIn
            ? trim(($this->attendee->checked_in_at ?? '').' '.($this->attendee->checked_in_by ? "via {$this->attendee->checked_in_by}" : ''))
            : '';
    }

    public function navTitle(): string
    {
        return $this->name;
    }

    public function render(): View
    {
        return view('native.attendee-detail');
    }
}
