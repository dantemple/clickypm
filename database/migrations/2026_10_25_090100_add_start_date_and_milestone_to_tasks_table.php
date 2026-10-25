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
        Schema::table('tasks', function (Blueprint $table) {
            // For the Gantt module: a bar runs from start_date to due_date,
            // and a milestone is a single point on the due date.
            $table->date('start_date')->nullable()->after('due_date');
            $table->boolean('is_milestone')->default(false)->after('start_date');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('tasks', function (Blueprint $table) {
            $table->dropColumn(['start_date', 'is_milestone']);
        });
    }
};
