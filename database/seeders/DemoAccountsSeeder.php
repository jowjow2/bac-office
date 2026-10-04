<?php

namespace Database\Seeders;

use App\Models\Bidder;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

/**
 * Synthetic demo accounts for volume testing: 200 bidders (170 approved,
 * 20 pending review, 10 rejected) and 50 end users spread over the LGU
 * offices. Every account uses the reserved domain @demo.sjbac.test, so none
 * can receive mail and all are easy to find and remove.
 *
 *   php artisan db:seed --class=DemoAccountsSeeder
 *   DEMO_ACCOUNTS=remove php artisan db:seed --class=DemoAccountsSeeder
 *
 * Passwords: DEMO_ACCOUNT_PASSWORD when set (all accounts share it);
 * otherwise a random one nobody knows. Running it again updates the same
 * accounts instead of adding more.
 */
class DemoAccountsSeeder extends Seeder
{
    public const DOMAIN = 'demo.sjbac.test';

    private const BIDDERS = 200;

    private const PENDING = 20;

    private const REJECTED = 10;

    private const END_USERS = 50;

    private const FIRST_NAMES = ['Maria', 'Jose', 'Juan', 'Ana', 'Mark', 'Kristine', 'Paolo', 'Liza', 'Ramon', 'Grace', 'Joel', 'Carmela', 'Rodel', 'Shiela', 'Arnel', 'Jasmine', 'Noel', 'Rowena', 'Dennis', 'Maricel', 'Edwin', 'Joy', 'Ronaldo', 'Lorna', 'Christian', 'Angelica', 'Ferdinand', 'Rhea', 'Gilbert', 'Analyn'];

    private const LAST_NAMES = ['Santos', 'Reyes', 'Cruz', 'Bautista', 'Garcia', 'Mendoza', 'Villanueva', 'Ramos', 'Aquino', 'Castillo', 'Dela Cruz', 'Gonzales', 'Fernandez', 'Torres', 'Navarro', 'Flores', 'Panganiban', 'Salazar', 'Manalo', 'Soriano', 'Pascual', 'Valdez', 'Gutierrez', 'Mercado', 'Domingo'];

    private const PLACES = ['San Jose', 'Mindoro', 'Occidental', 'Mangarin', 'Caminawit', 'Bubog', 'Magbay', 'Pandurucan', 'Ilin', 'Sablayan', 'Calintaan', 'Rizal', 'Magsaysay', 'Mamburao', 'Southwest', 'Island', 'Bayview', 'Golden', 'Prime', 'Unified'];

    /** Lines of business, each with the words its trade names use. */
    private const TRADES = [
        ['Builders', 'Construction', 'Engineering Services', 'Construction & Supply'],
        ['Office Supplies', 'Office Depot', 'School & Office Supply', 'Trading'],
        ['IT Solutions', 'Computer Center', 'Technologies', 'Digital Systems'],
        ['Medical Supply', 'Pharma Distributors', 'Health Solutions', 'Medical Trading'],
        ['Agri Supply', 'Farm Inputs', 'Agricultural Trading', 'Agrivet Supply'],
        ['Hardware', 'Hardware & Construction Supply', 'Steel & Hardware', 'Home Depot'],
        ['Motor Works', 'Auto Supply', 'Heavy Equipment Rental', 'Transport Services'],
        ['Catering Services', 'Food Supply', 'Events & Catering', 'General Merchandise'],
    ];

    private const ADDRESSES = ['Brgy. Poblacion', 'Brgy. Bubog', 'Brgy. Caminawit', 'Brgy. Labangan', 'Brgy. Mangarin', 'Brgy. Magbay', 'Brgy. Murtha', 'Brgy. San Roque', 'Brgy. Central', 'Brgy. Pag-asa', 'Brgy. Natandol', 'Brgy. San Agustin'];

    private const TOWNS = ['San Jose', 'San Jose', 'San Jose', 'Magsaysay', 'Rizal', 'Sablayan', 'Calintaan', 'Mamburao'];

    private const SUFFIXES = ['Enterprises', 'Corporation', 'Inc.', 'Co.', 'Trading', ''];

