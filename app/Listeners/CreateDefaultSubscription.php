<?php

namespace App\Listeners;

use App\Models\Plan;
use App\Models\Subscription;
use Laravel\Jetstream\Events\TeamCreated;

class CreateDefaultSubscription
{
    public function handle(TeamCreated $event): void
    {
        $team = $event->team;

        if ($team->personal_team) {
            return;
        }

        if ($team->subscription()->exists()) {
            return;
        }

        $freePlan = Plan::where('name', 'Free')->first();

        if ($freePlan === null) {
            return;
        }

        Subscription::create([
            'team_id' => $team->id,
            'plan_id' => $freePlan->id,
            'status' => 'active',
        ]);
    }
}
