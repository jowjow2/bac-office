<?php

namespace App\Support;

use App\Models\ProcurementRequest;
use App\Models\User;
use App\Models\Project;
use Carbon\CarbonInterface;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;

/**
 * The BAC's procurement register: every purchase request and project, the
 * pipeline stage it is in, what it waits for, and the dates coming up.
 * Derived from ProcurementTimeline, so the dashboard and the detail page
 * always agree.
 *
 * Staff see only the projects assigned to them (plus the shared request
 * queue); end-user offices see only their own requests.
 */
class ProcurementPipeline
{
    /** Pipeline buckets, in order. */
    public const BUCKETS = [
        'request' => ['label' => 'PR & funds check', 'hint' => 'Purchase requests awaiting PPMP/APP and funds review'],
        'preparation' => ['label' => 'Preparation', 'hint' => 'Bidding documents or RFQ, ABC and schedule'],
        'posted' => ['label' => 'ITB / RFQ posted', 'hint' => 'Posted and accepting bids or quotations'],
        'evaluation' => ['label' => 'Opening & evaluation', 'hint' => 'Opening, evaluation and post-qualification'],
        'award' => ['label' => 'Resolution & award', 'hint' => 'BAC resolution, HoPE approval and Notice of Award'],
        'contract' => ['label' => 'Contract & NTP', 'hint' => 'Performance security, contract or PO, Notice to Proceed'],
        'delivery' => ['label' => 'Delivery & IAR', 'hint' => 'Delivery or implementation, inspection and acceptance'],
    ];

    public const STATES = [
        'active' => 'In progress',
        'completed' => 'Completed',
        'failed' => 'Failed / stopped',
        'all' => 'All records',
    ];

    private const STAGE_BUCKETS = [
        'request' => 'request',
        'review' => 'request',
        'preparation' => 'preparation',
        'posting' => 'posted',
        'publication' => 'posted',
        'philgeps_posting' => 'posted',
        'prebid' => 'posted',
        'rfq_sent' => 'posted',
        'submission' => 'posted',
        'opening' => 'evaluation',
        'evaluation' => 'evaluation',
        'recommendation' => 'award',
        'award' => 'award',
        'contract' => 'contract',
        'ntp' => 'contract',
        'acceptance' => 'delivery',
    ];

    private ?Collection $rows = null;

    /**
     * @param  'admin'|'staff'|'end_user'  $role
     * @param  array<int, int>|null  $projectIds  staff scope
     */
    public function __construct(
        private readonly string $role = 'admin',
        private readonly ?array $projectIds = null,
        private readonly ?User $endUser = null,
    ) {}

    public static function forAdmin(): self
    {
        return new self('admin');
    }

    public static function forStaff(iterable $projectIds): self
    {
        return new self('staff', collect($projectIds)->map(fn ($id) => (int) $id)->values()->all());
    }

    /** An end-user account's own requests and the projects made from them (EndUserAccess). */
    public static function forEndUser(User $user): self
    {
        return new self('end_user', null, $user);
    }

    /**
     * Every record with its stage, bucket and status.
     *
     * @return Collection<int, array<string, mixed>>
     */
    public function rows(): Collection
    {
        if ($this->rows !== null) {
            return $this->rows;
        }

        $projects = Project::query()
            ->whereNull('archived_at')
            ->when($this->projectIds !== null, fn ($query) => $query->whereIn('id', $this->projectIds))
            ->when($this->role === 'end_user', fn ($query) => EndUserAccess::scopeProjects($query, $this->endUser))
            ->with(['schedule', 'procurementRequest', 'proceedings', 'bids.user', 'awards.bid.user', 'documents', 'rebidProject'])
            ->get();

        $rows = $projects->map(fn (Project $project) => $this->projectRow($project));

        $requests = ProcurementRequest::query()
            ->whereDoesntHave('project')
            ->when($this->role === 'end_user',
                fn ($query) => EndUserAccess::scopeRequests($query, $this->endUser),
                fn ($query) => $query->whereIn('status', [ProcurementRequest::STATUS_SUBMITTED, ProcurementRequest::STATUS_FORWARDED]))
            ->get();

        $rows = $rows->concat($requests->map(fn (ProcurementRequest $request) => $this->requestRow($request)));

        return $this->rows = $rows
            ->sortBy(fn (array $row) => [$row['state'] === 'active' ? 0 : 1, $row['next_date']?->getTimestamp() ?? PHP_INT_MAX, -($row['updated_at']?->getTimestamp() ?? 0)])
            ->values();
    }

