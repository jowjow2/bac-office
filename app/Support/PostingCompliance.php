<?php

namespace App\Support;

use App\Models\Project;
use App\Models\ProjectProceeding;

/**
 * Posting and transparency requirements of a competitive bidding, with what
 * applies at this project's ABC and what has been recorded:
 *
 *  - pre-procurement conference (RA 12009 IRR Sec. 49.1; RA 9184 Sec. 20.1)
 *  - Invitation to Bid contents (Sec. 50.2)
 *  - PhilGEPS posting for 7 calendar days (Sec. 50.3.1(b))
 *  - posting at a conspicuous place, certified by the BAC Secretariat (Sec. 50.3.1(a))
 *  - COA representative and at least two observers invited (Sec. 43.1)
 *  - video recording and livestream above the ABC threshold (Sec. 38)
 */
final class PostingCompliance
{
    public const DONE = 'done';
    public const PENDING = 'pending';
    public const OPTIONAL = 'optional';

    /** Publication checks that concern the contents of the notice. */
    private const NOTICE_FIELDS = ['award_criterion', 'evaluation_criteria', 'quality_price_ratio', 'evaluation_procedure', 'bid_opening_venue', 'electronic_submission_authority'];

    /**
     * @return list<array{key:string,label:string,basis:string,status:string,detail:string}>
     */
    public static function for(Project $project): array
    {
        if (! $project->mode()->isCompetitive()) {
            return [];
        }

        $recorded = fn (string $type) => $project->proceedings->firstWhere('type', $type);
        $published = $project->isPublishedLocally();
        $items = [];

        $conference = $recorded(ProjectProceeding::TYPE_PRE_PROCUREMENT);
        $required = $project->preProcurementConferenceRequired();
        $items[] = [
            'key' => 'pre_procurement', 'label' => 'Pre-procurement conference', 'basis' => $project->mode()->isRa12009() ? 'IRR Sec. 49.1' : 'IRR Sec. 20.1',
            'status' => $conference ? self::DONE : ($required ? self::PENDING : self::OPTIONAL),
            'detail' => $conference
                ? 'Held '.Format::date($conference->occurred_at, true)
                : ($required ? 'Mandatory for this ABC; record it before publishing.' : 'Optional at this ABC.'),
        ];

        $noticeGaps = array_intersect_key($project->publicationBlockers(), array_flip(self::NOTICE_FIELDS));
        $contactGaps = ProcuringEntity::missingContact();
        $items[] = [
            'key' => 'notice', 'label' => 'Invitation to Bid contents', 'basis' => 'IRR Sec. 50.2',
            'status' => $noticeGaps === [] && $contactGaps === [] ? self::DONE : self::PENDING,
            'detail' => match (true) {
                $noticeGaps !== [] => reset($noticeGaps),
                $contactGaps !== [] => 'Set the BAC contact '.strtolower(implode(', ', $contactGaps)).' in the system settings (.env BAC_CONTACT_*).',
                default => 'Award criterion, opening venue, contact details and submission mode are stated.',
            },
        ];

        $items[] = [
            'key' => 'philgeps', 'label' => 'PhilGEPS posting, 7 calendar days', 'basis' => 'IRR Sec. 50.3.1(b)',
            'status' => $project->philgeps_posted_at ? self::DONE : self::PENDING,
            'detail' => $project->philgeps_posted_at
                ? 'Posted '.Format::date($project->philgeps_posted_at).($project->philgeps_reference_no ? ' · Ref. '.$project->philgeps_reference_no : '')
                : ($published ? 'Record the PhilGEPS posting date and reference.' : 'After publishing, record the PhilGEPS posting.'),
        ];

        $certificate = $recorded(ProjectProceeding::TYPE_POSTING_CERTIFICATE);
        $items[] = [
            'key' => 'conspicuous', 'label' => 'Posting at a conspicuous place, 7 days', 'basis' => 'IRR Sec. 50.3.1(a)',
            'status' => $certificate ? self::DONE : self::PENDING,
            'detail' => $certificate
                ? 'Certified '.Format::date($certificate->occurred_at).($certificate->reference_no ? ' · '.$certificate->reference_no : '')
                : 'Record the certificate of the head of the BAC Secretariat after 7 days of posting.',
        ];

        $observers = $recorded(ProjectProceeding::TYPE_OBSERVERS);
        $items[] = [
            'key' => 'observers', 'label' => 'COA and observers invited', 'basis' => 'IRR Sec. 43.1',
            'status' => $observers ? self::DONE : self::PENDING,
            'detail' => $observers
                ? $observers->recipients_count.' invited · '.Format::date($observers->occurred_at)
                : 'Invite the COA representative and at least two observers to the BAC proceedings.',
        ];

        $video = $recorded(ProjectProceeding::TYPE_VIDEO_RECORDING);
        $videoRequired = $project->videoRecordingRequired();
        $items[] = [
            'key' => 'video', 'label' => 'Video recording and livestream', 'basis' => 'IRR Sec. 38',
            'status' => $video ? self::DONE : ($videoRequired ? self::PENDING : self::OPTIONAL),
            'detail' => $video
                ? 'Recorded '.Format::date($video->occurred_at).($video->reference_no ? ' · '.$video->reference_no : '')
                : ($videoRequired ? 'Required for this ABC: record the conferences and livestream the opening.' : 'Not required at this ABC.'),
        ];

        return $items;
    }
}
