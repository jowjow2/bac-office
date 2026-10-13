<?php

namespace Database\Seeders;

use App\Models\ProcurementRequest;
use App\Models\Project;
use App\Models\ProjectRequirement;
use App\Models\ProjectSchedule;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

/**
 * 20 purchase requests and 20 procurement projects with real-sounding titles for the LGU of San Jose,
 * Occidental Mindoro. Nothing in them is marked as a test.
 *
 *   php artisan db:seed --class=DemoProcurementSeeder
 *   DEMO_PROCUREMENT=remove php artisan db:seed --class=DemoProcurementSeeder
 *
 * Purchase requests are PR-2026-D001..D020 (a non-numeric suffix, so the PR numbering does not move). Projects are
 * SJ-BAC-2026-<G|GS|I|C>-D01..D20. Running it again adds nothing twice. Open projects have their deadline days
 * ahead, so bidders can still bid. tools/demo-procurement/make-sql.php writes the same records as SQL for Neon.
 */
class DemoProcurementSeeder extends Seeder
{
    private const OFFICES = [
        'eng' => 'Municipal Engineering Office',
        'health' => 'Municipal Health Office',
        'agri' => 'Municipal Agriculture Office',
        'mswdo' => 'Municipal Social Welfare and Development Office',
        'mayor' => 'Office of the Municipal Mayor',
        'mdrrmo' => 'Municipal Disaster Risk Reduction and Management Office',
        'treas' => "Municipal Treasurer's Office",
        'mpdo' => 'Municipal Planning and Development Office',
        'gso' => 'General Services Office',
        'sb' => 'Office of the Sangguniang Bayan',
    ];

    private const CODES = ['goods' => 'G', 'services' => 'GS', 'infrastructure' => 'I', 'consultancy' => 'C'];