    /** Shortcuts behind the KPI cards. */
    public const FLAGS = [
        'posting' => 'External PhilGEPS posting not recorded',
        'week' => 'Deadlines in the next 7 days',
        'late' => 'Past the IRR award period',
    ];

    /**
     * The filters of the register, read from the query string.
     *
     * @return array{q: string, stage: ?string, mode: ?string, state: string, flag: ?string}
     */
    public static function filtersFrom(Request $request): array
    {
        $stage = $request->query('stage');
        $mode = $request->query('mode');
        $state = $request->query('state');
        $flag = $request->query('flag');

        return [
            'q' => mb_substr(trim((string) $request->query('q', '')), 0, 100),
            'stage' => is_string($stage) && array_key_exists($stage, self::BUCKETS) ? $stage : null,
            'mode' => is_string($mode) && array_key_exists($mode, self::modeOptions()) ? $mode : null,
            'state' => is_string($state) && array_key_exists($state, self::STATES) ? $state : 'active',
            'flag' => is_string($flag) && array_key_exists($flag, self::FLAGS) ? $flag : null,
        ];
    }

    /**
     * Rows narrowed by the register filters: q, stage (bucket), mode, state, flag.
     */
    public function filtered(array $filters): Collection
    {
        $query = trim((string) ($filters['q'] ?? ''));
        $bucket = $filters['stage'] ?? null;
        $mode = $filters['mode'] ?? null;
        $state = $filters['state'] ?? 'active';
        $flag = $filters['flag'] ?? null;
        $weekEnd = now()->addDays(7);

        return $this->rows()
            ->when($flag === 'posting', fn (Collection $rows) => $rows->where('awaiting_posting', true))
            ->when($flag === 'late', fn (Collection $rows) => $rows->where('past_award_period', true))
            ->when($flag === 'week', fn (Collection $rows) => $rows->filter(fn (array $row) => $row['type'] === 'project' && $row['deadline'] !== null && $row['deadline']->between(now()->startOfDay(), $weekEnd)))
            ->when($state !== 'all', fn (Collection $rows) => $rows->where('state', array_key_exists($state, self::STATES) ? $state : 'active'))
            ->when(filled($bucket) && array_key_exists($bucket, self::BUCKETS), fn (Collection $rows) => $rows->where('bucket', $bucket))
            ->when(filled($mode), fn (Collection $rows) => $rows->filter(fn (array $row) => $mode === 'alternative'
                ? $row['mode_family'] !== null && $row['mode_family'] !== ProcurementMode::FAMILY_COMPETITIVE
                : $row['mode_key'] === $mode || $row['mode_family'] === $mode))
            ->when($query !== '', function (Collection $rows) use ($query) {
                $needle = mb_strtolower($query);

                return $rows->filter(fn (array $row) => str_contains($row['search'], $needle));
            })
            ->values();
    }

    public function paginate(array $filters, int $perPage = 12, ?string $path = null): LengthAwarePaginator
    {
        $rows = $this->filtered($filters);
        $page = max(1, (int) request()->query('page', 1));

        return new LengthAwarePaginator(
            $rows->forPage($page, $perPage)->values(),
            $rows->count(),
            $perPage,
            $page,
            ['path' => $path ?? request()->url(), 'query' => array_filter($filters, fn ($value) => filled($value))],
        );
    }

