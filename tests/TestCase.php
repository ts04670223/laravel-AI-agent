<?php

namespace Tests;

use App\Models\Team;
use App\Models\User;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;

abstract class TestCase extends BaseTestCase
{
    /**
     * 建立一個已認證、具備指定角色與目前 Team 的使用者。
     *
     * @return array{0: \App\Models\User, 1: \App\Models\Team}
     */
    protected function actingAsTeamMember(string $role = 'admin'): array
    {
        $user = User::factory()->create();
        $team = Team::forceCreate([
            'user_id' => $user->id,
            'name' => fake()->company(),
            'personal_team' => false,
        ]);
        $user->teams()->attach($team, ['role' => $role]);
        $user->forceFill(['current_team_id' => $team->id])->save();

        $user = $user->fresh();
        $this->actingAs($user);

        return [$user, $team];
    }
}
