<?php

namespace App\Actions\Projects;

use App\Models\CustomField;
use App\Models\CustomFieldValue;
use App\Models\Project;
use App\Models\Subtask;
use App\Models\Task;
use App\Models\Team;
use App\Support\LiveUpdates;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class MoveProject
{
    /**
     * Move a project, with everything in it, to another workspace.
     *
     * Tasks, statuses, views, comments, attachments and dependencies all
     * belong to the project, so they come along untouched. Two things belong
     * to the workspace instead and need sorting out: people (tasks assigned
     * to someone who isn't in the destination become unassigned) and custom
     * fields (the project's fields are matched to, or copied into, the
     * destination so no values are lost).
     */
    public function handle(Project $project, Team $destination): void
    {
        DB::transaction(function () use ($project, $destination) {
            $source = $project->team_id;
            $taskIds = $project->tasks()->withTrashed()->pluck('id');

            $this->carryCustomFields($project, $destination, $taskIds);
            $this->unassignNonMembers($destination, $taskIds);

            $project->forceFill([
                'team_id' => $destination->id,
                'position' => (int) $destination->projects()->max('position') + 1,
            ])->save();

            // Saving the project tells the destination; the old workspace
            // needs telling too, so its sidebar drops the project.
            app(LiveUpdates::class)->workspaceChanged($source);
        });
    }

    /**
     * Re-point the project's custom fields (and its tasks' values) at fields
     * in the destination workspace, copying any it doesn't already have.
     *
     * @param  Collection<int, int>  $taskIds
     */
    protected function carryCustomFields(Project $project, Team $destination, $taskIds): void
    {
        $used = [];

        foreach ($project->customFields as $field) {
            $replacement = $this->matchingField($destination, $field, $used)
                ?? $destination->customFields()->create($field->only(['name', 'type', 'options', 'hidden_from_clients']));

            $used[] = $replacement->id;

            $project->customFields()->detach($field->id);
            $project->customFields()->attach($replacement->id);

            CustomFieldValue::whereIn('task_id', $taskIds)
                ->where('custom_field_id', $field->id)
                ->update(['custom_field_id' => $replacement->id]);
        }
    }

    /**
     * A field in the destination that's the same as the given one in all but
     * id, and hasn't already been claimed by another of the project's fields.
     *
     * @param  array<int, int>  $used
     */
    protected function matchingField(Team $destination, CustomField $field, array $used): ?CustomField
    {
        return $destination->customFields()
            ->whereNotIn('id', $used)
            ->where('name', $field->name)
            ->where('type', $field->type->value)
            ->get()
            ->first(fn (CustomField $candidate) => $candidate->options == $field->options);
    }

    /**
     * Clear assignees who aren't members of the destination workspace.
     *
     * @param  Collection<int, int>  $taskIds
     */
    protected function unassignNonMembers(Team $destination, $taskIds): void
    {
        $memberIds = $destination->members()->pluck('users.id');

        Task::withTrashed()
            ->whereIn('id', $taskIds)
            ->whereNotNull('assignee_id')
            ->whereNotIn('assignee_id', $memberIds)
            ->update(['assignee_id' => null]);

        Subtask::whereIn('task_id', $taskIds)
            ->whereNotNull('assignee_id')
            ->whereNotIn('assignee_id', $memberIds)
            ->update(['assignee_id' => null]);
    }
}
