<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * The BAC admin records the office's signed hard copy, so a request needs no separate PPMP/APP review: it is
     * ready for procurement when recorded. Requests still waiting for that review become ready for procurement.
     */
    public function up(): void
    {
        if (! Schema::hasTable('procurement_requests')) {
            return;
        }

        DB::table('procurement_requests')->where('status', 'submitted')->update([
            'status' => 'forwarded',
            'forwarded_at' => DB::raw('COALESCE(forwarded_at, CURRENT_TIMESTAMP)'),
            'updated_at' => now(),
        ]);
    }

    public function down(): void
    {
        // The earlier status is not kept: a forwarded request stays forwarded.
    }
};
