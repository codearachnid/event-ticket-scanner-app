<?php

use App\Models\Attendee;
use App\Models\Event;
use App\Models\Site;
use App\NativeComponents\StatsScreen;
use App\Services\Api\ApiClient;
use App\Services\Api\ApiException;
use Native\Mobile\Testing\Native;

covers(StatsScreen::class);

beforeEach(function () {
    Native::fakeBridge();
    $this->site = Site::factory()->create();
    $this->event = Event::factory()->create(['site_id' => $this->site->id, 'last_synced_at' => now()]);
});

it('shows server-truth stats when online', function () {
    Native::test(StatsScreen::class, params: ['event' => $this->event->id])
        ->assertSet('source', 'server')
        ->assertSet('total', 50)
        ->assertSet('checkedIn', 14)
        ->assertSee('General Admission')
        ->assertSee('VIP')
        ->assertSee('Live server counts');
});

it('falls back to local counts when offline', function () {
    Attendee::factory()->count(2)->create([
        'site_id' => $this->site->id,
        'wp_event_id' => $this->event->wp_event_id,
        'ticket_name' => 'General Admission',
        'checked_in' => true,
    ]);
    Attendee::factory()->create([
        'site_id' => $this->site->id,
        'wp_event_id' => $this->event->wp_event_id,
        'ticket_name' => 'VIP',
        'checked_in' => false,
    ]);

    app(ApiClient::class)->failNextWith(new ApiException('offline'));

    Native::test(StatsScreen::class, params: ['event' => $this->event->id])
        ->assertSet('source', 'local')
        ->assertSet('total', 3)
        ->assertSet('checkedIn', 2)
        ->assertSee('Offline — local counts');
});
