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
 * The admin records the signed hard copy an end-user office handed to the
 * BAC; it reaches the Admin and BAC Secretariat "For PPMP/APP review" queue
 * with its office, status and details, and the office follows it read-only.
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

it('lets the admin add an office account and the office follows its requests read-only', function () {
    testCase()->actingAs($this->admin)->post(route('admin.users.store'), [
        'name' => 'Municipal Engineering Office', 'email' => 'meo-flow@example.com', 'role' => 'end_user', 'status' => 'active',
        'password' => 'secret123', 'office' => 'Municipal Engineering Office',
    ])->assertSessionHasNoErrors();

    $office = User::where('email', 'meo-flow@example.com')->firstOrFail();
    expect($office->role)->toBe('end_user')->and($office->office)->toBe('Municipal Engineering Office');

    // The office signs in with the emailed code, and has no way to file a request itself.
    auth()->logout();
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
        ->assertDontSee('New purchase request');
    testCase()->actingAs($office)->get('/end-user/requests/create')->assertNotFound();
    testCase()->actingAs($office)->get('/end-user/requests/1/edit')->assertNotFound();
    testCase()->actingAs($office)->post('/end-user/requests', $this->details)->assertStatus(405);
});

it('records a request for an office, sends it to the PPMP/APP review and lets the office track it', function () {
    $office = User::create(['name' => 'MEO Account', 'email' => 'meo@example.com', 'password' => Hash::make('password'), 'role' => 'end_user', 'status' => 'active', 'office' => 'Municipal Engineering Office']);
    $colleague = User::create(['name' => 'MEO Colleague', 'email' => 'meo2@example.com', 'password' => Hash::make('password'), 'role' => 'end_user', 'status' => 'active', 'office' => 'Municipal Engineering Office']);
    $otherOffice = User::create(['name' => 'MHO Account', 'email' => 'mho@example.com', 'password' => Hash::make('password'), 'role' => 'end_user', 'status' => 'active', 'office' => 'Municipal Health Office']);

    testCase()->actingAs($this->admin)->get(route('admin.requests'))->assertOk()->assertSee('Record purchase request');
    testCase()->actingAs($this->admin)->get(route('admin.requests.create'))->assertOk()
        ->assertSee('Record purchase request')->assertSee('End-user office')->assertSee('Estimated total cost');
    testCase()->actingAs($this->staff)->get(route('admin.requests.create'))->assertForbidden();

    // The office on the hard copy is required and must be a known office.
    testCase()->actingAs($this->admin)->post(route('admin.requests.store'), $this->details)->assertSessionHasErrors('end_user_office');
    testCase()->actingAs($this->admin)->post(route('admin.requests.store'), $this->details + ['end_user_office' => 'Nowhere Office'])->assertSessionHasErrors('end_user_office');
    // A recorded request is complete.
    testCase()->actingAs($this->admin)->post(route('admin.requests.store'), ['title' => 'Supply of survey equipment', 'end_user_office' => 'Municipal Engineering Office'])
        ->assertSessionHasErrors(['category', 'specifications', 'quantity', 'unit', 'estimated_cost', 'fund_source', 'delivery_period']);
    expect(ProcurementRequest::count())->toBe(0);

    testCase()->actingAs($this->admin)->post(route('admin.requests.store'), $this->details + ['end_user_office' => 'Municipal Engineering Office'])
        ->assertSessionHasNoErrors()
        ->assertRedirect(route('admin.requests', ['tab' => 'review']));

    $request = ProcurementRequest::firstOrFail();
    expect($request->status)->toBe(ProcurementRequest::STATUS_SUBMITTED)
        ->and($request->end_user_office)->toBe('Municipal Engineering Office')
        ->and($request->requested_by)->toBeNull()
        ->and($request->submitted_at)->not->toBeNull();

    // It is in the "For PPMP/APP review" queue of both the BAC Secretariat and the admin.
    foreach ([[$this->staff, 'staff.requests'], [$this->admin, 'admin.requests']] as [$reviewer, $queue]) {
        testCase()->actingAs($reviewer)->get(route($queue))
            ->assertOk()
            ->assertSee('For PPMP/APP review')
            ->assertSee($request->reference_no)
            ->assertSee('Supply of survey equipment')
            ->assertSee('Municipal Engineering Office')
            ->assertSee('650,000.00')
            ->assertSee('Total station, 1 unit')
            ->assertSee('General Fund');

        expect(UserNotification::where('user_id', $reviewer->id)->where('type', 'procurement_request')->exists())->toBeTrue();
    }

    // Every account of that office follows it; another office does not.
    foreach ([$office, $colleague] as $account) {
        testCase()->actingAs($account)->get(route('end-user.requests.index'))->assertOk()->assertSee($request->reference_no);
        testCase()->actingAs($account)->get(route('end-user.requests.show', $request))->assertOk()->assertSee('Supply of survey equipment');
    }
    testCase()->actingAs($otherOffice)->get(route('end-user.requests.index'))->assertOk()->assertDontSee($request->reference_no);
    testCase()->actingAs($otherOffice)->get(route('end-user.requests.show', $request))->assertNotFound();

    // The Secretariat forwards it to the BAC; the office sees the new status and is told.
    testCase()->actingAs($this->staff)->post(route('staff.requests.review', $request), [
        'decision' => 'forward', 'ppmp_reference' => 'PPMP-MEO-2026-04', 'app_reference' => 'APP-2026-112', 'budget_available' => '1',
    ])->assertSessionHasNoErrors();

    expect($request->fresh()->status)->toBe(ProcurementRequest::STATUS_FORWARDED)
        ->and(UserNotification::where('user_id', $office->id)->where('type', 'procurement_request')->exists())->toBeTrue()
        ->and(UserNotification::where('user_id', $colleague->id)->where('type', 'procurement_request')->exists())->toBeTrue()
        ->and(UserNotification::where('user_id', $otherOffice->id)->where('type', 'procurement_request')->exists())->toBeFalse();
    testCase()->actingAs($office)->get(route('end-user.requests.show', $request))->assertOk()->assertSee('Forwarded to BAC')->assertSee('PPMP-MEO-2026-04');
    testCase()->actingAs($this->admin)->get(route('admin.requests', ['tab' => 'bac']))->assertOk()->assertSee($request->reference_no)->assertSee('Prepare procurement');
});

