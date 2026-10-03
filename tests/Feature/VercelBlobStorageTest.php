<?php

use App\Models\Project;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request as HttpRequest;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;

uses(RefreshDatabase::class);

/*
 * On Vercel the filesystem is read-only, so with a Blob token the local and
 * public disks use private Vercel Blob storage. The Blob API is faked here.
 */
beforeEach(function () {
    $this->blobs = new ArrayObject;
    $host = 'storeabc123.private.blob.vercel-storage.com';
    config()->set('services.vercel_blob', ['enabled' => true, 'token' => 'vercel_blob_rw_storeABC123_secretvalue']);
    foreach (['local' => 'private', 'public' => 'public'] as $disk => $prefix) {
        config()->set("filesystems.disks.$disk", ['driver' => 'vercel-blob', 'prefix' => $prefix, 'name' => $disk, 'throw' => false, 'report' => false]);
        Storage::forgetDisk($disk);
    }

    $blobs = $this->blobs;
    Http::fake(function (HttpRequest $request) use ($blobs, $host) {
        $url = $request->url();
        if ($request->method() === 'PUT' && str_starts_with($url, 'https://vercel.com/api/blob/')) {
            parse_str((string) parse_url($url, PHP_URL_QUERY), $query);
            $blobs[$query['pathname']] = ['body' => $request->body(), 'type' => $request->header('x-content-type')[0] ?? 'application/octet-stream'];

            return Http::response(['url' => "https://$host/".$query['pathname'], 'pathname' => $query['pathname']]);
        }
        if ($request->method() === 'POST' && $url === 'https://vercel.com/api/blob/delete') {
            foreach ($request->data()['urls'] as $blobUrl) {
                unset($blobs[rawurldecode(ltrim((string) parse_url($blobUrl, PHP_URL_PATH), '/'))]);
            }

            return Http::response([]);
        }
        if ($request->method() === 'GET' && str_starts_with($url, 'https://vercel.com/api/blob?')) {
            parse_str((string) parse_url($url, PHP_URL_QUERY), $query);
            $matches = array_values(array_filter(array_keys($blobs->getArrayCopy()), fn ($pathname) => str_starts_with($pathname, $query['prefix'] ?? '')));

            return Http::response(['blobs' => array_map(fn ($pathname) => ['pathname' => $pathname, 'size' => strlen($blobs[$pathname]['body'])], $matches), 'hasMore' => false]);
        }
        if (str_starts_with($url, "https://$host/")) {
            $pathname = rawurldecode(ltrim((string) parse_url($url, PHP_URL_PATH), '/'));
            if (! isset($blobs[$pathname])) {
                return Http::response('', 404);
            }
            $blob = $blobs[$pathname];

            return Http::response($request->method() === 'HEAD' ? '' : $blob['body'], 200, [
                'Content-Type' => $blob['type'], 'Content-Length' => (string) strlen($blob['body']), 'Last-Modified' => 'Sat, 03 Oct 2026 10:00:00 GMT',
            ]);
        }

        return Http::response('Unexpected request', 500);
    });
});

it('stores, reads, serves through a signed link and deletes files on a private Blob disk', function () {
    $disk = Storage::disk('local');

    expect($disk->put('certificates/1/award.pdf', '%PDF-1.4 certificate'))->toBeTrue()
        ->and($this->blobs->offsetExists('private/certificates/1/award.pdf'))->toBeTrue()
        ->and($disk->exists('certificates/1/award.pdf'))->toBeTrue()
        ->and($disk->exists('certificates/1/missing.pdf'))->toBeFalse()
        ->and($disk->get('certificates/1/award.pdf'))->toBe('%PDF-1.4 certificate')
        ->and($disk->size('certificates/1/award.pdf'))->toBe(20);

    // Files open through a short-lived signed app link, never the private Blob URL.
    $url = $disk->temporaryUrl('certificates/1/award.pdf', now()->addMinutes(5));
    expect($url)->toContain('/files/local/certificates/1/award.pdf')->toContain('signature=');
    testCase()->get($url)->assertOk()->assertHeader('Content-Type', 'application/pdf');
    testCase()->get(route('files.blob', ['disk' => 'local', 'path' => 'certificates/1/award.pdf']))->assertForbidden();

    expect($disk->delete('certificates/1/award.pdf'))->toBeTrue()
        ->and($disk->exists('certificates/1/award.pdf'))->toBeFalse();
});

it('saves project documents from the admin wizard to Blob storage', function () {
    testCase()->withoutVite();
    config()->set('filesystems.uploads_disk', 'local');
    $admin = User::create(['name' => 'BAC Chair', 'email' => 'blob-admin@example.com', 'password' => Hash::make('password'), 'role' => 'admin', 'status' => 'active']);

    testCase()->actingAs($admin)->post(route('admin.projects.wizard.store'), [
        'status' => 'draft', 'title' => 'Drainage Works', 'procurement_mode' => 'public_bidding', 'category' => 'infrastructure', 'budget' => '2500000',
        'project_documents' => [UploadedFile::fake()->createWithContent('itb.pdf', "%PDF-1.4\n%%EOF")],
        'document_type' => ['invitation_to_bid'],
    ])->assertSessionHasNoErrors();

    $document = Project::firstOrFail()->documents()->firstOrFail();
    expect($document->file_path)->toStartWith('project-documents/')
        ->and($this->blobs->offsetExists('private/'.$document->file_path))->toBeTrue()
        ->and($document->file_url)->toContain('/files/local/project-documents/');
});
