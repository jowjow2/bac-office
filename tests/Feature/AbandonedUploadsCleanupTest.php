<?php

use App\Support\VercelBlob;
use Illuminate\Http\Client\Request as HttpRequest;
use Illuminate\Support\Facades\Http;

it('deletes uploads older than a day under the prefix and leaves everything else', function () {
    config()->set('services.vercel_blob', ['enabled' => true, 'token' => 'vercel_blob_rw_storeABC123_secretvalue']);
    $host = 'storeabc123.private.blob.vercel-storage.com';
    $deleted = [];

    Http::fake(function (HttpRequest $request) use ($host, &$deleted) {
        if ($request->method() === 'GET') {
            return Http::response(['hasMore' => false, 'blobs' => [
                ['url' => "https://$host/registration-uploads/old/a.pdf", 'pathname' => 'registration-uploads/old/a.pdf', 'uploadedAt' => now()->subDays(2)->toIso8601String()],
                ['url' => "https://$host/registration-uploads/new/b.pdf", 'pathname' => 'registration-uploads/new/b.pdf', 'uploadedAt' => now()->subHour()->toIso8601String()],
                ['url' => "https://$host/bidder-documents/keep.pdf", 'pathname' => 'bidder-documents/keep.pdf', 'uploadedAt' => now()->subDays(30)->toIso8601String()],
            ]]);
        }
        if (str_ends_with($request->url(), '/delete')) {
            $deleted = array_merge($deleted, $request['urls']);

            return Http::response([]);
        }

        return Http::response([], 404);
    });

    expect(VercelBlob::purgeOlderThan('registration-uploads', 24))->toBe(1)
        ->and($deleted)->toBe(["https://$host/registration-uploads/old/a.pdf"]);
});

it('does nothing without Blob storage', function () {
    config()->set('services.vercel_blob', ['enabled' => false, 'token' => null]);
    Http::fake();

    expect(VercelBlob::purgeOlderThan('registration-uploads'))->toBe(0);
    Http::assertNothingSent();
});
