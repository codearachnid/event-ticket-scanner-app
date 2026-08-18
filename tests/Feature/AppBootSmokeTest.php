<?php

use App\Models\Site;
use App\NativeComponents\EventsIndex;
use App\NativeComponents\Home;
use Native\Mobile\Testing\Native;

it('routes first-run users to the connect screen', function () {
    Native::fakeBridge();

    Native::test(Home::class)->assertReplacedWith('/connect');
});

it('routes returning users straight to the events list', function () {
    Native::fakeBridge();
    Site::factory()->create();

    Native::test(Home::class)
        ->assertReplacedWith('/events')
        ->followNavigation()
        ->assertScreen(EventsIndex::class);
});
