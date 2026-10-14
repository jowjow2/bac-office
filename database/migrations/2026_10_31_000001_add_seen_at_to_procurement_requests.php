<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * A request recorded by the BAC admin is marked NEW until the admin starts its procurement (Prepare
     * procurement). seen_at is that moment; every request that exists now counts as already seen.
     */
    public function up(): void
    {
        if (! Schema::hasTable('procurement_requests') || Schema::hasColumn('procurement_requests', 'seen_at')) {
            return;
        }

        Schema::table('procurement_requests', function (Blueprint $table) {
            $table->timestamp('seen_at')->nullable();
        });

        DB::table('procurement_requests')->whereNull('seen_at')->update(['seen_at' => now()]);
    }

    public function down(): void
    {
        if (Schema::hasColumn('procurement_requests', 'seen_at')) {
            Schema::table('procurement_requests', function (Blueprint $table) {
                $table->dropColumn('seen_at');
            });
        }
    }
};
