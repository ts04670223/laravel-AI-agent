<?php

use App\Models\Project;
use App\Models\Team;
use App\Models\User;

function makeTeamWithUser(string $teamName): array
{
    $user = User::factory()->create();
    $team = Team::forceCreate([
        'user_id' => $user->id,
        'name' => $teamName,
        'personal_team' => false,
    ]);
    $user->forceFill(['current_team_id' => $team->id])->save();
    $user->setRelation('currentTeam', $team);

    return [$user, $team];
}

beforeEach(function () {
    $this->seed(\Database\Seeders\PlanSeeder::class);
});

test('queries are scoped to the current team', function () {
    [$userA, $teamA] = makeTeamWithUser('Team A');
    [$userB, $teamB] = makeTeamWithUser('Team B');

    $this->actingAs($userA);
    Project::create(['name' => 'A Project']);

    $this->actingAs($userB);
    Project::create(['name' => 'B Project']);

    $this->actingAs($userA);
    expect(Project::pluck('name')->all())->toBe(['A Project']);

    $this->actingAs($userB);
    expect(Project::pluck('name')->all())->toBe(['B Project']);
});

test('team_id is auto-filled on create', function () {
    [$userA, $teamA] = makeTeamWithUser('Team A');

    $this->actingAs($userA);
    $project = Project::create(['name' => 'A Project']);

    expect($project->team_id)->toBe($teamA->id);
});

test('a team cannot retrieve another teams record by id', function () {
    [$userA, $teamA] = makeTeamWithUser('Team A');
    [$userB, $teamB] = makeTeamWithUser('Team B');

    $this->actingAs($userA);
    $projectA = Project::create(['name' => 'A Project']);

    $this->actingAs($userB);
    expect(Project::find($projectA->id))->toBeNull();
});
