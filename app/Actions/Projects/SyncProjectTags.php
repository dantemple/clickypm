<?php

namespace App\Actions\Projects;

use App\Models\Project;
use App\Models\Tag;
use Illuminate\Support\Facades\DB;

class SyncProjectTags
{
    /**
     * Replace the project's tags with the given list.
     *
     * Rows with an id are renamed or recoloured, rows without one are
     * created, and any tag left out is deleted and comes off its tasks.
     *
     * @param  array<int, array{id?: int|null, name: string, color: string}>  $rows
     */
    public function handle(Project $project, array $rows): void
    {
        DB::transaction(function () use ($project, $rows) {
            $existing = $project->tags()->get()->keyBy('id');
            $kept = [];

            foreach ($rows as $row) {
                $attributes = ['name' => trim($row['name']), 'color' => $row['color']];
                $tag = isset($row['id']) ? $existing->get($row['id']) : null;

                if ($tag) {
                    $tag->update($attributes);
                } else {
                    $tag = $project->tags()->create($attributes);
                }

                $kept[] = $tag->id;
            }

            $existing->except($kept)->each(fn (Tag $removed) => $removed->delete());
        });
    }
}
