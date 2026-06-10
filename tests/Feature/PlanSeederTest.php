<?php

namespace Tests\Feature;

use App\Models\Plan;
use Database\Seeders\PlanSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PlanSeederTest extends TestCase
{
    use RefreshDatabase;

    public function test_seeder_creates_free_and_pro_plans_with_quotas(): void
    {
        $this->seed(PlanSeeder::class);

        $free = Plan::where('name', 'Free')->first();
        $pro = Plan::where('name', 'Pro')->first();

        $this->assertNotNull($free);
        $this->assertSame(3, $free->max_projects);
        $this->assertSame(5, $free->max_members);
        $this->assertSame(50, $free->max_tasks_per_project);

        $this->assertNotNull($pro);
        $this->assertSame(50, $pro->max_projects);
        $this->assertSame(50, $pro->max_members);
        $this->assertSame(1000, $pro->max_tasks_per_project);
    }
}
