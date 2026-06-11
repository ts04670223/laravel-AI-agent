<?php

namespace App\Http\Controllers;

use App\Models\Project;
use App\Models\Subscription;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class ProjectController extends Controller
{
    public function index(): Response
    {
        /** @var \App\Models\User $user */
        $user = auth()->user();
        $subscription = Subscription::with('plan')
            ->where('team_id', $user->current_team_id)
            ->first();

        return Inertia::render('Projects/Index', [
            'projects' => Project::latest()->get(),
            'subscription' => $subscription,
            'plan' => $subscription?->plan,
        ]);
    }

    public function create(): Response
    {
        return Inertia::render('Projects/Create');
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:2000'],
        ]);

        Project::create($validated);

        return redirect()->route('projects.index');
    }

    public function destroy(Project $project)
    {
        $project->delete();

        return redirect()->route('projects.index');
    }
}
