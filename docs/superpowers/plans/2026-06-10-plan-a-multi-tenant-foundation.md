# 多租戶 SaaS 任務看板 — Plan A：多租戶地基 實作計畫

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** 建立多租戶平台地基——啟用 Jetstream Teams、安裝 Vue 前端、建立方案/訂閱資料模型，並完成以 `team_id` 為核心的資料隔離機制（trait + middleware），以可運行的跨租戶隔離測試驗證。

**Architecture:** 共享資料庫 + `team_id` 欄位。`BelongsToTenant` trait 透過 Eloquent Global Scope 自動依目前 Team 過濾查詢並於建立時補上 `team_id`；`EnsureValidTenant` middleware 確保使用者有合法的目前 Team。本計畫以 `Project` 模型作為隔離機制的試金石，後續 Plan B 再擴充看板其餘功能。

**Tech Stack:** Laravel 13.8 / PHP 8.3、Jetstream 5.5（Inertia + Teams）、Vue 3、Pest/PHPUnit、SQLite（測試）。

設計來源：[docs/superpowers/specs/2026-06-10-multi-tenant-saas-kanban-design.md](../specs/2026-06-10-multi-tenant-saas-kanban-design.md)

---

## 檔案結構

本計畫建立/修改的檔案與職責：

- `config/jetstream.php` — 啟用 `Features::teams(['invitations' => true])`。
- `app/Models/User.php` — 加上 `HasTeams` trait（由 Jetstream 安裝帶入）。
- `app/Models/Team.php` — Jetstream 產生的 Team 模型（租戶根）。
- `database/migrations/*_create_teams_table.php` 等 — Jetstream 產生的 teams 相關表。
- `app/Models/Plan.php` — 方案模型（Free/Pro 配額定義）。
- `app/Models/Subscription.php` — 訂閱模型（team ↔ plan）。
- `database/migrations/*_create_plans_table.php` — plans 表。
- `database/migrations/*_create_subscriptions_table.php` — subscriptions 表。
- `database/seeders/PlanSeeder.php` — 寫入 Free / Pro 方案。
- `app/Models/Concerns/BelongsToTenant.php` — 租戶隔離 trait。
- `app/Scopes/TenantScope.php` — global scope 實作。
- `app/Http/Middleware/EnsureValidTenant.php` — 租戶解析 middleware。
- `app/Models/Project.php` — 試金石模型（使用 `BelongsToTenant`）。
- `database/migrations/*_create_projects_table.php` — projects 表。
- `database/factories/ProjectFactory.php` — Project factory。
- `app/Listeners/CreateDefaultSubscription.php` — Team 建立時自動給 Free 訂閱。
- `tests/Feature/TenantIsolationTest.php` — 跨租戶隔離測試。
- `tests/Feature/EnsureValidTenantTest.php` — middleware 測試。
- `tests/TestCase.php` — 加入 `actingAsTeamMember()` 輔助方法。

---

## Task 1：啟用 Jetstream Teams

**Files:**
- Modify: `config/jetstream.php`
- 由安裝指令產生：`app/Models/Team.php`、`app/Models/Membership.php`、`app/Models/TeamInvitation.php`、teams 相關 migration、Inertia 團隊管理頁面。

- [ ] **Step 1：啟用 teams 功能設定**

修改 `config/jetstream.php` 的 `features` 陣列：

```php
    'features' => [
        // Features::termsAndPrivacyPolicy(),
        // Features::profilePhotos(),
        // Features::api(),
        Features::teams(['invitations' => true]),
        Features::accountDeletion(),
    ],
```

- [ ] **Step 2：執行 Jetstream 安裝以產生 Teams 骨架**

Run: `php artisan jetstream:install inertia --teams`
Expected: 產生 `app/Models/Team.php`、`Membership.php`、`TeamInvitation.php`、teams 相關 migration，並在 `app/Models/User.php` 加入 `HasTeams` trait。安裝過程若詢問覆寫，接受預設。

- [ ] **Step 3：安裝前端相依並建置（確認 Vue 已就緒）**

