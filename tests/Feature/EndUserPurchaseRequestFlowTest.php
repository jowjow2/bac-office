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

it('records a request for an office, ready for procurement at once', function () {
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
        ->assertRedirect(route('admin.requests', ['tab' => 'bac']));

    $request = ProcurementRequest::firstOrFail();
    expect($request->status)->toBe(ProcurementRequest::STATUS_FORWARDED)
        ->and($request->end_user_office)->toBe('Municipal Engineering Office')
        ->and($request->requested_by)->toBeNull()
        ->and($request->submitted_at)->not->toBeNull()
        ->and($request->forwarded_at)->not->toBeNull()
        ->and($request->reviewed_by)->toBe($this->admin->id);

    // It is in the "Ready for procurement" queue of both the BAC Secretariat and the admin.
    foreach ([[$this->staff, 'staff.requests'], [$this->admin, 'admin.requests']] as [$reviewer, $queue]) {
        testCase()->actingAs($reviewer)->get(route($queue))
            ->assertOk()
            ->assertSee('Ready for procurement')
            ->assertSee($request->reference_no)
            ->assertSee('Supply of survey equipment')
            ->assertSee('Municipal Engineering Office')
            ->assertSee('650,000.00')
            ->assertSee('Total station, 1 unit')
            ->assertSee('General Fund');
    }

    // The PR form can be printed for the signatures.
    foreach ([$this->admin, $this->staff] as $user) {
        testCase()->actingAs($user)->get(route($user->role.'.requests.print', $request))->assertOk()
            ->assertSee('Purchase Request')->assertSee($request->reference_no)->assertSee('650,000.00')->assertSee('Approved by');
    }

    // The admin can prepare the project straight away.
    testCase()->actingAs($this->admin)->get(route('admin.requests', ['tab' => 'bac']))->assertOk()->assertSee($request->reference_no)->assertSee('Prepare procurement');
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
    expect($request->status)->toBe(ProcurementRequest::STATUS_FORWARDED)
        ->and($document->file_path)->toStartWith('https://sjfnuhhmsluwvehs.private.blob.vercel-storage.com/procurement-requests/');

    Http::assertSent(fn ($request) => $request->method() === 'PUT'
        && $request->hasHeader('x-vercel-blob-access', 'private')
        && $request->hasHeader('Authorization', 'Bearer vercel_blob_rw_SjfNUHhmSlUWvEhs_test'));

    testCase()->actingAs($this->staff)->get(route('procurement.files.request', $document))
        ->assertOk()->assertDownload('TOR.pdf');
});

it('keeps a recorded request even when the notification store is unavailable', function () {
    Schema::drop('user_notifications');

    testCase()->actingAs($this->admin)->post(route('admin.requests.store'), $this->details + ['end_user_office' => 'Municipal Engineering Office'])
        ->assertRedirect()->assertSessionHasNoErrors();

    expect(ProcurementRequest::firstOrFail()->status)->toBe(ProcurementRequest::STATUS_FORWARDED);
});

