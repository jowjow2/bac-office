<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Official bid submission per the project's notice.
 *
 * projects: how bids must be submitted. Defaults to manual submission through
 *   the BAC Secretariat; electronic submission counts only when the LGU
 *   authority for it is recorded.
 * bids: whether and when a bid was officially submitted (electronic receipt or
 *   BAC-recorded manual receipt), and its receipt number.
 * bid_documents: one file per checklist requirement, kept by component
 *   (technical / financial) so the envelopes stay separate.
 *
 * Existing bids were accepted as submitted through the website, so they keep
 * that status: submitted_at is set to their creation time (no receipt number
 * is invented) and submission_channel to "website_legacy".
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('projects', function (Blueprint $table) {
            $table->string('philgeps_reference_no', 100)->nullable()->after('reference_no');
            $table->string('submission_mode', 20)->default('manual')->after('procurement_mode');
            $table->string('submission_venue')->nullable()->after('submission_mode');
            $table->string('electronic_submission_authority')->nullable()->after('submission_venue');
            $table->timestamp('electronic_submission_authorized_at')->nullable()->after('electronic_submission_authority');
            $table->foreignId('electronic_submission_authorized_by')->nullable()->after('electronic_submission_authorized_at')->constrained('users')->nullOnDelete();
        });

        Schema::table('bids', function (Blueprint $table) {
            $table->string('submission_channel', 20)->nullable()->after('status');
            $table->timestamp('submitted_at')->nullable()->after('submission_channel');
            $table->string('receipt_no', 60)->nullable()->unique()->after('submitted_at');
            $table->foreignId('submission_received_by')->nullable()->after('receipt_no')->constrained('users')->nullOnDelete();
        });

        Schema::create('bid_documents', function (Blueprint $table) {
            $table->id();
            $table->foreignId('bid_id')->constrained('bids')->cascadeOnDelete();
            $table->string('requirement_key', 100);
            $table->string('component', 20);
            $table->string('label');
            $table->string('file_path');
            $table->string('original_name');
            $table->unsignedBigInteger('size')->nullable();
            $table->string('sha256', 64)->nullable();
            $table->timestamps();

            $table->unique(['bid_id', 'requirement_key']);
        });

        DB::table('bids')->whereNull('submitted_at')->update([
            'submitted_at' => DB::raw('created_at'),
            'submission_channel' => 'website_legacy',
        ]);
    }

    public function down(): void
    {
        Schema::dropIfExists('bid_documents');

        Schema::table('bids', function (Blueprint $table) {
            $table->dropConstrainedForeignId('submission_received_by');
            $table->dropUnique(['receipt_no']);
            $table->dropColumn(['submission_channel', 'submitted_at', 'receipt_no']);
        });

        Schema::table('projects', function (Blueprint $table) {
            $table->dropConstrainedForeignId('electronic_submission_authorized_by');
            $table->dropColumn([
                'philgeps_reference_no',
                'submission_mode',
                'submission_venue',
                'electronic_submission_authority',
                'electronic_submission_authorized_at',
            ]);
        });
    }
};
