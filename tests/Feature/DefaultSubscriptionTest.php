<?php

namespace Tests\Feature;

use App\Models\Team;
use App\Models\User;
use Database\Seeders\PlanSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DefaultSubscriptionTest extends TestCase
{
    use RefreshDatabase;

    public function test_a_newly_created_non_personal_team_gets_a_free_subscription(): void
    {
        $this->seed(PlanSeeder::class);

        $user = User::factory()->create();
        $team = Team::forceCreate([
            'user_id' => $user->id,
            'name' => 'Acme',
            'personal_team' => false,
        ]);

        $this->assertNotNull($team->fresh()->subscription);
        $this->assertSame('Free', $team->fresh()->subscription->plan->name);
    }

    public function test_a_personal_team_does_not_get_a_subscription(): void
    {
        $this->seed(PlanSeeder::class);

        $user = User::factory()->create();
        $team = Team::forceCreate([
            'user_id' => $user->id,
            'name' => 'Personal',
            'personal_team' => true,
        ]);

        $this->assertNull($team->fresh()->subscription);
    }
}
