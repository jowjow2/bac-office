<?php

namespace App\Support;

use App\Models\Bidder;
use App\Models\BidderDocument;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Support\Facades\Schema;

/**
 * Checks on a new bidder registration that help the reviewer spot a made-up one: whether the email address was
 * confirmed, and whether the contact number, company name, address or a document file already belongs to another
 * bidder account.
 */
final class RegistrationChecks
{
    /** The fingerprint of an uploaded file, kept in its stored file name so a reused file can be found later. */
    public static function fileFingerprint(string $path): string
    {
        return substr((string) sha1_file($path), 0, 20);
    }

    /** Whether the database can record confirmations yet (the users.email_verified_at column exists). */
    public static function emailConfirmationReady(): bool
    {
        static $ready = null;

        return $ready ??= Schema::hasColumn('users', 'email_verified_at');
    }

    /** True when this bidder still has to confirm the email address (registered after the rule started, not confirmed yet). */
    public static function emailUnconfirmed(User $user): bool
    {
        if (! self::emailConfirmationReady() || $user->role !== 'bidder' || $user->email_verified_at !== null) {
            return false;
        }
        $from = config('bac-office.email_verification_from');

        return $from && $user->created_at !== null
            && $user->created_at->greaterThanOrEqualTo(Carbon::parse($from, config('bac-office.display_timezone', 'Asia/Manila'))->startOfDay());
    }

    /**
     * Other bidder accounts that share something with this one.
     *
     * @return list<array{what: string, accounts: list<array{id: int, name: string, status: string}>}>
     */
    public static function sharedWithOthers(User $user): array
    {
        if (! Schema::hasTable('bidders')) {
            return [];
        }
        $profile = $user->bidderProfile;
        $found = [];
        $add = function (string $what, $users) use (&$found) {
            $accounts = $users->unique('id')->map(fn (User $other) => [
                'id' => $other->id,
                'name' => $other->company ?: $other->name,
                'status' => self::statusLabel($other),
            ])->values()->all();
            if ($accounts !== []) {
                $found[] = ['what' => $what, 'accounts' => $accounts];
            }
        };
        $others = fn () => Bidder::query()->where('user_id', '!=', $user->id)->with('user.bidderProfile')->get();

        $number = substr(preg_replace('/\D+/', '', (string) $profile?->contact_number), -10);
        if (strlen($number) >= 7) {
            $add('the same contact number', $others()->filter(fn (Bidder $b) => substr(preg_replace('/\D+/', '', (string) $b->contact_number), -10) === $number)->pluck('user')->filter());
        }

        $company = self::squash($profile?->company_name ?: $user->company);
        if ($company !== '') {
            $add('the same company name', $others()->filter(fn (Bidder $b) => self::squash($b->company_name) === $company)->pluck('user')->filter());
        }

        $address = self::squash($profile?->business_address);
        if ($address !== '') {
            $add('the same business address', $others()->filter(fn (Bidder $b) => self::squash($b->business_address) === $address)->pluck('user')->filter());
        }

        // A company address on the same domain hints at one business behind two accounts (free mail providers do not count).
        $domain = strtolower(substr(strrchr((string) $user->email, '@') ?: '', 1));
        $free = ['gmail.com', 'yahoo.com', 'outlook.com', 'hotmail.com', 'icloud.com', 'yahoo.com.ph', 'live.com', 'proton.me', 'protonmail.com'];
        if ($domain !== '' && ! in_array($domain, $free, true)) {
            $add('an email on the same domain (@'.$domain.')', User::query()->where('role', 'bidder')->where('id', '!=', $user->id)->where('email', 'like', '%@'.$domain)->with('bidderProfile')->get());
        }

        // The same file uploaded under another account (the fingerprint is part of the stored file name).
        $fingerprints = BidderDocument::query()->where('user_id', $user->id)->pluck('file_path')
            ->map(fn ($path) => self::fingerprintOf((string) $path))->filter()->unique()->values();
        if ($fingerprints->isNotEmpty()) {
            $owners = collect();
            foreach ($fingerprints as $fingerprint) {
                $owners = $owners->merge(
                    BidderDocument::query()->where('user_id', '!=', $user->id)->where('file_path', 'like', '%'.$fingerprint.'%')
                        ->with('user.bidderProfile')->get()->filter(fn (BidderDocument $document) => self::fingerprintOf((string) $document->file_path) === $fingerprint)->pluck('user')->filter()
                );
            }
            $add('the same document file uploaded', $owners);
        }

        return $found;
    }

    /** The fingerprint inside a stored registration file name, or null. */
    public static function fingerprintOf(string $path): ?string
    {
        return preg_match('/^registration_\d+_[a-z0-9_]+?_([0-9a-f]{20})_[A-Za-z0-9]{12}\.[a-z0-9]+$/i', basename($path), $match) ? strtolower($match[1]) : null;
    }

    private static function statusLabel(User $other): string
    {
        $procurement = $other->bidderProcurementStatus();
        if (in_array($procurement, ['blacklisted', 'suspended'], true)) {
            return ucfirst($procurement);
        }

        return $other->bidderProfile?->approval_status === 'rejected' || $other->status === 'rejected' ? 'Rejected' : ucfirst((string) $other->status);
    }

    private static function squash(?string $value): string
    {
        return preg_replace('/[^a-z0-9]+/', '', strtolower((string) $value)) ?? '';
    }
}
