<?php

namespace App\Models;

use App\Concerns\SendsLiveUpdates;
use App\Concerns\Trashable;
use App\Enums\TaskPriority;
use Carbon\CarbonImmutable;
use Database\Factories\TaskFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\Storage;

/**
 * @property int $id
 * @property int $project_id
 * @property int $status_id
 * @property bool $needs_triage
 * @property string $title
 * @property string|null $description
 * @property TaskPriority|null $priority
 * @property int|null $assignee_id
 * @property CarbonImmutable|null $due_date
 * @property CarbonImmutable|null $start_date
 * @property bool $is_milestone
 * @property int $progress
 * @property int $position
 * @property int|null $created_by
 * @property CarbonImmutable|null $completed_at
 * @property CarbonImmutable|null $created_at
 * @property CarbonImmutable|null $updated_at
 * @property CarbonImmutable|null $deleted_at
 * @property-read Project $project
 * @property-read Status $status
 * @property-read User|null $assignee
 * @property-read User|null $creator
 * @property-read Collection<int, Comment> $comments
 * @property-read int|null $comments_count
 * @property-read Collection<int, Subtask> $subtasks
 * @property-read Collection<int, TaskActivity> $activities
 * @property-read int|null $subtasks_count
 * @property-read int|null $subtasks_done_count
 * @property-read Collection<int, Attachment> $attachments
 * @property-read int|null $attachments_count
 * @property-read Collection<int, CustomFieldValue> $customFieldValues
 * @property-read Collection<int, TaskDependency> $dependencies
 * @property-read Collection<int, TaskDependency> $dependents
 * @property-read Collection<int, Tag> $tags
 */
#[Fillable(['status_id', 'needs_triage', 'title', 'description', 'priority', 'assignee_id', 'due_date', 'start_date', 'is_milestone', 'progress', 'position', 'created_by'])]
class Task extends Model
{
    /** @use HasFactory<TaskFactory> */
    use HasFactory, SendsLiveUpdates, Trashable;

    /**
     * The model's default attribute values.
     *
     * @var array<string, mixed>
     */
    protected $attributes = [
        'needs_triage' => false,
        'is_milestone' => false,
        'progress' => 0,
    ];

    /**
     * Bootstrap the model and its traits.
     */
    protected static function booted(): void
    {
        // A task counts as complete while it sits in a "done" status.
        static::saving(function (Task $task) {
            if (! $task->isDirty('status_id')) {
                return;
            }

            $done = Status::whereKey($task->status_id)->value('is_done');

            $task->completed_at = $done ? ($task->completed_at ?? now()) : null;
        });

        static::created(fn (Task $task) => $task->recordActivity($task->needs_triage ? 'requested' : 'created'));

        static::restored(fn (Task $task) => $task->recordActivity('restored'));

        // Trashed tasks keep their files; once deleted for good the attachment
        // rows cascade away in the database and the files are removed here.
        static::forceDeleted(fn (Task $task) => Storage::disk(config('filesystems.attachments'))
            ->deleteDirectory(Attachment::directoryFor($task->project_id, $task->id)));

        static::updated(function (Task $task) {
            foreach ($task->describeChanges() as [$type, $data]) {
                $task->recordActivity($type, $data);
            }
        });
    }

    /**
     * Add a line to the task's history, credited to whoever is signed in.
     *
     * @param  array<string, mixed>  $data
     */
    public function recordActivity(string $type, array $data = []): void
    {
        $this->activities()->create([
            'user_id' => auth()->id(),
            'type' => $type,
            'data' => $data ?: null,
        ]);
    }

    /**
     * Turn the fields that just changed into history entries.
     *
     * @return array<int, array{0: string, 1: array<string, mixed>}>
     */
    protected function describeChanges(): array
    {
        $entries = [];
        $original = fn (string $key) => $this->getRawOriginal($key);

        if ($this->wasChanged('needs_triage') && ! $this->needs_triage) {
            $entries[] = ['accepted', []];
        }

        if ($this->wasChanged('status_id')) {
            $statuses = Status::whereKey([$original('status_id'), $this->status_id])->get()->keyBy('id');
            $describe = fn (?Status $status) => $status?->only(['name', 'color']);

            $entries[] = ['status', [
                'from' => $describe($statuses->get($original('status_id'))),
                'to' => $describe($statuses->get($this->status_id)),
            ]];
        }

        if ($this->wasChanged('assignee_id')) {
            $entries[] = $this->assignee_id
                ? ['assigned', ['name' => User::whereKey($this->assignee_id)->value('name')]]
                : ['unassigned', ['name' => User::whereKey($original('assignee_id'))->value('name')]];
        }

        if ($this->wasChanged('priority')) {
            $entries[] = ['priority', [
                'from' => TaskPriority::tryFrom((int) $original('priority'))?->label(),
                'to' => $this->priority?->label(),
            ]];
        }

        if ($this->wasChanged('due_date')) {
            $entries[] = ['due_date', [
                'from' => $original('due_date') ? CarbonImmutable::parse($original('due_date'))->toDateString() : null,
                'to' => $this->due_date?->toDateString(),
            ]];
        }

        if ($this->wasChanged('start_date')) {
            $entries[] = ['start_date', [
                'from' => $original('start_date') ? CarbonImmutable::parse($original('start_date'))->toDateString() : null,
                'to' => $this->start_date?->toDateString(),
            ]];
        }

        if ($this->wasChanged('is_milestone')) {
            $entries[] = [$this->is_milestone ? 'milestone' : 'not_milestone', []];
        }

        if ($this->wasChanged('progress')) {
            $entries[] = ['progress', ['from' => (int) $original('progress'), 'to' => $this->progress]];
        }

        if ($this->wasChanged('title')) {
            $entries[] = ['renamed', ['from' => $original('title'), 'to' => $this->title]];
        }

        if ($this->wasChanged('description')) {
            $entries[] = ['description', []];
        }

        return $entries;
    }

