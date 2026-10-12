<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
@include('partials.dashboard-viewport')
@php $kinds = \App\Models\Feedback::CATEGORIES; $tz = config('bac-office.display_timezone', 'Asia/Manila'); @endphp
<div class="admin-dashboard admin-role-page dashboard-home admin-feedback-page">
    @vite(['resources/css/dashboard.css'])
    @include('partials.admin-sidebar')

    <div class="main-area">
        <x-page-header title="Feedback" subtitle="What visitors and bidders say about the portal.">
            <x-slot:actions>
                @if($available)
                    <a class="afb-btn" href="{{ route('admin.feedback.export', array_filter(['category' => $category, 'status' => $status])) }}"><i class="fas fa-file-export" aria-hidden="true"></i> Export</a>
                @endif
            </x-slot:actions>
        </x-page-header>

        <main class="dashboard-content">
            @if(! $available)
                <section class="afb-card afb-empty">
                    <i class="fas fa-comment-slash" aria-hidden="true"></i>
                    <strong>Feedback is not set up yet</strong>
                    <span>The database update for feedback has not been applied. Once it is, messages sent through the feedback form appear here.</span>
                </section>
            @else
                <section class="afb-stats" aria-label="Summary">
                    <article><span>Total</span><strong>{{ $stats['total'] }}</strong></article>
                    <article><span>New</span><strong>{{ $stats['new'] }}</strong></article>
                    <article><span>Average rating</span><strong>{{ $stats['total'] ? number_format($stats['average'], 1) : '—' }} <small>/ 5</small></strong></article>
                    @foreach($kinds as $key => $kind)
                        <article><span>{{ $kind['label'] }}</span><strong>{{ $stats['by_category'][$key] ?? 0 }}</strong></article>
                    @endforeach
                </section>

                <form method="GET" class="afb-filters" action="{{ route('admin.feedback') }}">
                    <label><span class="sr-only">Kind</span>
                        <select name="category" onchange="this.form.submit()">
                            <option value="">All kinds</option>
                            @foreach($kinds as $key => $kind)<option value="{{ $key }}" @selected($category === $key)>{{ $kind['label'] }}</option>@endforeach
                        </select>
                    </label>
                    <label><span class="sr-only">Status</span>
                        <select name="status" onchange="this.form.submit()">
                            <option value="">New and reviewed</option>
                            <option value="new" @selected($status === 'new')>New only</option>
                            <option value="reviewed" @selected($status === 'reviewed')>Reviewed only</option>
                        </select>
                    </label>
                    @if($category || $status)<a href="{{ route('admin.feedback') }}" class="afb-clear">Clear</a>@endif
                </form>

                <section class="afb-list" aria-label="Feedback">
                    @forelse($items as $item)
                        <article class="afb-item {{ $item->reviewed_at ? 'is-reviewed' : 'is-new' }}">
                            <header>
                                <span class="afb-stars" aria-label="{{ $item->rating }} out of 5">
                                    @for($i = 1; $i <= 5; $i++)<i class="fas fa-star {{ $i <= $item->rating ? 'is-on' : '' }}" aria-hidden="true"></i>@endfor
                                </span>
                                <span class="afb-kind afb-kind--{{ $item->category }}"><i class="fas {{ $kinds[$item->category]['icon'] ?? 'fa-comment' }}" aria-hidden="true"></i> {{ $item->categoryLabel() }}</span>
                                @unless($item->reviewed_at)<span class="afb-new">New</span>@endunless
                                <time datetime="{{ $item->created_at?->toIso8601String() }}">{{ $item->created_at?->timezone($tz)->format('M d, Y g:i A') }}</time>
                            </header>
                            <p>{{ $item->message }}</p>
                            <footer>
                                <span class="afb-from">
                                    <i class="fas fa-user" aria-hidden="true"></i>
                                    @if($item->name || $item->email || $item->user)
                                        {{ $item->name ?: ($item->user?->company ?: $item->user?->name ?: 'Anonymous') }}
                                        @if($item->email) &middot; <a href="mailto:{{ $item->email }}?subject={{ rawurlencode('Your feedback on the SJBAC portal') }}">{{ $item->email }}</a>@endif
                                        @if($item->user) <em>(signed-in account)</em>@endif
                                    @else
                                        Anonymous
                                    @endif
                                    @if($item->page_url) &middot; from <code>{{ $item->page_url }}</code>@endif
                                </span>
                                <form method="POST" action="{{ route('admin.feedback.review', $item) }}">
                                    @csrf
                                    <button type="submit" class="afb-btn afb-btn--small">
                                        <i class="fas {{ $item->reviewed_at ? 'fa-rotate-left' : 'fa-check' }}" aria-hidden="true"></i>
                                        {{ $item->reviewed_at ? 'Mark as new' : 'Mark as reviewed' }}
                                    </button>
                                </form>
                            </footer>
                        </article>
                    @empty
                        <div class="afb-card afb-empty">
                            <i class="fas fa-comments" aria-hidden="true"></i>
                            <strong>No feedback yet</strong>
                            <span>{{ ($category || $status) ? 'Nothing matches these filters.' : 'Messages appear here when someone sends them from the Feedback page.' }}</span>
                        </div>
                    @endforelse
                </section>

                @if($items->hasPages())<div class="afb-pager">{{ $items->links() }}</div>@endif
            @endif
        </main>
    </div>
