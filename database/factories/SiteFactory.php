<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;

class SiteFactory extends Factory
{
    public function definition(): array
    {
        return [
            'name' => 'Fixture Fest Productions',
            'base_url' => 'https://'.fake()->unique()->domainWord().'.example.test',
            'username' => 'doorstaff',
        ];
    }
}
