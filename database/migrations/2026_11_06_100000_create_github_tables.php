<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * The GitHub module: a workspace installs the GitHub App once, picks
     * which of its repositories each project follows, and pull requests and
     * commits that mention a task key are linked to the task.
     */
    public function up(): void
    {
        Schema::create('github_installations', function (Blueprint $table) {
            $table->id();
            // Null until the person who installed the app lands back here;
            // the webhook can arrive first.
            $table->foreignId('team_id')->nullable()->unique()->constrained()->cascadeOnDelete();
            $table->unsignedBigInteger('installation_id')->unique();
            $table->string('account_login')->nullable();
            $table->string('account_type', 20)->default('User');
            $table->string('account_avatar_url')->nullable();
            $table->timestamp('suspended_at')->nullable();
            $table->timestamps();
        });

        Schema::create('github_repositories', function (Blueprint $table) {
            $table->id();
            $table->foreignId('github_installation_id')->constrained()->cascadeOnDelete();
            $table->unsignedBigInteger('repository_id')->unique();
            $table->string('full_name');
            $table->boolean('private')->default(false);
            $table->timestamps();
        });

        Schema::create('github_repository_project', function (Blueprint $table) {
            $table->foreignId('github_repository_id')->constrained()->cascadeOnDelete();
            $table->foreignId('project_id')->constrained()->cascadeOnDelete();
            $table->timestamps();

            $table->primary(['github_repository_id', 'project_id']);
        });

        Schema::table('projects', function (Blueprint $table) {
            // Where tasks go when a pull request mentioning them opens / merges.
            $table->foreignId('github_opened_status_id')->nullable()->after('hidden_from_clients')->constrained('statuses')->nullOnDelete();
            $table->foreignId('github_merged_status_id')->nullable()->after('github_opened_status_id')->constrained('statuses')->nullOnDelete();
        });

        Schema::create('github_links', function (Blueprint $table) {
            $table->id();
            $table->foreignId('task_id')->constrained()->cascadeOnDelete();
            // Kept (with the name below) after the repository is disconnected.
            $table->foreignId('github_repository_id')->nullable()->constrained()->nullOnDelete();
            $table->string('repository');
            $table->string('type', 20); // pull_request | commit
            $table->string('external_id', 64); // PR number or commit sha
            $table->string('title', 500);
            $table->string('url', 500);
            $table->string('state', 20)->nullable(); // open | draft | merged | closed
            $table->string('author')->nullable();
            $table->string('author_avatar_url')->nullable();
            $table->timestamps();

            $table->unique(['task_id', 'repository', 'type', 'external_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('github_links');

        Schema::table('projects', function (Blueprint $table) {
            $table->dropConstrainedForeignId('github_opened_status_id');
            $table->dropConstrainedForeignId('github_merged_status_id');
        });

        Schema::dropIfExists('github_repository_project');
        Schema::dropIfExists('github_repositories');
        Schema::dropIfExists('github_installations');
    }
};