it('points a search in the wrong queue to the queue that holds the request', function () {
    testCase()->actingAs($this->admin)->post(route('admin.requests.store'), array_merge($this->details, ['end_user_office' => 'Municipal Engineering Office']))->assertSessionHasNoErrors();
    $reference = ProcurementRequest::firstOrFail()->reference_no;

    testCase()->actingAs($this->admin)->get(route('admin.requests', ['tab' => 'procurement', 'q' => $reference]))
        ->assertOk()
        ->assertSee('No requests in In procurement match')
        ->assertSee('Ready for procurement (1)')
        ->assertSee(route('admin.requests', ['tab' => 'bac', 'q' => $reference]))
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
    expect($request->status)->toBe(ProcurementRequest::STATUS_FORWARDED)
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

it('opens the first queue that has something waiting', function () {
    // Nothing recorded yet: all requests.
    testCase()->actingAs($this->admin)->get(route('admin.requests'))->assertOk()->assertSee('aria-current="page"', false);

    ProcurementRequest::create(array_merge($this->details, ['reference_no' => 'PR-2026-0700', 'end_user_office' => 'Municipal Engineering Office', 'status' => ProcurementRequest::STATUS_FORWARDED, 'title' => 'Forwarded one']));
    // Only a forwarded request: that queue opens by itself.
    testCase()->actingAs($this->admin)->get(route('admin.requests'))->assertOk()->assertSee('Forwarded one');

});

it('lets the admin correct a recorded request until a project is made from it', function () {
    testCase()->actingAs($this->admin)->post(route('admin.requests.store'), $this->details + ['end_user_office' => 'Municipal Engineering Office'])->assertSessionHasNoErrors();
    $request = ProcurementRequest::firstOrFail();

    // The queue offers Edit, and the form opens filled in.
    testCase()->actingAs($this->admin)->get(route('admin.requests', ['tab' => 'bac']))->assertOk()->assertSee(route('admin.requests.edit', $request), false);
    testCase()->actingAs($this->admin)->get(route('admin.requests.edit', $request))->assertOk()
        ->assertSee('Edit purchase request')->assertSee('Save changes')
        ->assertSee('value="Supply of survey equipment"', false)
        ->assertSee(route('admin.requests.update', $request), false);
    testCase()->actingAs($this->staff)->get(route('admin.requests.edit', $request))->assertForbidden();

    // A wrong amount is fixed, and the request keeps its number and its ready state.
    testCase()->actingAs($this->admin)->put(route('admin.requests.update', $request), array_merge($this->details, [
        'end_user_office' => 'Municipal Health Office', 'title' => 'Supply of survey equipment (corrected)', 'estimated_cost' => '480,000.00',
    ]))->assertSessionHasNoErrors()->assertRedirect();

    $fresh = $request->fresh();
    expect($fresh->title)->toBe('Supply of survey equipment (corrected)')
        ->and($fresh->end_user_office)->toBe('Municipal Health Office')
        ->and($fresh->estimated_cost)->toBe('480000.00')
        ->and($fresh->reference_no)->toBe($request->reference_no)
        ->and($fresh->status)->toBe(ProcurementRequest::STATUS_FORWARDED);

    // The same checks apply as when recording.
    testCase()->actingAs($this->admin)->put(route('admin.requests.update', $request), array_merge($this->details, ['end_user_office' => 'Nowhere Office']))->assertSessionHasErrors('end_user_office');

    // Once a project exists, the request is locked.
    $project = \App\Models\Project::create(['title' => 'From the PR', 'description' => 'x', 'budget' => 480000, 'deadline' => now()->addDays(10), 'status' => 'draft', 'category' => 'goods', 'procurement_request_id' => $request->id]);
    testCase()->actingAs($this->admin)->get(route('admin.requests.edit', $request))->assertRedirect(route('admin.requests', ['tab' => 'all']));
    testCase()->actingAs($this->admin)->put(route('admin.requests.update', $request), $this->details + ['end_user_office' => 'Municipal Health Office'])->assertRedirect(route('admin.requests', ['tab' => 'all']));
    expect($request->fresh()->title)->toBe('Supply of survey equipment (corrected)');
});

it('takes a purchase request attachment straight from Blob storage, so a large file is not posted with the form', function () {
    config()->set('services.vercel_blob', ['enabled' => true, 'token' => 'vercel_blob_rw_storeABC123_secretvalue']);
    $host = 'storeabc123.private.blob.vercel-storage.com';
    $folder = 'request-uploads/'.$this->admin->id;
    $deleted = new ArrayObject;

    Http::fake(function ($request) use ($host, $deleted) {
        if ($request->method() === 'GET' && str_contains($request->url(), $host)) {
            return Http::response("%PDF-1.4\nattachment", 200, ['Content-Type' => 'application/pdf']);
        }
        if ($request->method() === 'POST' && str_ends_with($request->url(), '/api/blob/delete')) {
            foreach ($request->data()['urls'] as $url) {
                $deleted[] = $url;
            }

            return Http::response([], 200);
        }
        if ($request->method() === 'PUT') {
            parse_str((string) parse_url($request->url(), PHP_URL_QUERY), $query);

            return Http::response(['url' => 'https://'.$host.'/'.$query['pathname']]);
        }

        return Http::response([], 200);
    });

    // The token: only this admin's folder, only these file types.
    $token = fn (string $pathname) => testCase()->actingAs($this->admin)->postJson(route('admin.requests.upload-token'), ['type' => 'blob.generate-client-token', 'payload' => ['pathname' => $pathname, 'clientPayload' => null, 'multipart' => false]]);
    $token('request-uploads/999/0.pdf')->assertStatus(422);
    $token($folder.'/0.exe')->assertStatus(422);
    $token($folder.'/0.pdf')->assertOk()->assertJsonStructure(['clientToken']);
    testCase()->actingAs($this->staff)->postJson(route('admin.requests.upload-token'), ['type' => 'blob.generate-client-token', 'payload' => ['pathname' => $folder.'/0.pdf']])->assertForbidden();

    // The form sends the reference, not the file; the document type of that row is kept.
    testCase()->actingAs($this->admin)->post(route('admin.requests.store'), $this->details + [
        'end_user_office' => 'Municipal Engineering Office',
        'document_types' => ['tor'],
        'uploaded_documents' => ['r0' => 'https://'.$host.'/'.$folder.'/0-aB3dE.pdf'],
        'uploaded_document_names' => ['r0' => 'TOR-big.pdf'],
    ])->assertSessionHasNoErrors()->assertRedirect();

    $document = ProcurementRequest::firstOrFail()->documents()->firstOrFail();
    expect($document->original_name)->toBe('TOR-big.pdf')->and($document->document_type)->toBe('tor');
    // The temporary direct upload is removed once the stored copy exists.
    expect(collect($deleted)->contains('https://'.$host.'/'.$folder.'/0-aB3dE.pdf'))->toBeTrue();

    // A reference into someone else's folder is refused and nothing is saved.
    $before = ProcurementRequest::count();
    testCase()->actingAs($this->admin)->post(route('admin.requests.store'), $this->details + [
        'end_user_office' => 'Municipal Engineering Office',
        'uploaded_documents' => ['r0' => 'https://'.$host.'/request-uploads/999/0-x.pdf'],
        'uploaded_document_names' => ['r0' => 'x.pdf'],
    ])->assertSessionHasErrors();
    expect(ProcurementRequest::count())->toBe($before);
});

it('has no PPMP/APP review step: the queue has no review tab and a waiting request becomes ready for procurement', function () {
    $waiting = ProcurementRequest::create(array_merge($this->details, ['reference_no' => 'PR-2026-0800', 'end_user_office' => 'Municipal Engineering Office', 'status' => ProcurementRequest::STATUS_SUBMITTED, 'submitted_at' => now()->subDay(), 'title' => 'Waited for review']));

    (require base_path('database/migrations/2026_10_28_000001_forward_requests_waiting_for_review.php'))->up();

    expect($waiting->fresh()->status)->toBe(ProcurementRequest::STATUS_FORWARDED)->and($waiting->fresh()->forwarded_at)->not->toBeNull();

    testCase()->actingAs($this->admin)->get(route('admin.requests', ['tab' => 'bac']))->assertOk()
        ->assertSee('Waited for review')->assertSee('Prepare procurement')
        ->assertDontSee('For PPMP/APP review')->assertDontSee('Record decision');
    expect(\Illuminate\Support\Facades\Route::has('admin.requests.review'))->toBeFalse()
        ->and(\Illuminate\Support\Facades\Route::has('staff.requests.review'))->toBeFalse();
});

it('has no leftover PPMP/APP review wording where a recorded request is shown or tracked', function () {
    testCase()->actingAs($this->admin)->post(route('admin.requests.store'), $this->details + ['end_user_office' => 'Municipal Engineering Office'])->assertSessionHasNoErrors();
    $request = ProcurementRequest::firstOrFail();

    // The recording form says the request is ready for procurement, not that it goes for a funds check.
    testCase()->actingAs($this->admin)->get(route('admin.requests.create'))->assertOk()
        ->assertSee('Recording makes the request ready for procurement')->assertDontSee('for the PPMP/APP and funds check');

    // The register has no PR & funds check bucket and does not name PhilGEPS in its search; the request sits in preparation.
    testCase()->actingAs($this->admin)->get(route('admin.dashboard'))->assertOk()
        ->assertDontSee('PR &amp; funds check', false)->assertDontSee('PhilGEPS no.');
    expect(\App\Support\ProcurementPipeline::BUCKETS)->not->toHaveKey('request');

    // The timeline of a project made from the request has no review stage.
    $project = \App\Models\Project::create(['title' => 'Laptops', 'description' => 'x', 'category' => 'goods', 'procurement_mode' => 'public_bidding', 'legal_basis' => 'ra_12009', 'budget' => 900000, 'status' => 'draft', 'procurement_request_id' => $request->id]);
    $keys = collect(\App\Support\ProcurementTimeline::forProject($project)->stages())->pluck('key');
    expect($keys)->toContain('request')->and($keys)->not->toContain('review');
});

it('marks a request recorded within the last day as NEW, in front, and not an older one', function () {
    $old = ProcurementRequest::create(array_merge($this->details, ['reference_no' => 'PR-2026-0900', 'end_user_office' => 'Municipal Engineering Office', 'status' => ProcurementRequest::STATUS_FORWARDED, 'submitted_at' => now()->subDays(5), 'forwarded_at' => now()->subDays(5), 'title' => 'Older request']));
    ProcurementRequest::whereKey($old->id)->update(['created_at' => now()->subDays(5)]);
    expect($old->fresh()->isNew())->toBeFalse();

    testCase()->actingAs($this->admin)->post(route('admin.requests.store'), array_merge($this->details, ['end_user_office' => 'Municipal Engineering Office', 'title' => 'Brand new request']))->assertSessionHasNoErrors();
    $new = ProcurementRequest::where('title', 'Brand new request')->firstOrFail();
    expect($new->isNew())->toBeTrue();

    $page = testCase()->actingAs($this->admin)->get(route('admin.requests', ['tab' => 'bac']));
    $page->assertOk()->assertSee('pr-new', false);
    // The new one is in front of the older one, and only it carries the mark.
    $html = $page->getContent();
    expect(strpos($html, 'Brand new request'))->toBeLessThan(strpos($html, 'Older request'))
        ->and(substr_count($html, 'class="pr-new"'))->toBe(1);

    // Once it is a project it is no longer marked.
    $new->update(['status' => ProcurementRequest::STATUS_IN_PROCUREMENT]);
    expect($new->fresh()->isNew())->toBeFalse();
});
