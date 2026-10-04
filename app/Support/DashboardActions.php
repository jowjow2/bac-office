<?php

namespace App\Support;

use App\Models\Award;
use App\Models\Bid;
use App\Models\ProcurementRequest;
use App\Models\User;
use Illuminate\Support\Collection;

/**
 * "Needs your action" on the BAC and Secretariat overview: the work waiting
 * for the viewer, each with a count, what it means and where to do it.
 * Items with nothing waiting are left out.
 */
class DashboardActions
{
    /**
     * @param  array{awaiting_posting: array{count: int}, past_period: array{count: int}, openings_week: array{count: int}}  $kpis
     * @param  list<int>|null  $projectIds  the staff member's assigned projects; null for the BAC admin
     * @return list<array{key: string, count: int, label: string, hint: string, url: string, tone: string, icon: string}>
     */
    public static function for(string $role, array $kpis, ?array $projectIds = null): array
    {
        $isAdmin = $role === 'admin';
        $prefix = $isAdmin ? 'admin' : 'staff';
        $dashboard = $isAdmin ? 'admin.dashboard' : 'staff.dashboard';

        $items = [];
        $add = function (string $key, int $count, string $label, string $hint, string $url, string $tone, string $icon) use (&$items) {
            if ($count > 0) {
                $items[] = compact('key', 'count', 'label', 'hint', 'url', 'tone', 'icon');
            }
        };

        // Purchase requests: the Secretariat reviews them, the BAC turns them into projects.
        $requests = ProcurementRequest::query()
            ->whereIn('status', [ProcurementRequest::STATUS_SUBMITTED, ProcurementRequest::STATUS_FORWARDED])
            ->selectRaw('status, count(*) as total')->groupBy('status')->pluck('total', 'status');
        $add('requests_review', (int) ($requests[ProcurementRequest::STATUS_SUBMITTED] ?? 0),
            'Purchase requests to review', 'Check the PPMP/APP and budget, then forward to the BAC or return.',
            route($prefix.'.requests', ['tab' => 'review']), 'info', 'fa-file-circle-check');
        if ($isAdmin) {
            $add('requests_bac', (int) ($requests[ProcurementRequest::STATUS_FORWARDED] ?? 0),
                'Purchase requests to start', 'Forwarded to the BAC. Create the procurement project.',
                route('admin.requests', ['tab' => 'bac']), 'action', 'fa-folder-plus');
            $add('registrations', User::where('role', 'bidder')->where('status', 'pending')->count(),
                'Bidder registrations', 'Review the documents, then approve or ask for corrections.',
                route('admin.users', ['filter' => 'pending']), 'info', 'fa-user-check');
        }

        $bids = self::bidsInScope($projectIds);
        $add('bids_ready', $bids->filter(fn (Bid $bid) => ! $bid->isSealed() && $bid->progress()->adminStage() === BidProgress::STAGE_PRELIMINARY)->count(),
            $isAdmin ? 'Bids to examine' : 'Bids to check', $isAdmin ? 'Opened bids waiting for the pass/fail check of documents.' : 'Opened bids on your projects: check the submitted documents.',
            route($isAdmin ? 'admin.bids' : 'staff.review-bids', ['status' => BidProgress::STAGE_PRELIMINARY]), 'action', 'fa-magnifying-glass');

        // After the award: the Notice to Proceed, then the contract terms.
        $awards = Award::query()->whereNull('cancelled_at')
            ->when($projectIds !== null, fn ($query) => $query->whereIn('project_id', $projectIds))
            ->with(['bid', 'contractImplementation', 'project'])->get();
        if ($isAdmin) {
            $add('ntp', $awards->filter(fn (Award $award) => $award->bid?->contract_signed_at !== null && $award->bid->notice_to_proceed_at === null)->count(),
                'Notices to Proceed to issue', 'The contract is signed. Issue the NTP with its signed PDF.',
                route('admin.awards.index'), 'action', 'fa-file-signature');
        }
        $add('terms', $awards->filter(fn (Award $award) => $award->bid?->notice_to_proceed_at !== null
                && in_array(strtolower((string) $award->project?->category), ['goods', 'infrastructure'], true)
                && ! $award->contractImplementation?->isConfigured())->count(),
            'Contract terms to record', 'Copy the deadline, items and warranty from the signed contract.',
            route($isAdmin ? 'admin.awards.index' : 'staff.assign-projects'), 'action', 'fa-truck-ramp-box');

        // Compliance flags from the register.
        $add('posting', (int) ($kpis['awaiting_posting']['count'] ?? 0),
            'PhilGEPS posting not recorded', 'Record the ITB or RFQ posting date.', route($dashboard, ['flag' => 'posting']), 'warning', 'fa-bullhorn');
        $add('late', (int) ($kpis['past_period']['count'] ?? 0),
            'Past the IRR award period', 'Bid opening to award is over the limit. Needs BAC action.', route($dashboard, ['flag' => 'late']), 'danger', 'fa-triangle-exclamation');
        $add('week', (int) ($kpis['openings_week']['count'] ?? 0),
            'Deadlines this week', 'Bid and quotation deadlines in the next 7 days.', route($dashboard, ['flag' => 'week']), 'info', 'fa-calendar-day');

        return $items;
    }

    /** @return Collection<int, Bid> */
    private static function bidsInScope(?array $projectIds): Collection
    {
        return Bid::with(['project.awards', 'project.schedule', 'project.requirement', 'award', 'documents'])
            ->whereNotNull('submitted_at')
            ->when($projectIds !== null, fn ($query) => $query->whereIn('project_id', $projectIds))
            ->whereHas('project', fn ($query) => $query->whereNull('archived_at')->whereNotIn('status', ['awarded']))
            ->get();
    }
}
