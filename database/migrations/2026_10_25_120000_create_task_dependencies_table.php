<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // A link on the Gantt chart: the successor can't start until the
        // predecessor finishes (plus any lag in days). Links never cross projects.
        Schema::create('task_dependencies', function (Blueprint $table) {
            $table->id();
            $table->foreignId('predecessor_id')->constrained('tasks')->cascadeOnDelete();
            $table->foreignId('successor_id')->constrained('tasks')->cascadeOnDelete();
            $table->string('type')->default('finish_to_start');
            $table->integer('lag_days')->default(0);
            $table->timestamps();

            $table->unique(['predecessor_id', 'successor_id']);
            $table->index('successor_id');
        });

        Schema::table('tasks', function (Blueprint $table) {
            // How far along the work is, 0 to 100, set by hand. Done statuses
            // count as 100 whatever this says.
            $table->unsignedTinyInteger('progress')->default(0)->after('is_milestone');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('tasks', function (Blueprint $table) {
            $table->dropColumn('progress');
        });

        Schema::dropIfExists('task_dependencies');
    }
};
