<?php

namespace Database\Seeders;

use App\Models\Plan;
use Illuminate\Database\Seeder;

class PlanSeeder extends Seeder
{
    public function run(): void
    {
        Plan::updateOrCreate(['name' => 'Free'], [
            'max_projects' => 3,
            'max_members' => 5,
            'max_tasks_per_project' => 50,
            'price' => 0,
        ]);

        Plan::updateOrCreate(['name' => 'Pro'], [
            'max_projects' => 50,
            'max_members' => 50,
            'max_tasks_per_project' => 1000,
            'price' => 0,
        ]);
    }
}
