<?php

use App\Models\Assignment;
use App\Models\AuditLog;
use App\Models\Bid;
use App\Models\BidDocument;
use App\Models\BidDocumentReviewEvent;
use App\Models\Project;
use App\Models\ProjectSchedule;
use App\Support\SystemNotification;
use App\Models\User;
use App\Mail\BidDocumentReviewMail;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;

uses(RefreshDatabase::class);

beforeEach(function () {
    testCase()->withoutVite();
    config()->set('filesystems.uploads_disk', 'public');
    Storage::fake('public');
    Mail::fake();

    $this->admin = User::create(['name' => 'BAC Admin', 'email' => 'review-admin@example.test', 'password' => Hash::make('password'), 'role' => 'admin', 'status' => 'active']);
    $this->staff = User::create(['name' => 'Assigned Reviewer', 'email' => 'review-staff@example.test', 'password' => Hash::make('password'), 'role' => 'staff', 'status' => 'active']);
    $this->otherStaff = User::create(['name' => 'Unassigned Reviewer', 'email' => 'other-reviewer@example.test', 'password' => Hash::make('password'), 'role' => 'staff', 'status' => 'active']);
    $this->bidder = User::create(['name' => 'Supplier Contact', 'email' => 'review-bidder@example.test', 'password' => Hash::make('password'), 'role' => 'bidder', 'status' => 'active', 'company' => 'Mindoro Supplier']);

    $this->project = Project::create([
        'reference_no' => 'SJ-BAC-REV-001', 'title' => 'Road Safety Supplies', 'description' => 'Document review test.',
        'budget' => 500000, 'status' => 'open', 'deadline' => now()->addDay(),
        'procurement_mode' => 'public_bidding', 'submission_mode' => Project::SUBMISSION_ELECTRONIC,
    ]);
    ProjectSchedule::create([
        'project_id' => $this->project->id,
        'bid_submission_deadline' => now()->addDay(),
        'bid_opening_date' => now()->addDay()->addMinutes(30),
    ]);
    $this->project->forceFill(['bids_opened_at' => now()->subMinute(), 'bids_opened_by' => $this->admin->id])->save();

    $this->bid = Bid::create([
        'project_id' => $this->project->id, 'user_id' => $this->bidder->id,
        'bid_amount' => 450000, 'status' => 'pending', 'workflow_step' => Bid::STEP_SUBMITTED,
        'submission_channel' => Bid::CHANNEL_ELECTRONIC, 'submitted_at' => now()->subMinutes(5),
        'receipt_no' => 'SJ-REV-0001', 'financial_opening_password_hash' => Hash::make('123456'),
    ]);
    $this->document = BidDocument::create([
        'bid_id' => $this->bid->id, 'requirement_key' => 'technical_proposal',
        'component' => BidDocument::COMPONENT_TECHNICAL, 'label' => 'Technical Proposal',
        'file_path' => 'bid-submissions/1/1/technical/technical-v1.pdf', 'original_name' => 'technical-v1.pdf',
        'size' => 20, 'sha256' => str_repeat('a', 64),
    ]);
    Storage::disk('public')->put($this->document->file_path, '%PDF-1.4 version one');
    $this->uploadEvent = $this->document->reviewEvents()->create([
        'bid_id' => $this->bid->id, 'requirement_key' => $this->document->requirement_key,
        'version' => 1, 'status' => BidDocumentReviewEvent::STATUS_PENDING,
        'file_path' => $this->document->file_path, 'original_name' => $this->document->original_name,
        'sha256' => $this->document->sha256, 'actor_id' => $this->bidder->id, 'uploaded_at' => now()->subMinutes(5),
    ]);
    Assignment::create(['staff_id' => $this->staff->id, 'project_id' => $this->project->id, 'role_in_project' => 'Evaluator']);
});

