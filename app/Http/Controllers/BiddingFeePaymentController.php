<?php

namespace App\Http\Controllers;

use App\Models\AuditLog;
use App\Models\Bid;
use App\Models\BiddingFeePayment;
use App\Models\Project;
use App\Models\User;
use App\Support\SystemNotification;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

/**
 * Bidding documents fee payments made over the counter at the BAC office.
 * Admin and staff record the Official Receipt; the bidder can then submit
 * an online bid for that project. Shared by the admin and staff portals.
 */
class BiddingFeePaymentController extends Controller
{
    public function index(Request $request)
    {
        $role = $this->role($request);
        $search = trim((string) $request->query('q', ''));
        $projectFilter = $request->integer('project') ?: null;

        $feeProjects = Project::query()
            ->whereNull('archived_at')
            ->where('bidding_documents_fee', '>', 0)
            ->whereIn('status', Project::PUBLIC_STATUSES)
            ->with('schedule')
            ->withCount('biddingFeePayments')
            ->orderByDesc('created_at')
            ->get();
        $payableProjects = $feeProjects->filter->isOpenForBidding()->values();

        $bidders = User::query()
            ->where('role', 'bidder')
            ->with('bidderProfile.activeSanction')
            ->orderByRaw('COALESCE(company, name)')
            ->get()
            ->filter->isApprovedBidder()
            ->values();

        $payments = $this->filteredPayments($search, $projectFilter)
            ->paginate(15)
            ->withQueryString();

        // Who already paid for each open project, so the form can mark them instead of letting a second payment be typed.
        $paidByProject = BiddingFeePayment::query()
            ->whereIn('project_id', $payableProjects->pluck('id'))
            ->get(['project_id', 'user_id'])
            ->groupBy('project_id')
            ->map(fn ($rows) => $rows->pluck('user_id')->values()->all())
            ->all();

        $submittedKeys = $this->submittedKeys($payments->getCollection());

        $today = now()->toDateString();
        $stats = [
            'payable_projects' => $payableProjects->count(),
            'payments' => BiddingFeePayment::count(),
            'collected' => (float) BiddingFeePayment::sum('amount'),
            'today' => BiddingFeePayment::whereDate('paid_at', $today)->count(),
        ];

        return view($role.'.payments', [
            'routePrefix' => $role,
            'payments' => $payments,
            'payableProjects' => $payableProjects,
            'feeProjects' => $feeProjects,
            'bidders' => $bidders,
            'paidByProject' => $paidByProject,
            'submittedKeys' => $submittedKeys,
            'stats' => $stats,
            'exportFormats' => $this->exportFormats($role),
            'search' => $search,
            'projectFilter' => $projectFilter,
        ]);
    }

    /** Choices for the page's Export dialog: the payment records for a period, plus the printable collection report. */
    private function exportFormats(string $role): array
    {
        $zone = config('bac-office.display_timezone', 'Asia/Manila');
        $today = now($zone)->startOfDay();
        $periods = [
            ['Today', $today, $today],
            ['This month', $today->copy()->startOfMonth(), $today],
            ['This year', $today->copy()->startOfYear(), $today],
            ['All time', null, null],
        ];
        $choices = [];
        foreach ($periods as [$label, $from, $to]) {
            $query = BiddingFeePayment::query();
            if ($from) {
                $query->whereDate('paid_at', '>=', $from->toDateString())->whereDate('paid_at', '<=', $to->toDateString());
            }
            $count = (clone $query)->count();
            $amount = (float) $query->sum('amount');
            $choices[] = [
                'label' => $label,
                'hint' => $count === 0 ? 'No payments recorded' : '₱'.number_format($amount, 2).' collected',
                'count' => $count,
                'icon' => 'fa-calendar-days',
                'url' => route($role.'.payments.export', $from ? ['from' => $from->toDateString(), 'to' => $to->toDateString()] : []),
            ];
        }
        $choices[] = [
            'label' => 'Collection report (print or PDF)',
            'hint' => 'Totals by project and day. Pick any dates there',
            'icon' => 'fa-file-pdf',
            'open' => true,
            'dialog' => 'fee-report-dialog',
            'url' => route($role.'.payments.report'),
        ];

        return $choices;
    }

