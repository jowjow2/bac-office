<?php

namespace App\Http\Controllers;

use App\Models\Assignment;
use App\Models\Award;
use App\Models\BiddingFeePayment;
use App\Models\ProcurementRequest;
use App\Models\Project;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

/**
 * One search box for the BAC (Ctrl+K anywhere in the portal): projects, purchase requests, bidders,
 * Official Receipts and awards. The staff see the projects assigned to them and no user accounts.
 */
class PortalSearchController extends Controller
{
    private const PER_GROUP = 5;

    public function __invoke(Request $request): JsonResponse
    {
        $viewer = Auth::user();
        abort_unless(in_array($viewer?->role, ['admin', 'staff'], true), 403);
        $isAdmin = $viewer->role === 'admin';
        $prefix = $isAdmin ? 'admin' : 'staff';

        $term = trim((string) $request->query('q', ''));
        if (mb_strlen($term) < 2) {
            return response()->json(['ok' => true, 'groups' => []]);
        }
        $like = '%'.str_replace(['%', '_'], ['\%', '\_'], $term).'%';
        $assigned = $isAdmin ? null : Assignment::query()->where('staff_id', $viewer->id)->pluck('project_id')->all();

        $groups = [];

        $projects = Project::query()
            ->when($assigned !== null, fn ($query) => $query->whereIn('id', $assigned))
            ->where(fn ($query) => $query->where('title', 'like', $like)->orWhere('reference_no', 'like', $like))
            ->orderByDesc('created_at')->limit(self::PER_GROUP)->get();
        $groups[] = ['label' => 'Projects', 'icon' => 'fa-folder-open', 'items' => $projects->map(fn (Project $project) => [
            'title' => $project->title,
            'meta' => trim(($project->reference_no ?: 'No reference').' · '.ucwords(str_replace('_', ' ', (string) $project->status))),
            'url' => route($prefix.'.procurement.show', $project),
        ])->all()];

        $requests = ProcurementRequest::query()
            ->where('status', '!=', ProcurementRequest::STATUS_DRAFT)
            ->where(fn ($query) => $query->where('title', 'like', $like)->orWhere('reference_no', 'like', $like)->orWhere('end_user_office', 'like', $like))
            ->orderByDesc('created_at')->limit(self::PER_GROUP)->get();
        $groups[] = ['label' => 'Purchase requests', 'icon' => 'fa-file-signature', 'items' => $requests->map(fn (ProcurementRequest $item) => [
            'title' => $item->title,
            'meta' => $item->reference_no.' · '.$item->end_user_office,
            'url' => route($prefix.'.requests', ['tab' => 'all', 'q' => $item->reference_no]),
        ])->all()];

        if ($isAdmin) {
            $bidders = User::query()->where('role', 'bidder')
                ->where(fn ($query) => $query->where('name', 'like', $like)->orWhere('company', 'like', $like)->orWhere('email', 'like', $like)->orWhere('registration_no', 'like', $like))
                ->orderBy('company')->limit(self::PER_GROUP)->get();
            $groups[] = ['label' => 'Bidders', 'icon' => 'fa-building', 'items' => $bidders->map(fn (User $user) => [
                'title' => $user->company ?: $user->name,
                'meta' => $user->email.' · '.ucfirst((string) $user->status),
                'url' => route('admin.users', ['review' => $user->id]),
            ])->all()];

            $awards = Award::query()->with('project')
                ->where(fn ($query) => $query->where('certificate_number', 'like', $like)
                    ->orWhereHas('project', fn ($project) => $project->where('title', 'like', $like)->orWhere('reference_no', 'like', $like)))
                ->orderByDesc('id')->limit(self::PER_GROUP)->get();
            $groups[] = ['label' => 'Awards', 'icon' => 'fa-award', 'items' => $awards->map(fn (Award $award) => [
                'title' => $award->project?->title ?? 'Award',
                'meta' => ($award->certificate_number ?: 'No certificate no.').' · ₱'.number_format((float) $award->contract_amount, 2),
                'url' => route('admin.awards.index', ['award' => $award->id]),
            ])->all()];
        }

        $payments = BiddingFeePayment::query()->with(['project', 'bidder'])
            ->where('or_number', 'like', $like)
            ->orderByDesc('paid_at')->limit(self::PER_GROUP)->get();
        $groups[] = ['label' => 'Official Receipts', 'icon' => 'fa-receipt', 'items' => $payments->map(fn (BiddingFeePayment $payment) => [
            'title' => 'OR '.$payment->or_number.' · ₱'.number_format((float) $payment->amount, 2),
            'meta' => ($payment->bidder?->company ?: $payment->bidder?->name ?: 'Bidder').' · '.($payment->project?->reference_no ?? ''),
            'url' => route($prefix.'.payments', ['q' => $payment->or_number]),
        ])->all()];

        return response()->json(['ok' => true, 'groups' => array_values(array_filter($groups, fn ($group) => $group['items'] !== []))]);
    }
}
