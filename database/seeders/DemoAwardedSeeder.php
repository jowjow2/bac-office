<?php

namespace Database\Seeders;

use App\Models\Award;
use App\Models\Bid;
use App\Models\Bidder;
use App\Models\Project;
use App\Models\ProjectRequirement;
use App\Models\ProjectSchedule;
use App\Models\User;
use App\Support\BiddingDocumentsFee;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

/**
 * 30 awarded projects (SJ-BAC-2026-<code>-D21..D50), each with its winning bid, two other bids and the award
 * record with the Notice of Award issued, so they are on the public awards page and in Awards & Contracts.
 * The bidders are the demo bidder accounts (bidder001@demo.sjbac.test ...); run the demo accounts first.
 *
 *   php artisan db:seed --class=DemoAwardedSeeder
 *   DEMO_PROCUREMENT=remove php artisan db:seed --class=DemoProcurementSeeder   (removes these too)
 *
 * tools/demo-procurement/make-sql.php writes the same records as SQL for Neon.
 */
class DemoAwardedSeeder extends Seeder
{
    private const OFFICES = [
        'eng' => 'Municipal Engineering Office', 'health' => 'Municipal Health Office', 'agri' => 'Municipal Agriculture Office',
        'mswdo' => 'Municipal Social Welfare and Development Office', 'mayor' => 'Office of the Municipal Mayor',
        'mdrrmo' => 'Municipal Disaster Risk Reduction and Management Office', 'treas' => "Municipal Treasurer's Office",
        'mpdo' => 'Municipal Planning and Development Office', 'gso' => 'General Services Office', 'sb' => 'Office of the Sangguniang Bayan',
    ];

    private const CODES = ['goods' => 'G', 'services' => 'GS', 'infrastructure' => 'I', 'consultancy' => 'C'];

    /** Demo bidder numbers (bidderNNN) whose business fits each kind of project. */
    private const POOLS = [
        'build' => [11, 21, 35, 37, 48, 49, 51, 57, 66, 73, 76, 82, 86, 87],
        'medical' => [3, 5, 6, 8, 13, 15, 16, 25, 30, 36, 60, 63, 65, 99],
        'office' => [1, 7, 18, 23, 26, 27, 28, 31, 38, 42, 43, 47, 54, 55, 56, 64, 67, 71, 77, 78],
        'agri' => [24, 33, 39, 50, 53, 68, 79, 85, 89, 98],
        'food' => [29, 40, 41, 44, 52, 61, 9, 80, 81],
        'services' => [2, 45, 88, 93, 34, 46, 58, 74, 91],
        'motor' => [4, 10, 14, 19, 20, 32, 75, 97],
        'consult' => [62, 72, 11, 66, 57, 82],
    ];