    /**
     * Active records per bucket, narrowed by the register's mode and search so each
     * count equals what its stage link shows (the link keeps mode and q, not state/flag).
     *
     * @return array<string, int> bucket => active records
     */
    public function bucketCounts(array $filters = []): array
    {
        $active = $this->filtered([
            'q' => $filters['q'] ?? '',
            'mode' => $filters['mode'] ?? null,
            'state' => 'active',
        ]);

        return collect(self::BUCKETS)->map(fn (array $bucket, string $key) => $active->where('bucket', $key)->count())->all();
    }

    /** @return array<string, array<string, mixed>> */
    public function kpis(): array
    {
        $rows = $this->rows();
        $active = $rows->where('state', 'active')->where('type', 'project');
        $weekEnd = now()->addDays(7);

        return [
            'open' => [
                'count' => $active->count(),
                'abc' => (float) $active->sum('abc'),
            ],
            'awaiting_posting' => [
                'count' => $active->where('awaiting_posting', true)->count(),
            ],
            'openings_week' => [
                'count' => $active->filter(fn (array $row) => $row['deadline'] !== null && $row['deadline']->between(now()->startOfDay(), $weekEnd))->count(),
            ],
            'past_period' => [
                'count' => $active->where('past_award_period', true)->count(),
            ],
            'requests' => [
                'count' => $rows->where('type', 'request')->where('bucket', 'request')->count(),
            ],
        ];
    }

    /**
     * Scheduled activities in the coming days, soonest first.
     *
     * @return Collection<int, array{at: CarbonInterface, title: string, reference: string, detail: ?string, url: ?string, time: bool}>
     */
    public function upcoming(int $days = 14, int $limit = 8): Collection
    {
        $from = now();
        $until = now()->addDays($days)->endOfDay();
        $events = collect();

        foreach ($this->rows()->where('type', 'project')->where('state', 'active') as $row) {
            /** @var Project $project */
            $project = $row['model'];
            $mode = $project->mode();
            $schedule = $project->schedule;

            $candidates = [
                ['Pre-bid conference', $schedule?->pre_bid_conference_date, true],
                ['Deadline for clarifications', $schedule?->clarification_deadline, true],
                [$mode->deadlineLabel(), $project->bidSubmissionDeadline(), true],
                [$mode->openingLabel(), $project->bids_opened_at ? null : $schedule?->bid_opening_date, true],
                ['Award due ('.($mode->isRa12009() ? '60-day' : '3-month').' limit)', $row['award_due'], false],
            ];

            foreach ($candidates as [$title, $at, $time]) {
                if ($at instanceof CarbonInterface && $at->between($from, $until)) {
                    $events->push([
                        'at' => $at,
                        'title' => $title,
                        'reference' => $row['reference'],
                        'detail' => $row['title'],
                        'url' => $row['url'],
                        'time' => $time,
                    ]);
                }
            }
        }

        return $events->sortBy(fn (array $event) => $event['at']->getTimestamp())->take($limit)->values();
    }

    /** @return array<string, string> mode filter options */
    public static function modeOptions(): array
    {
        return [
            ProcurementMode::FAMILY_COMPETITIVE => 'Competitive bidding',
            'alternative' => 'All alternative modes',
            ProcurementMode::FAMILY_RFQ => 'RFQ (SVP / Shopping)',
            ProcurementMode::FAMILY_NEGOTIATED => 'Negotiated procurement',
            ProcurementMode::FAMILY_DIRECT => 'Direct contracting',
        ];
    }

