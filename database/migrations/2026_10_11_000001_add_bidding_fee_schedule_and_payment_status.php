<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Bidding documents fee from the ABC schedule (GPPB Circular No. 02-2026,
 * Sec. 5.2): how the fee was set, why it is lower or waived, amendments after
 * publication, and a payment status so only BAC-verified payments count.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('projects', function (Blueprint $table) {
            // schedule | reduced | waived; null = entered before the schedule, or not competitive bidding.
            $table->string('bidding_fee_mode', 20)->nullable()->after('bidding_documents_fee');
            $table->text('bidding_fee_reason')->nullable()->after('bidding_fee_mode');
        });

        Schema::table('bidding_fee_payments', function (Blueprint $table) {
            // Recorded payments were always entered and verified by the BAC.
            $table->string('status', 20)->default('verified')->after('or_number');
        });

        Schema::create('bidding_fee_amendments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('project_id')->constrained()->cascadeOnDelete();
            $table->decimal('previous_fee', 15, 2)->nullable();
            $table->string('previous_mode', 20)->nullable();
            $table->decimal('new_fee', 15, 2)->nullable();
            $table->string('new_mode', 20)->nullable();
            $table->string('reference');
            $table->text('reason');
            $table->foreignId('amended_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('bidding_fee_amendments');
        Schema::table('bidding_fee_payments', fn (Blueprint $table) => $table->dropColumn('status'));
        Schema::table('projects', fn (Blueprint $table) => $table->dropColumn(['bidding_fee_mode', 'bidding_fee_reason']));
    }
};