    /**
     * [title, category, ABC, office, bidder pool, location, days since the Notice of Award].
     *
     * @return list<array<int, mixed>>
     */
    public static function rows(): array
    {
        return [
            ['Construction of Two-Classroom School Building, Barangay Bagong Sikat', 'infrastructure', 2980000, 'eng', 'build', 'Barangay Bagong Sikat', 118],
            ['Concreting of Barangay Road, Sitio Pulang Lupa (400 lm)', 'infrastructure', 4350000, 'eng', 'build', 'Sitio Pulang Lupa', 109],
            ['Construction of Barangay Hall, Barangay Magbay', 'infrastructure', 3120000, 'eng', 'build', 'Barangay Magbay', 101],
            ['Construction of Slope Protection, Barangay Murtha', 'infrastructure', 2450000, 'eng', 'build', 'Barangay Murtha', 96],
            ['Improvement of the Public Plaza and Landscaping', 'infrastructure', 1780000, 'eng', 'build', 'Poblacion, San Jose', 90],
            ['Construction of Evacuation Center, Barangay Ilin', 'infrastructure', 5400000, 'mdrrmo', 'build', 'Barangay Ilin', 84],
            ['Rehabilitation of the Municipal Slaughterhouse', 'infrastructure', 2060000, 'agri', 'build', 'Municipal Slaughterhouse, San Jose', 79],
            ['Installation of Solar Street Lights along Poblacion Roads', 'infrastructure', 1640000, 'eng', 'build', 'Poblacion, San Jose', 74],
            ['Construction of Footbridge, Barangay Bubog', 'infrastructure', 1350000, 'eng', 'build', 'Barangay Bubog', 68],
            ['Repair of the Barangay Health Station, Barangay Caminawit', 'infrastructure', 780000, 'health', 'build', 'Barangay Caminawit', 63],
            ['Supply and Delivery of Desktop Computers for the Municipal Library', 'goods', 860000, 'mpdo', 'office', 'Municipal Library, San Jose', 58],
            ['Supply and Delivery of Medical Equipment for the Rural Health Unit', 'goods', 1450000, 'health', 'medical', 'Rural Health Unit, San Jose', 55],
            ['Supply and Delivery of Medicines for the Municipal Health Office, First Quarter', 'goods', 980000, 'health', 'medical', 'Municipal Health Office, San Jose', 52],
            ['Supply and Delivery of Fertilizer and Farm Tools for Farmers Associations', 'goods', 1120000, 'agri', 'agri', 'Municipal Agriculture Office, San Jose', 49],
            ['Supply and Delivery of Fingerlings and Fishing Gear for Fisherfolk', 'goods', 640000, 'agri', 'agri', 'Municipal Agriculture Office, San Jose', 46],
            ['Supply and Delivery of Livestock for the Dispersal Program', 'goods', 520000, 'agri', 'agri', 'Municipal Agriculture Office, San Jose', 43],
            ['Supply and Delivery of Food Packs for Senior Citizens', 'goods', 750000, 'mswdo', 'food', 'Municipal Social Welfare Office, San Jose', 40],
            ['Supply and Delivery of Uniforms and Sports Equipment for the Youth League', 'goods', 430000, 'mayor', 'food', 'Municipal Gymnasium, San Jose', 37],
            ['Procurement of Two Patrol Motorcycles for Traffic Management', 'goods', 580000, 'mayor', 'motor', 'Municipal Hall, San Jose', 34],
            ['Procurement of Ambulance for the Municipal Health Office', 'goods', 2350000, 'health', 'motor', 'Municipal Health Office, San Jose', 31],
            ['Supply and Delivery of Fire Fighting Equipment', 'goods', 1260000, 'mdrrmo', 'food', 'Municipal DRRM Office, San Jose', 28],
            ['Supply and Delivery of Printing Supplies and Ink for the Calendar Year', 'goods', 320000, 'treas', 'office', 'Municipal Hall, San Jose', 25],
            ['Catering Services for the Municipal Foundation Day', 'services', 380000, 'mayor', 'services', 'Municipal Hall, San Jose', 22],
            ['Solid Waste Hauling Services for the Poblacion', 'services', 920000, 'gso', 'services', 'Poblacion, San Jose', 19],
            ['Security Services for the Municipal Hall Annex', 'services', 540000, 'gso', 'services', 'Municipal Hall Annex, San Jose', 16],
            ['Repair and Maintenance of Municipal Buildings', 'services', 870000, 'gso', 'build', 'Municipal buildings, San Jose', 13],
            ['Preparation of the Local Climate Change Action Plan', 'consultancy', 1150000, 'mpdo', 'consult', 'San Jose, Occidental Mindoro', 10],
            ['Detailed Engineering Design of the Municipal Evacuation Center', 'consultancy', 1690000, 'mdrrmo', 'consult', 'San Jose, Occidental Mindoro', 7],
            ['Updating of the Local Shelter Plan', 'consultancy', 780000, 'mpdo', 'consult', 'San Jose, Occidental Mindoro', 5],
            ['Feasibility Study for the Public Market Expansion', 'consultancy', 1320000, 'mpdo', 'consult', 'Public Market, San Jose', 3],
        ];
    }

