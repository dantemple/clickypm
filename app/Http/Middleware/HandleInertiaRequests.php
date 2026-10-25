<?php

namespace App\Http\Middleware;

use App\Models\Project;
use App\Models\User;
use Illuminate\Http\Request;
use Inertia\Middleware;

class HandleInertiaRequests extends Middleware
{
    /**
     * The root template that's loaded on the first page visit.
     *
     * @see https://inertiajs.com/server-side-setup#root-template
     *
     * @var string
     */
    protected $rootView = 'app';

    /**
     * Determines the current asset version.
     *
     * @see https://inertiajs.com/asset-versioning
     */
    public function version(Request $request): ?string
    {
        return parent::version($request);
    }

    /**
     * Define the props that are shared by default.
     *
     * @see https://inertiajs.com/shared-data
     *
     * @return array<string, mixed>
     */
    public function share(Request $request): array
    {
        $user = $request->user();

        return [
            ...parent::share($request),
            'name' => config('app.name'),
            'auth' => [
                'user' => $user,
            ],
            'sidebarOpen' => ! $request->hasCookie('sidebar_state') || $request->cookie('sidebar_state') === 'true',
            'currentTeam' => fn () => $user?->currentTeam ? $user->toUserTeam($user->currentTeam) : null,
            'teams' => fn () => $user?->toUserTeams(includeCurrent: true) ?? [],
            'projects' => fn () => $this->sidebarProjects($user),
            'archivedProjectsCount' => fn () => $this->archivedProjectsCount($user),
        ];
    }

    /**
     * The current workspace's projects for the sidebar, with untriaged request
     * counts for the team (clients don't triage, so they don't get counts).
     *
     * @return array<int, array{id: int, name: string, color: string, requests_count: int}>
     */
    protected function sidebarProjects(?User $user): array
    {
        $team = $user?->currentTeam;

        if (! $team) {
            return [];
        }

        $isClient = $user->isClientOf($team);

        return $team->projects()
            ->active()
            ->visibleTo($isClient)
            ->unless($isClient, fn ($query) => $query->withCount(['tasks as requests_count' => fn ($query) => $query->where('needs_triage', true)]))
            ->get(['id', 'name', 'color'])
            ->map(fn (Project $project) => [
                'id' => $project->id,
                'name' => $project->name,
                'color' => $project->color,
                'requests_count' => $project->requests_count ?? 0,
            ])
            ->all();
    }

    /**
     * How many of the workspace's projects the viewer could find under Archived.
     */
    protected function archivedProjectsCount(?User $user): int
    {
        $team = $user?->currentTeam;

        if (! $team) {
            return 0;
        }

        return $team->projects()->archived()->visibleTo($user->isClientOf($team))->count();
    }
}