    public function run(): void
    {
        if (strtolower((string) env('DEMO_ACCOUNTS')) === 'remove') {
            $this->remove();

            return;
        }

        mt_srand(2026);
        $password = Hash::make((string) (env('DEMO_ACCOUNT_PASSWORD') ?: Str::random(40)));
        $offices = User::END_USER_OFFICES;
        $counts = ['bidders' => 0, 'end_users' => 0];

        DB::transaction(function () use ($password, $offices, &$counts) {
            $used = [];
            for ($i = 1; $i <= self::BIDDERS; $i++) {
                $this->seedBidder($i, $this->companyName($used), $password);
                $counts['bidders']++;
            }

            for ($i = 1; $i <= self::END_USERS; $i++) {
                [$first, $last] = $this->person();
                User::query()->updateOrCreate(['email' => sprintf('enduser%02d@%s', $i, self::DOMAIN)], [
                    'name' => "{$first} {$last}",
                    'password' => $password,
                    'role' => 'end_user',
                    'status' => 'active',
                    'office' => $offices[($i - 1) % count($offices)],
                ]);
                $counts['end_users']++;
            }
        });

        $this->command?->info(sprintf('Demo accounts ready: %d bidders (%d approved, %d pending, %d rejected) and %d end users, all @%s.',
            $counts['bidders'], self::BIDDERS - self::PENDING - self::REJECTED, self::PENDING, self::REJECTED, $counts['end_users'], self::DOMAIN));
        if (! env('DEMO_ACCOUNT_PASSWORD')) {
            $this->command?->warn('No DEMO_ACCOUNT_PASSWORD was set, so nobody knows these passwords. Set it and run again to sign in as a demo account.');
        }
    }

    private function seedBidder(int $i, string $company, string $password): void
    {
        // The last accounts are the pending and rejected registrations.
        $state = match (true) {
            $i > self::BIDDERS - self::REJECTED => 'rejected',
            $i > self::BIDDERS - self::REJECTED - self::PENDING => 'pending',
            default => 'approved',
        };
        [$first, $last] = $this->person();
        $registered = now()->subDays(mt_rand(5, 400))->setTime(mt_rand(8, 16), mt_rand(0, 59));

        $user = User::query()->updateOrCreate(['email' => sprintf('bidder%03d@%s', $i, self::DOMAIN)], [
            'name' => "{$first} {$last}",
            'password' => $password,
            'role' => 'bidder',
            'status' => match ($state) { 'approved' => 'active', 'rejected' => 'rejected', default => 'pending' },
            'company' => $company,
            'registration_no' => (mt_rand(0, 1) ? 'DTI-' : 'SEC-').$registered->format('Y').'-'.str_pad((string) (100000 + $i * 37), 6, '0', STR_PAD_LEFT),
        ]);
        if ($user->wasRecentlyCreated) {
            $user->forceFill(['created_at' => $registered, 'updated_at' => $registered])->save();
        }

        Bidder::query()->updateOrCreate(['user_id' => $user->id], [
            'company_name' => $company,
            'contact_person' => "{$first} {$last}",
            'contact_number' => '09'.mt_rand(10, 99).mt_rand(1000000, 9999999),
            'business_address' => self::ADDRESSES[array_rand(self::ADDRESSES)].', '.self::TOWNS[array_rand(self::TOWNS)].', Occidental Mindoro',
            'approval_status' => $state,
            'approved_at' => $state === 'approved' ? $registered->copy()->addDays(mt_rand(1, 4)) : null,
            'rejection_reason' => $state === 'rejected' ? 'Synthetic demo record: Mayor\'s permit expired.' : null,
            'review_status' => $state === 'pending' ? 'under_review' : null,
        ]);
    }

    /** A distinct trade name such as "Mangarin Office Supplies Trading". */
    private function companyName(array &$used): string
    {
        do {
            $trade = self::TRADES[array_rand(self::TRADES)];
            $name = trim(self::PLACES[array_rand(self::PLACES)].' '.$trade[array_rand($trade)].' '.self::SUFFIXES[array_rand(self::SUFFIXES)]);
        } while (isset($used[$name]));
        $used[$name] = true;

        return $name;
    }

    /** @return array{0: string, 1: string} */
    private function person(): array
    {
        return [self::FIRST_NAMES[array_rand(self::FIRST_NAMES)], self::LAST_NAMES[array_rand(self::LAST_NAMES)]];
    }

    private function remove(): void
    {
        $ids = User::query()->where('email', 'like', '%@'.self::DOMAIN)->pluck('id');
        DB::transaction(function () use ($ids) {
            Bidder::query()->whereIn('user_id', $ids)->delete();
            User::query()->whereIn('id', $ids)->delete();
        });
        $this->command?->info('Removed '.$ids->count().' demo accounts (@'.self::DOMAIN.').');
    }
}
