<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Awards follow the RA 9184 sequence: the award record is created when the
 * Notice of Award is issued (notice_of_award_date), and contract_date is the
 * actual contract signing date, recorded later.
 *
 * - contract_amount widened: decimal(10,2) overflowed above ₱99,999,999.99,
 *   while project ABCs allow up to 13 digits.
 * - contract_date becomes nullable (not yet signed).
 * - Existing awards keep their contract_date; it was the declaration date, so
 *   it is also copied to notice_of_award_date.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('awards', function (Blueprint $table) {
            $table->decimal('contract_amount', 15, 2)->change();
            $table->date('contract_date')->nullable()->change();
            $table->date('notice_of_award_date')->nullable()->after('contract_date');
        });

        DB::table('awards')->whereNull('notice_of_award_date')->update(['notice_of_award_date' => DB::raw('contract_date')]);
    }

    public function down(): void
    {
        DB::table('awards')->whereNull('contract_date')->update(['contract_date' => DB::raw('notice_of_award_date')]);

        Schema::table('awards', function (Blueprint $table) {
            $table->dropColumn('notice_of_award_date');
        });

        Schema::table('awards', function (Blueprint $table) {
            $table->date('contract_date')->nullable(false)->change();
            $table->decimal('contract_amount', 10, 2)->change();
        });
    }
};
