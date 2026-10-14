<?php

namespace App\Http\Controllers;

use App\Models\Feedback;
use App\Support\SystemNotification;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

/**
 * Feedback from anyone using the portal, read and followed up by the BAC Admin.
 * No IP address is stored; the name and email are optional.
 */
class FeedbackController extends Controller
{
    public function create()
    {
        return view('pages.feedback', ['available' => Feedback::available()]);
    }

    public function store(Request $request)
    {
        if (! Feedback::available()) {
            if ($request->expectsJson()) {
                return response()->json(['ok' => false, 'message' => 'Feedback is not available right now. Please try again later.'], 503);
            }

            return back()->withErrors(['message' => 'Feedback is not available right now. Please try again later.'])->withInput();
        }

        // A hidden field only bots fill in: pretend it worked.
        if (filled($request->input('website'))) {
            return $request->expectsJson() ? response()->json(['ok' => true]) : redirect()->route('feedback')->with('feedback_sent', true);
        }

        $data = $request->validate([
            'category' => ['required', Rule::in(array_keys(Feedback::CATEGORIES))],
            'rating' => ['required', 'integer', 'between:1,5'],
            'message' => ['required', 'string', 'min:5', 'max:2000'],
            'name' => ['nullable', 'string', 'max:120'],
            'email' => ['nullable', 'email', 'max:190'],
            'page_url' => ['nullable', 'string', 'max:500'],
        ], [
            'rating.required' => 'Choose a rating from 1 to 5 stars.',
            'rating.between' => 'Choose a rating from 1 to 5 stars.',
            'category.required' => 'Choose what kind of feedback this is.',
            'message.required' => 'Tell us a little about it.',
            'message.min' => 'Please write at least a few words.',
        ]);

        $feedback = Feedback::create([
            'category' => $data['category'],
            'rating' => (int) $data['rating'],
            'message' => trim($data['message']),
            'name' => filled($data['name'] ?? null) ? trim($data['name']) : null,
            'email' => filled($data['email'] ?? null) ? trim($data['email']) : null,
            // Only a path on this site, so the form cannot be used to store arbitrary links.
            'page_url' => $this->localPath($data['page_url'] ?? null),
            'user_id' => Auth::id(),
        ]);

        try {
            SystemNotification::createForRole(
                'admin',
                'New feedback',
                ($feedback->rating).'/5 · '.$feedback->categoryLabel().': '.Str::limit($feedback->message, 90),
                'system_alert',
                ['important' => true, 'url' => route('admin.feedback')]
            );
        } catch (\Throwable $exception) {
            report($exception);
        }

        // The pop-up form posts in the background and shows its own thank-you.
        if ($request->expectsJson()) {
            return response()->json(['ok' => true]);
        }

        return redirect()->route('feedback')->with('feedback_sent', true);
    }

    public function index(Request $request)
    {
        abort_unless(Auth::user()?->role === 'admin', 403);

        if (! Feedback::available()) {
            return view('admin.feedback', ['available' => false, 'items' => collect(), 'stats' => [], 'category' => '', 'status' => '']);
        }

        $category = (string) $request->query('category', '');
        $status = (string) $request->query('status', '');

        $items = $this->filtered($category, $status)->with('user:id,name,company')->latest()->paginate(15)->withQueryString();

        $stats = [
            'total' => Feedback::count(),
            'new' => Feedback::unreviewed()->count(),
            'average' => round((float) Feedback::avg('rating'), 1),
            'by_category' => Feedback::selectRaw('category, count(*) as total')->groupBy('category')->pluck('total', 'category')->all(),
        ];

        $keep = array_filter(['category' => $category, 'status' => $status]);
        $exportChoices = \App\Support\ExportPeriods::choices(
            'feedback',
            fn (?string $from, ?string $to) => $this->filtered($category, $status)
                ->when($from, fn ($query) => $query->whereDate('created_at', '>=', $from))
                ->when($to, fn ($query) => $query->whereDate('created_at', '<=', $to)),
            fn (array $params) => route('admin.feedback.export', $keep + $params)
        );

        return view('admin.feedback', ['available' => true, 'items' => $items, 'stats' => $stats, 'category' => $category, 'status' => $status, 'exportChoices' => $exportChoices]);
    }

    public function review(Feedback $feedback)
    {
        abort_unless(Auth::user()?->role === 'admin', 403);

        $feedback->forceFill($feedback->reviewed_at
            ? ['reviewed_at' => null, 'reviewed_by' => null]
            : ['reviewed_at' => now(), 'reviewed_by' => Auth::id()])->save();

        return back();
    }

    public function export(Request $request)
    {
        abort_unless(Auth::user()?->role === 'admin', 403);
        abort_unless(Feedback::available(), 404);

        [$from, $to] = \App\Support\ExportPeriods::range($request);
        $rows = $this->filtered((string) $request->query('category', ''), (string) $request->query('status', ''))
            ->when($from, fn ($query) => $query->whereDate('created_at', '>=', $from))
            ->when($to, fn ($query) => $query->whereDate('created_at', '<=', $to))
            ->with('user:id,name,company')->latest()->get();
        $zone = config('bac-office.display_timezone', 'Asia/Manila');

        $file = \App\Support\SimpleXlsx::build('Feedback', [
            ['title' => 'Date', 'width' => 18], ['title' => 'Kind', 'width' => 16], ['title' => 'Rating', 'width' => 8, 'type' => \App\Support\SimpleXlsx::INTEGER],
            ['title' => 'Message', 'width' => 70], ['title' => 'Name', 'width' => 24], ['title' => 'Email', 'width' => 28],
            ['title' => 'Signed-in account', 'width' => 26], ['title' => 'Page', 'width' => 36], ['title' => 'Reviewed', 'width' => 10],
        ], $rows->map(fn ($row) => [
            $row->created_at?->timezone($zone)->format('Y-m-d H:i'),
            $row->categoryLabel(),
            $row->rating,
            $row->message,
            $row->name,
            $row->email,
            $row->user ? ($row->user->company ?: $row->user->name) : '',
            $row->page_url,
            $row->reviewed_at ? 'Yes' : 'No',
        ])->all());

        return response($file, 200, [
            'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            'Content-Disposition' => 'attachment; filename="feedback'.\App\Support\ExportPeriods::suffix($from, $to).'-'.now()->format('Ymd').'.xlsx"',
            'Content-Length' => (string) strlen($file),
        ]);
    }
    private function filtered(string $category, string $status)
    {
        return Feedback::query()
            ->when(array_key_exists($category, Feedback::CATEGORIES), fn ($query) => $query->where('category', $category))
            ->when($status === 'new', fn ($query) => $query->whereNull('reviewed_at'))
            ->when($status === 'reviewed', fn ($query) => $query->whereNotNull('reviewed_at'));
    }

    private function localPath(?string $value): ?string
    {
        $path = parse_url((string) $value, PHP_URL_PATH);

        return is_string($path) && str_starts_with($path, '/') ? Str::limit($path, 500, '') : null;
    }
}
