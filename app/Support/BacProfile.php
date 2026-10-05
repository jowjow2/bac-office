<?php

namespace App\Support;

use App\Models\SiteSetting;

/**
 * Who the BAC is and how to reach it, as shown on the public About page:
 * the office details (falling back to config/bac-office.php contact) and
 * the BAC composition, both edited by the admin under About page settings.
 */
class BacProfile
{
    public const KEY = 'about';

    public const GROUPS = [
        'bac' => 'Bids and Awards Committee',
        'secretariat' => 'BAC Secretariat',
        'twg' => 'Technical Working Group',
    ];

    public const OFFICE_FIELDS = ['address', 'hours', 'email', 'phone', 'person', 'person_position'];

    /**
     * @return array{office: array<string, ?string>, members: list<array{group: string, position: string, name: string}>, updated_at: ?string}
     */
    public static function get(): array
    {
        $stored = SiteSetting::read(self::KEY);
        $contact = config('bac-office.contact', []);
        $office = [];
        foreach (self::OFFICE_FIELDS as $field) {
            $value = trim((string) ($stored['office'][$field] ?? ''));
            $office[$field] = $value !== '' ? $value : (filled($contact[$field] ?? null) ? (string) $contact[$field] : null);
        }

        $members = collect($stored['members'] ?? [])
            ->filter(fn ($member) => is_array($member) && filled($member['name'] ?? null) && isset(self::GROUPS[$member['group'] ?? '']))
            ->map(fn ($member) => ['group' => $member['group'], 'position' => trim((string) ($member['position'] ?? '')), 'name' => trim((string) $member['name'])])
            ->values()
            ->all();

        return ['office' => $office, 'members' => $members, 'updated_at' => $stored['updated_at'] ?? null];
    }

    /** The members of one group, in the order the admin entered them. */
    public static function group(array $profile, string $group): array
    {
        return array_values(array_filter($profile['members'], fn ($member) => $member['group'] === $group));
    }
}