Run: `npm install`
Expected: 安裝成功，`package.json` 出現 `vue`、`@inertiajs/vue3` 等相依。

- [ ] **Step 4：執行 migration**

Run: `php artisan migrate`
Expected: `teams`、`team_user`、`team_invitations` 表建立成功。

- [ ] **Step 5：確認 User 具備 HasTeams**

Run: `php artisan tinker --execute="echo in_array('Laravel\\Jetstream\\HasTeams', class_uses_recursive(App\\Models\\User::class)) ? 'OK' : 'MISSING';"`
Expected: 輸出 `OK`

- [ ] **Step 6：Commit**

```bash
git add -A
git commit -m "feat: enable Jetstream Teams and Vue frontend"
```

---

## Task 2：Plan 模型與 migration

**Files:**
- Create: `app/Models/Plan.php`
- Create: `database/migrations/2026_06_10_100000_create_plans_table.php`
- Create: `database/seeders/PlanSeeder.php`
- Test: `tests/Feature/PlanSeederTest.php`

- [ ] **Step 1：撰寫失敗測試**

Create `tests/Feature/PlanSeederTest.php`：

```php
<?php

use App\Models\Plan;

test('seeder creates free and pro plans with quotas', function () {
    $this->seed(\Database\Seeders\PlanSeeder::class);

    $free = Plan::where('name', 'Free')->first();
    $pro = Plan::where('name', 'Pro')->first();

    expect($free)->not->toBeNull();
    expect($free->max_projects)->toBe(3);
    expect($free->max_members)->toBe(5);
    expect($free->max_tasks_per_project)->toBe(50);

    expect($pro)->not->toBeNull();
    expect($pro->max_projects)->toBe(50);
    expect($pro->max_members)->toBe(50);
    expect($pro->max_tasks_per_project)->toBe(1000);
});
```

- [ ] **Step 2：執行測試確認失敗**

Run: `php artisan test --filter=PlanSeederTest`
Expected: FAIL（`App\Models\Plan` 不存在）

- [ ] **Step 3：建立 migration**

Create `database/migrations/2026_06_10_100000_create_plans_table.php`：

```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('plans', function (Blueprint $table) {
            $table->id();
            $table->string('name')->unique();
            $table->unsignedInteger('max_projects');
            $table->unsignedInteger('max_members');
            $table->unsignedInteger('max_tasks_per_project');
            $table->unsignedInteger('price')->default(0);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('plans');
    }
};
```

- [ ] **Step 4：建立 Plan 模型**

Create `app/Models/Plan.php`：

```php
<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Plan extends Model
{
    protected $fillable = [
        'name',
        'max_projects',
        'max_members',
        'max_tasks_per_project',
        'price',
    ];
}
```

- [ ] **Step 5：建立 seeder**

Create `database/seeders/PlanSeeder.php`：

```php
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
```

- [ ] **Step 6：執行測試確認通過**

Run: `php artisan test --filter=PlanSeederTest`
Expected: PASS

- [ ] **Step 7：Commit**

```bash
git add app/Models/Plan.php database/migrations/2026_06_10_100000_create_plans_table.php database/seeders/PlanSeeder.php tests/Feature/PlanSeederTest.php
git commit -m "feat: add Plan model with Free/Pro seeder"
```

---

## Task 3：Subscription 模型與 migration

**Files:**
- Create: `app/Models/Subscription.php`
- Create: `database/migrations/2026_06_10_100100_create_subscriptions_table.php`
- Modify: `app/Models/Plan.php`
- Test: `tests/Feature/SubscriptionModelTest.php`

- [ ] **Step 1：撰寫失敗測試**

Create `tests/Feature/SubscriptionModelTest.php`：

```php
<?php

use App\Models\Plan;
use App\Models\Subscription;
use App\Models\Team;
use App\Models\User;

test('subscription belongs to a team and a plan', function () {
    $this->seed(\Database\Seeders\PlanSeeder::class);

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

    expect($subscription->team->id)->toBe($team->id);
    expect($subscription->plan->name)->toBe('Free');
});
```