    private function projectRow(Project $project): array
    {
        $timeline = ProcurementTimeline::forProject($project);
        $mode = $project->mode();
        $current = $timeline->current();
        $deadline = $project->bidSubmissionDeadline();
        $bids = $timeline->officialBids();
        $failed = $project->isFailedBidding();
        $completed = $project->isCompleted();
        $pastPeriod = $timeline->isPastAwardPeriod();

        $state = match (true) {
            $completed => 'completed',
            $failed => 'failed',
            $current === null && $project->status === 'awarded' => 'completed',
            default => 'active',
        };

        $bucket = $current ? (self::STAGE_BUCKETS[$current['key']] ?? 'preparation') : ($state === 'active' ? 'delivery' : null);
        // A deadline that has passed moves the record from "posted" to opening and evaluation.
        if ($bucket === 'posted' && $current['key'] === 'submission' && $deadline?->isPast()) {
            $bucket = 'evaluation';
        }

        $awaitingPosting = $state === 'active'
            && $mode->requiresPosting()
            && $project->philgeps_posted_at === null
            && in_array($bucket, ['preparation', 'posted'], true);

        $reference = $project->reference_no ?: ($project->procurementRequest?->reference_no ?: 'Project #'.$project->id);
        $endUser = $project->end_user_unit ?: $project->procurementRequest?->end_user_office;

        return [
            'type' => 'project',
            'model' => $project,
            'id' => $project->id,
            'reference' => $reference,
            'title' => $project->title,
            'end_user' => $endUser,
            'mode_key' => $mode->key(),
            'mode_family' => $mode->family(),
            'mode_label' => $mode->label(),
            'mode_short' => $mode->shortLabel(),
            'legal_basis' => $mode->legalBasisShort(),
            'abc' => (float) $project->budget,
            'bucket' => $state === 'active' ? $bucket : null,
            'state' => $state,
            'stage_label' => match ($state) {
                'completed' => 'Completed',
                'failed' => $mode->isCompetitive() ? 'Failed bidding' : 'Failed procurement',
                default => $current['label'] ?? 'In progress',
            },
            'office' => $current['office'] ?? null,
            'status' => $this->projectStatus($project, $mode, $state, $bucket, $current, $deadline, $bids, $pastPeriod, $awaitingPosting),
            'deadline' => $deadline,
            'next_date' => $this->nextDate($project, $deadline, $timeline->awardDueDate()),
            'award_due' => $timeline->awardDueDate(),
            'past_award_period' => $pastPeriod,
            'awaiting_posting' => $awaitingPosting,
            'progress' => $timeline->progressPercent(),
            'updated_at' => $project->updated_at,
            'url' => $this->projectUrl($project),
            'search' => mb_strtolower(implode(' ', array_filter([
                $reference, $project->title, $endUser, $project->philgeps_reference_no,
                $project->procurementRequest?->reference_no, $mode->label(), $mode->shortLabel(),
            ]))),
        ];
    }

    private function requestRow(ProcurementRequest $request): array
    {
        $bucket = match ($request->status) {
            ProcurementRequest::STATUS_SUBMITTED => 'request',
            ProcurementRequest::STATUS_FORWARDED => 'preparation',
            default => 'request',
        };

        $state = match ($request->status) {
            ProcurementRequest::STATUS_REJECTED => 'failed',
            default => 'active',
        };

        return [
            'type' => 'request',
            'model' => $request,
            'id' => $request->id,
            'reference' => $request->reference_no,
            'title' => $request->title,
            'end_user' => $request->end_user_office,
            'mode_key' => null,
            'mode_family' => null,
            'mode_label' => 'Mode set by the BAC',
            'mode_short' => '—',
            'legal_basis' => null,
            'abc' => (float) $request->estimated_cost,
            'bucket' => $state === 'active' ? $bucket : null,
            'state' => $state,
            'stage_label' => match ($request->status) {
                ProcurementRequest::STATUS_FORWARDED => 'Forwarded to the BAC',
                ProcurementRequest::STATUS_SUBMITTED => 'PPMP/APP & funds check',
                default => $request->statusLabel(),
            },
            'office' => match ($request->status) {
                ProcurementRequest::STATUS_SUBMITTED => 'Budget / Procurement Office',
                ProcurementRequest::STATUS_FORWARDED => 'BAC Secretariat',
                default => 'End-user office',
            },
            'status' => ['label' => $request->statusLabel(), 'tone' => $request->statusTone()],
            'deadline' => null,
            'next_date' => null,
            'award_due' => null,
            'past_award_period' => false,
            'awaiting_posting' => false,
            'progress' => $request->status === ProcurementRequest::STATUS_FORWARDED ? 12 : ($request->status === ProcurementRequest::STATUS_SUBMITTED ? 6 : 0),
            'updated_at' => $request->updated_at,
            'url' => match ($this->role) {
                'end_user' => route('end-user.requests.show', $request),
                'staff' => route('staff.requests', ['q' => $request->reference_no]),
                default => route('admin.requests', ['q' => $request->reference_no]),
            },
            'search' => mb_strtolower(implode(' ', array_filter([$request->reference_no, $request->title, $request->end_user_office]))),
        ];
    }

