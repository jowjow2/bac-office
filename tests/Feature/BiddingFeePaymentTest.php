<?php

use App\Models\AuditLog;
use App\Models\Bid;
use App\Models\BiddingFeePayment;
use App\Models\Project;
use App\Models\ProjectSchedule;
use App\Models\User;
use App\Models\UserNotification;
use App\Support\BidSubmissionRequirements;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;

uses(RefreshDatabase::class);

beforeEach(function () {
    testCase()->withoutVite();
    config()->set('filesystems.uploads_disk', 'public');
    Storage::fake('public');

    $user = fn (string $email, string $role, array $extra = []) => User::create(array_merge([
        'name' => ucfirst($role).' User',
        'email' => $email,
        'password' => Hash::make('password'),
        'role' => $role,
        'status' => 'active',
    ], $extra));

    $this->admin = $user('fee-admin@example.com', 'admin');
    $this->staff = $user('fee-staff@example.com', 'staff');
    $this->bidder = $user('fee-bidder@example.com', 'bidder', ['name' => 'Juan Builder', 'company' => 'Mindoro Builders']);
    $this->otherBidder = $user('fee-other@example.com', 'bidder', ['name' => 'Ana Supplier', 'company' => 'Ana Trading']);

    $this->makeProject = function (array $attributes = []): Project {
        $project = Project::create(array_merge([
            'title' => 'Supply of Office Equipment',
            'description' => 'Office equipment for the municipal hall.',
            'reference_no' => 'SJOM-2026-G-021',
            'philgeps_reference_no' => '55667788',
            'category' => 'goods',
            'procurement_mode' => 'public_bidding',
            'budget' => 1500000,
            'deadline' => now()->addDays(5),
            'status' => 'open',
            'bidding_documents_fee' => 5000,
            'payment_venue' => 'BAC Secretariat, 2F Municipal Hall',
        ], $attributes));
        ProjectSchedule::create([
            'project_id' => $project->id,
            'date_posted' => now()->subDays(2)->toDateString(),
            'bid_submission_deadline' => $project->deadline,
            'bid_opening_date' => $project->deadline->copy()->addHour(),
        ]);

        return $project->fresh();
    };

    $this->project = ($this->makeProject)();

    $this->record = fn (array $data = [], ?User $as = null) => testCase()
        ->actingAs($as ?? $this->staff)
        ->post(route(($as ?? $this->staff)->role.'.payments.store'), array_merge([
            '_form' => 'record',
            'project_id' => $this->project->id,
            'user_id' => $this->bidder->id,
            'amount' => '5000',
            'or_number' => 'OR-7654321',
            'paid_at' => now()->toDateString(),
        ], $data));

    $this->submitBid = function (?Project $project = null) {
        $project ??= $this->project;
        $files = collect(BidSubmissionRequirements::for($project)->requiredKeys())
            ->mapWithKeys(fn (string $key) => [$key => UploadedFile::fake()->createWithContent($key.'.pdf', "%PDF-1.4\n1 0 obj\n<<>>\nendobj\ntrailer\n<<>>\n%%EOF")])
            ->all();

        return testCase()->actingAs($this->bidder)->post(route('bidder.bids.store', $project), [
            'project_id' => $project->id,
            'bid_amount' => '1450000',
            'documents' => $files,
            // Online bids set the 6-digit financial PIN.
            'financial_password' => '482913',
            'financial_password_confirmation' => '482913',
        ]);
    };
});