- [ ] **Step 2：執行測試確認失敗**

Run: `php artisan test --filter=SubscriptionModelTest`
Expected: FAIL（`App\Models\Subscription` 不存在）

- [ ] **Step 3：建立 migration**

Create `database/migrations/2026_06_10_100100_create_subscriptions_table.php`：

```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('subscriptions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('team_id')->constrained()->cascadeOnDelete();
            $table->foreignId('plan_id')->constrained();
            $table->string('status')->default('active');
            $table->timestamps();

            $table->unique('team_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('subscriptions');
    }
};
```

- [ ] **Step 4：建立 Subscription 模型**

Create `app/Models/Subscription.php`：

```php
<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Subscription extends Model
{
    protected $fillable = [
        'team_id',
        'plan_id',
        'status',
    ];

    public function team(): BelongsTo
    {
        return $this->belongsTo(Team::class);
    }

    public function plan(): BelongsTo
    {
        return $this->belongsTo(Plan::class);
    }
}
```

- [ ] **Step 5：執行測試確認通過**

Run: `php artisan test --filter=SubscriptionModelTest`
Expected: PASS

- [ ] **Step 6：Commit**

```bash
git add app/Models/Subscription.php database/migrations/2026_06_10_100100_create_subscriptions_table.php tests/Feature/SubscriptionModelTest.php
git commit -m "feat: add Subscription model linking team and plan"
```

---

## Task 4：Team 建立時自動給 Free 訂閱

**Files:**
- Create: `app/Listeners/CreateDefaultSubscription.php`
- Modify: `app/Models/Team.php`（加上 `subscription` 關聯）
- Test: `tests/Feature/DefaultSubscriptionTest.php`

- [ ] **Step 1：撰寫失敗測試**

Create `tests/Feature/DefaultSubscriptionTest.php`：

```php
<?php

use App\Models\Team;
use App\Models\User;

test('a newly created non-personal team gets a Free subscription', function () {
    $this->seed(\Database\Seeders\PlanSeeder::class);

    $user = User::factory()->create();
    $team = Team::forceCreate([
        'user_id' => $user->id,
        'name' => 'Acme',
        'personal_team' => false,
    ]);

    expect($team->fresh()->subscription)->not->toBeNull();
    expect($team->fresh()->subscription->plan->name)->toBe('Free');
});
```

- [ ] **Step 2：執行測試確認失敗**

Run: `php artisan test --filter=DefaultSubscriptionTest`
Expected: FAIL（`subscription` 關聯不存在 / 無自動建立）

- [ ] **Step 3：在 Team 模型加上 subscription 關聯**

修改 `app/Models/Team.php`，於 class 內加入（import `HasOne`）：

```php
use Illuminate\Database\Eloquent\Relations\HasOne;
```

```php
    public function subscription(): HasOne
    {
        return $this->hasOne(\App\Models\Subscription::class);
    }
```

- [ ] **Step 4：建立 listener**

Create `app/Listeners/CreateDefaultSubscription.php`：

```php
<?php

namespace App\Listeners;

use App\Models\Plan;
use App\Models\Subscription;
use App\Models\Team;

class CreateDefaultSubscription
{
    public function handle(Team $team): void
    {
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
```

- [ ] **Step 5：在 Team 模型註冊 created 事件呼叫 listener**

修改 `app/Models/Team.php`，在 class 內加入 `booted` 方法（若已存在則合併）：

```php
    protected static function booted(): void
    {
        static::created(function (Team $team) {
            app(\App\Listeners\CreateDefaultSubscription::class)->handle($team);
        });
    }
```

- [ ] **Step 6：執行測試確認通過**

Run: `php artisan test --filter=DefaultSubscriptionTest`
Expected: PASS

- [ ] **Step 7：Commit**

```bash
git add app/Listeners/CreateDefaultSubscription.php app/Models/Team.php tests/Feature/DefaultSubscriptionTest.php
git commit -m "feat: auto-create Free subscription on team creation"
```

---

## Task 5：TenantScope 與 BelongsToTenant trait

