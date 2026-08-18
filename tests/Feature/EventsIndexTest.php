<?php

use App\Models\Event;
use App\Models\Site;
use App\NativeComponents\EventHome;
use App\NativeComponents\EventsIndex;
use App\Services\Api\ApiClient;
use App\Services\Api\ApiException;
use Native\Mobile\Testing\Native;

covers(EventsIndex::class);

beforeEach(function () {
    Native::fakeBridge();
});

it('redirects to connect when no site exists', function () {
    Native::test(EventsIndex::class)->assertReplacedWith('/connect');
});

it('lists events from the API with server counts and stores them locally', function () {
    $site = Site::factory()->create();

    Native::test(EventsIndex::class)
        ->assertSee('Fixture Fest 2026')
        ->assertSee('14 / 50 checked in');

    $event = Event::sole();
    expect($event->site_id)->toBe($site->id)
        ->and($event->wp_event_id)->toBe(501)
        ->and($event->venue)->toBe('The Grand Hall');
});

it('falls back to cached events with local counts when the API is unreachable', function () {
    $site = Site::factory()->create();
    Event::factory()->create(['site_id' => $site->id, 'title' => 'Cached Event']);

    app(ApiClient::class)->failNextWith(new ApiException('timeout'));

    Native::test(EventsIndex::class)
        ->assertSee('Offline — showing cached events.')
        ->assertSee('Cached Event')
        ->assertSee('0 / 0 checked in');
});

it('opens an event', function () {
    Site::factory()->create();

    $harness = Native::test(EventsIndex::class);
    $eventId = Event::sole()->id;

    $harness->call('open', $eventId)
        ->assertNavigatedTo("/events/{$eventId}")
        ->followNavigation()
        ->assertScreen(EventHome::class);
});
