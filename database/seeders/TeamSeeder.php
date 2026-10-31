<?php

namespace Database\Seeders;

use App\Models\Team;
use App\Models\User;
use Illuminate\Database\Seeder;

class TeamSeeder extends Seeder
{
    public function run(): void
    {
        $normal_user_in_root_team = User::where('email', 'test2@example.com')->firstOrFail();
        $root_user_personal_team = Team::find(0);
        $root_user_personal_team->description = 'The root team';
        $root_user_personal_team->save();

        $normal_user_in_root_team->teams()->syncWithoutDetaching([$root_user_personal_team->id]);
        $normal_user_not_in_root_team = User::where('email', 'test3@example.com')->firstOrFail();
        $normal_user_in_root_team_personal_team = $normal_user_in_root_team->teams()->where('personal_team', true)->wherePivot('role', 'owner')->firstOrFail();
        $normal_user_not_in_root_team->teams()->syncWithoutDetaching([$normal_user_in_root_team_personal_team->id => ['role' => 'admin']]);
    }
}
