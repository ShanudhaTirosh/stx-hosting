<?php

namespace Database\Factories;

use App\Models\StandalonePostgresql;
use Illuminate\Database\Eloquent\Factories\Factory;

class StandalonePostgresqlFactory extends Factory
{
    protected $model = StandalonePostgresql::class;

    public function definition(): array
    {
        return [
            'name' => fake()->unique()->words(2, true),
            'postgres_user' => 'postgres',
            'postgres_password' => 'secret-password',
            'postgres_db' => 'postgres',
            'status' => 'running',
            'destination_id' => 1,
            'destination_type' => 'App\Models\StandaloneDocker',
            'environment_id' => 1,
        ];
    }
}

