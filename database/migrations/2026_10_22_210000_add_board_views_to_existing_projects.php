<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Give projects created before the Board view existed a Board tab.
     */
    public function up(): void
    {
        DB::table('projects')
            ->whereNotExists(fn ($query) => $query
                ->from('project_views')
                ->whereColumn('project_views.project_id', 'projects.id')
                ->where('project_views.type', 'board'))
            ->pluck('id')
            ->each(fn (int $projectId) => DB::table('project_views')->insert([
                'project_id' => $projectId,
                'name' => 'Board',
                'type' => 'board',
                'position' => (int) DB::table('project_views')->where('project_id', $projectId)->max('position') + 1,
                'created_at' => now(),
                'updated_at' => now(),
            ]));
    }

    /**
     * Board views may have been customised, so they are left in place.
     */
    public function down(): void
    {
        //
    }
};