    /** The payment records as CSV, with the same search and project filter as the page. */
    public function export(Request $request)
    {
        $this->role($request);
        $query = $this->filteredPayments(trim((string) $request->query('q', '')), $request->integer('project') ?: null);
        $zone = config('bac-office.display_timezone', 'Asia/Manila');
        $period = '';
        foreach (['from', 'to'] as $edge) {
            $value = (string) $request->query($edge, '');
            if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $value)) {
                $query->whereDate('paid_at', $edge === 'from' ? '>=' : '<=', $value);
                $period .= '-'.$edge.'-'.$value;
            }
        }
        $filename = 'bidding-fee-payments'.$period.'-'.now($zone)->format('Ymd-His').'.xlsx';

        $total = 0.0;
        $rows = [];
        $query->chunk(500, function ($payments) use (&$rows, $zone, &$total) {
            $submitted = $this->submittedKeys($payments);
            foreach ($payments as $payment) {
                $total += (float) $payment->amount;
                $rows[] = [
                    $payment->paid_at?->format('Y-m-d'),
                    $payment->or_number,
                    $payment->project?->reference_no,
                    $payment->project?->title,
                    $payment->bidder?->company ?: $payment->bidder?->name,
                    $payment->bidder?->company ? $payment->bidder?->name : null,
                    round((float) $payment->amount, 2),
                    $submitted->has($payment->project_id.':'.$payment->user_id) ? 'Yes' : 'No',
                    $payment->recorder?->name,
                    $payment->created_at?->timezone($zone)->format('Y-m-d H:i'),
                    $payment->notes,
                ];
            }
        });

        $file = \App\Support\SimpleXlsx::build('Payments', [
            ['title' => 'Date paid', 'width' => 14], ['title' => 'Official Receipt No.', 'width' => 20], ['title' => 'Project reference', 'width' => 22],
            ['title' => 'Project', 'width' => 50], ['title' => 'Bidder', 'width' => 30], ['title' => 'Contact person', 'width' => 24],
            ['title' => 'Amount (PHP)', 'width' => 16, 'type' => \App\Support\SimpleXlsx::MONEY], ['title' => 'Used for a submitted bid', 'width' => 22],
            ['title' => 'Recorded by', 'width' => 22], ['title' => 'Recorded on (Asia/Manila)', 'width' => 22], ['title' => 'Notes', 'width' => 36],
        ], $rows, ['', '', '', '', '', 'Total', round($total, 2)]);

        return response($file, 200, [
            'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            'Content-Disposition' => 'attachment; filename="'.$filename.'"',
            'Content-Length' => (string) strlen($file),
            'Cache-Control' => 'no-store',
        ]);
    }
    /** Printable collection report for a period: totals, by project, by day and every Official Receipt. */
    public function report(Request $request)
    {
        $role = $this->role($request);
        $zone = config('bac-office.display_timezone', 'Asia/Manila');
        $all = $request->boolean('all');
        $parse = function (?string $value, Carbon $fallback) use ($zone): Carbon {
            try {
                return $value ? Carbon::parse($value, $zone)->startOfDay() : $fallback;
            } catch (\Throwable) {
                return $fallback;
            }
        };
        $today = now($zone)->startOfDay();
        $from = $parse($request->query('from'), $today->copy()->startOfMonth());
        $to = $parse($request->query('to'), $today);
        if ($from->greaterThan($to)) {
            [$from, $to] = [$to, $from];
        }

        $payments = BiddingFeePayment::query()
            ->with(['project', 'bidder', 'recorder'])
            ->when(! $all, fn ($query) => $query->whereDate('paid_at', '>=', $from->toDateString())->whereDate('paid_at', '<=', $to->toDateString()))
            ->orderBy('paid_at')
            ->orderBy('id')
            ->get();

        $byProject = $payments->groupBy('project_id')->map(fn ($rows) => [
            'title' => $rows->first()->project?->title ?? 'Deleted project',
            'reference' => $rows->first()->project?->reference_no,
            'fee' => $rows->first()->project?->bidding_documents_fee,
            'count' => $rows->count(),
            'amount' => (float) $rows->sum('amount'),
        ])->sortByDesc('amount')->values();

        $byDay = $payments->groupBy(fn ($payment) => $payment->paid_at->toDateString())->map(fn ($rows, $date) => [
            'date' => Carbon::parse($date),
            'count' => $rows->count(),
            'amount' => (float) $rows->sum('amount'),
        ])->values();

        return view('payments.report', [
            'routePrefix' => $role,
            'zone' => $zone,
            'all' => $all,
            'from' => $from,
            'to' => $to,
            'payments' => $payments,
            'total' => (float) $payments->sum('amount'),
            'byProject' => $byProject,
            'byDay' => $byDay,
            'user' => Auth::user(),
        ]);
    }

    private function filteredPayments(string $search, ?int $projectFilter)
    {
        return BiddingFeePayment::query()
            ->with(['project', 'bidder', 'recorder'])
            ->when($projectFilter, fn ($query) => $query->where('project_id', $projectFilter))
            ->when($search !== '', function ($query) use ($search) {
                $like = '%'.$search.'%';
                $query->where(function ($inner) use ($like) {
                    $inner->where('or_number', 'like', $like)
                        ->orWhereHas('bidder', fn ($bidder) => $bidder->where('name', 'like', $like)->orWhere('company', 'like', $like))
                        ->orWhereHas('project', fn ($project) => $project->where('title', 'like', $like)->orWhere('reference_no', 'like', $like));
                });
            })
            ->orderByDesc('paid_at')
            ->orderByDesc('id');
    }

    /** Which of these payments have already been used for an official bid ("project:user" keys). */
    private function submittedKeys($payments)
    {
        return Bid::query()
            ->whereIn('project_id', $payments->pluck('project_id')->unique())
            ->whereIn('user_id', $payments->pluck('user_id')->unique())
            ->get(['project_id', 'user_id', 'submission_channel', 'submitted_at'])
            ->reject(fn (Bid $bid) => $bid->isDraft())
            ->map(fn (Bid $bid) => $bid->project_id.':'.$bid->user_id)
            ->flip();
    }

    /** Live check while the Official Receipt number is typed: is it already recorded, and for whom? */
    public function checkReceipt(Request $request)
    {
        $number = trim((string) $request->query('or', ''));
        if ($number === '') {
            return response()->json(['exists' => false]);
        }

        $payment = BiddingFeePayment::query()->with(['bidder:id,name,company', 'project:id,title,reference_no'])->where('or_number', $number)->first();

        return response()->json([
            'exists' => $payment !== null,
            'bidder' => $payment?->bidder?->company ?: $payment?->bidder?->name,
            'project' => $payment?->project?->reference_no ?: $payment?->project?->title,
        ]);
    }

    public function store(Request $request)
    {
        $role = $this->role($request);
        $request->merge(['or_number' => trim((string) $request->input('or_number'))]);

        $validated = $request->validate([
            'project_id' => ['required', 'integer', Rule::exists('projects', 'id')],
            'user_id' => ['required', 'integer', Rule::exists('users', 'id')->where('role', 'bidder')],
            'amount' => ['required', 'numeric', 'min:0.01', 'lte:9999999999999.99'],
            'or_number' => ['required', 'string', 'max:60', Rule::unique('bidding_fee_payments', 'or_number')],
            'paid_at' => ['required', 'date', 'before_or_equal:today'],
            'notes' => ['nullable', 'string', 'max:1000'],
        ], $this->messages(), $this->attributes());

        $project = Project::with('schedule')->findOrFail($validated['project_id']);
        $bidder = User::with('bidderProfile.activeSanction')->findOrFail($validated['user_id']);

        $error = match (true) {
            ! $project->requiresBiddingFee() => ['project_id', 'This project has no bidding documents fee, so no payment is needed.'],
            ! $project->isOpenForBidding() => ['project_id', 'Bidding for this project is closed. A payment recorded now could no longer be used for a bid.'],
            ! $bidder->isApprovedBidder() => ['user_id', 'This bidder is not an approved bidder, so they cannot take part in this bidding.'],
            default => null,
        };

        if ($error !== null) {
            throw ValidationException::withMessages([$error[0] => $error[1]]);
        }

        $this->assertValidPayment($project, $validated);

        $payment = DB::transaction(function () use ($project, $bidder, $validated) {
            $existing = BiddingFeePayment::where('project_id', $project->id)
                ->where('user_id', $bidder->id)
                ->lockForUpdate()
                ->first();

            if ($existing) {
                throw ValidationException::withMessages([
                    'user_id' => 'A payment for this bidder is already recorded for this project (OR No. '.$existing->or_number.').',
                ]);
            }

            return BiddingFeePayment::create([
                'project_id' => $project->id,
                'user_id' => $bidder->id,
                'amount' => number_format((float) $validated['amount'], 2, '.', ''),
                'or_number' => $validated['or_number'],
                'paid_at' => $validated['paid_at'],
                // Entered by the BAC from the Official Receipt it issued: verified on entry.
                'status' => BiddingFeePayment::STATUS_VERIFIED,
                'verified_at' => now(),
                'recorded_by' => Auth::id(),
                'notes' => filled($validated['notes'] ?? null) ? trim($validated['notes']) : null,
            ]);
        });

        AuditLog::log('bidding_fee_payment_recorded', $payment, null, $this->auditValues($payment));

        SystemNotification::createForUser(
            $bidder->id,
            'Payment recorded',
            'Your bidding documents fee of ₱'.number_format((float) $payment->amount, 2).' for "'.$project->title.'" was recorded (OR No. '.$payment->or_number.'). You can now submit your bid online.',
            'bidding_fee_paid',
            ['project_id' => $project->id, 'url' => route('bidder.available-projects', ['bid_project' => $project->id])]
        );

        // "Record another": the form opens again, empty, for the next bidder in line.
        return redirect()
            ->route($role.'.payments')
            ->with('success', 'Payment recorded: OR No. '.$payment->or_number.' for '.($bidder->company ?: $bidder->name).' (₱'.number_format((float) $payment->amount, 2).').')
            ->with('success_link', route($role.'.payments', ['q' => $payment->or_number]))
            ->with('reopen_record', $request->boolean('record_another'));
    }

    public function update(Request $request, BiddingFeePayment $payment)
    {
        $role = $this->role($request);
        $request->merge(['or_number' => trim((string) $request->input('or_number'))]);

        $validated = $request->validate([
            'amount' => ['required', 'numeric', 'min:0.01', 'lte:9999999999999.99'],
            'or_number' => ['required', 'string', 'max:60', Rule::unique('bidding_fee_payments', 'or_number')->ignore($payment->id)],
            'paid_at' => ['required', 'date', 'before_or_equal:today'],
            'notes' => ['nullable', 'string', 'max:1000'],
        ], $this->messages(), $this->attributes());

        $project = $payment->project()->with('schedule')->firstOrFail();
        $this->assertValidPayment($project, $validated);

        $before = $this->auditValues($payment);
        $payment->update([
            'amount' => number_format((float) $validated['amount'], 2, '.', ''),
            'or_number' => $validated['or_number'],
            'paid_at' => $validated['paid_at'],
            'notes' => filled($validated['notes'] ?? null) ? trim($validated['notes']) : null,
        ]);

        AuditLog::log('bidding_fee_payment_updated', $payment, $before, $this->auditValues($payment->fresh()));

        return redirect()
            ->route($role.'.payments', $request->only(['q', 'project', 'page']))
            ->with('success', 'Payment OR No. '.$payment->or_number.' updated.');
    }

    public function destroy(Request $request, BiddingFeePayment $payment)
    {
        $role = $this->role($request);

        $usedForBid = Bid::where('project_id', $payment->project_id)
            ->where('user_id', $payment->user_id)
            ->get()
            ->contains(fn (Bid $bid) => ! $bid->isDraft());

        if ($usedForBid) {
            return redirect()
                ->route($role.'.payments', $request->only(['q', 'project', 'page']))
                ->withErrors(['payment' => 'OR No. '.$payment->or_number.' cannot be removed: the bidder already submitted a bid using this payment.']);
        }

        $before = $this->auditValues($payment);
        $orNumber = $payment->or_number;
        $payment->delete();

        AuditLog::log('bidding_fee_payment_removed', $payment, $before, null);

        return redirect()
            ->route($role.'.payments', $request->only(['q', 'project', 'page']))
            ->with('success', 'Payment OR No. '.$orNumber.' removed.');
    }

    /**
     * @throws ValidationException
     */
    private function assertValidPayment(Project $project, array $validated): void
    {
        $fee = (float) $project->bidding_documents_fee;
        if (round((float) $validated['amount'], 2) < round($fee, 2)) {
            throw ValidationException::withMessages([
                'amount' => 'The amount paid must be at least the bidding documents fee of ₱'.number_format($fee, 2).'.',
            ]);
        }

        $posted = $project->schedule?->date_posted ?? $project->created_at;
        if ($posted !== null) {
            $postedDate = Carbon::parse($posted)->startOfDay();
            if (Carbon::parse($validated['paid_at'])->startOfDay()->lessThan($postedDate)) {
                throw ValidationException::withMessages([
                    'paid_at' => 'The payment date cannot be before the project was posted ('.$postedDate->format('M d, Y').').',
                ]);
            }
        }
    }

    private function auditValues(BiddingFeePayment $payment): array
    {
        return [
            'project_id' => $payment->project_id,
            'user_id' => $payment->user_id,
            'amount' => $payment->amount,
            'or_number' => $payment->or_number,
            'paid_at' => $payment->paid_at?->toDateString(),
            'notes' => $payment->notes,
        ];
    }

    /** The bidder behind a scanned Verified Bidding Record QR code, if they are approved and can pay. */
    public function bidderLookup(Request $request)
    {
        $this->role($request);
        $token = preg_replace('/[^A-Za-z0-9_-]/', '', (string) $request->query('token', ''));
        $profile = strlen($token) >= 8 ? \App\Models\Bidder::with('user.bidderProfile.activeSanction')->where('qr_token', $token)->first() : null;
        $user = $profile?->user;

        if (! $user) {
            return response()->json(['ok' => false, 'message' => 'This QR code does not belong to a registered bidder.'], 404);
        }
        if (! $user->isApprovedBidder()) {
            return response()->json(['ok' => false, 'message' => ($user->company ?: $user->name).' is not an approved bidder, so a payment cannot be recorded for them.'], 422);
        }

        return response()->json(['ok' => true, 'id' => $user->id, 'label' => $user->company ?: $user->name]);
    }

    private function role(Request $request): string
    {
        return $request->routeIs('staff.*') ? 'staff' : 'admin';
    }

    private function messages(): array
    {
        return [
            'or_number.unique' => 'This Official Receipt number is already recorded.',
            'paid_at.before_or_equal' => 'The payment date cannot be in the future.',
            'user_id.exists' => 'Select a registered bidder.',
        ];
    }

    private function attributes(): array
    {
        return [
            'project_id' => 'project',
            'user_id' => 'bidder',
            'or_number' => 'Official Receipt number',
            'paid_at' => 'payment date',
        ];
    }
}