    public function run(): void
    {
        $admin = User::where('role', 'admin')->where('status', 'active')->orderBy('id')->firstOrFail();
        $added = 0;

        DB::transaction(function () use ($admin, &$added) {
            foreach (self::rows() as $i => $row) {
                [$title, $category, $abc, $office, $pool, $location, $daysAgo] = $row;
                $number = 21 + $i;
                $reference = sprintf('SJ-BAC-2026-%s-D%02d', self::CODES[$category], $number);
                if (Project::where('reference_no', $reference)->exists()) {
                    continue;
                }

                $noa = now()->subDays($daysAgo)->startOfDay();
                $contractDate = $noa->copy()->addDays(6);
                $decisionAt = $noa->copy()->subDays(2)->setTime(14, 0);
                $recommendedAt = $noa->copy()->subDays(5)->setTime(10, 0);
                $postQualifiedAt = $noa->copy()->subDays(8)->setTime(15, 0);
                $openingAt = $noa->copy()->subDays(21)->setTime(10, 30);
                $deadline = $noa->copy()->subDays(21)->setTime(10, 0);
                $postedAt = $deadline->copy()->subDays(14);
                $quality = $category === 'consultancy';

                $project = Project::create([
                    'reference_no' => $reference, 'title' => $title, 'category' => $category, 'budget' => $abc,
                    'award_criterion' => $quality ? 'quality_based' : 'lowest_calculated_bid',
                    'evaluation_procedure' => $quality ? 'qcbe' : null,
                    'opening_documents_reference' => 'Bidding Documents, ITB Clause 29 (opening and preliminary examination)',
                    'description' => $title.'.', 'location' => $location, 'end_user_unit' => self::OFFICES[$office],
                    'procurement_mode' => 'public_bidding', 'legal_basis' => 'ra_12009',
                    'source_of_fund' => $category === 'infrastructure' ? '20% Development Fund' : 'General Fund',
                    'contract_duration' => $category === 'infrastructure' ? '120 calendar days' : '30 calendar days',
                    'submission_mode' => Project::SUBMISSION_ELECTRONIC, 'submission_venue' => 'BAC Secretariat, Municipal Hall, San Jose',
                    'bid_security_required' => true,
                    'bidding_fee_mode' => BiddingDocumentsFee::MODE_SCHEDULE, 'bidding_documents_fee' => BiddingDocumentsFee::maximumFor($abc),
                    'status' => 'awarded', 'deadline' => $deadline, 'published_at' => $postedAt, 'published_by' => $admin->id,
                    'created_by' => $admin->id,
                ]);
                ProjectSchedule::create(['project_id' => $project->id, 'date_posted' => $postedAt, 'bid_submission_deadline' => $deadline, 'bid_opening_date' => $openingAt]);
                ProjectRequirement::create(['project_id' => $project->id, 'required_documents' => []]);

                // The winner and two others from the same line of business (a different start for each project).
                $candidates = self::POOLS[$pool];
                $picked = [];
                for ($k = 0; count($picked) < 3; $k++) {
                    $n = $candidates[($i * 5 + $k * 2) % count($candidates)];
                    if (! in_array($n, $picked, true)) {
                        $picked[] = $n;
                    }
                    if ($k > 40) {
                        break;
                    }
                }
                $winning = round($abc * (1 - (0.02 + (($i * 7) % 8) / 100)), 2);
                $amounts = [$winning, round(min($abc, $winning * 1.021), 2), round(min($abc, $winning * 1.047), 2)];

                foreach ($picked as $index => $bidderNo) {
                    $bidderUser = $this->bidder($bidderNo, $admin);
                    $won = $index === 0;
                    $submittedAt = $deadline->copy()->subHours(6 + $index * 5);
                    $bid = Bid::create([
                        'project_id' => $project->id, 'user_id' => $bidderUser->id, 'bid_amount' => $amounts[$index],
                        'status' => $won ? 'awarded' : 'rejected', 'workflow_step' => $won ? Bid::STEP_NOTICE_TO_PROCEED : Bid::STEP_NOT_AWARDED,
                        'submission_channel' => Bid::CHANNEL_ELECTRONIC, 'submitted_at' => $submittedAt,
                        'receipt_no' => sprintf('SJ-RCPT-2026-D%02d-%d', $number, $index + 1),
                    ]);
                    if (! $won) {
                        continue;
                    }

                    $bid->forceFill([
                        'documents_validated_at' => $openingAt->copy()->addDay(), 'documents_validated_by' => $admin->id,
                        'bac_evaluation_at' => $openingAt->copy()->addDays(2), 'bac_evaluation_by' => $admin->id,
                        'evaluated_at' => $openingAt->copy()->addDays(3), 'evaluated_by' => $admin->id,
                        'post_qualification_at' => $openingAt->copy()->addDays(5), 'post_qualification_by' => $admin->id,
                        'post_qualification_result' => Bid::POST_QUALIFICATION_PASSED, 'post_qualification_completed_at' => $postQualifiedAt,
                        'bac_recommended_at' => $recommendedAt, 'bac_recommended_by' => $admin->id,
                        'award_decision' => Bid::AWARD_DECISION_APPROVED, 'award_decision_at' => $decisionAt, 'award_decision_by' => $admin->id,
                        'awarded_at' => $decisionAt, 'awarded_by' => $admin->id,
                        'notice_of_award_at' => $noa->copy()->setTime(9, 0), 'notice_of_award_by' => $admin->id,
                        'performance_security_at' => $contractDate->copy()->subDay()->setTime(11, 0),
                        'contract_signed_at' => $contractDate->copy()->setTime(10, 0), 'contract_signed_by' => $admin->id,
                        'notice_to_proceed_at' => $contractDate->copy()->addDays(3)->setTime(9, 0), 'notice_to_proceed_by' => $admin->id,
                        'bac_resolution_no' => sprintf('BAC Resolution No. 2026-%03d', $number), 'bac_resolution_date' => $recommendedAt->toDateString(),
                    ])->save();

                    Award::create([
                        'project_id' => $project->id, 'bid_id' => $bid->id, 'bidder_id' => $bidderUser->id,
                        'contract_amount' => $amounts[$index], 'notice_of_award_date' => $noa->toDateString(), 'contract_date' => $contractDate->toDateString(),
                        'status' => Award::STATUS_VALID, 'certificate_status' => Award::STATUS_VALID,
                        'award_approved_at' => $decisionAt, 'award_approved_by' => $admin->id,
                    ]);
                }
                $added++;
            }
        });

        $this->command?->info('Demo awarded projects added: '.$added.'.');
    }

    /** A demo bidder account; the seeder makes it only where the accounts were not loaded (never in production). */
    private function bidder(int $number, User $admin): User
    {
        $email = sprintf('bidder%03d@demo.sjbac.test', $number);
        $user = User::where('email', $email)->first();
        if ($user) {
            return $user;
        }
        if (app()->environment('production')) {
            throw new \RuntimeException("The demo bidder account {$email} is missing. Load the demo accounts first.");
        }

        $company = 'Demo Bidder '.$number.' Trading';
        $user = User::create(['name' => $company, 'email' => $email, 'company' => $company, 'password' => Hash::make(Str::random(40)), 'role' => 'bidder', 'status' => 'active']);
        Bidder::create([
            'user_id' => $user->id, 'company_name' => $company, 'contact_person' => 'Authorized Representative', 'contact_number' => '09170000000',
            'business_address' => 'San Jose, Occidental Mindoro', 'approval_status' => 'approved', 'approved_at' => now()->subMonths(6), 'approved_by' => $admin->id,
        ]);

        return $user;
    }
}