it('blocks the online bid until the BAC records the bidding fee payment', function () {
    testCase()->actingAs($this->bidder)->get(route('bidder.available-projects'))
        ->assertOk()
        ->assertSee('Pay ₱5,000.00 at BAC')
        ->assertSee('How to pay')
        ->assertSee('Pay the bidding documents fee first')
        ->assertSee('BAC Secretariat, 2F Municipal Hall')
        ->assertSee('Payment required')
        ->assertSee('data-payment-locked="true"', false);

    // The server refuses the bid even if the locked form is bypassed.
    ($this->submitBid)()->assertSessionHasErrors('payment');
    expect(Bid::count())->toBe(0)
        ->and(Storage::disk('public')->allFiles('bid-submissions'))->toBe([]);

    ($this->record)()
        ->assertSessionHasNoErrors()
        ->assertRedirect(route('staff.payments'));

    $payment = BiddingFeePayment::firstOrFail();
    expect($payment->amount)->toBe('5000.00')
        ->and($payment->verified_at)->not->toBeNull()
        ->and($payment->or_number)->toBe('OR-7654321')
        ->and($payment->recorded_by)->toBe($this->staff->id)
        ->and(AuditLog::where('action', 'bidding_fee_payment_recorded')->exists())->toBeTrue();

    $notification = UserNotification::where('user_id', $this->bidder->id)->where('type', 'bidding_fee_paid')->firstOrFail();
    expect($notification->message)->toContain('OR No. OR-7654321')
        ->and($notification->data['url'])->toBe(route('bidder.available-projects', ['bid_project' => $this->project->id]));

    testCase()->actingAs($this->bidder)->get(route('bidder.available-projects'))
        ->assertSee('Bidding fee paid')
        ->assertSee('OR No. OR-7654321')
        ->assertSee('data-payment-locked="false"', false)
        ->assertDontSee('Pay the bidding documents fee first');

    ($this->submitBid)()->assertSessionHasNoErrors();
    expect(Bid::firstOrFail()->isDraft())->toBeFalse();

    testCase()->actingAs($this->staff)->get(route('staff.payments'))
        ->assertOk()
        ->assertSee('OR-7654321')
        ->assertSee('Bid submitted');
});

it('does not let a payment for another project unlock a bid', function () {
    ($this->record)()->assertSessionHasNoErrors();
    $otherProject = ($this->makeProject)(['title' => 'Different Project', 'reference_no' => 'SJOM-2026-G-099']);

    ($this->submitBid)($otherProject)->assertSessionHasErrors('payment');
    expect(Bid::where('project_id', $otherProject->id)->where('user_id', $this->bidder->id)->exists())->toBeFalse();
});

it('blocks manual draft uploads until payment is verified', function () {
    $this->project->update(['submission_mode' => Project::SUBMISSION_MANUAL]);

    testCase()->actingAs($this->bidder)->get(route('bidder.available-projects'))
        ->assertOk()
        ->assertSee('Payment required')
        ->assertSee('data-payment-locked="true"', false);

    ($this->submitBid)()->assertSessionHasErrors('payment');
    expect(Bid::count())->toBe(0)
        ->and(Storage::disk('public')->allFiles('bid-submissions'))->toBe([]);
});
it('refuses a paid bid after the submission deadline', function () {
    ($this->record)()->assertSessionHasNoErrors();
    $this->travel(6)->days();

    ($this->submitBid)()->assertSessionHasErrors('deadline');
    expect(Bid::count())->toBe(0);
});

it('keeps the sample documents fee separate from the project ABC in the bidder modal', function () {
    ($this->makeProject)(['title' => 'Demo fee and ABC', 'reference_no' => 'SJOM-2026-G-098', 'budget' => 500000, 'bidding_documents_fee' => 500]);

    testCase()->actingAs($this->bidder)->get(route('bidder.available-projects'))
        ->assertOk()
        ->assertSee('Approved Budget for the Contract')
        ->assertSee('500,000.00')
        ->assertSee('500.00');
});
it('does not ask for payment when the project has no bidding fee', function () {
    $free = ($this->makeProject)(['title' => 'Free Documents Project', 'reference_no' => 'SJOM-2026-G-022', 'bidding_documents_fee' => null]);

    expect($free->requiresBiddingFee())->toBeFalse()
        ->and($free->hasPaidBiddingFee($this->bidder))->toBeTrue();

    ($this->submitBid)($free)->assertSessionHasNoErrors();
    expect(Bid::where('project_id', $free->id)->firstOrFail()->isDraft())->toBeFalse();

    ($this->record)(['project_id' => $free->id])->assertSessionHasErrors('project_id');
});

it('validates payments against the fee, the receipt number, the bidder and the bidding period', function () {
    ($this->record)(['amount' => '4999.99'])->assertSessionHasErrors('amount');
    ($this->record)(['paid_at' => now()->addDay()->toDateString()])->assertSessionHasErrors('paid_at');
    ($this->record)(['paid_at' => now()->subDays(3)->toDateString()])->assertSessionHasErrors('paid_at');
    ($this->record)(['or_number' => '   '])->assertSessionHasErrors('or_number');
    ($this->record)(['user_id' => $this->admin->id])->assertSessionHasErrors('user_id');

    $pending = User::create(['name' => 'Pending Bidder', 'email' => 'fee-pending@example.com', 'password' => Hash::make('password'), 'role' => 'bidder', 'status' => 'pending']);
    ($this->record)(['user_id' => $pending->id])->assertSessionHasErrors('user_id');

    expect(BiddingFeePayment::count())->toBe(0);

    ($this->record)(['or_number' => '  OR-1001  '])->assertSessionHasNoErrors();
    expect(BiddingFeePayment::firstOrFail()->or_number)->toBe('OR-1001');

    // One receipt, one payment; one payment per bidder per project.
    ($this->record)(['user_id' => $this->otherBidder->id, 'or_number' => 'OR-1001'])->assertSessionHasErrors('or_number');
    ($this->record)(['or_number' => 'OR-1002'])->assertSessionHasErrors('user_id');

    // Once submission closes, a payment could no longer be used.
    $this->travel(6)->days();
    ($this->record)(['user_id' => $this->otherBidder->id, 'or_number' => 'OR-1003'])->assertSessionHasErrors('project_id');

    expect(BiddingFeePayment::count())->toBe(1);
});

