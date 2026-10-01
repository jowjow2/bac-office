<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        if (DB::getDriverName() !== 'pgsql') {
            return;
        }

        // Older PostgreSQL installs recorded the certificate migration without
        // creating these columns. The current application expects them.
        DB::statement('alter table awards add column if not exists certificate_file_path varchar(255)');
        DB::statement('alter table awards add column if not exists certificate_uploaded_at timestamp(0) without time zone');
        DB::statement('alter table awards add column if not exists certificate_revoked_at timestamp(0) without time zone');
        DB::statement('alter table awards add column if not exists certificate_revoked_by bigint');

        // MySQL enum migrations did not update PostgreSQL's original checks.
        DB::statement('alter table bids drop constraint if exists bids_status_check');
        DB::statement("alter table bids add constraint bids_status_check check (status in ('pending', 'approved', 'rejected', 'evaluated', 'awarded'))");
        DB::statement('alter table bids drop constraint if exists bids_workflow_step_check');
        DB::statement("alter table bids add constraint bids_workflow_step_check check (workflow_step in ('submitted', 'pending_validation', 'documents_validated', 'for_bac_evaluation', 'approved', 'disqualified', 'awarded', 'not_awarded', 'notice_of_award', 'notice_to_proceed', 'project_completed', 'evaluated', 'post_qualification', 'post_qualified', 'recommended', 'contract_signed'))");
        DB::statement('alter table awards drop constraint if exists awards_status_check');
        DB::statement("alter table awards add constraint awards_status_check check (status in ('active', 'completed', 'valid', 'revoked', 'expired'))");
        DB::statement('alter table projects drop constraint if exists projects_status_check');
        DB::statement("alter table projects add constraint projects_status_check check (status in ('draft', 'approved_for_bidding', 'open', 'closed', 'awarded'))");
    }

    public function down(): void
    {
        // Preserve the wider statuses and certificate data on rollback.
    }
};
