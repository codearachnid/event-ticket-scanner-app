<?php

namespace Database\Factories;

use App\Models\Site;
use Illuminate\Database\Eloquent\Factories\Factory;

class AttendeeFactory extends Factory
{
    public function definition(): array
    {
        return [
            'site_id' => Site::factory(),
            'wp_attendee_id' => fake()->unique()->numberBetween(10000, 99999),
            'wp_event_id' => 501,
            'wp_ticket_id' => 701,
            'ticket_name' => 'General Admission',
            'provider' => 'tickets-commerce',
            'holder_name' => fake()->name(),
            'holder_email' => fake()->safeEmail(),
            'security_code' => substr(md5(fake()->unique()->uuid()), 0, 8),
            'order_status' => 'completed',
            'checked_in' => false,
        ];
    }

    public function checkedIn(string $by = 'box-office'): static
    {
        return $this->state([
            'checked_in' => true,
            'checked_in_at' => '2026-08-11T15:11:00Z',
            'checked_in_by' => $by,
            'checked_in_source' => 'server',
        ]);
    }
}
