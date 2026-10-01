<?php

namespace Database\Seeders;

use App\Models\Assignment;
use App\Models\Bid;
use App\Models\BidDocument;
use App\Models\Bidder;
use App\Models\ProcurementRequest;
use App\Models\Project;
use App\Models\ProjectDocument;
use App\Models\ProjectRequirement;
use App\Models\ProjectSchedule;
use App\Models\User;
use App\Support\BidSubmissionRequirements;
use App\Support\Uploads;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * Sample procurement records for trying the BAC workflow by hand:
 *
 *  1. TEST-LCRB-01  – competitive bidding (LCRB), deadline and scheduled
 *                     opening passed, 3 sealed bids: record the technical
 *                     opening, pass preliminary examination, then open each
 *                     financial component the same day.
 *  2. TEST-MEARB-01 – competitive bidding (MEARB), 2 sealed bids; financial
 *                     opening allowed from the documented time (1 hour after
 *                     seeding) for bidders meeting the minimum score of 70.
 *  3. TEST-OPEN-01  – open for bids for 7 days, so a real bidder account can
 *                     submit a bid from the bidder portal.
 *  4. PR-2026-T001  – purchase request forwarded to the BAC, ready for the
 *                     Create procurement project wizard.
 *
 * Every record is marked TEST. Remove them with
 *   php artisan db:seed --class=ProcurementTestCleanupSeeder
 *
 * The TEST bidder accounts are not meant for signing in (bidder sign-in sends
 * a code to the account e-mail, and these addresses do not receive mail).
 */
class ProcurementTestSeeder extends Seeder
{
    public const FILE_DIR = 'test-procurement';
    public const EMAIL_DOMAIN = 'sanjose-test.invalid';

    public function run(): void
    {
        if (app()->environment('production')) {
            $this->command?->error('Refusing to seed TEST procurement data in production.');
            return;
        }

        if (Project::where('reference_no', 'TEST-LCRB-01')->exists()) {
            $this->command?->warn('TEST procurement data already exists. Run ProcurementTestCleanupSeeder first to recreate it.');
            return;
        }

        $admin = User::where('role', 'admin')->where('status', 'active')->orderBy('id')->firstOrFail();
        $staff = User::where('role', 'staff')->where('status', 'active')->orderBy('id')->first();

        DB::transaction(function () use ($admin, $staff) {
            $bidders = collect([
                ['TEST Mindoro Builders Corp.', 'mindoro-builders'],
                ['TEST Occidental Engineering Supply', 'occidental-engineering'],
                ['TEST Sablayan Trading', 'sablayan-trading'],
            ])->map(fn (array $b) => $this->bidder($b[0], $b[1], $admin));

            // 1. LCRB: sealed bids waiting for the recorded technical opening.
            $lcrb = $this->project($admin, $staff, [
                'reference_no' => 'TEST-LCRB-01',
                'title' => '[TEST] Supply and delivery of office equipment for the Municipal Hall',
                'category' => 'goods', 'budget' => 2500000,
                'award_criterion' => 'lowest_calculated_bid',
                'opening_documents_reference' => 'TEST Bidding Documents, ITB Clause 29 (opening and preliminary examination)',
            ], deadline: now()->subHour(), opening: now()->subMinutes(30));
            $this->bid($lcrb, $bidders[0], 2385000.00);
            $this->bid($lcrb, $bidders[1], 2412500.50);
            $this->bid($lcrb, $bidders[2], 2299999.00);

            // 2. MEARB: technical first, financial from the documented time.
            $mearb = $this->project($admin, $staff, [
                'reference_no' => 'TEST-MEARB-01',
                'title' => '[TEST] Design and installation of a solar-powered water system, Brgy. Bubog',
                'category' => 'infrastructure', 'budget' => 4800000,
                'award_criterion' => 'mearb',
                'opening_documents_reference' => 'TEST Bidding Documents, Section III (rating and opening of financial components)',
                'financial_opening_at' => now('Asia/Manila')->addHour()->startOfMinute(),
                'minimum_technical_score' => 70,
            ], deadline: now()->subHour(), opening: now()->subMinutes(30));
            $this->bid($mearb, $bidders[0], 4650000.00);
            $this->bid($mearb, $bidders[2], 4495000.00);

            // 3. Open for bids.
            $this->project($admin, $staff, [
                'reference_no' => 'TEST-OPEN-01',
                'title' => '[TEST] Supply of medicines and medical supplies for the Rural Health Unit',
                'category' => 'goods', 'budget' => 1200000,
                'award_criterion' => 'lowest_calculated_bid',
                'opening_documents_reference' => 'TEST Bidding Documents, ITB Clause 29',
            ], deadline: now()->addDays(7)->setTime(10, 0), opening: now()->addDays(7)->setTime(10, 30));

            // 4. Purchase request forwarded to the BAC.
            $office = User::firstOrCreate(['email' => 'test.office@'.self::EMAIL_DOMAIN], [
                'name' => 'TEST Municipal Engineering Office', 'password' => Hash::make(Str::random(40)),
                'role' => 'end_user', 'status' => 'active', 'office' => 'TEST Municipal Engineering Office',
            ]);
            ProcurementRequest::create([
                'reference_no' => 'PR-2026-T001', 'end_user_office' => 'TEST Municipal Engineering Office', 'requested_by' => $office->id,
                'title' => '[TEST] Concreting of farm-to-market road, Sitio Malaylay (250 lm)', 'category' => 'infrastructure',
                'specifications' => "PCCP 0.20 m thick, 4.0 m wide, 250 linear meters\nIncludes base course and shoulder",
                'quantity' => 1, 'unit' => 'lot', 'estimated_cost' => 3450000, 'fund_source' => '20% Development Fund',
                'delivery_period' => '90 calendar days', 'justification' => 'TEST record for the Create procurement project wizard.',
                'status' => ProcurementRequest::STATUS_FORWARDED, 'ppmp_reference' => 'TEST-PPMP-MEO-2026-04', 'app_reference' => 'TEST-APP-2026-17',
                'budget_available' => true, 'submitted_at' => now()->subDays(3), 'forwarded_at' => now()->subDay(),
            ]);
        });

        $this->command?->info('TEST procurement data created: TEST-LCRB-01, TEST-MEARB-01, TEST-OPEN-01 and PR-2026-T001.');
    }