it('lets the BAC correct a payment and remove it only before a bid uses it', function () {
    ($this->record)()->assertSessionHasNoErrors();
    $payment = BiddingFeePayment::firstOrFail();

    testCase()->actingAs($this->staff)->put(route('staff.payments.update', $payment), [
        '_form' => 'edit',
        '_payment_id' => $payment->id,
        'amount' => '4000',
        'or_number' => 'OR-7654322',
        'paid_at' => now()->toDateString(),
    ])->assertSessionHasErrors('amount');

    testCase()->actingAs($this->staff)->put(route('staff.payments.update', $payment), [
        '_form' => 'edit',
        '_payment_id' => $payment->id,
        'amount' => '5000',
        'or_number' => 'OR-7654322',
        'paid_at' => now()->subDay()->toDateString(),
        'notes' => 'Corrected OR number.',
    ])->assertSessionHasNoErrors()->assertRedirect(route('staff.payments'));

    $payment->refresh();
    expect($payment->or_number)->toBe('OR-7654322')
        ->and($payment->paid_at->toDateString())->toBe(now()->subDay()->toDateString())
        ->and($payment->notes)->toBe('Corrected OR number.')
        ->and(AuditLog::where('action', 'bidding_fee_payment_updated')->exists())->toBeTrue();

    testCase()->actingAs($this->staff)->delete(route('staff.payments.destroy', $payment))->assertSessionHasNoErrors();
    expect(BiddingFeePayment::count())->toBe(0);

    ($this->record)()->assertSessionHasNoErrors();
    ($this->submitBid)()->assertSessionHasNoErrors();
    $payment = BiddingFeePayment::firstOrFail();

    testCase()->actingAs($this->admin)->delete(route('admin.payments.destroy', $payment))->assertSessionHasErrors('payment');
    expect(BiddingFeePayment::whereKey($payment->id)->exists())->toBeTrue();
});

it('locks the fee once a payment is recorded', function () {
    ($this->record)([], $this->admin)->assertSessionHasNoErrors()->assertRedirect(route('admin.payments'));

    testCase()->actingAs($this->admin)->post(route('admin.project.submission-settings', $this->project), ['bidding_documents_fee' => '6000'])
        ->assertSessionHasErrors('bidding_documents_fee');
    expect($this->project->fresh()->bidding_documents_fee)->toBe('5000.00');

    // Other settings can still be saved with the same fee.
    testCase()->actingAs($this->admin)->post(route('admin.project.submission-settings', $this->project), [
        'bidding_documents_fee' => '5000.00',
        'payment_venue' => 'Treasurer\'s Office, Municipal Hall',
    ])->assertSessionHasNoErrors();
    expect($this->project->fresh()->payment_venue)->toBe('Treasurer\'s Office, Municipal Hall');

    testCase()->actingAs($this->admin)->get(route('admin.project.view', $this->project), ['X-Requested-With' => 'XMLHttpRequest'])
        ->assertOk()
        ->assertSee('Notice, Submission &amp; Bidding Fee', false)
        ->assertSee('Locked: payments were already recorded.');
});

