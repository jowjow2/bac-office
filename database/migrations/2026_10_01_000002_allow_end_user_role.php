<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * users.role was an enum of admin, staff and bidder; end-user office accounts
 * add "end_user". The column becomes a plain string (roles are validated in
 * the application). Existing values are unchanged.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (DB::getDriverName() === 'pgsql') {
            DB::statement('ALTER TABLE users DROP CONSTRAINT IF EXISTS users_role_check');
        }

        Schema::table('users', function (Blueprint $table) {
            $table->string('role', 30)->default('bidder')->change();
        });
    }

    public function down(): void
    {
        DB::table('users')->where('role', 'end_user')->update(['role' => 'staff']);

        Schema::table('users', function (Blueprint $table) {
            $table->enum('role', ['admin', 'staff', 'bidder'])->default('bidder')->change();
        });
    }
};
