<?php

namespace App\Support;

use App\Models\AuditLog;
use App\Models\Bid;
use App\Models\BidDocument;
use App\Models\BidDocumentReviewEvent;
use App\Models\User;
use App\Mail\BidDocumentReviewMail;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class BidDocumentReviewWorkflow
{
    public function review(Bid $bid, BidDocument $document, User $reviewer, string $status, ?string $comment = null): BidDocumentReviewEvent
    {
        $this->assertReviewer($bid, $reviewer);
        $this->assertDocument($bid, $document);
        $this->assertReviewable($bid);

        if ($status === BidDocumentReviewEvent::STATUS_NEEDS_REVISION && blank($comment)) {
            throw ValidationException::withMessages(['comment' => 'Explain what the bidder needs to correct.']);
        }

        $event = DB::transaction(function () use ($bid, $document, $reviewer, $status, $comment): BidDocumentReviewEvent {
            $locked = BidDocument::query()->lockForUpdate()->findOrFail($document->id);
            $latest = $locked->reviewEvents()->reorder()->latest('id')->first();

            if (($latest?->status ?? BidDocumentReviewEvent::STATUS_PENDING) !== BidDocumentReviewEvent::STATUS_PENDING) {
                throw ValidationException::withMessages(['status' => 'This document already has a decision. The bidder must submit a requested replacement before it can be reviewed again.']);
            }

            if ($status === BidDocumentReviewEvent::STATUS_NEEDS_REVISION) {
                $otherOutstanding = BidDocument::query()
                    ->where('bid_id', $bid->id)
                    ->where('component', BidDocument::COMPONENT_TECHNICAL)
                    ->whereKeyNot($locked->id)
                    ->with('reviewEvents')
                    ->lockForUpdate()
                    ->get()
                    ->first(fn (BidDocument $candidate) => $candidate->reviewEvents->last()?->status === BidDocumentReviewEvent::STATUS_NEEDS_REVISION);

                if ($otherOutstanding) {
                    throw ValidationException::withMessages(['status' => 'Wait for the bidder to replace the currently requested document before requesting another revision.']);
                }
            }

            $version = (int) ($latest?->version ?? 1);
            $event = $this->record($bid, $locked, $reviewer, $status, $comment, $version, $latest?->uploaded_at ?? $locked->created_at);

            AuditLog::log(
                $status === BidDocumentReviewEvent::STATUS_ACCEPTED ? 'bid_document_accepted' : 'bid_document_revision_requested',
                $locked,
                ['status' => BidDocumentReviewEvent::STATUS_PENDING, 'version' => $version],
                ['status' => $status, 'version' => $version, 'comment' => $comment],
                ['user_id' => $reviewer->id],
            );

            $title = $status === BidDocumentReviewEvent::STATUS_ACCEPTED ? 'Bid document accepted' : 'Bid document needs revision';
            $message = $status === BidDocumentReviewEvent::STATUS_ACCEPTED
                ? $locked->label.' for '.$bid->project->title.' was accepted by the BAC.'
                : $locked->label.' for '.$bid->project->title.' needs revision: '.$comment;
            SystemNotification::createForUser($bid->user_id, $title, $message, 'bid_document_review', [
                'bid_id' => $bid->id,
                'project_id' => $bid->project_id,
                'document_id' => $locked->id,
            ]);

            return $event;
        });

        $this->emailBidder($bid, $document, $status, (int) $event->version, $comment);

        return $event;
    }

    public function requestRevisionForRequirement(Bid $bid, string $requirementKey, User $reviewer, string $comment): BidDocumentReviewEvent
    {
        $this->assertReviewer($bid, $reviewer);
        $this->assertReviewable($bid);

        $requirement = collect($bid->documentChecklist())->first(
            fn (array $item): bool => $item['key'] === $requirementKey && ($item['component'] ?? null) === BidDocument::COMPONENT_TECHNICAL
        );
        abort_unless($requirement, 404);

        $document = BidDocument::query()
            ->where('bid_id', $bid->id)
            ->where('requirement_key', $requirementKey)
            ->first();

        if (! $document) {
            $document = BidDocument::create([
                'bid_id' => $bid->id,
                'requirement_key' => $requirementKey,
                'component' => BidDocument::COMPONENT_TECHNICAL,
                'label' => $requirement['label'],
                'file_path' => null,
                'original_name' => null,
                'size' => null,
                'sha256' => null,
            ]);
        }

        return $this->review($bid, $document, $reviewer, BidDocumentReviewEvent::STATUS_NEEDS_REVISION, $comment);
    }

    public function approveSubmission(Bid $bid, User $reviewer): int
    {
        $this->assertReviewer($bid, $reviewer);
        $this->assertReviewable($bid);

        $bid->unsetRelation('documents');
        $missing = collect($bid->documentChecklist())
            ->filter(fn (array $item): bool => ($item['component'] ?? null) === BidDocument::COMPONENT_TECHNICAL
                && ($item['required'] ?? true)
                && $item['submitted'] === false);

        if ($missing->isNotEmpty()) {
            throw ValidationException::withMessages([
                'submission' => 'Cannot approve yet. Required technical documents are missing: '.$missing->pluck('label')->implode(', '),
            ]);
        }

        return DB::transaction(function () use ($bid, $reviewer): int {
            $documents = BidDocument::query()
                ->where('bid_id', $bid->id)
                ->where('component', BidDocument::COMPONENT_TECHNICAL)
                ->orderBy('id')
                ->lockForUpdate()
                ->get();

            if ($documents->isEmpty()) {
                throw ValidationException::withMessages(['submission' => 'No technical documents are available to approve.']);
            }

            $accepted = 0;
            foreach ($documents as $document) {
                $latest = $document->reviewEvents()->reorder()->latest('id')->lockForUpdate()->first();

                if ($latest?->status === BidDocumentReviewEvent::STATUS_NEEDS_REVISION) {
                    throw ValidationException::withMessages([
                        'submission' => 'Resolve every requested revision before approving the submission.',
                    ]);
                }
                if ($latest?->status === BidDocumentReviewEvent::STATUS_ACCEPTED) {
                    continue;
                }

                $this->record(
                    $bid,
                    $document,
                    $reviewer,
                    BidDocumentReviewEvent::STATUS_ACCEPTED,
                    null,
                    (int) ($latest?->version ?? 1),
                    $latest?->uploaded_at ?? $document->created_at,
                );

                AuditLog::log(
                    'bid_document_accepted',
                    $document,
                    ['status' => $latest?->status ?? BidDocumentReviewEvent::STATUS_PENDING],
                    ['status' => BidDocumentReviewEvent::STATUS_ACCEPTED, 'version' => (int) ($latest?->version ?? 1)],
                    ['user_id' => $reviewer->id],
                );
                $accepted++;
            }

            if ($accepted > 0) {
                AuditLog::log('bid_submission_documents_approved', $bid,
                    ['pending_technical_documents' => $accepted],
                    ['status' => 'accepted', 'accepted_documents' => $accepted],
                    ['user_id' => $reviewer->id],
                );
                SystemNotification::createForUser(
                    $bid->user_id,
                    'Bid documents approved',
                    'The BAC approved the technical and eligibility documents for '.$bid->project->title.'.',
                    'bid_document_review',
                    ['bid_id' => $bid->id, 'project_id' => $bid->project_id],
                );
            }

            return $accepted;
        });
    }
    public function replace(Bid $bid, BidDocument $document, User $bidder, UploadedFile $file): BidDocumentReviewEvent
    {
        abort_unless((int) $bid->user_id === (int) $bidder->id, 404);
        $this->assertDocument($bid, $document);

        if ($document->component !== BidDocument::COMPONENT_TECHNICAL) {
            throw ValidationException::withMessages(['document' => 'Financial documents cannot be changed through document revision.']);
        }
        if (! $bid->isModifiableOnline()) {
            throw ValidationException::withMessages(['document' => 'This bid is no longer eligible for document revision.']);
        }

        $project = $bid->project()->with('schedule')->firstOrFail();
        app(BidSubmission::class)->assertBeforeDeadline($project);

        $latest = $document->reviewEvents()->reorder()->latest('id')->first();
        if ($latest?->status !== BidDocumentReviewEvent::STATUS_NEEDS_REVISION) {
            throw ValidationException::withMessages(['document' => 'A replacement can be uploaded only after the BAC marks this document Needs Revision.']);
        }

        $maxKb = (int) config('bac-office.registration.max_document_size_kb', 20480);
        if ($file->getSize() > $maxKb * 1024) {
            throw ValidationException::withMessages(['file' => 'The replacement file exceeds the allowed file size.']);
        }

        $path = Uploads::store(
            $file,
            'bid-submissions/'.$bid->project_id.'/'.$bid->id.'/technical',
            Str::slug($document->requirement_key).'_v'.((int) $latest->version + 1).'_'.Str::random(10).'.'.strtolower($file->getClientOriginalExtension()),
        );

        try {
            return DB::transaction(function () use ($bid, $document, $bidder, $file, $path): BidDocumentReviewEvent {
                $locked = BidDocument::query()->lockForUpdate()->findOrFail($document->id);
                $previous = $locked->reviewEvents()->reorder()->latest('id')->first();
                if ($previous?->status !== BidDocumentReviewEvent::STATUS_NEEDS_REVISION) {
                    throw ValidationException::withMessages(['document' => 'This revision request has already been resolved. Refresh the page and review the latest document status.']);
                }

                $project = $bid->project()->with('schedule')->firstOrFail();
                app(BidSubmission::class)->assertBeforeDeadline($project);

                $version = (int) $previous->version + 1;
                $locked->update([
                    'file_path' => $path,
                    'original_name' => $file->getClientOriginalName(),
                    'size' => $file->getSize(),
                    'sha256' => hash_file('sha256', $file->getRealPath()),
                ]);

                $event = $this->record($bid, $locked, $bidder, BidDocumentReviewEvent::STATUS_PENDING, null, $version, now());
                AuditLog::log('bid_document_replaced', $locked,
                    ['status' => BidDocumentReviewEvent::STATUS_NEEDS_REVISION, 'version' => $previous->version, 'file' => $previous->original_name],
                    ['status' => BidDocumentReviewEvent::STATUS_PENDING, 'version' => $version, 'file' => $locked->original_name],
                    ['user_id' => $bidder->id],
                );

                $notice = ($bidder->company ?: $bidder->name).' uploaded a revised '.$locked->label.' for '.$project->title.'.';
                SystemNotification::createForRole('admin', 'Revised bid document uploaded', $notice, 'bid_document_review', [
                    'bid_id' => $bid->id, 'project_id' => $bid->project_id, 'document_id' => $locked->id,
                ]);
                SystemNotification::createForUsers($project->assignments()->pluck('staff_id'), 'Revised bid document uploaded', $notice, 'bid_document_review', [
                    'bid_id' => $bid->id, 'project_id' => $bid->project_id, 'document_id' => $locked->id,
                ]);

                return $event;
            });
        } catch (\Throwable $exception) {
            Uploads::delete($path);
            throw $exception;
        }
    }

    private function record(Bid $bid, BidDocument $document, User $actor, string $status, ?string $comment, int $version, $uploadedAt): BidDocumentReviewEvent
    {
        return BidDocumentReviewEvent::create([
            'bid_id' => $bid->id,
            'bid_document_id' => $document->id,
            'requirement_key' => $document->requirement_key,
            'version' => $version,
            'status' => $status,
            'comment' => $comment,
            'file_path' => $document->file_path,
            'original_name' => $document->original_name,
            'sha256' => $document->sha256,
            'actor_id' => $actor->id,
            'uploaded_at' => $uploadedAt,
        ]);
    }

    private function assertReviewer(Bid $bid, User $reviewer): void
    {
        abort_unless($reviewer->status === 'active', 403, 'An active BAC account is required to review bid documents.');
        if ($reviewer->role === 'admin') return;
        abort_unless($reviewer->role === 'staff', 403);
        abort_unless($bid->project()->whereHas('assignments', fn ($query) => $query->where('staff_id', $reviewer->id))->exists(), 403, 'You are not assigned to this project.');
    }

    private function emailBidder(Bid $bid, BidDocument $document, string $status, int $version, ?string $comment): void
    {
        $bidder = $bid->user;
        if (! $bidder?->email || blank(config('mail.default'))) {
            return;
        }

        try {
            Mail::to($bidder->email)->send(new BidDocumentReviewMail(
                (string) $bid->project?->title,
                $document->label,
                $status,
                $version,
                $comment,
            ));
        } catch (\Throwable $exception) {
            report($exception);
        }
    }
    private function assertDocument(Bid $bid, BidDocument $document): void
    {
        abort_unless((int) $document->bid_id === (int) $bid->id, 404);
        abort_unless($document->component === BidDocument::COMPONENT_TECHNICAL, 404);
    }

    private function assertReviewable(Bid $bid): void
    {
        if ($bid->isDraft() || $bid->isSealed()) {
            abort(403, 'Technical and eligibility documents remain sealed until the authorized bid opening.');
        }
    }
}