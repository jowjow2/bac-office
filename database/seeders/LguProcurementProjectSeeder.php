<?php

namespace Database\Seeders;

use App\Models\Project;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

/**
 * Rewrites existing project rows in place with plausible LGU procurement data.
 *
 * Rows are updated rather than replaced so that the bids, awards, assignments and
 * notifications already pointing at these project ids stay intact.
 */
class LguProcurementProjectSeeder extends Seeder
{
    /** Realistic San Jose, Occidental Mindoro procurement packages. */
    private const PACKAGES = [
        [
            'title' => 'Concreting of Barangay San Roque Farm-to-Market Road (Phase 2)',
            'category' => 'Infrastructure',
            'unit' => 'Municipal Engineering Office',
            'budget' => 18750000,
            'fund' => '20% Development Fund',
            'duration' => '180 calendar days',
        ],
        [
            'title' => 'Construction of Two-Storey Barangay Health Station, Brgy. Labangan',
            'category' => 'Infrastructure',
            'unit' => 'Municipal Engineering Office',
            'budget' => 8420000,
            'fund' => 'General Fund',
            'duration' => '150 calendar days',
        ],
        [
            'title' => 'Rehabilitation of Municipal Drainage System, Poblacion Area',
            'category' => 'Infrastructure',
            'unit' => 'Municipal Engineering Office',
            'budget' => 12300000,
            'fund' => '20% Development Fund',
            'duration' => '120 calendar days',
        ],
        [
            'title' => 'Supply and Delivery of Assorted Medical Supplies for Rural Health Unit',
            'category' => 'Goods',
            'unit' => 'Municipal Health Office',
            'budget' => 2485000,
            'fund' => 'Trust Fund',
            'duration' => '45 calendar days',
        ],
        [
            'title' => 'Supply and Delivery of Laboratory Reagents and Consumables',
            'category' => 'Goods',
            'unit' => 'Municipal Health Office',
            'budget' => 1675000,
            'fund' => 'Trust Fund',
            'duration' => '30 calendar days',
        ],
        [
            'title' => 'Procurement of One (1) Unit Ambulance, Type II',
            'category' => 'Goods',
            'unit' => 'Municipal Health Office',
            'budget' => 3250000,
            'fund' => 'General Fund',
            'duration' => '90 calendar days',
        ],
        [
            'title' => 'Procurement of Two (2) Units Dump Truck, 6-Wheeler',
            'category' => 'Goods',
            'unit' => 'General Services Office',
            'budget' => 7900000,
            'fund' => '20% Development Fund',
            'duration' => '120 calendar days',
        ],
        [
            'title' => 'Procurement of One (1) Unit Backhoe Loader for Municipal Motorpool',
            'category' => 'Goods',
            'unit' => 'General Services Office',
            'budget' => 5650000,
            'fund' => 'General Fund',
            'duration' => '90 calendar days',
        ],
        [
            'title' => 'Janitorial Services for Municipal Hall and Annex Buildings (CY 2027)',
            'category' => 'Services',
            'unit' => 'General Services Office',
            'budget' => 4380000,
            'fund' => 'General Fund',
            'duration' => '12 months',
        ],
        [
            'title' => 'Security Services for Municipal Compound and Public Market (CY 2027)',
            'category' => 'Services',
            'unit' => 'General Services Office',
            'budget' => 5120000,
            'fund' => 'General Fund',
            'duration' => '12 months',
        ],
        [
            'title' => 'Supply and Delivery of Office Supplies for All Departments, 1st Semester',
            'category' => 'Goods',
            'unit' => 'General Services Office',
            'budget' => 1940000,
            'fund' => 'General Fund',
            'duration' => '60 calendar days',
        ],
        [
            'title' => 'Supply and Delivery of Information Technology Equipment',
            'category' => 'Goods',
            'unit' => 'Management Information Systems Office',
            'budget' => 3760000,
            'fund' => 'General Fund',
            'duration' => '60 calendar days',
        ],
        [
            'title' => 'Improvement of Municipal Public Market Stalls, Phase 1',
            'category' => 'Infrastructure',
            'unit' => 'Municipal Engineering Office',
            'budget' => 9850000,
            'fund' => '20% Development Fund',
            'duration' => '150 calendar days',
        ],
        [
            'title' => 'Construction of Multi-Purpose Evacuation Center, Brgy. Central',
            'category' => 'Infrastructure',
            'unit' => 'Municipal Disaster Risk Reduction and Management Office',
            'budget' => 14200000,
            'fund' => 'Local DRRM Fund',
            'duration' => '210 calendar days',
        ],
        [
            'title' => 'Supply and Delivery of Disaster Response Equipment and Rescue Gear',
            'category' => 'Goods',
            'unit' => 'Municipal Disaster Risk Reduction and Management Office',
            'budget' => 2870000,
            'fund' => 'Local DRRM Fund',
            'duration' => '45 calendar days',
        ],
        [
            'title' => 'Supply and Delivery of Agricultural Inputs and Certified Seeds',
            'category' => 'Goods',
            'unit' => 'Municipal Agriculture Office',
            'budget' => 2150000,
            'fund' => 'General Fund',
            'duration' => '30 calendar days',
        ],
        [
            'title' => 'Consultancy Services for Municipal Water System Feasibility Study',
            'category' => 'Consulting Services',
            'unit' => 'Municipal Planning and Development Office',
            'budget' => 1850000,
            'fund' => 'General Fund',
            'duration' => '120 calendar days',
        ],
        [
            'title' => 'Installation of Solar Street Lighting Along National Highway',
            'category' => 'Infrastructure',
            'unit' => 'Municipal Engineering Office',
            'budget' => 6480000,
            'fund' => '20% Development Fund',
            'duration' => '90 calendar days',
        ],
        [
            'title' => 'Supply and Delivery of School Furniture for Public Elementary Schools',
            'category' => 'Goods',
            'unit' => 'Municipal Social Welfare and Development Office',
            'budget' => 2640000,
            'fund' => 'Special Education Fund',
            'duration' => '60 calendar days',
        ],
        [
            'title' => 'Catering Services for Municipal Training and Nutrition Programs',
            'category' => 'Services',
            'unit' => 'Municipal Social Welfare and Development Office',
            'budget' => 1280000,
            'fund' => 'General Fund',
            'duration' => '12 months',
        ],
    ];

