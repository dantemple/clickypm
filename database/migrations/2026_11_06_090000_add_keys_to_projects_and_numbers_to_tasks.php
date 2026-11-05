<?php

use App\Models\Project;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Give every project a short key (WEB) and every task a number within
     * its project, so a task can be referred to as WEB-42 in branch names,
     * commit messages and pull requests.
     */
    public function up(): void
    {
        Schema::table('projects', function (Blueprint $table) {
            $table->string('key', 10)->nullable()->after('name');
        });

        Schema::table('tasks', function (Blueprint $table) {
            $table->unsignedInteger('number')->nullable()->after('project_id');
        });

        $this->backfill();

        Schema::table('projects', function (Blueprint $table) {
            $table->string('key', 10)->nullable(false)->change();
            $table->unique(['team_id', 'key']);
        });

        Schema::table('tasks', function (Blueprint $table) {
            $table->unsignedInteger('number')->nullable(false)->change();
            $table->unique(['project_id', 'number']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('tasks', function (Blueprint $table) {
            $table->dropUnique(['project_id', 'number']);
            $table->dropColumn('number');
        });

        Schema::table('projects', function (Blueprint $table) {
            $table->dropUnique(['team_id', 'key']);
            $table->dropColumn('key');
        });
    }

    /**
     * Key existing projects from their names and number their tasks in the
     * order they were created, trashed ones included so nothing collides
     * when they're restored.
     */
    private function backfill(): void
    {
        $taken = [];

        DB::table('projects')->orderBy('id')->lazy()->each(function (object $project) use (&$taken) {
            $teamKeys = $taken[$project->team_id] ??= [];
            $key = Project::suggestKey($project->name);
            $candidate = $key;

            for ($suffix = 2; in_array($candidate, $teamKeys, true); $suffix++) {
                $candidate = $key.$suffix;
            }

            $taken[$project->team_id][] = $candidate;

            DB::table('projects')->where('id', $project->id)->update(['key' => $candidate]);

            $number = 0;

            DB::table('tasks')->where('project_id', $project->id)->orderBy('id')->pluck('id')->each(function (int $id) use (&$number) {
                DB::table('tasks')->where('id', $id)->update(['number' => ++$number]);
            });
        });
    }
};
