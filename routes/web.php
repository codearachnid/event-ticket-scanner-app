<?php

use App\NativeComponents\AttendeeDetail;
use App\NativeComponents\AttendeesIndex;
use App\NativeComponents\ConnectSite;
use App\NativeComponents\EventHome;
use App\NativeComponents\EventsIndex;
use App\NativeComponents\Home;
use App\NativeComponents\ScanScreen;
use App\NativeComponents\StatsScreen;
use Illuminate\Support\Facades\Route;

Route::native('/', Home::class);
Route::native('/connect', ConnectSite::class);
Route::native('/events', EventsIndex::class);
Route::native('/events/{event}', EventHome::class);
Route::native('/events/{event}/attendees', AttendeesIndex::class);
Route::native('/events/{event}/stats', StatsScreen::class);
Route::native('/attendees/{attendee}', AttendeeDetail::class);
Route::native('/scan/{event}', ScanScreen::class);
