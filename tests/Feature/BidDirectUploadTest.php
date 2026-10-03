<?php

use App\Models\Bid;
use App\Models\BiddingFeePayment;
use App\Models\Project;
use App\Models\ProjectSchedule;
use App\Models\User;
use App\Support\BidSubmissionRequirements;
use App\Support\BiddingDocumentsFee;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request as HttpRequest;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;

uses(RefreshDatabase::class);

/*
 * Vercel limits a function's request body to 4.5 MB, so on Vercel bid files go
 * from the browser straight to private Blob storage and the bid form sends only
 * their references. The Blob API is faked here.
 */
beforeEach(function () {
    testCase()->withoutVite();
    config()->set('filesystems.uploads_disk', 'public');
    Storage::fake('public');
    Storage::fake('local');
    $this->rwToken = 'vercel_blob_rw_storeABC123_secretvalue';
    config()->set('services.vercel_blob', ['enabled' => true, 'token' => $this->rwToken]);
    $this->host = 'storeabc123.private.blob.vercel-storage.com';

    $this->admin = User::create(['name' => 'Admin', 'email' => 'du-admin@example.com', 'password' => Hash::make('password'), 'role' => 'admin', 'status' => 'active']);
    $this->bidder = User::create(['name' => 'Bidder', 'email' => 'du-bidder@example.com', 'password' => Hash::make('password'), 'role' => 'bidder', 'status' => 'active', 'company' => 'Mindoro Builders']);
    $this->project = Project::create([
        'title' => 'Office Supplies', 'description' => 'Supplies.', 'reference_no' => 'SJOM-2026-G-501', 'category' => 'goods',
        'procurement_mode' => 'public_bidding', 'budget' => 1500000, 'deadline' => now()->addDays(5), 'status' => 'open', 'published_at' => now()->subDay(),
    ]);
    $this->project->forceFill(BiddingDocumentsFee::resolve($this->project, ['bidding_fee_mode' => 'schedule']))->save();
    ProjectSchedule::create(['project_id' => $this->project->id, 'date_posted' => now()->subDay()->toDateString(), 'bid_submission_deadline' => $this->project->deadline, 'bid_opening_date' => $this->project->deadline->copy()->addHour()]);
    $this->project = $this->project->fresh();
    $this->folder = 'bid-uploads/'.$this->bidder->id.'/'.$this->project->id;

    $this->token = fn (string $pathname) => testCase()->actingAs($this->bidder)->postJson(route('bidder.bids.upload-token', $this->project), [
        'type' => 'blob.generate-client-token', 'payload' => ['pathname' => $pathname, 'clientPayload' => null, 'multipart' => false],
    ]);
    $this->pay = fn () => BiddingFeePayment::create(['project_id' => $this->project->id, 'user_id' => $this->bidder->id, 'amount' => 5000, 'or_number' => 'OR-DU-1', 'status' => 'verified', 'paid_at' => now()->toDateString(), 'verified_at' => now(), 'recorded_by' => $this->admin->id]);
});

it('issues a browser upload token only for the bidder\'s own folder on a paid, open online bid', function () {
    ($this->token)($this->folder.'/philgeps.pdf')->assertForbidden();
    ($this->pay)();

    ($this->token)('bid-uploads/999/'.$this->project->id.'/philgeps.pdf')->assertStatus(422);
    ($this->token)($this->folder.'/script.exe')->assertStatus(422);

    $clientToken = ($this->token)($this->folder.'/philgeps.pdf')->assertOk()->assertJsonPath('type', 'blob.generate-client-token')->json('clientToken');
    expect($clientToken)->toStartWith('vercel_blob_client_storeABC123_');

    // Same format as @vercel/blob: base64("<hmac-sha256 hex>.<base64 json payload>").
    [$signature, $payload] = explode('.', base64_decode(substr($clientToken, strlen('vercel_blob_client_storeABC123_'))), 2);
    $claims = json_decode(base64_decode($payload), true);
    expect($signature)->toBe(hash_hmac('sha256', $payload, $this->rwToken))
        ->and($claims['pathname'])->toBe($this->folder.'/philgeps.pdf')
        ->and($claims['maximumSizeInBytes'])->toBe(20480 * 1024)
        ->and($claims['addRandomSuffix'])->toBeTrue()
        ->and($claims['validUntil'])->toBeGreaterThan(now()->getTimestampMs());
});

it('saves a bid sent with direct-upload references and deletes the temporary uploads', function () {
    ($this->pay)();
    $pdf = "%PDF-1.4\n1 0 obj\n<<>>\nendobj\ntrailer\n<<>>\n%%EOF";
    $deleted = new ArrayObject;
    Http::fake(function (HttpRequest $request) use ($pdf, $deleted) {
        if ($request->method() === 'GET' && str_contains($request->url(), $this->host)) {
            return Http::response($pdf, 200, ['Content-Type' => 'application/pdf']);
        }
        if ($request->method() === 'POST' && str_ends_with($request->url(), '/api/blob/delete')) {
            foreach ($request->data()['urls'] as $url) {
                $deleted[] = $url;
            }

            return Http::response([]);
        }

        return Http::response('Unexpected', 500);
    });

    $references = collect(BidSubmissionRequirements::for($this->project)->requiredKeys())
        ->mapWithKeys(fn (string $key) => [$key => "https://{$this->host}/{$this->folder}/{$key}-Ab12Cd.pdf"])->all();
    $post = fn (array $refs) => testCase()->actingAs($this->bidder)->post(route('bidder.bids.store', $this->project), [
        'project_id' => $this->project->id, 'bid_amount' => '1450000',
        'financial_password' => '482913', 'financial_password_confirmation' => '482913',
        'uploaded_documents' => $refs,
        'uploaded_document_names' => collect($refs)->map(fn ($url, $key) => $key.' form.pdf')->all(),
    ]);

    // A reference outside this bidder's folder is refused before anything is read.
    $post(['philgeps' => "https://{$this->host}/bid-uploads/999/{$this->project->id}/philgeps.pdf"] + $references)
        ->assertSessionHasErrors('documents');
    expect(Bid::count())->toBe(0);

    $post($references)->assertSessionHasNoErrors()->assertSessionHas('success');

    $bid = Bid::with('documents')->firstOrFail();
    expect($bid->isDraft())->toBeFalse()
        ->and($bid->documents)->toHaveCount(count($references))
        ->and($bid->documents->pluck('original_name')->all())->toContain('philgeps form.pdf')
        ->and($bid->documents->every(fn ($document) => $document->sha256 === hash('sha256', $pdf)))->toBeTrue()
        ->and($deleted->getArrayCopy())->toEqualCanonicalizing(array_values($references));
});
