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