it('requests a revision, notifies the bidder, keeps version history, and accepts a replacement', function () {
    $this->actingAs($this->admin)
        ->post(route('admin.bid.document.review', [$this->bid, $this->document]), [
            'status' => BidDocumentReviewEvent::STATUS_NEEDS_REVISION,
            'comment' => 'Missing signature on the final page.',
        ])->assertRedirect();

    $this->assertDatabaseHas('bid_document_review_events', [
        'bid_document_id' => $this->document->id, 'version' => 1,
        'status' => 'needs_revision', 'comment' => 'Missing signature on the final page.', 'actor_id' => $this->admin->id,
    ]);
    expect(SystemNotification::forUser($this->bidder->id)->first()?->type)->toBe('bid_document_review');

    $this->actingAs($this->admin)->get(route('admin.bid.view', $this->bid), ['X-Requested-With' => 'XMLHttpRequest'])
        ->assertOk()->assertSee('Technical Proposal')->assertSee('Needs Revision');
    Mail::assertSent(BidDocumentReviewMail::class, fn ($mail) => $mail->status === 'needs_revision' && $mail->comment === 'Missing signature on the final page.');
    expect(AuditLog::where('action', 'bid_document_revision_requested')->exists())->toBeTrue();

    $this->actingAs($this->bidder)->get(route('bidder.my-bids'))
        ->assertOk()->assertSee('For Revision')->assertSee('Missing signature on the final page.')->assertSee('Revision history');

    $oldPath = $this->document->file_path;
    $this->actingAs($this->bidder)
        ->post(route('bidder.bid-document.replace', [$this->bid, $this->document]), [
            'file' => UploadedFile::fake()->createWithContent('technical-v2.pdf', "%PDF-1.4\nreplacement content"),
        ])->assertRedirect();

    $events = $this->document->fresh()->reviewEvents;
    expect($events->pluck('version')->all())->toBe([1, 1, 2])
        ->and($events->last()->status)->toBe(BidDocumentReviewEvent::STATUS_PENDING)
        ->and($this->document->fresh()->original_name)->toBe('technical-v2.pdf')
        ->and($this->document->fresh()->file_path)->not->toBe($oldPath)
        ->and(Storage::disk('public')->exists($oldPath))->toBeTrue()
        ->and(Hash::check('123456', $this->bid->fresh()->financial_opening_password_hash))->toBeTrue();

    $this->actingAs($this->staff)
        ->post(route('staff.bid.document.review', [$this->bid, $this->document]), ['status' => 'accepted'])
        ->assertRedirect();
    expect($this->document->fresh()->reviewEvents->last()->status)->toBe(BidDocumentReviewEvent::STATUS_ACCEPTED)
        ->and(AuditLog::where('action', 'bid_document_accepted')->exists())->toBeTrue();
});

it('requires a revision reason and prevents duplicate decisions without a new upload', function () {
    $this->actingAs($this->admin)->from('/admin/bidders')
        ->post(route('admin.bid.document.review', [$this->bid, $this->document]), ['status' => 'needs_revision'])
        ->assertSessionHasErrors('comment');

    $this->actingAs($this->admin)->post(route('admin.bid.document.review', [$this->bid, $this->document]), ['status' => 'accepted'])
        ->assertRedirect();
    $this->actingAs($this->admin)->from('/admin/bidders')
        ->post(route('admin.bid.document.review', [$this->bid, $this->document]), ['status' => 'needs_revision', 'comment' => 'Incorrect document uploaded.'])
        ->assertSessionHasErrors('status');
    expect($this->document->fresh()->reviewEvents()->count())->toBe(2);
});

it('allows only an assigned active staff reviewer and blocks other users', function () {
    $this->actingAs($this->otherStaff)
        ->post(route('staff.bid.document.review', [$this->bid, $this->document]), ['status' => 'accepted'])
        ->assertForbidden();

    $this->actingAs($this->bidder)
        ->post(route('admin.bid.document.review', [$this->bid, $this->document]), ['status' => 'accepted'])
        ->assertForbidden();

    $this->actingAs($this->staff)
        ->post(route('staff.bid.document.review', [$this->bid, $this->document]), ['status' => 'accepted'])
        ->assertRedirect();
});

it('rejects an inactive account even when it is assigned to the project', function () {
    $inactiveReviewer = User::create([
        'name' => 'Inactive Reviewer', 'email' => 'inactive-reviewer@example.test',
        'password' => Hash::make('password'), 'role' => 'staff', 'status' => 'inactive',
    ]);
    Assignment::create(['staff_id' => $inactiveReviewer->id, 'project_id' => $this->project->id, 'role_in_project' => 'Evaluator']);

    $this->actingAs($inactiveReviewer)
        ->post(route('staff.bid.document.review', [$this->bid, $this->document]), ['status' => 'accepted'])
        ->assertForbidden();

    expect($this->document->fresh()->reviewEvents()->count())->toBe(1);
});
it('does not review financial documents or reveal technical documents while the bid is sealed', function () {
    $financial = BidDocument::create([
        'bid_id' => $this->bid->id, 'requirement_key' => 'financial_bid_form',
        'component' => BidDocument::COMPONENT_FINANCIAL, 'label' => 'Financial Bid Form',
        'file_path' => 'encrypted/financial.bin', 'original_name' => 'financial.pdf', 'size' => 30,
    ]);

    $this->actingAs($this->admin)
        ->post(route('admin.bid.document.review', [$this->bid, $financial]), ['status' => 'accepted'])
        ->assertNotFound();

    $this->project->forceFill(['bids_opened_at' => null, 'bids_opened_by' => null])->save();
    $this->actingAs($this->admin)
        ->post(route('admin.bid.document.review', [$this->bid, $this->document]), ['status' => 'accepted'])
        ->assertForbidden();
    expect($financial->fresh()->reviewEvents)->toBeEmpty();
});

