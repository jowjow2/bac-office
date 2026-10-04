<?php

namespace App\Support;

use App\Models\BidDocument;
use App\Models\Project;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;

/**
 * The bid submission checklist for one project, grouped into the Technical
 * and Eligibility Component and the Financial Component.
 *
 * Built from the project's procurement mode, category and ABC (the standard
 * forms of the Philippine Bidding Documents / RA 9184 IRR, and Annex H for
 * alternative modes), plus any extra documents the BAC listed on the project.
 *
 * This single list drives the bidder's upload form, the server-side check of
 * an electronic submission, and the BAC's preliminary examination checklist.
 */
class BidSubmissionRequirements
{
    public const CATEGORY_LABELS = [
        'goods' => 'Goods',
        'services' => 'Goods (General Support Services)',
        'infrastructure' => 'Infrastructure Projects',
        'consultancy' => 'Consulting Services',
    ];

    /** Project "required documents" (wizard options) already covered by a standard item. */
    private const PROJECT_DOCUMENT_ALIASES = [
        'PhilGEPS Registration' => 'philgeps',
        'Omnibus Sworn Statement' => 'omnibus',
        'PCAB License' => 'pcab',
        'Technical Proposal' => 'technical_proposal',
        'Financial Proposal' => 'financial_bid_form',
        "Mayor's Permit" => 'mayors_permit',
        'Business Permit' => 'mayors_permit',
    ];

    private const OMNIBUS_THRESHOLD = 50000;

    private const TAX_RETURN_THRESHOLD = 500000;

    public function __construct(private readonly Project $project) {}

    /**
     * Standard documents for every legal basis, category, mode and ABC band, for the
     * create-project wizard. Alternative modes depend on the ABC (₱50,000 and ₱500,000).
     *
     * @return array<string, array<string, array<string, array<string, list<array{label: string, component: string, required: bool, condition: ?string}>>>>>
     */
    public static function wizardPreview(): array
    {
        $bands = ['low' => self::OMNIBUS_THRESHOLD, 'mid' => self::TAX_RETURN_THRESHOLD, 'high' => self::TAX_RETURN_THRESHOLD + 1];
        $sets = [];
        foreach ([ProcurementMode::RA_12009, ProcurementMode::RA_9184] as $basis) {
            foreach (array_keys(self::CATEGORY_LABELS) as $category) {
                foreach (array_keys(ProcurementMode::MODES) as $mode) {
                    foreach ($bands as $band => $abc) {
                        $project = new Project(['legal_basis' => $basis, 'category' => $category, 'procurement_mode' => $mode, 'budget' => $abc]);
                        $project->setRelation('requirement', null);
                        $sets[$basis][$category][$mode][$band] = self::for($project)->items()
                            ->map(fn (array $item) => [
                                'label' => $item['label'],
                                'component' => $item['component'],
                                'required' => (bool) $item['required'],
                                'condition' => $item['condition'] ?? null,
                            ])->values()->all();
                    }
                }
            }
        }

        return $sets;
    }

    public static function for(Project $project): self
    {
        return new self($project);
    }

    public function isCompetitiveBidding(): bool
    {
        return $this->project->mode()->isCompetitive();
    }

    public function categoryLabel(): string
    {
        return self::CATEGORY_LABELS[$this->project->category] ?? 'Not specified';
    }

    public function modeLabel(): string
    {
        return $this->project->mode()->label();
    }

    /**
     * @return Collection<int, array{key: string, label: string, component: string, required: bool, condition: ?string, source: string}>
     */
    public function items(): Collection
    {
        $items = $this->isCompetitiveBidding()
            ? $this->competitiveBiddingItems()
            : $this->alternativeModeItems();

        $keys = $items->pluck('key')->all();

        foreach ($this->projectDocuments() as $label) {
            $alias = self::PROJECT_DOCUMENT_ALIASES[$label] ?? null;
            if ($alias !== null && in_array($alias, $keys, true)) {
                continue;
            }

            $key = $alias ?? 'project_'.Str::slug($label, '_');
            if (in_array($key, $keys, true)) {
                continue;
            }

            $items->push($this->item(
                $key,
                $label,
                preg_match('/financial|price|bill of quantities|cost/i', $label) ? BidDocument::COMPONENT_FINANCIAL : BidDocument::COMPONENT_TECHNICAL,
                true,
                'Listed in this project\'s bidding requirements.',
                'project'
            ));
            $keys[] = $key;
        }

        return $items->values();
    }

    public function technical(): Collection
    {
        return $this->items()->where('component', BidDocument::COMPONENT_TECHNICAL)->values();
    }

    public function financial(): Collection
    {
        return $this->items()->where('component', BidDocument::COMPONENT_FINANCIAL)->values();
    }

    public function requiredKeys(): array
    {
        return $this->items()->where('required', true)->pluck('key')->all();
    }

    public function find(string $key): ?array
    {
        return $this->items()->firstWhere('key', $key);
    }

