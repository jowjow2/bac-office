<?php

namespace App\Support;

use App\Models\Bid;
use App\Models\Project;
use Illuminate\Support\Collection;

/**
 * Ranks a project's opened bids the way the BAC prepares the Abstract of Bids
 * (RA 12009 IRR / RA 9184 IRR), using the award criterion in its bidding documents:
 *
 *  - LCRB and alternative modes: calculated bid prices from lowest to highest;
 *    the first is the Lowest Calculated Bid (post-qualified next).
 *  - MEARB: combined rating = quality-price ratio x technical score
 *    + remainder x price score (lowest price / bid price x 100), highest first.
 *  - MARB and consulting (highest rated): technical score, highest first.
 *
 * Only what is already open takes part, so a rank never reveals a sealed price:
 * sealed bids, drafts, disqualified bids and bids above the ABC are not ranked.
 * Ties share a rank (1, 1, 3) and are flagged for the tie-breaking method.
 */
class BidRanking
{
    public const RANKED = 'ranked';
    public const SEALED = 'sealed';
    public const ABOVE_ABC = 'above_abc';
    public const BELOW_MINIMUM = 'below_minimum';
    public const DISQUALIFIED = 'disqualified';
    public const PENDING = 'pending';

    /**
     * Rankings for every bid of the given projects, keyed by bid id.
     *
     * @param  iterable<int>  $projectIds
     * @return array<int, array<string, mixed>>
     */
    public function forProjects(iterable $projectIds): array
    {
        $ids = collect($projectIds)->filter()->unique()->values();
        if ($ids->isEmpty()) return [];

        $bids = Bid::with(['project.awards', 'project.rebidProject', 'award', 'user'])
            ->whereIn('project_id', $ids)->get()->groupBy('project_id');

        $rankings = [];
        foreach ($bids as $projectBids) {
            $rankings += $this->forProject($projectBids->first()->project, $projectBids);
        }

        return $rankings;
    }

    /**
     * @param  Collection<int, Bid>  $bids  all bids of the project
     * @return array<int, array<string, mixed>>
     */
    public function forProject(Project $project, Collection $bids): array
    {
        $basis = $this->basis($project);
        $abc = (float) ($project->budget ?? 0);
        $results = [];
        $candidates = collect();

        foreach ($bids as $bid) {
            if ($bid->isDraft()) continue;
            $status = match (true) {
                (bool) $bid->progress()->facts()['disqualified'] => self::DISQUALIFIED,
                $bid->isSealed() => self::SEALED,
                // Its financial component is never opened, so say why instead of "sealed".
                $basis['key'] === 'mearb' && $project->minimum_technical_score !== null && $bid->technical_score !== null
                    && (float) $bid->technical_score < (float) $project->minimum_technical_score => self::BELOW_MINIMUM,
                $basis['uses_price'] && $bid->isFinancialSealed() => self::SEALED,
                $basis['uses_price'] && $abc > 0 && (float) $bid->amount > $abc => self::ABOVE_ABC,
                $basis['uses_score'] && $bid->technical_score === null => self::PENDING,
                default => self::RANKED,
            };
            $results[$bid->id] = ['status' => $status];
            if ($status === self::RANKED) $candidates->push($bid);
        }

        $scores = $this->scores($basis, $project, $candidates);
        if ($scores === null) {
            // MEARB without a quality-price ratio cannot be rated yet.
            foreach ($candidates as $bid) $results[$bid->id] = ['status' => self::PENDING];
            $candidates = collect();
            $scores = [];
        }

        $ordered = $candidates->sort(function (Bid $a, Bid $b) use ($scores, $basis) {
            $cmp = $basis['ascending'] ? $scores[$a->id] <=> $scores[$b->id] : $scores[$b->id] <=> $scores[$a->id];

            return $cmp ?: (($a->submitted_at ?? $a->created_at) <=> ($b->submitted_at ?? $b->created_at));
        })->values();

        $pending = collect($results)->whereIn('status', [self::SEALED, self::PENDING])->count();
        $previous = null;
        $rank = 0;
        foreach ($ordered as $position => $bid) {
            $score = round((float) $scores[$bid->id], 4);
            if ($previous === null || $score !== $previous) $rank = $position + 1;
            $tied = $ordered->filter(fn (Bid $other) => round((float) $scores[$other->id], 4) === $score)->count() > 1;
            $previous = $score;

            $results[$bid->id] = [
                'status' => self::RANKED,
                'rank' => $rank,
                'of' => $ordered->count(),
                'tied' => $tied,
                'score' => $score,
                'provisional' => $pending > 0,
            ];
        }

        foreach ($results as $id => $result) {
            $results[$id] += ['rank' => null, 'of' => $ordered->count(), 'tied' => false, 'score' => null, 'provisional' => $pending > 0];
            $results[$id]['basis'] = $basis;
            $results[$id]['label'] = $this->label($results[$id], $basis);
            $results[$id]['pending_count'] = $pending;
        }

        return $results;
    }

