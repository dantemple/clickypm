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
        Schema::table('users', function (Blueprint $table) {
            // Emails about tasks you're involved in: assignments, comments and client requests.
            $table->boolean('task_emails')->default(true)->after('remember_token');
            // The morning email listing your tasks that are due today or overdue.
            $table->boolean('reminder_emails')->default(true)->after('task_emails');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn(['task_emails', 'reminder_emails']);
        });
    }
};
