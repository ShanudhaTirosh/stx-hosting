<?php

namespace Database\Seeders;

use App\Models\Application;
use Illuminate\Database\Seeder;

class ApplicationSettingsSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $application_1 = Application::find(1) ?? Application::first();
        if ($application_1) {
            $application_1->load(['settings']);
            if ($application_1->settings) {
                $application_1->settings->is_debug_enabled = false;
                $application_1->settings->save();
            }
        }
    }
}
