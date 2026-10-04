<?php

use App\Models\ProcurementRequest;
use App\Models\User;
use App\Models\UserNotification;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Schema;

uses(RefreshDatabase::class);

/*
 * An end-user office account files a purchase request (draft → edit →
 * submit) and the request reaches the Admin and BAC Secretariat
 * "For PPMP/APP review" queue with its office, status and details.
 */
beforeEach(function () {
    testCase()->withoutVite();

    $this->admin = User::create(['name' => 'BAC Chair', 'email' => 'flow-admin@example.com', 'password' => Hash::make('password'), 'role' => 'admin', 'status' => 'active']);
    $this->staff = User::create(['name' => 'Secretariat Staff', 'email' => 'flow-staff@example.com', 'password' => Hash::make('password'), 'role' => 'staff', 'status' => 'active', 'office' => 'SJBAC']);

    $this->details = [
        'title' => 'Supply of survey equipment',
        'category' => 'goods',
        'specifications' => "Total station, 1 unit\nTripod and prism set",
        'quantity' => '1',
        'unit' => 'lot',
        'estimated_cost' => '650000',
        'fund_source' => 'General Fund',
        'delivery_period' => '30 calendar days from receipt of the Notice to Proceed',
        'justification' => 'Road survey works',
    ];
});

it('points the empty review queue to office accounts and lets the admin create one', function () {
    testCase()->actingAs($this->admin)->get(route('admin.requests'))
        ->assertOk()
        ->assertSee('No end-user office account exists yet')
        ->assertSee(route('admin.users', ['create' => 'end_user']), false);

    testCase()->actingAs($this->staff)->get(route('staff.requests'))
        ->assertOk()
        ->assertSee('Ask the BAC administrator to create an account');

    testCase()->actingAs($this->admin)->post(route('admin.users.store'), [
        'name' => 'Municipal Engineering Office', 'email' => 'meo-flow@example.com', 'role' => 'end_user', 'status' => 'active',
        'password' => 'secret123', 'office' => 'Municipal Engineering Office',
    ])->assertSessionHasNoErrors();

    $office = User::where('email', 'meo-flow@example.com')->firstOrFail();
    expect($office->role)->toBe('end_user')->and($office->office)->toBe('Municipal Engineering Office');

    testCase()->actingAs($this->admin)->get(route('admin.users', ['filter' => 'end_user']))
        ->assertOk()
        ->assertSee('meo-flow@example.com')
        ->assertSee('Municipal Engineering Office');

    // The office signs in to its own portal.
    auth()->logout();
    // End-user offices confirm the sign-in with the emailed code.
    \Illuminate\Support\Facades\Mail::fake();
    testCase()->postJson('/login', ['email' => 'meo-flow@example.com', 'password' => 'secret123'])->assertOk()->assertJsonPath('requires_verification', true);
    $loginCode = null;
    \Illuminate\Support\Facades\Mail::assertSent(\App\Mail\LoginVerificationCodeMail::class, function ($mail) use (&$loginCode) { $loginCode = $mail->code; return true; });
    testCase()->postJson(route('login.verify-code'), ['code' => $loginCode])
        ->assertOk()
        ->assertJsonPath('redirect', route('end-user.dashboard'));

    testCase()->actingAs($office)->get(route('end-user.dashboard'))
        ->assertOk()
        ->assertSee('My purchase requests')
        ->assertSee('New purchase request')
        ->assertSee(route('end-user.requests.create'), false);
});

