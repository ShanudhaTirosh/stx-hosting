<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;

class UserSeeder extends Seeder
{
    public function run(): void
    {
        if (! User::where('id', 0)->exists()) {
            User::factory()->create([
                'id' => 0,
                'name' => 'Root User',
                'email' => 'test@example.com',
            ]);
        }
        if (! User::where('email', 'test2@example.com')->exists()) {
            User::factory()->create([
                'name' => 'Normal User (but in root team)',
                'email' => 'test2@example.com',
            ]);
        }
        if (! User::where('email', 'test3@example.com')->exists()) {
            User::factory()->create([
                'name' => 'Normal User (not in root team)',
                'email' => 'test3@example.com',
            ]);
        }
    }
}