    private const MODE_BY_CATEGORY = [
        'Infrastructure' => 'public_bidding',
        'Goods' => 'public_bidding',
        'Services' => 'public_bidding',
        'Consulting Services' => 'negotiated_procurement',
    ];

    public function run(): void
    {
        $projects = Project::orderBy('id')->get();

        if ($projects->isEmpty()) {
            $this->command?->warn('No projects found to rewrite.');

            return;
        }

        $sequence = 1;

        foreach ($projects as $index => $project) {
            $package = self::PACKAGES[$index % count(self::PACKAGES)];
            $year = $project->created_at?->year ?? now()->year;
            $prefix = match ($package['category']) {
                'Infrastructure' => 'INFRA',
                'Services' => 'SVC',
                'Consulting Services' => 'CONS',
                default => 'GOODS',
            };

            $project->forceFill([
                'title' => $package['title'],
                'reference_no' => sprintf('%s-%d-%03d', $prefix, $year, $sequence),
                'description' => $this->descriptionFor($package),
                'category' => $package['category'],
                'location' => 'San Jose, Occidental Mindoro',
                'end_user_unit' => $package['unit'],
                'procurement_mode' => self::MODE_BY_CATEGORY[$package['category']],
                'source_of_fund' => $package['fund'],
                'contract_duration' => $package['duration'],
                'budget' => $package['budget'],
                'slug' => Str::slug(Str::limit($package['title'], 60, '')) . '-' . $project->id,
            ])->save();

            $sequence++;
        }

        $this->command?->info("Rewrote {$projects->count()} projects with LGU procurement data.");
    }

    private function descriptionFor(array $package): string
    {
        return sprintf(
            'The Bids and Awards Committee of San Jose, Occidental Mindoro invites eligible bidders to submit proposals for %s. The contract shall be completed within %s and is funded under the %s. Bidders must meet the eligibility requirements set out in the bidding documents.',
            lcfirst($package['title']),
            $package['duration'],
            $package['fund'],
        );
    }
}