    /** @return array{key: string, title: string, top: string, measure: string, uses_price: bool, uses_score: bool, ascending: bool} */
    public function basis(Project $project): array
    {
        $criterion = $project->mode()->isCompetitive() ? ($project->award_criterion ?: 'lowest_calculated_bid') : 'lowest_quotation';

        return match ($criterion) {
            'mearb' => ['key' => 'mearb', 'title' => 'MEARB rating', 'top' => 'Highest rated (MEARB)', 'measure' => 'Combined rating', 'uses_price' => true, 'uses_score' => true, 'ascending' => false],
            'marb' => ['key' => 'marb', 'title' => 'MARB technical rating', 'top' => 'Highest rated (MARB)', 'measure' => 'Technical score', 'uses_price' => false, 'uses_score' => true, 'ascending' => false],
            'quality_based' => ['key' => 'quality_based', 'title' => 'Highest rated bid', 'top' => 'Highest rated bid', 'measure' => 'Technical score', 'uses_price' => false, 'uses_score' => true, 'ascending' => false],
            'lowest_quotation' => ['key' => 'lowest_quotation', 'title' => 'Abstract of quotations', 'top' => 'Lowest quotation', 'measure' => 'Quoted price', 'uses_price' => true, 'uses_score' => false, 'ascending' => true],
            default => ['key' => 'lowest_calculated_bid', 'title' => 'Abstract of bids (LCRB)', 'top' => 'Lowest calculated bid', 'measure' => 'Calculated bid price', 'uses_price' => true, 'uses_score' => false, 'ascending' => true],
        };
    }

    /**
     * @param  Collection<int, Bid>  $bids
     * @return array<int, float>|null  null when the MEARB ratio is not configured
     */
    private function scores(array $basis, Project $project, Collection $bids): ?array
    {
        if ($basis['key'] === 'mearb') {
            if ($project->quality_price_ratio === null) return $bids->isEmpty() ? [] : null;
            $quality = (float) $project->quality_price_ratio / 100;
            $lowest = (float) $bids->min(fn (Bid $bid) => (float) $bid->amount);

            return $bids->mapWithKeys(fn (Bid $bid) => [$bid->id => $quality * (float) $bid->technical_score
                + (1 - $quality) * ((float) $bid->amount > 0 ? $lowest / (float) $bid->amount * 100 : 0)])->all();
        }

        return $bids->mapWithKeys(fn (Bid $bid) => [$bid->id => $basis['uses_score'] ? (float) $bid->technical_score : (float) $bid->amount])->all();
    }

    private function label(array $result, array $basis): string
    {
        return match ($result['status']) {
            self::RANKED => $result['rank'] === 1 ? $basis['top'] : 'Rank '.$result['rank'].' of '.$result['of'],
            self::SEALED => 'Ranked after opening',
            self::ABOVE_ABC => 'Above the ABC — not ranked',
            self::BELOW_MINIMUM => 'Below minimum technical score',
            self::DISQUALIFIED => 'Disqualified — not ranked',
            default => $basis['key'] === 'mearb' ? 'Awaiting technical score or quality-price ratio' : 'Awaiting technical score',
        };
    }
}