    private function competitiveBiddingItems(): Collection
    {
        $category = $this->project->category ?: 'goods';
        $isGoods = in_array($category, ['goods', 'services'], true);
        $isInfra = $category === 'infrastructure';
        $isConsulting = $category === 'consultancy';
        $t = BidDocument::COMPONENT_TECHNICAL;
        $f = BidDocument::COMPONENT_FINANCIAL;

        $items = collect([
            $this->item('philgeps', 'PhilGEPS Certificate of Registration (Platinum Membership)', $t),
            $this->item('ongoing_contracts', 'Statement of all ongoing government and private contracts', $t),
        ]);

        $items->push($isConsulting
            ? $this->item('similar_contracts', 'Statement of completed government and private contracts similar to the project', $t)
            : $this->item('slcc', 'Statement of Single Largest Completed Contract (SLCC) similar to the contract to be bid', $t));

        if (! $isConsulting) {
            $items->push($this->item('nfcc', 'Net Financial Contracting Capacity (NFCC) computation or committed Line of Credit', $t));
        }

        if ($isInfra) {
            // Under RA 12009 the contractor's own PCAB license is part of its PhilGEPS Platinum
            // registration; the bid envelope carries one only for a joint venture (IRR Sec. 54.2(b)(ii)).
            $items->push($this->project->mode()->isRa12009()
                ? $this->item('pcab', 'PCAB License and Registration of the joint venture partners', $t, false, 'Only if bidding as a joint venture; otherwise covered by the PhilGEPS Platinum registration.')
                : $this->item('pcab', 'PCAB License and Registration for the type and cost of the contract', $t));
        }

        $items->push($this->item('jva', 'Joint Venture Agreement or notarized statement to enter into one', $t, false, 'Only if bidding as a joint venture.'));
        $items->push($this->item('bid_security', 'Bid Security (Bid Securing Declaration, or cash, bank draft/guarantee, or surety bond)', $t));

        if ($isGoods) {
            $items->push($this->item('technical_proposal', 'Conformity with the Technical Specifications (Section VII)', $t));
            $items->push($this->item('delivery_schedule', 'Production / Delivery Schedule', $t));
        } elseif ($isInfra) {
            $items->push($this->item('technical_proposal', 'Project Requirements: organizational chart, key personnel, and list of equipment', $t));
        } else {
            $items->push($this->item('technical_proposal', 'Technical Proposal Forms: firm references, CVs of key personnel, approach and methodology, work plan', $t));
        }

        $items->push($this->item('omnibus', 'Omnibus Sworn Statement', $t));

        if ($isGoods) {
            $items->push($this->item('financial_bid_form', 'Financial Bid Form', $f));
            $items->push($this->item('price_schedule', 'Price Schedule(s)', $f));
        } elseif ($isInfra) {
            $items->push($this->item('financial_bid_form', 'Bid Form (Financial Component)', $f));
            $items->push($this->item('bill_of_quantities', 'Priced Bill of Quantities', $f));
            $items->push($this->item('detailed_estimates', 'Detailed estimates, including the summary sheet of unit prices', $f));
            $items->push($this->item('cash_flow', 'Cash flow by quarter and payment schedule', $f));
        } else {
            $items->push($this->item('financial_bid_form', 'Financial Proposal Submission Form', $f));
            $items->push($this->item('cost_breakdown', 'Financial Proposal Forms (breakdown of costs)', $f));
        }

        return $items;
    }

    /**
     * Shopping, Small Value Procurement, Negotiated Procurement and Direct
     * Contracting, with the ABC-based conditions of RA 9184 IRR Annex H.
     * The RA 12009 IRR leaves the documentary requirements of these modes
     * to the GPPB manual, so for RA 12009 projects the same list is shown as
     * the BAC's standing checklist and the RFQ issued by the BAC governs.
     */
    private function alternativeModeItems(): Collection
    {
        $abc = (float) $this->project->budget;
        $t = BidDocument::COMPONENT_TECHNICAL;
        $f = BidDocument::COMPONENT_FINANCIAL;
        $ra12009 = $this->project->mode()->isRa12009();
        $because = fn (string $threshold) => $ra12009
            ? 'Asked for ABCs above '.$threshold.' (BAC checklist based on RA 9184 Annex H); the RFQ governs.'
            : 'Required because the ABC is above '.$threshold.' (RA 9184 IRR Annex H).';

        $items = collect([
            $this->item('philgeps', 'PhilGEPS Registration Number or Certificate', $t),
            $this->item('mayors_permit', "Mayor's / Business Permit", $t),
        ]);

        if ($abc > self::TAX_RETURN_THRESHOLD) {
            $items->push($this->item('tax_return', 'Latest Income / Business Tax Return', $t, true, $because('₱500,000')));
        }

        if ($abc > self::OMNIBUS_THRESHOLD) {
            $items->push($this->item('omnibus', 'Omnibus Sworn Statement', $t, true, $because('₱50,000')));
        }

        if ($this->project->procurement_mode === 'direct_contracting') {
            $items->push($this->item('exclusive_distributor', 'Certificate of exclusive manufacturer / distributor', $t));
        }

        $items->push($this->item('financial_bid_form', 'Price Quotation / Proposal Form', $f));

        return $items;
    }

    private function projectDocuments(): Collection
    {
        $requirement = $this->project->relationLoaded('requirement')
            ? $this->project->requirement
            : $this->project->requirement()->first();

        return collect($requirement?->required_documents ?? [])
            ->filter(fn ($label) => is_string($label) && trim($label) !== '')
            ->map(fn (string $label) => trim($label))
            ->unique()
            ->values();
    }

    private function item(string $key, string $label, string $component, bool $required = true, ?string $condition = null, string $source = 'standard'): array
    {
        return compact('key', 'label', 'component', 'required', 'condition', 'source');
    }
}
