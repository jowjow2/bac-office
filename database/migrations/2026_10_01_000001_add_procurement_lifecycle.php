<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * The procurement lifecycle around a bidding project.
 *
 * procurement_requests: what an end-user office asks to procure (specs/TOR,
 *   quantity, estimated cost, fund source, delivery or contract duration),
 *   reviewed against the PPMP/APP and budget before it is forwarded to the BAC.
 * procurement_request_documents: the TOR / technical specifications and other
 *   attachments of a request.
 * projects: the request it came from, the governing law, the PhilGEPS posting
 *   the BAC Secretariat did by hand (link and date), the bid security
 *   requirement, and when the contract was accepted.
 * project_proceedings: dated BAC records with their documents: pre-bid
 *   conference, clarifications, bid bulletins, resolutions, requests for
 *   reconsideration, inspection and acceptance.
 * bids: the BAC resolution recommending the award.
 * bid_trackings: a supporting document for a recorded decision.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('procurement_requests', function (Blueprint $table) {
            $table->id();
            $table->string('reference_no', 40)->unique();
            $table->string('end_user_office');
            $table->foreignId('requested_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('title');
            $table->string('category', 30)->nullable();
            $table->text('specifications');
            $table->decimal('quantity', 15, 2);
            $table->string('unit', 40);
            $table->decimal('estimated_cost', 15, 2);
            $table->string('fund_source');
            $table->string('delivery_period');
            $table->text('justification')->nullable();
            $table->string('status', 30)->default('draft')->index();
            $table->timestamp('submitted_at')->nullable();
            // PPMP / APP and budget review by authorized staff.
            $table->string('ppmp_reference')->nullable();
            $table->string('app_reference')->nullable();
            $table->boolean('budget_available')->nullable();
            $table->text('review_remarks')->nullable();
            $table->foreignId('reviewed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('reviewed_at')->nullable();
            $table->timestamp('forwarded_at')->nullable();
            $table->timestamps();
        });

        Schema::create('procurement_request_documents', function (Blueprint $table) {
            $table->id();
            $table->foreignId('procurement_request_id')->constrained()->cascadeOnDelete();
            $table->string('document_type', 40);
            $table->string('original_name');
            $table->string('file_path');
            $table->foreignId('uploaded_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });

        Schema::table('projects', function (Blueprint $table) {
            $table->foreignId('procurement_request_id')->nullable()->after('id')->constrained()->nullOnDelete();
            $table->string('legal_basis', 20)->nullable()->after('procurement_mode');
            $table->string('philgeps_url', 500)->nullable()->after('philgeps_reference_no');
            $table->date('philgeps_posted_at')->nullable()->after('philgeps_url');
            $table->foreignId('philgeps_posted_recorded_by')->nullable()->after('philgeps_posted_at')->constrained('users')->nullOnDelete();
            $table->boolean('bid_security_required')->default(false)->after('payment_venue');
            $table->text('bid_security_notes')->nullable()->after('bid_security_required');
            $table->timestamp('completed_at')->nullable()->after('bids_opened_by');
        });

        Schema::create('project_proceedings', function (Blueprint $table) {
            $table->id();
            $table->foreignId('project_id')->constrained()->cascadeOnDelete();
            $table->string('type', 40)->index();
            $table->string('title');
            $table->dateTime('occurred_at');
            $table->string('reference_no', 100)->nullable();
            $table->text('summary')->nullable();
            $table->string('outcome', 40)->nullable();
            $table->string('file_path')->nullable();
            $table->string('original_name')->nullable();
            $table->foreignId('recorded_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });

        Schema::table('bids', function (Blueprint $table) {
            $table->string('bac_resolution_no', 100)->nullable();
            $table->date('bac_resolution_date')->nullable();
        });

        Schema::table('bid_trackings', function (Blueprint $table) {
            $table->string('attachment_path')->nullable();
            $table->string('attachment_name')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('bid_trackings', function (Blueprint $table) {
            $table->dropColumn(['attachment_path', 'attachment_name']);
        });

        Schema::table('bids', function (Blueprint $table) {
            $table->dropColumn(['bac_resolution_no', 'bac_resolution_date']);
        });

        Schema::dropIfExists('project_proceedings');

        Schema::table('projects', function (Blueprint $table) {
            $table->dropConstrainedForeignId('procurement_request_id');
            $table->dropConstrainedForeignId('philgeps_posted_recorded_by');
            $table->dropColumn(['legal_basis', 'philgeps_url', 'philgeps_posted_at', 'bid_security_required', 'bid_security_notes', 'completed_at']);
        });

        Schema::dropIfExists('procurement_request_documents');
        Schema::dropIfExists('procurement_requests');
    }
};
