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
        Schema::table('projects', function (Blueprint $table) {
            $table->boolean('hidden_from_clients')->default(false)->after('description');
        });

        Schema::table('tasks', function (Blueprint $table) {
            $table->boolean('needs_triage')->default(false)->after('status_id');
        });

        Schema::create('comments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('task_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->text('body');
            $table->boolean('is_internal')->default(false);
            $table->timestamps();

            $table->index(['task_id', 'created_at']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('comments');

        Schema::table('tasks', function (Blueprint $table) {
            $table->dropColumn('needs_triage');
        });

        Schema::table('projects', function (Blueprint $table) {
            $table->dropColumn('hidden_from_clients');
        });
    }
};
