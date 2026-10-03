<?php

namespace App\Support;

use App\Models\User;
use Illuminate\Support\Facades\Route;

/**
 * The sidebar of every portal, by role. Items link to real pages only; the
 * active item is decided from the current route (and, for the register
 * filters, the mode in the query string).
 */
class PortalNavigation
{
    /**
     * @return list<array{title: ?string, items: list<array{label: string, url: string, icon: string, active: bool, badge: ?int, badge_attr: ?string}>}>
     */
    public static function for(User $user, int $unreadMessages = 0, int $unreadNotifications = 0): array
    {
        $sections = match ($user->role) {
            'admin' => self::admin(),
            'staff' => self::staff(),
            'bidder' => self::bidder($user),
            'end_user' => self::endUser(),
            default => [],
        };

        $inbox = array_values(array_filter([
            match ($user->role) {
                'admin' => self::item('Messages', 'admin.messages', 'fa-comments', ['admin.messages*'], badge: $unreadMessages, badgeAttr: 'data-message-badge'),
                'staff' => self::item('Messages', 'staff.messages', 'fa-comments', ['staff.messages*'], badge: $unreadMessages, badgeAttr: 'data-message-badge'),
                'bidder' => self::item('BAC messages', 'bidder.messages', 'fa-comments', ['bidder.messages*'], badge: $unreadMessages, badgeAttr: 'data-message-badge'),
                default => null,
            },
            self::item('Notifications', match ($user->role) {
                'admin' => 'admin.notifications',
                'staff' => 'staff.notifications',
                'bidder' => 'bidder.notifications',
                default => 'end-user.notifications',
            }, 'fa-bell', ['*.notifications*'], badge: $unreadNotifications, badgeAttr: 'data-notification-badge'),
        ]));

        $sections[] = ['title' => 'Inbox', 'items' => $inbox];

        return collect($sections)
            ->map(fn (array $section) => ['title' => $section['title'], 'items' => array_values(array_filter($section['items']))])
            ->filter(fn (array $section) => $section['items'] !== [])
            ->values()
            ->all();
    }

    private static function admin(): array
    {
        return [
            ['title' => null, 'items' => [
                self::item('Dashboard', 'admin.dashboard', 'fa-gauge-high', ['admin.dashboard'], exceptMode: true),
            ]],
            ['title' => 'Procurement', 'items' => [
                self::item('Purchase requests', 'admin.requests', 'fa-file-signature', ['admin.requests*']),
                self::item('Competitive bidding', 'admin.dashboard', 'fa-gavel', ['admin.dashboard'], ['mode' => ProcurementMode::FAMILY_COMPETITIVE]),
                self::item('Projects', 'admin.projects', 'fa-folder-open', ['admin.projects*', 'admin.project.*', 'admin.procurement.*']),
                self::item('Bids & quotations', 'admin.bids', 'fa-envelope-open-text', ['admin.bids', 'admin.bid.*']),
                self::item('Bidding fee payments', 'admin.payments', 'fa-receipt', ['admin.payments*']),
                self::item('Awards & contracts', 'admin.awards.index', 'fa-award', ['admin.awards*', 'admin.award.*']),
            ]],
            ['title' => 'Registry', 'items' => [
                self::item('Suppliers & users', 'admin.users', 'fa-users', ['admin.users*']),
                self::item('Staff assignments', 'admin.assignments', 'fa-people-arrows', ['admin.assignments*']),
                self::item('Reports', 'admin.reports', 'fa-chart-column', ['admin.reports*']),
                self::item('Audit logs', 'admin.audit-logs', 'fa-clipboard-list', ['admin.audit-logs*']),
            ]],
        ];
    }

    private static function staff(): array
    {
        return [
            ['title' => null, 'items' => [
                self::item('Dashboard', 'staff.dashboard', 'fa-gauge-high', ['staff.dashboard'], exceptMode: true),
            ]],
            ['title' => 'Procurement', 'items' => [
                self::item('Purchase requests', 'staff.requests', 'fa-file-signature', ['staff.requests*']),
                self::item('Competitive bidding', 'staff.dashboard', 'fa-gavel', ['staff.dashboard'], ['mode' => ProcurementMode::FAMILY_COMPETITIVE]),
                self::item('My assigned projects', 'staff.assign-projects', 'fa-folder-open', ['staff.assign-projects', 'staff.procurement.*']),
                self::item('Review bids & quotations', 'staff.review-bids', 'fa-envelope-open-text', ['staff.review-bids*']),
                self::item('Bidding fee payments', 'staff.payments', 'fa-receipt', ['staff.payments*']),
            ]],
            ['title' => 'Reports', 'items' => [
                self::item('Reports', 'staff.reports', 'fa-chart-column', ['staff.reports*']),
            ]],
        ];
    }

    private static function bidder(User $user): array
    {
        $approved = $user->isApprovedBidder();

        return [
            ['title' => null, 'items' => [
                self::item('Dashboard', 'bidder.dashboard', 'fa-gauge-high', ['bidder.dashboard']),
            ]],
            ['title' => 'Bidding', 'items' => $approved ? [
                self::item('Opportunities', 'bidder.available-projects', 'fa-bullhorn', ['bidder.available-projects', 'bidder.opportunities.*', 'bidder.project.document.*']),
                self::item('My bids & quotations', 'bidder.my-bids', 'fa-envelope-circle-check', ['bidder.my-bids']),
                self::item('Track evaluation', 'bidder.bidding-track', 'fa-route', ['bidder.bidding-track*'], badgeAttr: 'data-bidding-track-badge'),
                self::item('Awarded contracts', 'bidder.awarded-contracts', 'fa-award', ['bidder.awarded-contracts']),
            ] : []],
            ['title' => 'Account', 'items' => [
                self::item('Company profile & documents', 'bidder.company-profile', 'fa-building', ['bidder.company-profile', 'bidder.profile.update', 'bidder.documents.store', 'bidder.document.*']),
            ]],
        ];
    }

    private static function endUser(): array
    {
        return [
            ['title' => null, 'items' => [
                self::item('Dashboard', 'end-user.dashboard', 'fa-gauge-high', ['end-user.dashboard']),
            ]],
            ['title' => 'Purchase requests', 'items' => [
                self::item('Infrastructure tracking', 'end-user.infrastructure.index', 'fa-helmet-safety', ['end-user.infrastructure.*']),
                self::item('My purchase requests', 'end-user.requests.index', 'fa-file-signature', ['end-user.requests.index', 'end-user.requests.show', 'end-user.requests.edit']),
                self::item('New purchase request', 'end-user.requests.create', 'fa-circle-plus', ['end-user.requests.create']),
            ]],
        ];
    }

    /**
     * @param  list<string>  $patterns
     * @param  array<string, string>  $query  query string that also has to match for the item to be active
     */
    private static function item(string $label, string $route, string $icon, array $patterns, array $query = [], ?int $badge = null, ?string $badgeAttr = null, bool $exceptMode = false): ?array
    {
        if (! Route::has($route)) {
            return null;
        }

        $active = request()->routeIs(...$patterns);

        if ($query !== []) {
            foreach ($query as $key => $value) {
                $active = $active && request()->query($key) === $value;
            }
        } elseif ($exceptMode) {
            // "Competitive bidding" is its own item; any other mode filter stays under Dashboard.
            $active = $active && request()->query('mode') !== ProcurementMode::FAMILY_COMPETITIVE;
        }

        return [
            'label' => $label,
            'url' => route($route, $query),
            'icon' => $icon,
            'active' => $active,
            'badge' => $badge,
            'badge_attr' => $badgeAttr,
        ];
    }
}

