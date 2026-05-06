<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('bids', function (Blueprint $table) {
            $table->enum('workflow_step', [
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
            ])->default('submitted')->after('eligibility_status');

            $table->timestamp('workflow_step_updated_at')->nullable()->after('workflow_step');
            $table->foreignId('workflow_step_updated_by')->nullable()->after('workflow_step_updated_at')->constrained('users')->nullOnDelete();

            // Timestamps for each major milestone
            $table->timestamp('documents_validated_at')->nullable()->after('workflow_step_updated_by');
            $table->foreignId('documents_validated_by')->nullable()->after('documents_validated_at')->constrained('users')->nullOnDelete();

            $table->timestamp('bac_evaluation_at')->nullable()->after('documents_validated_by');
            $table->foreignId('bac_evaluation_by')->nullable()->after('bac_evaluation_at')->constrained('users')->nullOnDelete();

            $table->timestamp('approved_at')->nullable()->after('bac_evaluation_by');
            $table->foreignId('approved_by')->nullable()->after('approved_at')->constrained('users')->nullOnDelete();

            $table->timestamp('disqualified_at')->nullable()->after('approved_by');
            $table->foreignId('disqualified_by')->nullable()->after('disqualified_at')->constrained('users')->nullOnDelete();

            $table->timestamp('awarded_at')->nullable()->after('disqualified_by');
            $table->foreignId('awarded_by')->nullable()->after('awarded_at')->constrained('users')->nullOnDelete();

            $table->timestamp('notice_of_award_at')->nullable()->after('awarded_by');
            $table->foreignId('notice_of_award_by')->nullable()->after('notice_of_award_at')->constrained('users')->nullOnDelete();

            $table->timestamp('notice_to_proceed_at')->nullable()->after('notice_of_award_by');
            $table->foreignId('notice_to_proceed_by')->nullable()->after('notice_to_proceed_at')->constrained('users')->nullOnDelete();

            $table->timestamp('project_completed_at')->nullable()->after('notice_to_proceed_by');
            $table->foreignId('project_completed_by')->nullable()->after('project_completed_at')->constrained('users')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('bids', function (Blueprint $table) {
            $table->dropConstrainedForeignId('workflow_step_updated_by');
            $table->dropConstrainedForeignId('documents_validated_by');
            $table->dropConstrainedForeignId('bac_evaluation_by');
            $table->dropConstrainedForeignId('approved_by');
            $table->dropConstrainedForeignId('disqualified_by');
            $table->dropConstrainedForeignId('awarded_by');
            $table->dropConstrainedForeignId('notice_of_award_by');
            $table->dropConstrainedForeignId('notice_to_proceed_by');
            $table->dropConstrainedForeignId('project_completed_by');

            $table->dropColumn([
                'workflow_step',
                'workflow_step_updated_at',
                'documents_validated_at',
                'documents_validated_by',
                'bac_evaluation_at',
                'bac_evaluation_by',
                'approved_at',
                'approved_by',
                'disqualified_at',
                'disqualified_by',
                'awarded_at',
                'awarded_by',
                'notice_of_award_at',
                'notice_of_award_by',
                'notice_to_proceed_at',
                'notice_to_proceed_by',
                'project_completed_at',
                'project_completed_by',
            ]);
        });
    }
};
