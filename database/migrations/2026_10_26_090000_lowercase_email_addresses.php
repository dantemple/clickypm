<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Email addresses are compared case-insensitively everywhere, but the
     * unique index on users.email is not. Storing them lowercased keeps the
     * two in step, so nobody can register a second spelling of an address.
     */
    public function up(): void
    {
        DB::table('users')->whereRaw('email <> LOWER(email)')->update(['email' => DB::raw('LOWER(email)')]);
        DB::table('team_invitations')->whereRaw('email <> LOWER(email)')->update(['email' => DB::raw('LOWER(email)')]);
    }

    public function down(): void
    {
        // The original casing is gone; nothing to restore.
    }
};
