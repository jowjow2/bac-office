<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('bidding_fee_payments', function (Blueprint $table) {
            $table->timestamp('verified_at')->nullable()->after('paid_at');
        });

        // Before this column, a payment row could only be created by the BAC recording a paid receipt.
        // Carry that verified state forward using the original record timestamp; receipt and amount fields stay untouched.
        DB::table('bidding_fee_payments')->whereNull('verified_at')->whereNotNull('created_at')
            ->update(['verified_at' => DB::raw('created_at')]);
    }

    public function down(): void
    {
        Schema::table('bidding_fee_payments', function (Blueprint $table) {
            $table->dropColumn('verified_at');
        });
    }
};