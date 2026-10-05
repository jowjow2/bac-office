<?php

namespace App\Http\Controllers;

use App\Models\AuditLog;
use App\Models\SiteSetting;
use App\Support\BacProfile;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;

/**
 * Admin settings for the public About page: the BAC office details and the
 * BAC composition (members, Secretariat, Technical Working Group).
 */
class AboutSettingsController extends Controller
{
    public function edit()
    {
        return view('admin.settings.about', [
            'profile' => BacProfile::get(),
            'available' => SiteSetting::available(),
        ]);
    }

    public function update(Request $request)
    {
        abort_unless(SiteSetting::available(), 503, 'The settings table is not in the database yet. Run the migrations first.');

        $validated = $request->validate([
            'office' => ['array'],
            'office.address' => ['nullable', 'string', 'max:255'],
            'office.hours' => ['nullable', 'string', 'max:255'],
            'office.email' => ['nullable', 'email', 'max:255'],
            'office.phone' => ['nullable', 'string', 'max:100'],
            'office.person' => ['nullable', 'string', 'max:255'],
            'office.person_position' => ['nullable', 'string', 'max:255'],
            'members' => ['array', 'max:60'],
            'members.*.group' => ['required', Rule::in(array_keys(BacProfile::GROUPS))],
            'members.*.position' => ['nullable', 'string', 'max:120'],
            'members.*.name' => ['nullable', 'string', 'max:120'],
        ], [
            'office.email.email' => 'Enter a valid office email address.',
        ]);

        $members = collect($validated['members'] ?? [])
            ->map(fn ($member) => ['group' => $member['group'], 'position' => trim((string) ($member['position'] ?? '')), 'name' => trim((string) ($member['name'] ?? ''))])
            ->filter(fn ($member) => $member['name'] !== '')
            ->values()
            ->all();
        $office = collect(BacProfile::OFFICE_FIELDS)
            ->mapWithKeys(fn ($field) => [$field => trim((string) ($validated['office'][$field] ?? ''))])
            ->all();

        $before = SiteSetting::read(BacProfile::KEY);
        $value = ['office' => $office, 'members' => $members, 'updated_at' => now()->toIso8601String()];
        $setting = SiteSetting::write(BacProfile::KEY, $value, Auth::id());
        AuditLog::log('about_page_updated', $setting, $before, $value);

        return redirect()->route('admin.settings.about')->with('success', 'About page updated. '.count($members).' '.\Illuminate\Support\Str::plural('person', count($members)).' listed.');
    }
}
