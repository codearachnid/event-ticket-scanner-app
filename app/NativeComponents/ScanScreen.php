<?php

namespace App\NativeComponents;

use App\Models\Event;
use App\Services\CheckinService;
use App\Services\Qr\QrParser;
use App\Services\Scan\ScanOutcome;
use App\Services\Scan\ScanValidator;
use Illuminate\View\View;
use Native\Mobile\Attributes\On;
use Native\Mobile\Attributes\Poll;
use Native\Mobile\Edge\NativeComponent;
use Native\Mobile\Events\Scanner\CodeScanned;
use Native\Mobile\Events\Scanner\ScannerCancelled;
use Native\Mobile\Facades\Haptics;
use Native\Mobile\Facades\Scanner;
use Throwable;

/**
 * The door-scanning loop (PLAN.md Stage 4): continuous QR scanning with
 * full-bleed GREEN / AMBER / RED results. All validation is local SQLite —
 * scanning works fully offline; check-ins queue for sync.
 *
 * GREEN auto-dismisses after ~2s; AMBER and RED require a tap so staff
 * consciously acknowledge duplicates and rejections.
 */
class ScanScreen extends NativeComponent
{
    private const DEBOUNCE_SECONDS = 3;

    private const GREEN_DISMISS_SECONDS = 2;

    /** scanning | green | amber | red | unavailable */
    public string $phase = 'scanning';

    public string $reasonLabel = '';

    public string $attendeeName = '';

    public string $ticketName = '';

    public string $checkedInInfo = '';

    public int $sessionScans = 0;

    protected ?Event $event = null;

    /** @var array<string, float> raw code → last-seen unix time (duplicate-read debounce) */
    protected array $recentCodes = [];

    protected float $resultShownAt = 0.0;

    public function mount(): void
    {
        $this->event = Event::find((int) $this->param('event', 0));

        if (! $this->event) {
            $this->replace('/events');

            return;
        }

        $this->startScanner();
    }

    public function startScanner(): void
    {
        try {
            Scanner::scan()
                ->prompt('Point at a ticket QR code')
                ->formats(['qr'])
                ->continuous()
                ->id('door-scan')
                ->scan();
        } catch (Throwable) {
            // Native scanner unavailable (plugin not compiled into this build).
            $this->phase = 'unavailable';
        }
    }

    #[On(CodeScanned::class)]
    public function onCodeScanned(string $data, string $format, ?string $id = null): void
    {
        // Continuous scanners re-fire the same frame; and while a result is
        // on screen, new reads are ignored (AMBER/RED demand acknowledgment).
        if ($this->phase !== 'scanning' || $this->isDuplicateRead($data)) {
            return;
        }

        $result = app(ScanValidator::class)->validate(app(QrParser::class)->parse($data), $this->event);

        $this->sessionScans++;
        $this->resultShownAt = microtime(true);
        $this->attendeeName = $result->attendee->holder_name ?? '';
        $this->ticketName = $result->attendee->ticket_name ?? '';
        $this->reasonLabel = $result->reason->label();

        match ($result->outcome) {
            ScanOutcome::Green => $this->showGreen($result->attendee),
            ScanOutcome::Amber => $this->showAmber($result->attendee),
            ScanOutcome::Red => $this->showRed(),
        };
    }

    #[On(ScannerCancelled::class)]
    public function onScannerCancelled(): void
    {
        $this->back();
    }

    /** GREEN auto-returns to scanning; ticks are cheap no-ops otherwise. */
    #[Poll(500)]
    public function tick(): void
    {
        if ($this->phase === 'green'
            && (microtime(true) - $this->resultShownAt) >= self::GREEN_DISMISS_SECONDS) {
            $this->dismiss();
        }
    }

    public function dismiss(): void
    {
        if ($this->phase === 'amber' || $this->phase === 'red' || $this->phase === 'green') {
            $this->phase = 'scanning';
        }
    }

    private function showGreen($attendee): void
    {
        app(CheckinService::class)->checkin($attendee, $this->event);
        $this->vibrate(1);
        $this->phase = 'green';
    }

    private function showAmber($attendee): void
    {
        $this->checkedInInfo = trim(sprintf(
            'Already checked in %s%s',
            $attendee->checked_in_at ? "at {$attendee->checked_in_at}" : '',
            $attendee->checked_in_by ? " via {$attendee->checked_in_by}" : '',
        ));
        $this->vibrate(2);
        $this->phase = 'amber';
    }

    private function showRed(): void
    {
        $this->vibrate(2);
        $this->phase = 'red';
    }

    private function isDuplicateRead(string $data): bool
    {
        $now = microtime(true);
        $this->recentCodes = array_filter(
            $this->recentCodes,
            fn (float $seen) => ($now - $seen) < self::DEBOUNCE_SECONDS,
        );

        if (isset($this->recentCodes[$data])) {
            return true;
        }

        $this->recentCodes[$data] = $now;

        return false;
    }

    private function vibrate(int $times): void
    {
        try {
            for ($i = 0; $i < $times; $i++) {
                Haptics::vibrate();
            }
        } catch (Throwable) {
            // Haptics are best-effort.
        }
    }

    public function navTitle(): string
    {
        return 'Scan';
    }

    public function render(): View
    {
        return view('native.scan-screen');
    }
}