it('saves a partial draft, edits it, submits it, and puts it in the PPMP/APP review queue', function () {
    $office = User::create(['name' => 'MEO Account', 'email' => 'meo@example.com', 'password' => Hash::make('password'), 'role' => 'end_user', 'status' => 'active', 'office' => 'Municipal Engineering Office']);
    $otherOffice = User::create(['name' => 'MHO Account', 'email' => 'mho@example.com', 'password' => Hash::make('password'), 'role' => 'end_user', 'status' => 'active', 'office' => 'Municipal Health Office']);

    testCase()->actingAs($office)->get(route('end-user.requests.create'))->assertOk()->assertSee('New purchase request');

    // 1. A draft needs only its title.
    testCase()->actingAs($office)->post(route('end-user.requests.store'), ['title' => 'Supply of survey equipment', 'action' => 'draft'])
        ->assertSessionHasNoErrors()
        ->assertRedirect();

    $request = ProcurementRequest::firstOrFail();
    expect($request->status)->toBe(ProcurementRequest::STATUS_DRAFT)
        ->and($request->end_user_office)->toBe('Municipal Engineering Office')
        ->and($request->requested_by)->toBe($office->id)
        ->and($request->estimated_cost)->toBeNull()
        ->and($request->missingForSubmission())->toHaveKeys(['specifications', 'estimated_cost', 'fund_source']);

    testCase()->actingAs($office)->get(route('end-user.requests.show', $request))
        ->assertOk()
        ->assertSee('This draft is not complete yet')
        ->assertDontSee('Submit for PPMP/APP review');
    testCase()->actingAs($office)->get(route('end-user.requests.index'))
        ->assertOk()
        ->assertSee($request->reference_no)
        ->assertSee('Draft');

    // Drafts stay with the office: not in the review queue.
    testCase()->actingAs($this->staff)->get(route('staff.requests'))->assertOk()->assertDontSee($request->reference_no)->assertSee('1 draft is still being prepared');

    // An incomplete draft cannot be submitted.
    testCase()->actingAs($office)->post(route('end-user.requests.submit', $request))
        ->assertRedirect(route('end-user.requests.edit', $request))
        ->assertSessionHasErrors();
    expect($request->fresh()->status)->toBe(ProcurementRequest::STATUS_DRAFT);

    // Submitting from the form checks every detail.
    testCase()->actingAs($office)->put(route('end-user.requests.update', $request), ['title' => 'Supply of survey equipment', 'action' => 'submit'])
        ->assertSessionHasErrors(['category', 'specifications', 'quantity', 'unit', 'estimated_cost', 'fund_source', 'delivery_period']);

    // 2. Edit the draft with every detail, still as a draft.
    testCase()->actingAs($office)->get(route('end-user.requests.edit', $request))->assertOk()->assertSee('Supply of survey equipment');
    testCase()->actingAs($office)->put(route('end-user.requests.update', $request), $this->details + ['action' => 'draft'])
        ->assertSessionHasNoErrors();
    expect($request->fresh()->status)->toBe(ProcurementRequest::STATUS_DRAFT)
        ->and($request->fresh()->missingForSubmission())->toBe([]);

    // Another office can neither see nor open it.
    testCase()->actingAs($otherOffice)->get(route('end-user.requests.index'))->assertOk()->assertDontSee($request->reference_no);
    testCase()->actingAs($otherOffice)->get(route('end-user.requests.show', $request))->assertNotFound();

    // 3. Submit for the PPMP/APP and funds review.
    testCase()->actingAs($office)->post(route('end-user.requests.submit', $request))
        ->assertRedirect(route('end-user.requests.show', $request))
        ->assertSessionHas('success');

    $request->refresh();
    expect($request->status)->toBe(ProcurementRequest::STATUS_SUBMITTED)
        ->and($request->submitted_at)->not->toBeNull();

    // No more edits once submitted.
    testCase()->actingAs($office)->get(route('end-user.requests.edit', $request))->assertForbidden();

    // 4. It is in the "For PPMP/APP review" queue of both the BAC Secretariat and the admin.
    foreach ([[$this->staff, 'staff.requests'], [$this->admin, 'admin.requests']] as [$reviewer, $queue]) {
        testCase()->actingAs($reviewer)->get(route($queue))
            ->assertOk()
            ->assertSee('For PPMP/APP review')
            ->assertSee($request->reference_no)
            ->assertSee('Supply of survey equipment')
            ->assertSee('Municipal Engineering Office')
            ->assertSee('650,000.00')
            ->assertSee('For PPMP/APP review')
            ->assertSee('Total station, 1 unit')
            ->assertSee('General Fund')
            ->assertSee('MEO Account');

        expect(UserNotification::where('user_id', $reviewer->id)->where('type', 'procurement_request')->exists())->toBeTrue();
    }

    // 5. The Secretariat forwards it to the BAC; the office sees the new status.
    testCase()->actingAs($this->staff)->post(route('staff.requests.review', $request), [
        'decision' => 'forward', 'ppmp_reference' => 'PPMP-MEO-2026-04', 'app_reference' => 'APP-2026-112', 'budget_available' => '1',
    ])->assertSessionHasNoErrors();

    expect($request->fresh()->status)->toBe(ProcurementRequest::STATUS_FORWARDED);
    testCase()->actingAs($office)->get(route('end-user.requests.show', $request))->assertOk()->assertSee('Forwarded to BAC')->assertSee('PPMP-MEO-2026-04');
    testCase()->actingAs($this->admin)->get(route('admin.requests', ['tab' => 'bac']))->assertOk()->assertSee($request->reference_no)->assertSee('Prepare procurement');
});

