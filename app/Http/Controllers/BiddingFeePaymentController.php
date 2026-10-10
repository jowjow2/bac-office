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
            'submittedKeys' => $submittedKeys,
            'stats' => $stats,
            'search' => $search,
            'projectFilter' => $projectFilter,
        ]);
    }

    /** The payment records as CSV, with the same search and project filter as the page. */
    public function export(Request $request)
    {
        $this->role($request);
        $query = $this->filteredPayments(trim((string) $request->query('q', '')), $request->integer('project') ?: null);
        $zone = config('bac-office.display_timezone', 'Asia/Manila');
        $filename = 'bidding-fee-payments-'.now($zone)->format('Ymd-His').'.csv';

        return response()->streamDownload(function () use ($query, $zone) {
            $out = fopen('php://output', 'w');
            fwrite($out, "\xEF\xBB\xBF"); // byte-order mark so Excel reads ñ and accents correctly
            fputcsv($out, ['Date paid', 'Official Receipt No.', 'Project reference', 'Project', 'Bidder', 'Contact person', 'Amount (PHP)', 'Used for a submitted bid', 'Recorded by', 'Recorded on (Asia/Manila)', 'Notes']);
            $total = 0.0;
            $query->chunk(500, function ($payments) use ($out, $zone, &$total) {
                $submitted = $this->submittedKeys($payments);
                foreach ($payments as $payment) {
                    $total += (float) $payment->amount;
                    fputcsv($out, [
                        $payment->paid_at?->format('Y-m-d'),
                        $payment->or_number,
                        $payment->project?->reference_no,
                        $payment->project?->title,
                        $payment->bidder?->company ?: $payment->bidder?->name,
                        $payment->bidder?->company ? $payment->bidder?->name : null,
                        number_format((float) $payment->amount, 2, '.', ''),
                        $submitted->has($payment->project_id.':'.$payment->user_id) ? 'Yes' : 'No',
                        $payment->recorder?->name,
                        $payment->created_at?->timezone($zone)->format('Y-m-d H:i'),
                        $payment->notes,
                    ]);
                }
            });
            fputcsv($out, ['', '', '', '', '', 'Total', number_format($total, 2, '.', '')]);
            fclose($out);
        }, $filename, ['Content-Type' => 'text/csv; charset=UTF-8', 'Cache-Control' => 'no-store']);
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

        return redirect()
            ->route($role.'.payments')
            ->with('success', 'Payment recorded: OR No. '.$payment->or_number.' for '.($bidder->company ?: $bidder->name).'.');
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