    /**
     * Purchase requests: [office, title, category, specifications, items [description, qty, unit, unit cost], fund, period, status].
     * The first 6 are in procurement (they have the project of the same number); 7-16 are ready for the wizard.
     *
     * @return list<array<int, mixed>>
     */
    public static function requestRows(): array
    {
        return [
            ['eng', 'Concreting of Barangay Road, Sitio Malaylay (250 lm)', 'infrastructure', 'PCCP 0.20 m thick, 4.0 m wide, 250 linear meters, with base course and shoulder.', [['Concrete pavement, 0.20 m', 1000, 'sq.m', 3150], ['Base course and shoulder works', 1, 'lot', 450000]], '20% Development Fund', '90 calendar days', 'in_procurement'],
            ['health', 'Supply and Delivery of Medicines and Medical Supplies for the Rural Health Unit', 'goods', 'Essential medicines and medical supplies for the RHU, per the attached list.', [['Essential medicines, assorted', 1, 'lot', 620000], ['Medical supplies, assorted', 1, 'lot', 280000]], 'General Fund', '30 calendar days', 'in_procurement'],
            ['agri', 'Supply and Delivery of Certified Rice Seeds and Fertilizer for Farmers', 'goods', 'Certified inbred rice seeds (20 kg) and complete fertilizer (50 kg) for registered farmers.', [['Certified rice seeds, 20 kg', 800, 'bag', 1450], ['Complete fertilizer, 50 kg', 600, 'bag', 1250]], 'General Fund', '45 calendar days', 'in_procurement'],
            ['mdrrmo', 'Procurement of Rescue Equipment and Personal Protective Equipment', 'goods', 'Rescue and protective equipment for the municipal responders.', [['Inflatable rescue boat with motor', 1, 'unit', 520000], ['Life vests', 40, 'piece', 2800], ['Safety helmets and boots, set', 40, 'set', 3200]], '5% LDRRMF', '60 calendar days', 'in_procurement'],
            ['mpdo', 'Detailed Engineering Design of the Municipal Drainage System', 'consultancy', 'Consulting services for the detailed engineering design, with cost estimate.', [['Consulting services, detailed engineering design', 1, 'lot', 1850000]], 'General Fund', '120 calendar days', 'in_procurement'],
            ['gso', 'Supply and Delivery of Office Furniture for the Municipal Hall', 'goods', 'Office tables, chairs and filing cabinets.', [['Executive table', 12, 'piece', 18500], ['Ergonomic chair', 40, 'piece', 6500], ['Steel filing cabinet, 4-drawer', 20, 'piece', 9800]], 'General Fund', '30 calendar days', 'in_procurement'],
            ['eng', 'Rehabilitation of Drainage Canal along the Poblacion Road', 'infrastructure', 'Reinforced concrete canal with covers, 480 linear meters.', [['RC canal with cover', 480, 'lm', 5800]], '20% Development Fund', '100 calendar days', 'forwarded'],
            ['eng', 'Construction of Two-Storey Multi-Purpose Building, Barangay Mangarin', 'infrastructure', 'Two-storey structure, 420 sq.m. floor area, with electrical and plumbing works.', [['Building construction', 1, 'lot', 6350000], ['Electrical and plumbing works', 1, 'lot', 780000]], '20% Development Fund', '240 calendar days', 'forwarded'],
            ['eng', 'Repair and Repainting of the Municipal Hall Roof and Ceiling', 'infrastructure', 'Replacement of roofing sheets, truss repair and repainting.', [['Roofing and truss repair', 1, 'lot', 1650000], ['Ceiling and repainting', 1, 'lot', 410000]], 'General Fund', '75 calendar days', 'forwarded'],
            ['eng', 'Rehabilitation of the Community Water Supply System, Barangay Bubog', 'infrastructure', 'Replacement of pipelines, pump and a 20 cu.m. storage tank.', [['Pipeline replacement', 1, 'lot', 1980000], ['Pump and storage tank', 1, 'lot', 1320000]], '20% Development Fund', '120 calendar days', 'forwarded'],
            ['mswdo', 'Supply and Delivery of Relief Goods for Families Affected by Calamities', 'goods', 'Family food packs for the disaster response stockpile.', [['Family food pack', 4000, 'pack', 600]], 'Local Disaster Risk Reduction and Management Fund', '30 calendar days', 'forwarded'],
            ['health', 'Supply and Delivery of Laptops and Printers for the Rural Health Unit', 'goods', 'Laptop computers and multi-function printers for the RHU and barangay health stations.', [['Laptop computer, 16 GB RAM', 12, 'unit', 62000], ['Multi-function printer', 8, 'unit', 14500]], 'General Fund', '30 calendar days', 'forwarded'],
            ['treas', 'Supply and Delivery of Office Supplies for the Calendar Year', 'goods', 'Bond paper, folders, ink and other consumables for all offices.', [['Bond paper, 70 gsm (ream)', 1800, 'ream', 215], ['Ink and toner, assorted', 1, 'lot', 310000], ['Other office supplies', 1, 'lot', 245000]], 'General Fund', '30 calendar days', 'forwarded'],
            ['mayor', 'Janitorial and Security Services for the Municipal Hall', 'services', 'Twelve months of janitorial and security services, with the required personnel.', [['Janitorial services, 12 months', 1, 'lot', 780000], ['Security services, 12 months', 1, 'lot', 1120000]], 'General Fund', '12 months', 'forwarded'],
            ['gso', 'Maintenance and Repair of Service Vehicles', 'services', 'Preventive maintenance and repair of the municipal service vehicles.', [['Vehicle maintenance and repair', 1, 'lot', 690000]], 'General Fund', '12 months', 'forwarded'],
            ['agri', 'Feasibility Study for the Municipal Sanitary Landfill', 'consultancy', 'Consulting services for the feasibility study and site assessment.', [['Consulting services, feasibility study', 1, 'lot', 1340000]], 'General Fund', '150 calendar days', 'forwarded'],
            ['sb', 'Supply and Delivery of Audio-Visual Equipment for the Session Hall', 'goods', 'Sound system, projector and microphones for the session hall.', [['Sound system', 1, 'lot', 285000], ['Projector and screen', 1, 'set', 96000], ['Conference microphone set', 1, 'set', 142000]], 'General Fund', '30 calendar days', 'submitted'],
            ['mswdo', 'Construction of Day Care Center, Barangay Caminawit', 'infrastructure', 'Single-storey day care center, 72 sq.m.', [['Building construction', 1, 'lot', 2380000]], '20% Development Fund', '150 calendar days', 'submitted'],
            ['treas', 'Procurement of Accounting Software and Annual Subscription', 'goods', 'Accounting software and annual subscription for the Treasurer\'s Office.', [['Accounting software license', 1, 'lot', 320000]], 'General Fund', '30 calendar days', 'draft'],
            ['health', 'Supply and Delivery of Hospital Beds and Medical Equipment', 'goods', 'Hospital beds and medical equipment for the Rural Health Unit.', [['Hospital bed, 3-crank', 10, 'unit', 28500], ['Examination table', 6, 'unit', 17500], ['Vital signs monitor', 4, 'unit', 38000]], 'General Fund', '45 calendar days', 'returned'],
        ];
    }