it('edits the fee from the Edit Project form until a payment is recorded', function () {
    $edit = fn (array $data) => testCase()->actingAs($this->admin)->put(route('admin.project.update', $this->project), array_merge([
        'title' => $this->project->title,
        'description' => $this->project->description,
        'budget' => '1,500,000.00',
        'status' => 'open',
        'deadline' => $this->project->deadline->format('Y-m-d\TH:i'),
    ], $data));

    testCase()->actingAs($this->admin)->get(route('admin.project.edit', $this->project))
        ->assertOk()
        ->assertSee('name="bidding_documents_fee"', false)
        ->assertSee('value="5,000.00"', false);

    // Typed with separators; the payment place is saved with it. Lower than the ₱5,000
    // maximum, so it needs a reason; the project is published, so it is an amendment.
    $edit(['bidding_documents_fee' => '1,500.00', 'payment_venue' => 'Treasurer\'s Office'])->assertSessionHasErrors('bidding_fee_reason');
    $edit([
        'bidding_documents_fee' => '1,500.00',
        'payment_venue' => 'Treasurer\'s Office',
        'bidding_fee_reason' => 'Reduced by BAC Resolution No. 2026-020 for small suppliers.',
        'bidding_fee_amendment_reference' => 'Supplemental Bid Bulletin No. 1',
        'bidding_fee_amendment_reason' => 'Fee lowered before any bidder paid.',
    ])->assertSessionHasNoErrors();
    expect($this->project->fresh()->bidding_documents_fee)->toBe('1500.00')
        ->and($this->project->fresh()->payment_venue)->toBe('Treasurer\'s Office');

    // Without the fee in the request (a locked, disabled field), the fee stays as it is.
    $edit([])->assertSessionHasNoErrors();
    expect($this->project->fresh()->bidding_documents_fee)->toBe('1500.00');

    ($this->record)(['amount' => '1500'], $this->admin)->assertSessionHasNoErrors();
    $edit([
        'bidding_documents_fee' => '2,000.00',
        'bidding_fee_reason' => 'Reduced by BAC Resolution No. 2026-020 for small suppliers.',
        'bidding_fee_amendment_reference' => 'Supplemental Bid Bulletin No. 2',
        'bidding_fee_amendment_reason' => 'Trying to change it after a payment.',
    ])->assertSessionHasErrors('bidding_documents_fee');
    expect($this->project->fresh()->bidding_documents_fee)->toBe('1500.00');

    testCase()->actingAs($this->admin)->get(route('admin.project.edit', $this->project))
        ->assertOk()
        ->assertSee('Payments were already recorded for this project');
});

it('shows the payments board to the admin and staff only', function () {
    ($this->record)()->assertSessionHasNoErrors();

    foreach ([$this->admin, $this->staff] as $user) {
        testCase()->actingAs($user)->get(route($user->role.'.payments'))
            ->assertOk()
            ->assertDontSee('Bidders pay the bidding documents fee in person at the BAC office')
            ->assertSee('Record a payment')
            ->assertSee('Supply of Office Equipment')
            ->assertSee('Mindoro Builders')
            ->assertSee('OR-7654321')
            ->assertSee('Awaiting bid');
    }

    testCase()->actingAs($this->admin)->get(route('admin.payments', ['q' => 'no-such-receipt']))
        ->assertOk()
        ->assertSee('No payments match these filters');

    expect(testCase()->actingAs($this->bidder)->get(route('admin.payments'))->status())->not->toBe(200)
        ->and(testCase()->actingAs($this->bidder)->get(route('staff.payments'))->status())->not->toBe(200)
        ->and(testCase()->actingAs($this->staff)->get(route('admin.payments'))->status())->not->toBe(200);
});

it('notifies the bidder when the payment is recorded so an open page can unlock the bid live', function () {
    // While unpaid, the bid form is locked and marked with its project for the live check.
    testCase()->actingAs($this->bidder)->get(route('bidder.available-projects'))
        ->assertOk()
        ->assertSee('data-payment-locked="true" data-project-id="'.$this->project->id.'"', false)
        ->assertSee("bac:notifications-updated", false);

    ($this->record)()->assertSessionHasNoErrors();

    $feed = testCase()->actingAs($this->bidder)->getJson(route('notifications.feed'))->assertOk();
    $notification = collect($feed->json('notifications'))->firstWhere('type', 'bidding_fee_paid');
    expect($notification)->not->toBeNull()
        ->and($notification['title'])->toBe('Payment recorded')
        ->and($notification['project_id'])->toBe($this->project->id)
        ->and($notification['is_read'])->toBeFalse()
        ->and($notification['url'])->toContain('bid_project='.$this->project->id)
        ->and($notification['message'])->toContain('OR No. OR-7654321')->toContain('You can now submit your bid online');

    // The same page now opens unlocked.
    testCase()->actingAs($this->bidder)->get(route('bidder.available-projects', ['bid_project' => $this->project->id]))
        ->assertSee('data-payment-locked="false" data-project-id="'.$this->project->id.'"', false);
});
