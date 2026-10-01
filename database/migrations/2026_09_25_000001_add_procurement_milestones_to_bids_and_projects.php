<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Adds the RA 9184 milestones that the bidding tracker needs but the bids
 * table never recorded (evaluation result, post-qualification, BAC
 * recommendation, HOPE decision, contract signing) plus project-level failed
 * bidding. Existing rows are not rewritten: legacy statuses are mapped when
 * read (see App\Support\BidProgress), so no history is lost.
 */
return new class extends Migration
{
    private const LEGACY_WORKFLOW_STEPS = [
        'submitted',
        'pending_validation',
        'documents_validated',
        'for_bac_evaluation',
        'approved',
        'disqualified',
        'awarded',
        'not_awarded',
        'notice_of_award',
        'notice_to_proceed',
        'project_completed',
    ];

    public function up(): void
    {
        // The enum cannot hold the new milestone steps; widen it to a string
        // while keeping every existing value intact.
        Schema::table('bids', function (Blueprint $table) {
            $table->string('workflow_step', 40)->default('submitted')->change();
        });

        Schema::table('bids', function (Blueprint $table) {
            $table->timestamp('evaluated_at')->nullable()->after('bac_evaluation_by');
            $table->foreignId('evaluated_by')->nullable()->after('evaluated_at')->constrained('users')->nullOnDelete();

            $table->timestamp('post_qualification_at')->nullable()->after('evaluated_by');
            $table->foreignId('post_qualification_by')->nullable()->after('post_qualification_at')->constrained('users')->nullOnDelete();
            $table->string('post_qualification_result', 20)->nullable()->after('post_qualification_by');
            $table->timestamp('post_qualification_completed_at')->nullable()->after('post_qualification_result');

            $table->timestamp('bac_recommended_at')->nullable()->after('post_qualification_completed_at');
            $table->foreignId('bac_recommended_by')->nullable()->after('bac_recommended_at')->constrained('users')->nullOnDelete();

            $table->string('award_decision', 20)->nullable()->after('bac_recommended_by');
            $table->timestamp('award_decision_at')->nullable()->after('award_decision');
            $table->foreignId('award_decision_by')->nullable()->after('award_decision_at')->constrained('users')->nullOnDelete();

            $table->date('performance_security_at')->nullable()->after('notice_of_award_by');
            $table->timestamp('contract_signed_at')->nullable()->after('performance_security_at');
            $table->foreignId('contract_signed_by')->nullable()->after('contract_signed_at')->constrained('users')->nullOnDelete();

            $table->string('disqualified_stage', 40)->nullable()->after('disqualified_by');
        });

        Schema::table('projects', function (Blueprint $table) {
            $table->timestamp('failed_bidding_at')->nullable()->after('status');
            $table->foreignId('failed_bidding_by')->nullable()->after('failed_bidding_at')->constrained('users')->nullOnDelete();
            $table->text('failed_bidding_reason')->nullable()->after('failed_bidding_by');
            $table->foreignId('rebid_project_id')->nullable()->after('failed_bidding_reason')->constrained('projects')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('projects', function (Blueprint $table) {
            $table->dropConstrainedForeignId('rebid_project_id');
            $table->dropConstrainedForeignId('failed_bidding_by');
            $table->dropColumn(['failed_bidding_at', 'failed_bidding_reason']);
        });

        Schema::table('bids', function (Blueprint $table) {
            $table->dropConstrainedForeignId('evaluated_by');
            $table->dropConstrainedForeignId('post_qualification_by');
            $table->dropConstrainedForeignId('bac_recommended_by');
            $table->dropConstrainedForeignId('award_decision_by');
            $table->dropConstrainedForeignId('contract_signed_by');
            $table->dropColumn([
                'evaluated_at',
                'post_qualification_at',
                'post_qualification_result',
                'post_qualification_completed_at',
                'bac_recommended_at',
                'award_decision',
                'award_decision_at',
                'performance_security_at',
                'contract_signed_at',
                'disqualified_stage',
            ]);
        });

        // Fold the new steps back onto the closest legacy value before the
        // column becomes an enum again.
        DB::table('bids')->whereIn('workflow_step', ['evaluated', 'post_qualification', 'post_qualified'])
            ->update(['workflow_step' => 'for_bac_evaluation']);
        DB::table('bids')->where('workflow_step', 'recommended')->update(['workflow_step' => 'approved']);
        DB::table('bids')->where('workflow_step', 'contract_signed')->update(['workflow_step' => 'notice_of_award']);

        Schema::table('bids', function (Blueprint $table) {
            $table->enum('workflow_step', self::LEGACY_WORKFLOW_STEPS)->default('submitted')->change();
        });
    }
};
