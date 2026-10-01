<?php

namespace App\Http\Controllers;

use App\Models\Bid;
use App\Support\BidProgress;
use App\Support\ProcurementClock;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Response;

class AwardRecommendationDocumentController extends Controller
{
    /**
     * Build an export-ready BAC resolution or post-qualification report from
     * the recorded procurement facts. The PDF is a working document for BAC
     * review and signatures; it does not approve or issue an award.
     */
    public function download(Bid $bid, string $document): Response
    {
        abort_unless(in_array($document, ['resolution', 'post-qualification-report'], true), 404);

        $bid->loadMissing(['project.requirement', 'user', 'trackings.creator']);
        $facts = $bid->progress()->facts();

        abort_unless(
            $facts['recommended']
                && $facts['post_qualification_result'] === Bid::POST_QUALIFICATION_PASSED
                && ! $bid->isFinancialSealed(),
            404
        );

        $project = $bid->project;
        abort_unless($project && filled($bid->bac_resolution_no) && $bid->bac_resolution_date, 404);

        $timezone = config('bac-office.display_timezone', 'Asia/Manila');
        $postQualification = $bid->trackings
            ->filter(fn ($event) => $event->stage === BidProgress::STAGE_POST_QUALIFICATION && $event->decision === 'passed')
            ->sortByDesc('created_at')
            ->first();
        $evaluation = $bid->trackings
            ->filter(fn ($event) => $event->stage === BidProgress::STAGE_EVALUATION && $event->decision === 'passed')
            ->sortByDesc('created_at')
            ->first();
        $details = (array) ($postQualification?->details ?? []);
        $evaluationDetails = (array) ($evaluation?->details ?? []);
        $criteria = collect($evaluationDetails['criteria'] ?? [])->filter()->values();
        $criterionResults = collect($evaluationDetails['criterion_results'] ?? [])
            ->filter(fn ($row) => is_array($row) && filled($row['result'] ?? null))
            ->values();

        $viewData = [
            'documentType' => $document,
            'bid' => $bid,
            'project' => $project,
            'bidderName' => $bid->user?->company ?: ($bid->user?->name ?? 'Not recorded'),
            'resolutionNo' => $bid->bac_resolution_no,
            'resolutionDate' => $bid->bac_resolution_date->format('F d, Y'),
            'postQualification' => $postQualification,
            'evaluation' => $evaluation,
            'evaluationFindings' => $evaluationDetails['findings'] ?? $evaluation?->reason,
            'postQualificationFindings' => $details['findings'] ?? $postQualification?->reason,
            'qualificationBasis' => $details['qualification_basis'] ?? $details['basis'] ?? $project->requirement?->qualification_notes,
            'criteria' => $criteria,
            'criterionResults' => $criterionResults,
            'generatedAt' => app(ProcurementClock::class)->now()->format('F d, Y h:i A'),
            'timezone' => $timezone,
            'awardCriterion' => \App\Models\Project::AWARD_CRITERIA[$project->award_criterion] ?? 'As stated in the bidding documents',
            'modeLabel' => $project->mode()->label(),
        ];

        $pdf = Pdf::loadView('admin.award-recommendation-document', $viewData)->setPaper('a4');
        $safeReference = preg_replace('/[^A-Za-z0-9_-]+/', '-', (string) ($project->reference_no ?: 'procurement'));
        $suffix = $document === 'resolution' ? 'BAC-Resolution' : 'Post-Qualification-Report';

        return $pdf->download($safeReference.'-'.$suffix.'.pdf');
    }
}