it('blocks replacements after the bid deadline and rejects a bidder changing another bidder file', function () {
    $this->document->reviewEvents()->create([
        'bid_id' => $this->bid->id, 'requirement_key' => $this->document->requirement_key,
        'version' => 1, 'status' => BidDocumentReviewEvent::STATUS_NEEDS_REVISION,
        'comment' => 'Unreadable scan.', 'file_path' => $this->document->file_path,
        'original_name' => $this->document->original_name, 'actor_id' => $this->admin->id, 'uploaded_at' => now()->subMinute(),
    ]);
    $this->project->update(['deadline' => now()->subMinute()]);
    $this->project->schedule()->update(['bid_submission_deadline' => now()->subMinute()]);

    $this->actingAs($this->bidder)->postJson(route('bidder.bid-document.replace', [$this->bid, $this->document]), [
        'file' => UploadedFile::fake()->createWithContent('late.pdf', '%PDF-1.4 late replacement'),
    ])->assertUnprocessable()->assertJsonValidationErrors('deadline');

    $otherBidder = User::create(['name' => 'Other Supplier', 'email' => 'other-bidder@example.test', 'password' => Hash::make('password'), 'role' => 'bidder', 'status' => 'active']);
    $this->bid->update(['user_id' => $otherBidder->id]);
    $this->actingAs($this->bidder)->post(route('bidder.bid-document.replace', [$this->bid, $this->document]), [
        'file' => UploadedFile::fake()->createWithContent('wrong-owner.pdf', '%PDF-1.4 no'),
    ])->assertNotFound();
});

it('requests revision for only the selected requirement through the submission review actions', function () {
    $other = BidDocument::create([
        'bid_id' => $this->bid->id, 'requirement_key' => 'other_technical',
        'component' => BidDocument::COMPONENT_TECHNICAL, 'label' => 'Other technical document',
        'file_path' => 'bid-submissions/1/1/technical/other.pdf', 'original_name' => 'other.pdf',
        'size' => 20, 'sha256' => str_repeat('b', 64),
    ]);
    $other->reviewEvents()->create([
        'bid_id' => $this->bid->id, 'requirement_key' => $other->requirement_key, 'version' => 1,
        'status' => BidDocumentReviewEvent::STATUS_PENDING, 'file_path' => $other->file_path,
        'original_name' => $other->original_name, 'actor_id' => $this->bidder->id, 'uploaded_at' => now(),
    ]);

    $this->actingAs($this->admin)->post(route('admin.bid.documents.review-submission', $this->bid), [
        'action' => 'request_revision', 'requirement_key' => $this->document->requirement_key,
        'comment' => 'Please upload a signed copy.',
    ])->assertRedirect();

    expect($this->document->fresh()->reviewEvents->last()->status)->toBe(BidDocumentReviewEvent::STATUS_NEEDS_REVISION)
        ->and($other->fresh()->reviewEvents->last()->status)->toBe(BidDocumentReviewEvent::STATUS_PENDING);
    $this->actingAs($this->bidder)->get(route('bidder.my-bids'))
        ->assertOk()->assertSee('For Revision')->assertSee('Please upload a signed copy.');
    expect(SystemNotification::forUser($this->bidder->id)->contains('type', 'bid_document_review'))->toBeTrue();
});

it('creates a missing-document revision placeholder and lets the bidder replace only that requirement', function () {
    $missing = collect($this->bid->documentChecklist())->first(
        fn (array $item): bool => ($item['component'] ?? null) === BidDocument::COMPONENT_TECHNICAL
            && ($item['required'] ?? false)
            && $item['submitted'] === false
    );
    expect($missing)->not->toBeNull();

    $this->actingAs($this->admin)->post(route('admin.bid.documents.review-submission', $this->bid), [
        'action' => 'request_revision', 'requirement_key' => $missing['key'],
        'comment' => 'This required document is missing.',
    ])->assertRedirect();

    $placeholder = BidDocument::where('bid_id', $this->bid->id)->where('requirement_key', $missing['key'])->firstOrFail();
    expect($placeholder->file_path)->toBeNull()
        ->and($placeholder->reviewEvents->last()->status)->toBe(BidDocumentReviewEvent::STATUS_NEEDS_REVISION);

    $this->actingAs($this->bidder)->get(route('bidder.my-bids'))
        ->assertOk()->assertSee('Upload requested document')->assertSee('This required document is missing.');

    $this->actingAs($this->bidder)->post(route('bidder.bid-document.replace', [$this->bid, $placeholder]), [
        'file' => UploadedFile::fake()->createWithContent('missing-document.pdf', '%PDF-1.4 replacement'),
    ])->assertRedirect();

    expect($placeholder->fresh()->file_path)->not->toBeNull()
        ->and($placeholder->fresh()->reviewEvents->last()->status)->toBe(BidDocumentReviewEvent::STATUS_PENDING)
        ->and($placeholder->fresh()->reviewEvents->last()->version)->toBe(2);
});

