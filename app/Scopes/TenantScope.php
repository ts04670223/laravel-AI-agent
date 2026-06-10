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
