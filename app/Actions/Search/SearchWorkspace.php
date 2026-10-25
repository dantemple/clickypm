<?php

namespace App\Actions\Search;

use App\Models\Comment;
use App\Models\Project;
use App\Models\Task;
use App\Models\Team;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Finds the projects, tasks and comments in a workspace that mention a
 * phrase, showing each person only what they could open themselves:
 * clients miss hidden projects, internal notes and other people's requests,
 * and nothing in the trash turns up.
 *
 * Matching is a case-insensitive "contains". On Postgres the trigram
 * indexes added in the search migration keep that fast.
 */
class SearchWorkspace
{
    /**
     * Shorter searches match too much to be useful.
     */
    public const MIN_LENGTH = 2;

    /**
     * @return array{projects: array<int, array<string, mixed>>, tasks: array<int, array<string, mixed>>, comments: array<int, array<string, mixed>>}
     */
    public function handle(User $user, Team $team, string $term, int $limit = 10): array
    {
        $term = trim($term);

        if (mb_strlen($term) < self::MIN_LENGTH) {
            return ['projects' => [], 'tasks' => [], 'comments' => []];
        }

        $isClient = $user->isClientOf($team);

        return [
            'projects' => $this->projects($team, $term, $isClient, min($limit, 5)),
            'tasks' => $this->tasks($user, $team, $term, $isClient, $limit),
            'comments' => $this->comments($user, $team, $term, $isClient, min($limit, 5)),
        ];
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function projects(Team $team, string $term, bool $isClient, int $limit): array
    {
        return $team->projects()
            ->active()
            ->visibleTo($isClient)
            ->where(fn (Builder $query) => $this->whereMentions($query, ['projects.name', 'projects.description'], $term))
            ->limit($limit)
            ->get()
            ->map(fn (Project $project) => [
                'id' => $project->id,
                'name' => $project->name,
                'color' => $project->color,
                'description' => $project->description ? Str::limit($project->description, 120) : null,
                'url' => route('projects.show', [$team, $project]),
            ])
            ->all();
    }

    /**
     * Tasks whose title or description mentions the term, title matches first.
     *
     * @return array<int, array<string, mixed>>
     */
    private function tasks(User $user, Team $team, string $term, bool $isClient, int $limit): array
    {
        return Task::query()
            ->whereHas('project', fn (Builder $query) => $query->where('team_id', $team->id)->active()->visibleTo($isClient))
            ->tap(fn (Builder $query) => $this->onlyRequestsBy($query, $user, $isClient))
            ->where(fn (Builder $query) => $this->whereMentions($query, ['tasks.title', 'tasks.description'], $term))
            ->with(['project:id,name,color', 'status:id,name,color,is_done'])
            ->orderByRaw("CASE WHEN tasks.title {$this->operator()} ? ESCAPE '\\' THEN 0 ELSE 1 END", [$this->pattern($term)])
            ->latest('tasks.updated_at')
            ->limit($limit)
            ->get()
            ->map(fn (Task $task) => [
                'id' => $task->id,
                'title' => $task->title,
                'needs_triage' => $task->needs_triage,
                'completed_at' => $task->completed_at?->toIso8601String(),
                'project' => $task->project->only(['id', 'name', 'color']),
                'status' => $task->status->only(['id', 'name', 'color', 'is_done']),
                'url' => route('projects.show', [$team, $task->project, 'task' => $task->id]),
            ])
            ->all();
    }

    /**
     * Comments mentioning the term, newest first, with a snippet around the match.
     *
     * @return array<int, array<string, mixed>>
     */
    private function comments(User $user, Team $team, string $term, bool $isClient, int $limit): array
    {
        return Comment::query()
            ->visibleTo($isClient)
            ->whereHas('task', fn (Builder $query) => $query
                ->whereHas('project', fn (Builder $query) => $query->where('team_id', $team->id)->active()->visibleTo($isClient))
                ->tap(fn (Builder $query) => $this->onlyRequestsBy($query, $user, $isClient)))
            ->where(fn (Builder $query) => $this->whereMentions($query, ['comments.body'], $term))
            ->with(['user:id,name', 'task:id,title,project_id', 'task.project:id,name,color'])
            ->latest('comments.created_at')
            ->latest('comments.id')
            ->limit($limit)
            ->get()
            ->map(fn (Comment $comment) => [
                'id' => $comment->id,
                'snippet' => Str::excerpt($comment->body, $term, ['radius' => 60]) ?? Str::limit($comment->body, 120),
                'is_internal' => $comment->is_internal,
                'user' => $comment->user?->only(['id', 'name']),
                'task' => $comment->task->only(['id', 'title']),
                'project' => $comment->task->project->only(['id', 'name', 'color']),
                'url' => route('projects.show', [$team, $comment->task->project, 'task' => $comment->task_id]),
            ])
            ->all();
    }

    /**
     * Clients see requests awaiting triage only when they raised them.
     *
     * @param  Builder<Task>  $query
     */
    private function onlyRequestsBy(Builder $query, User $user, bool $isClient): void
    {
        $query->when($isClient, fn (Builder $query) => $query->where(fn (Builder $query) => $query
            ->where('tasks.needs_triage', false)
            ->orWhere('tasks.created_by', $user->id)));
    }

    /**
     * Match rows where any of the columns contains the term, ignoring case.
     *
     * @param  Builder<covariant \Illuminate\Database\Eloquent\Model>  $query
     * @param  array<int, literal-string>  $columns
     */
    private function whereMentions(Builder $query, array $columns, string $term): void
    {
        foreach ($columns as $column) {
            $query->orWhereRaw("{$column} {$this->operator()} ? ESCAPE '\\'", [$this->pattern($term)]);
        }
    }

    /**
     * Postgres needs ILIKE to ignore case; SQLite's LIKE already does.
     *
     * @return literal-string
     */
    private function operator(): string
    {
        return DB::getDriverName() === 'pgsql' ? 'ILIKE' : 'LIKE';
    }

    /**
     * The term as a "contains" pattern, with LIKE's own wildcards taken literally.
     */
    private function pattern(string $term): string
    {
        return '%'.addcslashes($term, '\\%_').'%';
    }
}
