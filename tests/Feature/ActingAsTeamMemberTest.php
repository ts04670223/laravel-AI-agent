<?php

use App\Models\Team;
use App\Models\User;

beforeEach(function () {
    $this->seed(\Database\Seeders\PlanSeeder::class);
});

test('actingAsTeamMember sets up an authenticated user with a current team', function () {
    [$user, $team] = $this->actingAsTeamMember();

    expect($user)->toBeInstanceOf(User::class);
    expect($team)->toBeInstanceOf(Team::class);
    expect($user->current_team_id)->toBe($team->id);
    expect(auth()->id())->toBe($user->id);
    expect($user->belongsToTeam($team))->toBeTrue();
});
