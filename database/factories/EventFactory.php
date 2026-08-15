<?php

namespace Database\Factories;

use App\Models\Site;
use Illuminate\Database\Eloquent\Factories\Factory;

class EventFactory extends Factory
{
    public function definition(): array
    {
        return [
            'site_id' => Site::factory(),
            // Matches the docs/api/fixtures event so fixture-backed tests line up.
            'wp_event_id' => 501,
            'title' => 'Fixture Fest 2026',
            'starts_at' => '2026-09-12T18:00:00',
            'ends_at' => '2026-09-12T23:00:00',
            'timezone' => 'America/Chicago',
            'venue' => 'The Grand Hall',
        ];
    }
}