**Files:**
- Create: `app/Scopes/TenantScope.php`
- Create: `app/Models/Concerns/BelongsToTenant.php`
- Create: `app/Models/Project.php`
- Create: `database/migrations/2026_06_10_100200_create_projects_table.php`
- Create: `database/factories/ProjectFactory.php`
- Test: `tests/Feature/TenantIsolationTest.php`

> 隔離機制以 `Project` 作為試金石模型，透過跨租戶測試驗證。

- [ ] **Step 1：建立 projects migration**

Create `database/migrations/2026_06_10_100200_create_projects_table.php`：

```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('projects', function (Blueprint $table) {
            $table->id();
            $table->foreignId('team_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->text('description')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('projects');
    }
};
```

- [ ] **Step 2：撰寫失敗測試**

Create `tests/Feature/TenantIsolationTest.php`：

```php
<?php

use App\Models\Plan;
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
```

- [ ] **Step 3：執行測試確認失敗**

Run: `php artisan test --filter=TenantIsolationTest`
Expected: FAIL（`App\Models\Project` / scope 不存在）

- [ ] **Step 4：建立 TenantScope**

Create `app/Scopes/TenantScope.php`：

```php
<?php

namespace App\Scopes;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Scope;
use Illuminate\Support\Facades\Auth;

class TenantScope implements Scope
{
    public function apply(Builder $builder, Model $model): void
    {
        $teamId = Auth::user()?->current_team_id;

        if ($teamId !== null) {
            $builder->where($model->getTable().'.team_id', $teamId);
        }
    }
}
```

- [ ] **Step 5：建立 BelongsToTenant trait**

Create `app/Models/Concerns/BelongsToTenant.php`：

```php
<?php

namespace App\Models\Concerns;

use App\Scopes\TenantScope;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Auth;

trait BelongsToTenant
{
    public static function bootBelongsToTenant(): void
    {
        static::addGlobalScope(new TenantScope);

        static::creating(function ($model) {
            if ($model->team_id === null && Auth::user()?->current_team_id !== null) {
                $model->team_id = Auth::user()->current_team_id;
            }
        });
    }

    public function team(): BelongsTo
    {
        return $this->belongsTo(\App\Models\Team::class);
    }
}
```

- [ ] **Step 6：建立 Project 模型**

Create `app/Models/Project.php`：

```php
<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Project extends Model
{
    use BelongsToTenant, HasFactory;

    protected $fillable = [
        'name',
        'description',
    ];
}
```

- [ ] **Step 7：建立 ProjectFactory**

Create `database/factories/ProjectFactory.php`：

```php
<?php

namespace Database\Factories;

use App\Models\Project;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Project>
 */
class ProjectFactory extends Factory
{
    protected $model = Project::class;

    public function definition(): array
    {
        return [
            'name' => fake()->sentence(3),
            'description' => fake()->paragraph(),
        ];
    }
}
```

- [ ] **Step 8：執行測試確認通過**

Run: `php artisan test --filter=TenantIsolationTest`
Expected: PASS（3 個測試全綠）

- [ ] **Step 9：Commit**

```bash
git add app/Scopes/TenantScope.php app/Models/Concerns/BelongsToTenant.php app/Models/Project.php database/migrations/2026_06_10_100200_create_projects_table.php database/factories/ProjectFactory.php tests/Feature/TenantIsolationTest.php
git commit -m "feat: add tenant isolation via BelongsToTenant trait and global scope"
```

---

## Task 6：EnsureValidTenant middleware

**Files:**
- Create: `app/Http/Middleware/EnsureValidTenant.php`
- Modify: `bootstrap/app.php`（註冊 middleware alias）
- Test: `tests/Feature/EnsureValidTenantTest.php`

- [ ] **Step 1：撰寫失敗測試**

Create `tests/Feature/EnsureValidTenantTest.php`：

```php
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
    $user->users()->attach ?? null; // no-op guard for static analysers
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
```

- [ ] **Step 2：執行測試確認失敗**

