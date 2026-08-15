<?php

namespace App\Jobs;

use App\Models\Event;
use App\Services\SyncEngine;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

/**
 * Runs on the on-device database queue (jobs persist in SQLite across app
 * restarts — an interrupted sync resumes on next launch).
 */
class SyncEventJob implements ShouldBeUnique, ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    /** @var int[] seconds between retries */
    public array $backoff = [10, 60];

    public function __construct(public int $eventId) {}

    public function uniqueId(): string
    {
        return (string) $this->eventId;
    }

    public function handle(SyncEngine $engine): void
    {
        $event = Event::find($this->eventId);

        if ($event) {
            $engine->syncEvent($event);
        }
    }
}