    private function bidder(string $company, string $slug, User $admin): User
    {
        $user = User::create([
            'name' => $company, 'email' => "test.{$slug}@".self::EMAIL_DOMAIN, 'company' => $company,
            'password' => Hash::make(Str::random(40)), 'role' => 'bidder', 'status' => 'active',
        ]);
        Bidder::create([
            'user_id' => $user->id, 'company_name' => $company, 'contact_person' => 'TEST Contact',
            'contact_number' => '09170000000', 'business_address' => 'San Jose, Occidental Mindoro',
            'approval_status' => 'approved', 'approved_at' => now()->subMonth(), 'approved_by' => $admin->id,
        ]);

        return $user;
    }

    private function project(User $admin, ?User $staff, array $attributes, $deadline, $opening): Project
    {
        $project = Project::create($attributes + [
            'description' => 'TEST record for trying the BAC workflow. Safe to delete.',
            'location' => 'San Jose, Occidental Mindoro', 'end_user_unit' => 'TEST Municipal Engineering Office',
            'procurement_mode' => 'public_bidding', 'legal_basis' => 'ra_12009',
            'source_of_fund' => 'General Fund', 'contract_duration' => '60 calendar days',
            'submission_mode' => Project::SUBMISSION_ELECTRONIC, 'submission_venue' => 'BAC Secretariat, Municipal Hall, San Jose',
            'bid_security_required' => true, 'status' => 'open', 'deadline' => $deadline,
            'published_at' => now()->subDays(10), 'published_by' => $admin->id, 'created_by' => $admin->id,
        ]);

        ProjectSchedule::create([
            'project_id' => $project->id, 'date_posted' => now()->subDays(10),
            'bid_submission_deadline' => $deadline, 'bid_opening_date' => $opening,
        ]);
        ProjectRequirement::create(['project_id' => $project->id, 'required_documents' => []]);

        $path = $this->file("{$project->reference_no}/invitation-to-bid.pdf", [
            'INVITATION TO BID (TEST)', $project->title, 'Reference: '.$project->reference_no,
            'Approved Budget for the Contract: PHP '.number_format((float) $project->budget, 2),
            'This is a TEST document for trying the BAC workflow.',
        ]);
        ProjectDocument::create(['project_id' => $project->id, 'original_name' => 'Invitation to Bid (TEST).pdf', 'file_path' => $path, 'document_type' => 'invitation_to_bid']);

        if ($staff) {
            Assignment::create(['project_id' => $project->id, 'staff_id' => $staff->id]);
        }

        return $project;
    }

