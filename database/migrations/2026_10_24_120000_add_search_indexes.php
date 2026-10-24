<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Trigram indexes that make the search's "contains" matching fast.
     *
     * Only Postgres has pg_trgm; the SQLite databases used in tests and CI
     * are small enough to scan.
     *
     * @var array<string, array{0: string, 1: string}>
     */
    private const INDEXES = [
        'projects_name_trgm_index' => ['projects', 'name'],
        'tasks_title_trgm_index' => ['tasks', 'title'],
        'tasks_description_trgm_index' => ['tasks', 'description'],
        'comments_body_trgm_index' => ['comments', 'body'],
    ];

    /**
     * Run the migrations.
     */
    public function up(): void
    {
        if (DB::getDriverName() !== 'pgsql') {
            return;
        }

        DB::statement('CREATE EXTENSION IF NOT EXISTS pg_trgm');

        foreach (self::INDEXES as $name => [$table, $column]) {
            DB::statement("CREATE INDEX IF NOT EXISTS {$name} ON {$table} USING gin ({$column} gin_trgm_ops)");
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (DB::getDriverName() !== 'pgsql') {
            return;
        }

        foreach (array_keys(self::INDEXES) as $name) {
            DB::statement("DROP INDEX IF EXISTS {$name}");
        }
    }
};
