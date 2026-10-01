<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * The award record is handed off to Awards & Contracts when the HoPE approves
 * the award, before the Notice of Award: record who approved it and when. A
 * cancellation (before contract signing) is kept on the record with its
 * reason and authority instead of deleting or replacing it.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('awards', function (Blueprint $table) {
            $table->timestamp('award_approved_at')->nullable()->after('notice_of_award_date');
            $table->foreignId('award_approved_by')->nullable()->after('award_approved_at')->constrained('users')->nullOnDelete();
            $table->timestamp('cancelled_at')->nullable()->after('certificate_revoked_by');
            $table->foreignId('cancelled_by')->nullable()->after('cancelled_at')->constrained('users')->nullOnDelete();
            $table->string('cancellation_reference')->nullable()->after('cancelled_by');
            $table->text('cancellation_reason')->nullable()->after('cancellation_reference');
        });
    }

    public function down(): void
    {
        Schema::table('awards', function (Blueprint $table) {
            $table->dropConstrainedForeignId('award_approved_by');
            $table->dropConstrainedForeignId('cancelled_by');
            $table->dropColumn(['award_approved_at', 'cancelled_at', 'cancellation_reference', 'cancellation_reason']);
        });
    }
};