    /**
     * The one-line status shown in the register: what the record waits for.
     *
     * @return array{label: string, tone: string}
     */
    private function projectStatus(Project $project, ProcurementMode $mode, string $state, ?string $bucket, ?array $current, ?CarbonInterface $deadline, Collection $bids, bool $pastPeriod, bool $awaitingPosting): array
    {
        if ($state === 'completed') {
            return ['label' => 'Accepted '.$project->completed_at?->format('M d, Y'), 'tone' => 'success'];
        }

        if ($state === 'failed') {
            return ['label' => $project->rebidProject ? 'Rebid posted' : 'Review before rebid', 'tone' => 'danger'];
        }

        if ($pastPeriod) {
            return ['label' => 'Past award period', 'tone' => 'danger'];
        }

        $noun = $mode->submissionNoun();
        $today = now()->startOfDay();

        return match (true) {
            $bucket === 'preparation' && in_array($project->status, ['draft', 'approved_for_bidding'], true) => ['label' => $project->status === 'draft' ? 'Draft' : 'Approved for posting', 'tone' => 'neutral'],
            $awaitingPosting => ['label' => 'External PhilGEPS posting not recorded', 'tone' => 'warning'],
            $bucket === 'posted' && $deadline !== null && $deadline->isFuture() => (function () use ($deadline, $today, $noun) {
                $days = (int) $today->diffInDays($deadline->copy()->startOfDay());

                return [
                    'label' => ucfirst(str($noun)->plural()).($days === 0 ? ' due today' : ' due in '.$days.' '.str('day')->plural($days)),
                    'tone' => $days <= 2 ? 'warning' : 'info',
                ];
            })(),
            $bucket === 'evaluation' && $bids->isEmpty() => ['label' => 'No '.$noun.' received', 'tone' => 'danger'],
            $bucket === 'evaluation' && $mode->isAlternative() && $bids->count() === 1 => ['label' => 'Only 1 '.$noun.' received', 'tone' => 'warning'],
            $bucket === 'evaluation' && ! $project->bidsAreOpened() => ['label' => $mode->openingLabel().' pending', 'tone' => 'info'],
            $bucket === 'evaluation' => ['label' => $bids->count().' '.str($noun)->plural($bids->count()).' under evaluation', 'tone' => 'info'],
            $bucket === 'award' && ($current['key'] ?? null) === 'award' => ['label' => 'Awaiting HoPE approval', 'tone' => 'warning'],
            default => ['label' => 'With '.($current['office'] ?? 'the BAC'), 'tone' => 'neutral'],
        };
    }

    private function nextDate(Project $project, ?CarbonInterface $deadline, ?CarbonInterface $awardDue): ?CarbonInterface
    {
        $schedule = $project->schedule;

        return collect([
            $schedule?->pre_bid_conference_date,
            $deadline,
            $project->bids_opened_at ? null : $schedule?->bid_opening_date,
            $awardDue,
        ])
            ->filter(fn ($date) => $date instanceof CarbonInterface && $date->isFuture())
            ->sortBy(fn (CarbonInterface $date) => $date->getTimestamp())
            ->first();
    }

    private function projectUrl(Project $project): ?string
    {
        return match ($this->role) {
            'staff' => route('staff.procurement.show', $project),
            'end_user' => $project->procurementRequest ? route('end-user.requests.show', $project->procurementRequest) : null,
            default => route('admin.procurement.show', $project),
        };
    }
}