</div>

<style>
    .admin-feedback-page .afb-stats { display: grid; grid-template-columns: repeat(auto-fit, minmax(130px, 1fr)); gap: 12px; margin-bottom: 16px; }
    .admin-feedback-page .afb-stats article { display: grid; gap: 4px; padding: 14px 16px; border: 1px solid var(--ui-line); border-radius: 12px; background: #fff; }
    .admin-feedback-page .afb-stats span { color: var(--ui-muted); font-size: 12px; font-weight: 600; letter-spacing: .03em; text-transform: uppercase; }
    .admin-feedback-page .afb-stats strong { color: var(--ui-ink); font-size: 24px; }
    .admin-feedback-page .afb-stats small { color: var(--ui-muted); font-size: 13px; font-weight: 500; }
    .admin-feedback-page .afb-filters { display: flex; flex-wrap: wrap; align-items: center; gap: 10px; margin-bottom: 14px; }
    .admin-feedback-page .afb-filters select { min-height: 38px; padding: 0 30px 0 12px; border: 1px solid var(--ui-line-strong); border-radius: 9px; background: #fff; font: inherit; font-size: 13.5px; color: var(--ui-ink-2); }
    .admin-feedback-page .afb-clear { color: var(--ui-primary); font-size: 13px; font-weight: 600; }
    .admin-feedback-page .afb-list { display: grid; gap: 10px; }
    .admin-feedback-page .afb-item { padding: 14px 16px; border: 1px solid var(--ui-line); border-radius: 12px; background: #fff; }
    .admin-feedback-page .afb-item.is-new { border-left: 4px solid var(--ui-primary); }
    .admin-feedback-page .afb-item header { display: flex; flex-wrap: wrap; align-items: center; gap: 8px 12px; }
    .admin-feedback-page .afb-item time { margin-left: auto; color: var(--ui-muted); font-size: 12.5px; }
    .admin-feedback-page .afb-stars { display: inline-flex; gap: 2px; font-size: 14px; }
    .admin-feedback-page .afb-stars i { color: #d5dbd7; }
    .admin-feedback-page .afb-stars i.is-on { color: #e0a82e; }
    .admin-feedback-page .afb-kind { display: inline-flex; align-items: center; gap: 6px; padding: 3px 10px; border-radius: 999px; background: var(--sb-soft, #f6f4ee); color: var(--ui-ink-2); font-size: 12px; font-weight: 700; }
    .admin-feedback-page .afb-kind--problem { background: var(--ui-danger-soft, #fdecea); color: var(--ui-danger, #b42318); }
    .admin-feedback-page .afb-kind--praise { background: var(--ui-success-soft, #eaf6ef); color: var(--ui-success, #1f7a4d); }
    .admin-feedback-page .afb-kind--suggestion { background: var(--ui-info-soft, #e8f0fb); color: var(--ui-info, #1f4f86); }
    .admin-feedback-page .afb-new { padding: 2px 8px; border-radius: 999px; background: var(--ui-primary); color: #fff; font-size: 11px; font-weight: 700; }
    .admin-feedback-page .afb-item p { margin: 10px 0 12px; color: var(--ui-ink); font-size: 14.5px; line-height: 1.6; white-space: pre-line; overflow-wrap: anywhere; }
    .admin-feedback-page .afb-item footer { display: flex; flex-wrap: wrap; align-items: center; justify-content: space-between; gap: 8px 14px; }
    .admin-feedback-page .afb-from { color: var(--ui-muted); font-size: 12.5px; overflow-wrap: anywhere; }
    .admin-feedback-page .afb-from a { color: var(--ui-primary); }
    .admin-feedback-page .afb-from em { font-style: normal; }
    .admin-feedback-page .afb-from code { font-size: 12px; }
    .admin-feedback-page .afb-btn { display: inline-flex; align-items: center; gap: 8px; min-height: 38px; padding: 0 14px; border: 1px solid var(--ui-line-strong); border-radius: 9px; background: #fff; color: var(--ui-ink); font: inherit; font-size: 13.5px; font-weight: 600; text-decoration: none; cursor: pointer; }
    .admin-feedback-page .afb-btn:hover { background: var(--ui-surface-2, #f7faf8); }
    .admin-feedback-page .afb-btn--small { min-height: 32px; padding: 0 11px; font-size: 12.5px; }
    .admin-feedback-page .afb-empty { display: grid; justify-items: center; gap: 6px; padding: 44px 20px; text-align: center; color: var(--ui-muted); border: 1px dashed var(--ui-line-strong); border-radius: 12px; background: #fff; }
    .admin-feedback-page .afb-empty i { font-size: 28px; }
    .admin-feedback-page .afb-empty strong { color: var(--ui-ink); }
    .admin-feedback-page .afb-pager { margin-top: 14px; }
</style>
