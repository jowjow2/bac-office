<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * A project the BAC admin has just created is marked NEW in the Projects list until the admin opens it.
     * seen_at is that moment; every project that exists now counts as already seen.
     */
    public function up(): void
    {
        if (! Schema::hasTable('projects') || Schema::hasColumn('projects', 'seen_at')) {
            return;
        }

        Schema::table('projects', function (Blueprint $table) {
            $table->timestamp('seen_at')->nullable();
        });

        DB::table('projects')->whereNull('seen_at')->update(['seen_at' => now()]);
    }

    public function down(): void
    {
        if (Schema::hasColumn('projects', 'seen_at')) {
            Schema::table('projects', function (Blueprint $table) {
                $table->dropColumn('seen_at');
            });
        }
    }
};