    /**
     * Get the project the task belongs to.
     *
     * @return BelongsTo<Project, $this>
     */
    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }

    /**
     * Get the task's status.
     *
     * @return BelongsTo<Status, $this>
     */
    public function status(): BelongsTo
    {
        return $this->belongsTo(Status::class);
    }

    /**
     * Get the user the task is assigned to.
     *
     * @return BelongsTo<User, $this>
     */
    public function assignee(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assignee_id');
    }

    /**
     * Get the user who created the task.
     *
     * @return BelongsTo<User, $this>
     */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /**
     * Get the task's comments, oldest first.
     *
     * @return HasMany<Comment, $this>
     */
    public function comments(): HasMany
    {
        return $this->hasMany(Comment::class)->oldest()->oldest('id');
    }

    /**
     * Get the task's checklist of subtasks.
     *
     * @return HasMany<Subtask, $this>
     */
    public function subtasks(): HasMany
    {
        return $this->hasMany(Subtask::class)->orderBy('position')->orderBy('id');
    }

    /**
     * Get the files attached to the task, oldest first.
     *
     * @return HasMany<Attachment, $this>
     */
    public function attachments(): HasMany
    {
        return $this->hasMany(Attachment::class)->oldest()->oldest('id');
    }

    /**
     * Get the task's custom field values.
     *
     * @return HasMany<CustomFieldValue, $this>
     */
    public function customFieldValues(): HasMany
    {
        return $this->hasMany(CustomFieldValue::class);
    }

    /**
     * Get the task's tags, in name order.
     *
     * @return BelongsToMany<Tag, $this>
     */
    public function tags(): BelongsToMany
    {
        return $this->belongsToMany(Tag::class)->withTimestamps()->orderBy('tags.name');
    }

    /**
     * Links to the tasks this one waits for.
     *
     * @return HasMany<TaskDependency, $this>
     */
    public function dependencies(): HasMany
    {
        return $this->hasMany(TaskDependency::class, 'successor_id');
    }

    /**
     * Links to the tasks waiting for this one.
     *
     * @return HasMany<TaskDependency, $this>
     */
    public function dependents(): HasMany
    {
        return $this->hasMany(TaskDependency::class, 'predecessor_id');
    }

    /**
     * Only the tasks the viewer may open. Clients see requests awaiting
     * triage only when they raised them, so one client never reads another's.
     *
     * @param  Builder<Task>  $query
     */
    public function scopeVisibleTo(Builder $query, User $user, bool $isClient): void
    {
        $query->when($isClient, fn (Builder $query) => $query->where(fn (Builder $query) => $query
            ->where('tasks.needs_triage', false)
            ->orWhere('tasks.created_by', $user->id)));
    }

    /**
     * Get the task's history, oldest first.
     *
     * @return HasMany<TaskActivity, $this>
     */
    public function activities(): HasMany
    {
        return $this->hasMany(TaskActivity::class)->oldest()->oldest('id');
    }

    /**
     * A link that opens the project with this task's panel showing.
     */
    public function url(): string
    {
        return route('projects.show', [$this->project->team, $this->project, 'task' => $this->id]);
    }

    /**
     * Get the task as an array for the frontend.
     *
     * @return array<string, mixed>
     */
    public function toTaskData(): array
    {
        return [
            'id' => $this->id,
            'project_id' => $this->project_id,
            'status_id' => $this->status_id,
            'needs_triage' => $this->needs_triage,
            'title' => $this->title,
            'description' => $this->description,
            'priority' => $this->priority?->value,
            'assignee_id' => $this->assignee_id,
            'due_date' => $this->due_date?->toDateString(),
            'start_date' => $this->start_date?->toDateString(),
            'is_milestone' => $this->is_milestone,
            'progress' => $this->progress,
            'position' => $this->position,
            'created_by' => $this->creator?->only(['id', 'name']),
            'completed_at' => $this->completed_at?->toIso8601String(),
            'comments_count' => $this->comments_count ?? 0,
            'subtasks_count' => $this->subtasks_count ?? 0,
            'subtasks_done_count' => $this->subtasks_done_count ?? 0,
            'attachments_count' => $this->attachments_count ?? 0,
            // Keyed by field id. Only sent where the caller loaded the values
            // it wants this viewer to see.
            'custom_fields' => (object) ($this->relationLoaded('customFieldValues')
                ? $this->customFieldValues->pluck('value', 'custom_field_id')->all()
                : []),
            // Only sent where the caller loaded them.
            'tags' => $this->relationLoaded('tags') ? $this->tags->map->toTagData()->values()->all() : [],
            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
        ];
    }

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, mixed>
     */
    protected function casts(): array
    {
        return [
            'needs_triage' => 'boolean',
            'priority' => TaskPriority::class,
            'due_date' => 'date',
            'start_date' => 'date',
            'is_milestone' => 'boolean',
            'progress' => 'integer',
            'completed_at' => 'datetime',
        ];
    }

    /**
     * The project (and task) live updates for this model go to.
     *
     * @return array{0: int, 1: int|null}
     */
    public function liveUpdateTarget(): array
    {
        return [$this->project_id, $this->id];
    }
}