    /** An official electronic bid with every required technical and financial file. */
    private function bid(Project $project, User $bidder, float $amount): Bid
    {
        $bid = Bid::create([
            'project_id' => $project->id, 'user_id' => $bidder->id, 'bid_amount' => $amount,
            'status' => 'pending', 'workflow_step' => Bid::STEP_SUBMITTED,
            'submission_channel' => Bid::CHANNEL_ELECTRONIC, 'submitted_at' => now()->subHours(2),
            'receipt_no' => 'TEST-'.$project->id.'-'.$bidder->id,
        ]);

        foreach (BidSubmissionRequirements::for($project)->items()->where('required', true) as $item) {
            $lines = [strtoupper($item['component']).' COMPONENT (TEST)', $item['label'], $bidder->company, 'Project: '.$project->reference_no];
            if ($item['component'] === BidDocument::COMPONENT_FINANCIAL) {
                $lines[] = 'Total bid price: PHP '.number_format($amount, 2);
            }
            $path = $this->file("{$project->reference_no}/bid-{$bid->id}/{$item['key']}.pdf", $lines);
            BidDocument::create([
                'bid_id' => $bid->id, 'requirement_key' => $item['key'], 'component' => $item['component'],
                'label' => $item['label'], 'file_path' => $path, 'original_name' => Str::slug($bidder->company).'-'.$item['key'].'.pdf',
                'size' => Storage::disk(Uploads::diskName())->size($path), 'sha256' => hash('sha256', Storage::disk(Uploads::diskName())->get($path)),
            ]);
            if ($item['key'] === 'financial_bid_form') {
                $bid->forceFill(['proposal_file' => $path])->save();
            }
        }

        return $bid;
    }

    private function file(string $relative, array $lines): string
    {
        $path = self::FILE_DIR.'/'.$relative;
        Storage::disk(Uploads::diskName())->put($path, $this->pdf($lines));

        return $path;
    }

    /** A small, valid one-page PDF showing the given lines. */
    private function pdf(array $lines): string
    {
        $escape = fn (string $text) => str_replace(['\\', '(', ')'], ['\\\\', '\\(', '\\)'], preg_replace('/[^\x20-\x7E]/', '-', $text));
        $stream = "BT /F1 12 Tf 16 TL 56 780 Td\n".collect($lines)->map(fn ($line) => '('.$escape((string) $line).') Tj T*')->implode("\n")."\nET";
        $objects = [
            '<< /Type /Catalog /Pages 2 0 R >>',
            '<< /Type /Pages /Kids [3 0 R] /Count 1 >>',
            '<< /Type /Page /Parent 2 0 R /MediaBox [0 0 595 842] /Resources << /Font << /F1 4 0 R >> >> /Contents 5 0 R >>',
            '<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica >>',
            '<< /Length '.strlen($stream)." >>\nstream\n{$stream}\nendstream",
        ];
        $pdf = "%PDF-1.4\n";
        $offsets = [];
        foreach ($objects as $i => $object) {
            $offsets[] = strlen($pdf);
            $pdf .= ($i + 1)." 0 obj\n{$object}\nendobj\n";
        }
        $xref = strlen($pdf);
        $pdf .= 'xref'."\n0 ".(count($objects) + 1)."\n0000000000 65535 f \n";
        foreach ($offsets as $offset) {
            $pdf .= sprintf("%010d 00000 n \n", $offset);
        }

        return $pdf.'trailer'."\n<< /Size ".(count($objects) + 1)." /Root 1 0 R >>\nstartxref\n{$xref}\n%%EOF\n";
    }
}