it('accepts an estimated total cost typed with thousands separators', function () {
    $office = User::create(['name' => 'MEO Account', 'email' => 'meo-comma@example.com', 'password' => Hash::make('password'), 'role' => 'end_user', 'status' => 'active', 'office' => 'Municipal Engineering Office']);

    testCase()->actingAs($office)->get(route('end-user.requests.create'))
        ->assertOk()
        ->assertSee('Estimated total cost')
        ->assertSee('not the price of one unit');

    testCase()->actingAs($office)
        ->post(route('end-user.requests.store'), array_merge($this->details, ['estimated_cost' => '1,250,000.50', 'action' => 'draft']))
        ->assertSessionHasNoErrors();

    expect(ProcurementRequest::firstOrFail()->estimated_cost)->toBe('1250000.50');

    testCase()->actingAs($office)
        ->post(route('end-user.requests.store'), array_merge($this->details, ['estimated_cost' => '1,2x0', 'action' => 'draft']))
        ->assertSessionHasErrors('estimated_cost');
});

it('submits a request with a private Blob attachment and serves it through the authorized file route', function () {
    $office = User::create(['name' => 'MEO Account', 'email' => 'meo-blob@example.com', 'password' => Hash::make('password'), 'role' => 'end_user', 'status' => 'active', 'office' => 'Municipal Engineering Office']);
    config()->set('services.vercel_blob', [
        'enabled' => true,
        'token' => 'vercel_blob_rw_SjfNUHhmSlUWvEhs_test',
    ]);

    Http::fake(function ($request) {
        if ($request->method() === 'PUT') {
            parse_str((string) parse_url($request->url(), PHP_URL_QUERY), $query);

            return Http::response([
                'url' => 'https://sjfnuhhmsluwvehs.private.blob.vercel-storage.com/'.$query['pathname'],
            ]);
        }

        return Http::response("%PDF-1.4\nattachment", 200, ['Content-Type' => 'application/pdf']);
    });

    testCase()->actingAs($office)->post(route('end-user.requests.store'), $this->details + [
        'action' => 'submit',
        'documents' => [UploadedFile::fake()->createWithContent('TOR.pdf', "%PDF-1.4\nattachment")],
        'document_types' => ['tor'],
    ])->assertSessionHasNoErrors()->assertRedirect();

    $request = ProcurementRequest::firstOrFail();
    $document = $request->documents()->firstOrFail();
    expect($request->status)->toBe(ProcurementRequest::STATUS_SUBMITTED)
        ->and($document->file_path)->toStartWith('https://sjfnuhhmsluwvehs.private.blob.vercel-storage.com/procurement-requests/');

    Http::assertSent(fn ($request) => $request->method() === 'PUT'
        && $request->hasHeader('x-vercel-blob-access', 'private')
        && $request->hasHeader('Authorization', 'Bearer vercel_blob_rw_SjfNUHhmSlUWvEhs_test'));

    testCase()->actingAs($office)->get(route('procurement.files.request', $document))
        ->assertOk()->assertDownload('TOR.pdf');
});

it('keeps a submitted request when the review notification store is unavailable', function () {
    $office = User::create(['name' => 'MEO Account', 'email' => 'meo-notification@example.com', 'password' => Hash::make('password'), 'role' => 'end_user', 'status' => 'active', 'office' => 'Municipal Engineering Office']);
    Schema::drop('user_notifications');

    testCase()->actingAs($office)->post(route('end-user.requests.store'), $this->details + ['action' => 'submit'])
        ->assertRedirect()->assertSessionHasNoErrors();

    expect(ProcurementRequest::firstOrFail()->status)->toBe(ProcurementRequest::STATUS_SUBMITTED);
});

it('points a search in the wrong queue to the queue that holds the request', function () {
    $office = User::create(['name' => 'MEO Account', 'email' => 'meo-search@example.com', 'password' => Hash::make('password'), 'role' => 'end_user', 'status' => 'active', 'office' => 'Municipal Engineering Office']);
    testCase()->actingAs($office)->post(route('end-user.requests.store'), array_merge($this->details, ['action' => 'submit']))->assertSessionHasNoErrors();
    $reference = ProcurementRequest::firstOrFail()->reference_no;

    testCase()->actingAs($this->admin)->get(route('admin.requests', ['tab' => 'bac', 'q' => $reference]))
        ->assertOk()
        ->assertSee('No requests in Forwarded to BAC match')
        ->assertSee('For PPMP/APP review (1)')
        ->assertSee(route('admin.requests', ['tab' => 'review', 'q' => $reference]))
        ->assertSee('Clear search');
});
