<?php

namespace App\Http\Controllers;

use App\Models\AuditLog;
use App\Models\Bid;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;

/**
 * Read-only audit trail for the BAC Admin: who did what to which record and
 * when (Philippine time), with what changed. Entries are never edited here.
 */
class AuditLogController extends Controller
{
    private const PER_PAGE = 25;

    public function index(Request $request)
    {
        $logs = $this->filtered($request)
            ->with('user:id,name,role')
            ->latest('id')
            ->paginate(self::PER_PAGE)
            ->withQueryString();
        $this->loadRecords($logs->getCollection());

        return view('admin.audit-logs', [
            'logs' => $logs,
            'filters' => $this->filters($request),
            'recordTypes' => AuditLog::query()->distinct()->orderBy('auditable_type')->pluck('auditable_type')
                ->mapWithKeys(fn (string $type) => [class_basename($type) => AuditLog::recordTypeLabel($type)])->sort()->all(),
            'actors' => User::query()->whereIn('id', AuditLog::query()->whereNotNull('user_id')->select('user_id'))
                ->orderBy('name')->get(['id', 'name', 'role']),
            'total' => AuditLog::count(),
        ]);
    }

    /** The filtered entries as CSV, for COA / internal audit requests. */
    public function export(Request $request)
    {
        $query = $this->filtered($request)->with('user:id,name,role')->latest('id');
        $zone = config('bac-office.display_timezone', 'Asia/Manila');
        $filename = 'audit-logs-'.now($zone)->format('Ymd-His').'.csv';

        return response()->streamDownload(function () use ($query, $zone) {
            $out = fopen('php://output', 'w');
            fputcsv($out, ['Date and time (Asia/Manila)', 'Action', 'Record', 'By', 'Role', 'Changes', 'IP address']);
            $query->chunk(500, function ($logs) use ($out, $zone) {
                $this->loadRecords($logs);
                foreach ($logs as $log) {
                    fputcsv($out, [
                        $log->created_at?->timezone($zone)->format('Y-m-d H:i:s'),
                        $log->actionLabel(),
                        $log->recordLabel(),
                        $log->actorLabel(),
                        $log->user?->role ?? 'system',
                        collect($log->changes())->map(fn ($change) => $change['field'].': '.($change['old'] ?? '—').' → '.($change['new'] ?? '—'))->implode('; '),
                        $log->ip_address,
                    ]);
                }
            });
            fclose($out);
        }, $filename, ['Content-Type' => 'text/csv; charset=UTF-8', 'Cache-Control' => 'no-store']);
    }

    /**
     * The logged records, one query per model type. Types that are not (or no
     * longer) models are skipped, and deleted records stay null.
     */
    private function loadRecords(Collection $logs): void
    {
        foreach ($logs->groupBy('auditable_type') as $type => $group) {
            $records = class_exists($type) && is_subclass_of($type, Model::class)
                ? $type::query()->when($type === Bid::class, fn ($query) => $query->with('user:id,name,company'))
                    ->whereKey($group->pluck('auditable_id')->filter()->unique()->all())->get()->keyBy(fn ($record) => $record->getKey())
                : collect();
            $group->each(fn (AuditLog $log) => $log->setRelation('auditable', $records->get($log->auditable_id)));
        }
    }

    /** @return array{q: string, type: string, actor: string, from: string, to: string} */
    private function filters(Request $request): array
    {
        $date = fn (string $key) => preg_match('/^\d{4}-\d{2}-\d{2}$/', (string) $request->query($key)) ? (string) $request->query($key) : '';

        return [
            'q' => trim((string) $request->query('q', '')),
            'type' => (string) $request->query('type', ''),
            'actor' => (string) $request->query('actor', ''),
            'from' => $date('from'),
            'to' => $date('to'),
        ];
    }

    private function filtered(Request $request): Builder
    {
        $filters = $this->filters($request);
        $zone = config('bac-office.display_timezone', 'Asia/Manila');
        $query = AuditLog::query();

        if ($filters['type'] !== '') {
            $types = AuditLog::query()->distinct()->pluck('auditable_type')->filter(fn ($type) => class_basename($type) === $filters['type']);
            $query->whereIn('auditable_type', $types->all());
        }
        if ($filters['actor'] === 'system') {
            $query->whereNull('user_id');
        } elseif (ctype_digit($filters['actor'])) {
            $query->where('user_id', (int) $filters['actor']);
        }
        // Dates are Philippine calendar days.
        if ($filters['from'] !== '') {
            $query->where('created_at', '>=', Carbon::parse($filters['from'], $zone)->startOfDay()->timezone(config('app.timezone')));
        }
        if ($filters['to'] !== '') {
            $query->where('created_at', '<=', Carbon::parse($filters['to'], $zone)->endOfDay()->timezone(config('app.timezone')));
        }
        if ($filters['q'] !== '') {
            // "!" escapes LIKE wildcards the same way on MySQL, PostgreSQL and SQLite.
            $like = fn (string $value) => '%'.str_replace(['!', '%', '_'], ['!!', '!%', '!_'], $value).'%';
            $term = $like(Str::lower($filters['q']));
            $action = $like(Str::snake(Str::lower($filters['q'])));
            $query->where(function (Builder $search) use ($term, $action, $filters) {
                $search->whereRaw("LOWER(action) LIKE ? ESCAPE '!'", [$term])
                    ->orWhereRaw("LOWER(action) LIKE ? ESCAPE '!'", [$action])
                    ->orWhereHas('user', fn (Builder $user) => $user->whereRaw("LOWER(name) LIKE ? ESCAPE '!'", [$term]));
                if (ctype_digit($filters['q'])) {
                    $search->orWhere('auditable_id', (int) $filters['q']);
                }
            });
        }

        return $query;
    }
}
