<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Online-only bid submission with the bidding documents fee paid at the BAC.
 *
 * projects: the bidding documents fee (null / 0 = free) and where it is paid.
 * bidding_fee_payments: one Official Receipt per bidder per project, recorded
 *   by the BAC when the bidder pays over the counter. A bidder can submit an
 *   online bid only after this payment is recorded.
 *
 * Every existing project switches to online submission.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('projects', function (Blueprint $table) {
            $table->decimal('bidding_documents_fee', 15, 2)->nullable()->after('submission_venue');
            $table->string('payment_venue')->nullable()->after('bidding_documents_fee');
        });

        Schema::create('bidding_fee_payments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('project_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->decimal('amount', 15, 2);
            $table->string('or_number', 60)->unique();
            $table->date('paid_at');
            $table->foreignId('recorded_by')->nullable()->constrained('users')->nullOnDelete();
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->unique(['project_id', 'user_id']);
        });

        DB::table('projects')
            ->whereNull('payment_venue')
            ->whereNotNull('submission_venue')
            ->update(['payment_venue' => DB::raw('submission_venue')]);

        DB::table('projects')->update(['submission_mode' => 'electronic']);
    }

    public function down(): void
    {
        Schema::dropIfExists('bidding_fee_payments');

        Schema::table('projects', function (Blueprint $table) {
            $table->dropColumn(['bidding_documents_fee', 'payment_venue']);
        });
    }
};
