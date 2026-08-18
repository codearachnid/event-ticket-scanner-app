<?php

namespace App\NativeComponents;

use App\Models\Attendee;
use App\Models\Event;
use App\Services\Api\ApiClient;
use App\Services\Api\ApiException;
use Illuminate\View\View;
use Native\Mobile\Edge\NativeComponent;

class StatsScreen extends NativeComponent
{
    public int $total = 0;

    public int $checkedIn = 0;

    /** @var array<int, array{name: string, total: int, checked_in: int}> */
    public array $byTicketType = [];

    public string $source = 'local';

    public string $lastSynced = 'never';

    protected ?Event $event = null;

    public function mount(): void
    {
        $this->event = Event::find((int) $this->param('event', 0));

        if (! $this->event) {
            $this->replace('/events');

            return;
        }

        $this->loadLocal();
        $this->refresh();
    }

    /** Pull server-truth stats when reachable; local counts otherwise. */
    public function refresh(): void
    {
        try {
            $stats = app(ApiClient::class)->stats($this->event->site, $this->event->wp_event_id);

            $this->total = $stats['total'];
            $this->checkedIn = $stats['checked_in'];
            $this->byTicketType = array_map(fn (array $t) => [
                'name' => $t['name'],
                'total' => $t['total'],
                'checked_in' => $t['checked_in'],
            ], $stats['by_ticket_type']);
            $this->source = 'server';
        } catch (ApiException) {
            $this->loadLocal();
            $this->source = 'local';
        }
    }

    private function loadLocal(): void
    {
        $attendees = Attendee::query()
            ->where('site_id', $this->event->site_id)
            ->where('wp_event_id', $this->event->wp_event_id);

        $this->total = (clone $attendees)->count();
        $this->checkedIn = (clone $attendees)->where('checked_in', true)->count();

        $this->byTicketType = (clone $attendees)
            ->selectRaw("coalesce(ticket_name, 'Unknown') as name, count(*) as total, sum(checked_in) as checked_in")
            ->groupBy('name')
            ->orderBy('name')
            ->get()
            ->map(fn ($row) => [
                'name' => $row->name,
                'total' => (int) $row->total,
                'checked_in' => (int) $row->checked_in,
            ])
            ->all();

        $this->lastSynced = $this->event->last_synced_at?->diffForHumans() ?? 'never';
    }

    public function navTitle(): string
    {
        return 'Stats';
    }

    public function render(): View
    {
        return view('native.stats-screen');
    }
}
