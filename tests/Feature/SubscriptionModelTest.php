<?php

namespace Tests\Feature;

use App\Models\Plan;
use App\Models\Subscription;
use App\Models\Team;
use App\Models\User;
use Database\Seeders\PlanSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SubscriptionModelTest extends TestCase
{
    use RefreshDatabase;

    public function test_subscription_belongs_to_a_team_and_a_plan(): void
    {
        $this->seed(PlanSeeder::class);

        $user = User::factory()->create();
        $team = Team::forceCreate([
            'user_id' => $user->id,
            'name' => 'Acme',
            'personal_team' => false,
        ]);
        $plan = Plan::where('name', 'Free')->first();

        $subscription = Subscription::create([
            'team_id' => $team->id,
            'plan_id' => $plan->id,
            'status' => 'active',
        ]);

        $this->assertSame($team->id, $subscription->team->id);
        $this->assertSame('Free', $subscription->plan->name);
    }
}
