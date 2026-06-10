<?php

use App\Http\Middleware\EnsureValidTenant;
use App\Models\Team;
use App\Models\User;
use Illuminate\Http\Request;

beforeEach(function () {
    $this->seed(\Database\Seeders\PlanSeeder::class);
});

function teamUser(): array
{
    $user = User::factory()->create();
    $team = Team::forceCreate([
        'user_id' => $user->id,
        'name' => 'Acme',
        'personal_team' => false,
    ]);
    $user->teams()->attach($team, ['role' => 'admin']);
    $user->forceFill(['current_team_id' => $team->id])->save();

    return [$user, $team];
}

test('passes when user belongs to the current team', function () {
    [$user, $team] = teamUser();

    $request = Request::create('/dashboard', 'GET');
    $request->setUserResolver(fn () => $user);

    $middleware = new EnsureValidTenant;
    $response = $middleware->handle($request, fn () => response('ok'));

    expect($response->getContent())->toBe('ok');
});

test('redirects when user has no current team', function () {
    $user = User::factory()->create();
    $user->forceFill(['current_team_id' => null])->save();

    $request = Request::create('/dashboard', 'GET');
    $request->setUserResolver(fn () => $user);

    $middleware = new EnsureValidTenant;
    $response = $middleware->handle($request, fn () => response('ok'));

    expect($response->getStatusCode())->toBe(302);
});

test('aborts 403 when current team does not belong to user', function () {
    [$user, $team] = teamUser();

    $otherUser = User::factory()->create();
    $otherTeam = Team::forceCreate([
        'user_id' => $otherUser->id,
        'name' => 'Other',
        'personal_team' => false,
    ]);
    // 竄改：把 current_team_id 指向不屬於自己的 team
    $user->forceFill(['current_team_id' => $otherTeam->id])->save();

    $request = Request::create('/dashboard', 'GET');
    $request->setUserResolver(fn () => $user->fresh());

    $middleware = new EnsureValidTenant;

    expect(fn () => $middleware->handle($request, fn () => response('ok')))
        ->toThrow(\Symfony\Component\HttpKernel\Exception\HttpException::class);
});
