<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Postgres doesn't index foreign keys on its own, so the columns the
     * busiest queries filter and count on get indexes here. The attachment
     * count on every task row of a project page is the one that shows most.
     *
     * @var array<string, array<int, string>>
     */
    private const INDEXES = [
        'attachments' => ['task_id'],
        'tasks' => ['status_id'],
        'projects' => ['team_id'],
        'statuses' => ['project_id'],
        'project_views' => ['project_id'],
        'team_members' => ['user_id'],
        'comment_mentions' => ['user_id'],
        'custom_field_values' => ['custom_field_id'],
        'custom_field_project' => ['custom_field_id'],
    ];

    /**
     * Run the migrations.
     */
    public function up(): void
    {
        foreach (self::INDEXES as $table => $columns) {
            Schema::table($table, function (Blueprint $blueprint) use ($columns) {
                foreach ($columns as $column) {
                    $blueprint->index($column);
                }
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        foreach (self::INDEXES as $table => $columns) {
            Schema::table($table, function (Blueprint $blueprint) use ($columns) {
                foreach ($columns as $column) {
                    $blueprint->dropIndex([$column]);
                }
            });
        }
    }
};
