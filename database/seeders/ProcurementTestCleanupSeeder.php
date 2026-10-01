<?php

namespace Database\Seeders;

use App\Models\ProcurementRequest;
use App\Models\Project;
use App\Models\User;
use App\Support\Uploads;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;

/**
 * Removes only the records created by ProcurementTestSeeder: projects whose
 * reference starts with TEST-, the PR-2026-T### purchase requests, the TEST
 * accounts on the sanjose-test.invalid domain, and their files.
 */
class ProcurementTestCleanupSeeder extends Seeder
{
    public function run(): void
    {
        $projectIds = Project::withoutGlobalScopes()->where('reference_no', 'like', 'TEST-%')->pluck('id');
        $userIds = User::where('email', 'like', '%@'.ProcurementTestSeeder::EMAIL_DOMAIN)->pluck('id');
        $bidIds = DB::table('bids')->whereIn('project_id', $projectIds)->orWhereIn('user_id', $userIds)->pluck('id');

        DB::transaction(function () use ($projectIds, $userIds, $bidIds) {
            // Rows that point at TEST bids, projects or users, children first.
            foreach (['bid_documents', 'bid_trackings'] as $table) {
                if (Schema::hasTable($table)) DB::table($table)->whereIn('bid_id', $bidIds)->delete();
            }
            foreach (['awards', 'bidding_fee_payments', 'bid_trackings', 'project_proceedings', 'project_documents', 'project_schedules', 'project_requirements', 'assignments'] as $table) {
                if (Schema::hasTable($table) && Schema::hasColumn($table, 'project_id')) DB::table($table)->whereIn('project_id', $projectIds)->delete();
            }
            DB::table('bids')->whereIn('id', $bidIds)->delete();
            if (Schema::hasTable('audit_logs')) {
                DB::table('audit_logs')->where(fn ($q) => $q
                    ->where(fn ($q) => $q->where('auditable_type', Project::class)->whereIn('auditable_id', $projectIds))
                    ->orWhere(fn ($q) => $q->where('auditable_type', \App\Models\Bid::class)->whereIn('auditable_id', $bidIds)))->delete();
            }
            if (Schema::hasTable('user_notifications')) DB::table('user_notifications')->whereIn('user_id', $userIds)->delete();
            DB::table('projects')->whereIn('id', $projectIds)->delete();

            $requestIds = ProcurementRequest::where('reference_no', 'like', 'PR-2026-T%')->pluck('id');
            DB::table('projects')->whereIn('procurement_request_id', $requestIds)->update(['procurement_request_id' => null]);
            if (Schema::hasTable('procurement_request_documents')) DB::table('procurement_request_documents')->whereIn('procurement_request_id', $requestIds)->delete();
            DB::table('procurement_requests')->whereIn('id', $requestIds)->delete();

            DB::table('bidders')->whereIn('user_id', $userIds)->delete();
            DB::table('users')->whereIn('id', $userIds)->delete();
        });

        Storage::disk(Uploads::diskName())->deleteDirectory(ProcurementTestSeeder::FILE_DIR);
        $this->command?->info("Removed {$projectIds->count()} TEST projects, {$bidIds->count()} bids and {$userIds->count()} TEST accounts.");
    }
}