    /**
     * Projects: [title, category, ABC, office, location, status, days to the deadline (negative = already closed)].
     * The first 6 come from the requests of the same number (their title, ABC and office are taken from them).
     *
     * @return list<array<int, mixed>>
     */
    public static function projectRows(): array
    {
        return [
            [null, null, null, null, 'Sitio Malaylay, San Jose', 'open', 12],
            [null, null, null, null, 'Rural Health Unit, San Jose', 'open', 9],
            [null, null, null, null, 'Municipal Agriculture Office, San Jose', 'open', 15],
            [null, null, null, null, 'Municipal DRRM Office, San Jose', 'open', 11],
            [null, null, null, null, 'San Jose, Occidental Mindoro', 'open', 18],
            [null, null, null, null, 'Municipal Hall, San Jose', 'open', 8],
            ['Construction of Barangay Health Station, Barangay Labangan Poblacion', 'infrastructure', 2650000, 'health', 'Barangay Labangan Poblacion', 'open', 14],
            ['Supply and Delivery of School Armchairs and Teachers\' Tables', 'goods', 1480000, 'mayor', 'Public schools of San Jose', 'open', 10],
            ['Rehabilitation of Farm-to-Market Road, Sitio Pulang Lupa', 'infrastructure', 4120000, 'eng', 'Sitio Pulang Lupa', 'open', 20],
            ['Supply and Delivery of Fuel, Oil and Lubricants for Municipal Vehicles', 'goods', 1950000, 'gso', 'Municipal Hall, San Jose', 'open', 7],
            ['Construction of Public Market Stalls and Drainage', 'infrastructure', 3380000, 'eng', 'Public Market, San Jose', 'open', 16],
            ['Supply and Delivery of Computers and Networking Equipment for the Municipal Hall', 'goods', 1160000, 'mpdo', 'Municipal Hall, San Jose', 'open', 13],
            ['Janitorial Services for the Municipal Buildings', 'services', 840000, 'mayor', 'Municipal buildings, San Jose', 'open', 9],
            ['Preparation of the Comprehensive Land Use Plan Update', 'consultancy', 2250000, 'mpdo', 'San Jose, Occidental Mindoro', 'open', 21],
            ['Construction of Covered Court Roofing, Barangay Bagong Sikat', 'infrastructure', 1890000, 'eng', 'Barangay Bagong Sikat', 'draft', 30],
            ['Supply and Delivery of Uniforms for Municipal Employees', 'goods', 760000, 'treas', 'Municipal Hall, San Jose', 'draft', 30],
            ['Construction of Riprap and Slope Protection, Mangarin River', 'infrastructure', 5200000, 'eng', 'Mangarin River, San Jose', 'draft', 30],
            ['Supply and Delivery of Vaccines and Cold Chain Equipment', 'goods', 970000, 'health', 'Rural Health Unit, San Jose', 'closed', -6],
            ['Repair of the Municipal Gymnasium Flooring', 'infrastructure', 1240000, 'eng', 'Municipal Gymnasium, San Jose', 'closed', -9],
            ['Security Services for the Public Market', 'services', 690000, 'treas', 'Public Market, San Jose', 'closed', -4],
        ];
    }

