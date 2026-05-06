<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('bids')) {
            return;
        }

        try {
            DB::statement("ALTER TABLE bids MODIFY COLUMN status ENUM('pending', 'approved', 'rejected', 'evaluated', 'awarded') NOT NULL DEFAULT 'pending'");
        } catch (Throwable) {
            // Projects that already use a string status column do not need the enum alteration.
        }
    }

    public function down(): void
    {
        if (! Schema::hasTable('bids')) {
            return;
        }

        DB::table('bids')
            ->whereIn('status', ['evaluated', 'awarded'])
            ->update(['status' => 'approved']);

        try {
            DB::statement("ALTER TABLE bids MODIFY COLUMN status ENUM('pending', 'approved', 'rejected') NOT NULL DEFAULT 'pending'");
        } catch (Throwable) {
        }
    }
};
