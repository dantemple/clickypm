<?php

namespace App\Actions\Projects;

use App\Models\Project;
use App\Models\Status;
use Illuminate\Support\Facades\DB;

class SyncProjectStatuses
{
    /**
     * Replace the project's statuses with the given ordered list.
     *
     * Rows with an id are updated, rows without one are created, and any
     * status left out is deleted after its tasks move to the first remaining
     * status of the same kind (open or done).
     *
     * @param  array<int, array{id?: int|null, name: string, color: string, is_done: bool}>  $rows
     */
    public function handle(Project $project, array $rows): void
    {
        DB::transaction(function () use ($project, $rows) {
            $existing = $project->statuses()->get()->keyBy('id');
            $kept = collect();

            foreach (array_values($rows) as $position => $row) {
                $attributes = [
                    'name' => $row['name'],
                    'color' => $row['color'],
                    'is_done' => $row['is_done'],
                    'position' => $position,
                ];

                $status = isset($row['id']) ? $existing->get($row['id']) : null;

                if ($status) {
                    $status->update($attributes);
                } else {
                    $status = $project->statuses()->create($attributes);
                }

                $kept->push($status);
            }

            $existing->except($kept->pluck('id')->all())->each(function (Status $removed) use ($kept) {
                $target = $kept->firstWhere('is_done', $removed->is_done) ?? $kept->first();

                // Include trashed tasks: they still point at this status.
                $removed->tasks()->withTrashed()->update(['status_id' => $target->id]);
                $removed->delete();
            });

            // Re-evaluate completion for tasks whose status flipped between open and done.
            foreach ($kept as $status) {
                $status->is_done
                    ? $status->tasks()->withTrashed()->whereNull('completed_at')->update(['completed_at' => now()])
                    : $status->tasks()->withTrashed()->whereNotNull('completed_at')->update(['completed_at' => null]);
            }
        });
    }
}