    public function run(): void
    {
        $refs = collect(self::requestRows())->keys()->map(fn ($i) => sprintf('PR-2026-D%03d', $i + 1));

        if (strtolower((string) env('DEMO_PROCUREMENT')) === 'remove') {
            DB::transaction(function () use ($refs) {
                Project::where('reference_no', 'like', 'SJ-BAC-2026-%-D__')->get()->each(function (Project $project) {
                    ProjectSchedule::where('project_id', $project->id)->delete();
                    ProjectRequirement::where('project_id', $project->id)->delete();
                    $project->delete();
                });
                ProcurementRequest::whereIn('reference_no', $refs)->delete();
            });
            $this->command?->info('Demo purchase requests and projects removed.');

            return;
        }

        $admin = User::where('role', 'admin')->where('status', 'active')->orderBy('id')->firstOrFail();
        $requests = [];
        $projects = 0;

        DB::transaction(function () use ($admin, &$requests, &$projects) {
            foreach (self::requestRows() as $i => $row) {
                [$office, $title, $category, $specs, $items, $fund, $period, $status] = $row;
                $reference = sprintf('PR-2026-D%03d', $i + 1);
                $total = array_sum(array_map(fn ($it) => $it[1] * $it[3], $items));

                $requests[$i] = ProcurementRequest::firstOrCreate(['reference_no' => $reference], [
                    'end_user_office' => self::OFFICES[$office], 'requested_by' => null, 'title' => $title, 'category' => $category,
                    'specifications' => $specs, 'quantity' => 1, 'unit' => 'lot', 'estimated_cost' => $total,
                    'items' => array_map(fn ($it) => ['description' => $it[0], 'quantity' => $it[1], 'unit' => $it[2], 'unit_cost' => $it[3]], $items),
                    'fund_source' => $fund, 'delivery_period' => $period,
                    'justification' => 'Included in the Annual Procurement Plan for the year.',
                    'status' => $status,
                    'ppmp_reference' => in_array($status, ['draft'], true) ? null : sprintf('PPMP-2026-%03d', $i + 1),
                    'app_reference' => in_array($status, ['draft'], true) ? null : sprintf('APP-2026-%03d', $i + 1),
                    'budget_available' => in_array($status, ['forwarded', 'in_procurement'], true),
                    'submitted_at' => $status === 'draft' ? null : now()->subDays(20 - $i),
                    'forwarded_at' => in_array($status, ['forwarded', 'in_procurement'], true) ? now()->subDays(15 - min($i, 10)) : null,
                ]);
            }

            foreach (self::projectRows() as $i => $row) {
                [$title, $category, $budget, $office, $location, $status, $days] = $row;
                $request = $i < 6 ? $requests[$i] : null;
                $title ??= $request->title;
                $category ??= $request->category;
                $budget ??= (float) $request->estimated_cost;
                $officeName = $office ? self::OFFICES[$office] : $request->end_user_office;
                $reference = sprintf('SJ-BAC-2026-%s-D%02d', self::CODES[$category], $i + 1);

                if (Project::where('reference_no', $reference)->exists()) {
                    continue;
                }

                $open = $status !== 'draft';
                $deadline = now()->addDays($days)->setTime(10, 0);
                $quality = $category === 'consultancy';

                $project = Project::create([
                    'reference_no' => $reference, 'title' => $title, 'category' => $category, 'budget' => $budget,
                    'award_criterion' => $quality ? 'quality_based' : 'lowest_calculated_bid',
                    'evaluation_procedure' => $quality ? 'qcbe' : null,
                    'opening_documents_reference' => 'Bidding Documents, ITB Clause 29 (opening and preliminary examination)',
                    'description' => $request?->specifications ?: $title.'.',
                    'location' => $location, 'end_user_unit' => $officeName,
                    'procurement_mode' => 'public_bidding', 'legal_basis' => 'ra_12009',
                    'source_of_fund' => $request?->fund_source ?: 'General Fund',
                    'contract_duration' => $request?->delivery_period ?: '60 calendar days',
                    'submission_mode' => Project::SUBMISSION_ELECTRONIC, 'submission_venue' => 'BAC Secretariat, Municipal Hall, San Jose',
                    'bid_security_required' => true, 'procurement_request_id' => $request?->id,
                    'status' => $status === 'draft' ? 'draft' : ($status === 'closed' ? 'closed' : 'open'),
                    'deadline' => $deadline,
                    'published_at' => $open ? now()->subDays(max(3, 14 - $days)) : null,
                    'published_by' => $open ? $admin->id : null, 'created_by' => $admin->id,
                ]);

                ProjectSchedule::create([
                    'project_id' => $project->id, 'date_posted' => $open ? now()->subDays(max(3, 14 - $days)) : null,
                    'bid_submission_deadline' => $deadline, 'bid_opening_date' => (clone $deadline)->setTime(10, 30),
                ]);
                ProjectRequirement::create(['project_id' => $project->id, 'required_documents' => []]);
                $projects++;
            }
        });

        $this->command?->info('Demo purchase requests: '.count($requests).'; projects added: '.$projects.'.');
    }
}