Run: `php artisan test --filter=EnsureValidTenantTest`
Expected: FAIL（`App\Http\Middleware\EnsureValidTenant` 不存在）

- [ ] **Step 3：建立 middleware**

Create `app/Http/Middleware/EnsureValidTenant.php`：

```php
<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureValidTenant
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if ($user === null || $user->current_team_id === null) {
            return redirect()->route('dashboard');
        }

        if (! $user->belongsToTeam($user->currentTeam)) {
            abort(403, 'You do not belong to the selected team.');
        }

        return $next($request);
    }
}
```

- [ ] **Step 4：註冊 middleware alias**

修改 `bootstrap/app.php`，在 `->withMiddleware(function (Middleware $middleware) {` 區塊內加入：

```php
        $middleware->alias([
            'tenant' => \App\Http\Middleware\EnsureValidTenant::class,
        ]);
```

- [ ] **Step 5：執行測試確認通過**

Run: `php artisan test --filter=EnsureValidTenantTest`
Expected: PASS

- [ ] **Step 6：Commit**

```bash
git add app/Http/Middleware/EnsureValidTenant.php bootstrap/app.php tests/Feature/EnsureValidTenantTest.php
git commit -m "feat: add EnsureValidTenant middleware for tenant resolution"
```

---

## Task 7：測試輔助方法 actingAsTeamMember

**Files:**
- Modify: `tests/TestCase.php`
- Test: `tests/Feature/ActingAsTeamMemberTest.php`

- [ ] **Step 1：撰寫失敗測試**

Create `tests/Feature/ActingAsTeamMemberTest.php`：

```php
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
```

- [ ] **Step 2：執行測試確認失敗**

Run: `php artisan test --filter=ActingAsTeamMemberTest`
Expected: FAIL（`actingAsTeamMember` 不存在）

- [ ] **Step 3：在 TestCase 加入輔助方法**

修改 `tests/TestCase.php`，在 class 內加入（import `App\Models\Team`、`App\Models\User`）：

```php
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
```

- [ ] **Step 4：執行測試確認通過**

Run: `php artisan test --filter=ActingAsTeamMemberTest`
Expected: PASS

- [ ] **Step 5：Commit**

```bash
git add tests/TestCase.php tests/Feature/ActingAsTeamMemberTest.php
git commit -m "test: add actingAsTeamMember helper for tenant scenarios"
```

---

## Task 8：整體回歸驗證

**Files:** 無新增，僅驗證。

- [ ] **Step 1：執行完整測試套件**

Run: `php artisan test`
Expected: 全數 PASS（含 Jetstream 預設測試與本計畫新增測試）

- [ ] **Step 2：靜態格式檢查**

Run: `vendor/bin/pint --test`
Expected: PASS（若有格式問題，執行 `vendor/bin/pint` 後重新 commit）

- [ ] **Step 3：最終 Commit（如有 pint 修正）**

```bash
git add -A
git commit -m "style: apply pint formatting"
```

---

## 自我檢查（Self-Review）

對照 spec 各節：

- **隔離（spec §6 單元 A）** → Task 5（TenantScope + BelongsToTenant + 隔離測試）。
- **租戶解析（spec §6 單元 B）** → Task 6（EnsureValidTenant middleware）。
- **方案/訂閱資料模型（spec §5、§7.4）** → Task 2、3、4。
- **Jetstream Teams 啟用（spec §2、§7.2）** → Task 1。
- **Vue 前端（spec §2）** → Task 1 Step 3。
- **測試策略（spec §9）** → Task 5（隔離）、Task 6（防竄改）、Task 7（輔助方法）。
- **配額服務（spec §6 單元 C）** → 不在 Plan A，屬 Plan C（已於範圍拆解標明）。
- **看板 CRUD / 拖拉 / Policy（spec §7.3）** → 不在 Plan A，屬 Plan B。

無 placeholder；型別與方法命名（`current_team_id`、`belongsToTeam`、`subscription`、`actingAsTeamMember`）跨 Task 一致。

## 後續

Plan A 完成後，依序撰寫 Plan B（看板核心）、Plan C（訂閱與配額）。