it('accepts an estimated total cost typed with thousands separators', function () {
    $post = fn (array $extra) => testCase()->actingAs($this->admin)->post(route('admin.requests.store'), array_merge($this->details, ['end_user_office' => 'Municipal Engineering Office'], $extra));

    $post(['estimated_cost' => '1,250,000.50'])->assertSessionHasNoErrors();
    expect(ProcurementRequest::firstOrFail()->estimated_cost)->toBe('1250000.50');

    $post(['estimated_cost' => '1,2x0'])->assertSessionHasErrors('estimated_cost');
});

it('records a request with a private Blob attachment and serves it to the office through the authorized file route', function () {
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

    testCase()->actingAs($this->admin)->post(route('admin.requests.store'), $this->details + [
        'end_user_office' => 'Municipal Engineering Office',
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

it('keeps a recorded request when the review notification store is unavailable', function () {
    Schema::drop('user_notifications');

    testCase()->actingAs($this->admin)->post(route('admin.requests.store'), $this->details + ['end_user_office' => 'Municipal Engineering Office'])
        ->assertRedirect()->assertSessionHasNoErrors();

    expect(ProcurementRequest::firstOrFail()->status)->toBe(ProcurementRequest::STATUS_SUBMITTED);
});

it('points a search in the wrong queue to the queue that holds the request', function () {
    testCase()->actingAs($this->admin)->post(route('admin.requests.store'), array_merge($this->details, ['end_user_office' => 'Municipal Engineering Office']))->assertSessionHasNoErrors();
    $reference = ProcurementRequest::firstOrFail()->reference_no;

    testCase()->actingAs($this->admin)->get(route('admin.requests', ['tab' => 'bac', 'q' => $reference]))
        ->assertOk()
        ->assertSee('No requests in Forwarded to BAC match')
        ->assertSee('For PPMP/APP review (1)')
        ->assertSee(route('admin.requests', ['tab' => 'review', 'q' => $reference]))
        ->assertSee('Clear search');
});

it('records an itemized request, derives the totals, and shows the items to the office and the reviewer', function () {
    $office = User::create(['name' => 'MEO Items', 'email' => 'meo-items@example.com', 'password' => Hash::make('password'), 'role' => 'end_user', 'status' => 'active', 'office' => 'Municipal Engineering Office']);
    $base = collect($this->details)->except(['quantity', 'unit', 'estimated_cost'])->all() + ['end_user_office' => 'Municipal Engineering Office'];
    $items = [
        ['description' => 'Bond paper, A4, 80 gsm', 'quantity' => '50', 'unit' => 'ream', 'unit_cost' => '245.50'],
        ['description' => 'Toner cartridge', 'quantity' => '3', 'unit' => 'piece', 'unit_cost' => '3,200'],
        ['description' => '', 'quantity' => '', 'unit' => '', 'unit_cost' => ''], // empty row is ignored
    ];

    // Valid items are required.
    testCase()->actingAs($this->admin)->post(route('admin.requests.store'), $base + ['items' => [['description' => '', 'quantity' => '0', 'unit' => 'box', 'unit_cost' => '-5']]])
        ->assertSessionHasErrors(['items.0.description', 'items.0.quantity', 'items.0.unit_cost']);
    testCase()->actingAs($this->admin)->post(route('admin.requests.store'), $base + ['items' => []])->assertSessionHasErrors('items');

    testCase()->actingAs($this->admin)->post(route('admin.requests.store'), $base + ['items' => $items])->assertSessionHasNoErrors();
    $request = ProcurementRequest::firstOrFail();
    expect($request->status)->toBe(ProcurementRequest::STATUS_SUBMITTED)
        ->and($request->items)->toHaveCount(2)
        ->and($request->estimated_cost)->toBe('21875.00')   // 50 × 245.50 + 3 × 3,200
        ->and($request->quantity)->toBe('1.00')
        ->and($request->unit)->toBe('lot')
        ->and($request->quantityLabel())->toBe('2 items');

    testCase()->actingAs($office)->get(route('end-user.requests.show', $request))->assertOk()
        ->assertSee('Bond paper, A4, 80 gsm')->assertSee('₱245.50')->assertSee('₱21,875.00');
    testCase()->actingAs($this->staff)->get(route('staff.requests'))->assertOk()
        ->assertSee('Bond paper, A4, 80 gsm')->assertSee('₱245.50');
});

it('shows a request saved before items as one item row', function () {
    $office = User::create(['name' => 'MEO Legacy', 'email' => 'meo-legacy@example.com', 'password' => Hash::make('password'), 'role' => 'end_user', 'status' => 'active', 'office' => 'Municipal Engineering Office']);
    $request = ProcurementRequest::create(array_merge($this->details, [
        'reference_no' => 'PR-2026-0900', 'end_user_office' => 'Municipal Engineering Office', 'requested_by' => null,
        'status' => ProcurementRequest::STATUS_SUBMITTED,
    ]));

    expect($request->items)->toBeNull()
        ->and($request->itemRows())->toBe([['description' => 'Supply of survey equipment', 'quantity' => 1.0, 'unit' => 'lot', 'unit_cost' => 650000.0, 'total' => 650000.0]]);
    testCase()->actingAs($office)->get(route('end-user.requests.show', $request))->assertOk()->assertSee('Supply of survey equipment')->assertSee('₱650,000.00');
});

it('keeps the form and explains a failed attachment upload instead of a server error', function () {
    config()->set('services.vercel_blob', ['enabled' => true, 'token' => 'vercel_blob_rw_SjfNUHhmSlUWvEhs_test']);
    $attempts = 0;
    Http::fake(function ($request) use (&$attempts) {
        $attempts++;

        return Http::response(['error' => ['code' => 'service_unavailable']], 503);
    });

    testCase()->actingAs($this->admin)->from(route('admin.requests.create'))->post(route('admin.requests.store'), $this->details + [
        'end_user_office' => 'Municipal Engineering Office',
        'documents' => [UploadedFile::fake()->createWithContent('TOR.pdf', "%PDF-1.4\nattachment")],
        'document_types' => ['tor'],
    ])->assertRedirect(route('admin.requests.create'))
        ->assertSessionHasErrors('documents')
        ->assertSessionHasInput('title', 'Supply of survey equipment');

    // Tried three times, and nothing was half-saved.
    expect($attempts)->toBe(3)->and(ProcurementRequest::count())->toBe(0);
});