it('approves all pending technical documents with one submission action and notifies the bidder', function () {
    foreach (collect($this->bid->documentChecklist())->where('component', BidDocument::COMPONENT_TECHNICAL)->where('required', true) as $item) {
        $document = BidDocument::firstOrCreate(
            ['bid_id' => $this->bid->id, 'requirement_key' => $item['key']],
            [
                'component' => BidDocument::COMPONENT_TECHNICAL, 'label' => $item['label'],
                'file_path' => 'bid-submissions/'.$this->bid->project_id.'/'.$this->bid->id.'/technical/'.$item['key'].'.pdf',
                'original_name' => $item['key'].'.pdf', 'size' => 20, 'sha256' => str_repeat('c', 64),
            ],
        );
        if ($document->reviewEvents()->doesntExist()) {
            $document->reviewEvents()->create([
                'bid_id' => $this->bid->id, 'requirement_key' => $document->requirement_key,
                'version' => 1, 'status' => BidDocumentReviewEvent::STATUS_PENDING,
                'file_path' => $document->file_path, 'original_name' => $document->original_name,
                'sha256' => $document->sha256, 'actor_id' => $this->bidder->id, 'uploaded_at' => now(),
            ]);
        }
    }

    $this->actingAs($this->admin)->post(route('admin.bid.documents.review-submission', $this->bid), [
        'action' => 'approve',
    ])->assertRedirect();

    expect(BidDocumentReviewEvent::where('bid_id', $this->bid->id)
        ->where('status', BidDocumentReviewEvent::STATUS_ACCEPTED)->count())
        ->toBe(BidDocument::where('bid_id', $this->bid->id)->where('component', BidDocument::COMPONENT_TECHNICAL)->count());
    expect(SystemNotification::forUser($this->bidder->id)->contains('title', 'Bid documents approved'))->toBeTrue();
});

it('blocks submission review actions for an unassigned staff reviewer', function () {
    $this->actingAs($this->otherStaff)->post(route('staff.bid.documents.review-submission', $this->bid), [
        'action' => 'approve',
    ])->assertForbidden();

    expect($this->document->fresh()->reviewEvents->last()->status)->toBe(BidDocumentReviewEvent::STATUS_PENDING);
});
it('allows the assigned active staff reviewer to use the simple revision action', function () {
    $this->actingAs($this->staff)->post(route('staff.bid.documents.review-submission', $this->bid), [
        'action' => 'request_revision',
        'requirement_key' => $this->document->requirement_key,
        'comment' => 'Please correct the uploaded form.',
    ])->assertRedirect();

    expect($this->document->fresh()->reviewEvents->last()->status)->toBe(BidDocumentReviewEvent::STATUS_NEEDS_REVISION);
});
it('allows only one active revision request until the bidder resubmits the requested document', function () {
    $secondRequirement = collect($this->bid->documentChecklist())->first(
        fn (array $item): bool => ($item['component'] ?? null) === BidDocument::COMPONENT_TECHNICAL
            && $item['key'] !== $this->document->requirement_key
    );
    expect($secondRequirement)->not->toBeNull();

    $second = BidDocument::create([
        'bid_id' => $this->bid->id, 'requirement_key' => $secondRequirement['key'],
        'component' => BidDocument::COMPONENT_TECHNICAL, 'label' => $secondRequirement['label'],
        'file_path' => 'bid-submissions/1/1/technical/second.pdf', 'original_name' => 'second.pdf',
        'size' => 20, 'sha256' => str_repeat('d', 64),
    ]);
    $second->reviewEvents()->create([
        'bid_id' => $this->bid->id, 'requirement_key' => $second->requirement_key, 'version' => 1,
        'status' => BidDocumentReviewEvent::STATUS_PENDING, 'file_path' => $second->file_path,
        'original_name' => $second->original_name, 'actor_id' => $this->bidder->id, 'uploaded_at' => now(),
    ]);

    $this->actingAs($this->admin)->post(route('admin.bid.documents.review-submission', $this->bid), [
        'action' => 'request_revision', 'requirement_key' => $this->document->requirement_key,
        'comment' => 'Please correct this document.',
    ])->assertRedirect();

    $this->actingAs($this->admin)->from(route('admin.bids', ['view_bid' => $this->bid->id]))
        ->post(route('admin.bid.documents.review-submission', $this->bid), [
            'action' => 'request_revision', 'requirement_key' => $second->requirement_key,
            'comment' => 'Please correct this other document.',
        ])->assertSessionHasErrors('status');

    expect($second->fresh()->reviewEvents->last()->status)->toBe(BidDocumentReviewEvent::STATUS_PENDING);
});