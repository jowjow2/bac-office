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

it('has no end-user portal: nothing to file, edit or track from an office account', function () {
    foreach (['/end-user/dashboard', '/end-user/requests', '/end-user/messages', '/end-user/requests/create'] as $path) {
        testCase()->actingAs($this->admin)->get($path)->assertNotFound();
    }
});

it('records a request for an office and sends it to the PPMP/APP review', function () {
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

    // The PR form can be printed for the signatures.
    foreach ([$this->admin, $this->staff] as $user) {
        testCase()->actingAs($user)->get(route($user->role.'.requests.print', $request))->assertOk()
            ->assertSee('Purchase Request')->assertSee($request->reference_no)->assertSee('650,000.00')->assertSee('Approved by');
    }

    // The Secretariat forwards it to the BAC, and the admin is told.
    testCase()->actingAs($this->staff)->post(route('staff.requests.review', $request), [
        'decision' => 'forward', 'ppmp_reference' => 'PPMP-MEO-2026-04', 'app_reference' => 'APP-2026-112', 'budget_available' => '1',
    ])->assertSessionHasNoErrors();

    expect($request->fresh()->status)->toBe(ProcurementRequest::STATUS_FORWARDED);
    testCase()->actingAs($this->admin)->get(route('admin.requests', ['tab' => 'bac']))->assertOk()->assertSee($request->reference_no)->assertSee('Prepare procurement');
});

it('tells the admin when the Secretariat returns a request, so the office can hand in a corrected copy', function () {
    testCase()->actingAs($this->admin)->post(route('admin.requests.store'), $this->details + ['end_user_office' => 'Municipal Engineering Office'])->assertSessionHasNoErrors();
    $request = ProcurementRequest::firstOrFail();
    UserNotification::query()->delete();

    testCase()->actingAs($this->staff)->post(route('staff.requests.review', $request), ['decision' => 'return', 'review_remarks' => 'Attach the canvass.'])->assertSessionHasNoErrors();

    $notice = UserNotification::where('user_id', $this->admin->id)->where('title', 'Request returned to the office')->firstOrFail();
    expect($request->fresh()->status)->toBe(ProcurementRequest::STATUS_RETURNED)
        ->and($notice->message)->toContain('Attach the canvass.')->toContain('corrected hard copy');
});

it('accepts an estimated total cost typed with thousands separators', function () {
    $post = fn (array $extra) => testCase()->actingAs($this->admin)->post(route('admin.requests.store'), array_merge($this->details, ['end_user_office' => 'Municipal Engineering Office'], $extra));

    $post(['estimated_cost' => '1,250,000.50'])->assertSessionHasNoErrors();
    expect(ProcurementRequest::firstOrFail()->estimated_cost)->toBe('1250000.50');

    $post(['estimated_cost' => '1,2x0'])->assertSessionHasErrors('estimated_cost');
});

it('records a request with a private Blob attachment and serves it to the BAC through the authorized file route', function () {
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

    testCase()->actingAs($this->staff)->get(route('procurement.files.request', $document))
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

it('records an itemized request, derives the totals, and shows the items to the reviewer and on the printed form', function () {
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

    testCase()->actingAs($this->admin)->get(route('admin.requests.print', $request))->assertOk()
        ->assertSee('Bond paper, A4, 80 gsm')->assertSee('245.50')->assertSee('21,875.00');
    testCase()->actingAs($this->staff)->get(route('staff.requests'))->assertOk()
        ->assertSee('Bond paper, A4, 80 gsm')->assertSee('₱245.50');
});

it('shows a request saved before items as one item row', function () {
    $request = ProcurementRequest::create(array_merge($this->details, [
        'reference_no' => 'PR-2026-0900', 'end_user_office' => 'Municipal Engineering Office', 'requested_by' => null,
        'status' => ProcurementRequest::STATUS_SUBMITTED,
    ]));

    expect($request->items)->toBeNull()
        ->and($request->itemRows())->toBe([['description' => 'Supply of survey equipment', 'quantity' => 1.0, 'unit' => 'lot', 'unit_cost' => 650000.0, 'total' => 650000.0]]);
    testCase()->actingAs($this->admin)->get(route('admin.requests.print', $request))->assertOk()->assertSee('Supply of survey equipment')->assertSee('650,000.00');
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

it('opens the first queue that has something waiting, and shows a one-line all-clear', function () {
    // Nothing recorded yet: all requests.
    testCase()->actingAs($this->admin)->get(route('admin.requests'))->assertOk()->assertSee('aria-current="page"', false);

    ProcurementRequest::create(array_merge($this->details, ['reference_no' => 'PR-2026-0700', 'end_user_office' => 'Municipal Engineering Office', 'status' => ProcurementRequest::STATUS_FORWARDED, 'title' => 'Forwarded one']));
    // Only a forwarded request: that queue opens by itself.
    testCase()->actingAs($this->admin)->get(route('admin.requests'))->assertOk()->assertSee('Forwarded one');

    // A request waiting for review takes priority over the forwarded one.
    ProcurementRequest::create(array_merge($this->details, ['reference_no' => 'PR-2026-0701', 'end_user_office' => 'Municipal Engineering Office', 'status' => ProcurementRequest::STATUS_SUBMITTED, 'title' => 'Waiting one']));
    testCase()->actingAs($this->admin)->get(route('admin.requests'))->assertOk()->assertSee('Waiting one')->assertDontSee('Forwarded one');

    // Opening the empty review queue by hand explains in one line where the requests went.
    ProcurementRequest::where('reference_no', 'PR-2026-0701')->delete();
    testCase()->actingAs($this->admin)->get(route('admin.requests', ['tab' => 'review']))->assertOk()
        ->assertSee('Nothing waiting for your review.')->assertSee('1 forwarded to the BAC');
});
