<?php

namespace App\Support;

use Illuminate\Http\File;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Http;
use RuntimeException;

/** Private Vercel Blob storage for serverless purchase request attachments. */
class VercelBlob
{
    private const API = 'https://vercel.com/api/blob';

    public static function enabled(): bool
    {
        return (bool) config('services.vercel_blob.enabled')
            && filled(config('services.vercel_blob.token'));
    }

    public static function isUrl(?string $url): bool
    {
        $host = is_string($url) ? parse_url($url, PHP_URL_HOST) : null;

        return is_string($host)
            && preg_match('/^[a-z0-9]+\.private\.blob\.vercel-storage\.com$/i', $host) === 1;
    }

    public static function store(File|UploadedFile $file, string $directory, string $filename): string
    {
        $pathname = trim($directory, '/').'/'.$filename;
        $source = $file->getRealPath();
        $bytes = is_string($source) ? file_get_contents($source) : false;
        if ($bytes === false) {
            throw new RuntimeException('Unable to read the uploaded file.');
        }

        $response = Http::withToken(self::token())
            ->withHeaders(self::apiHeaders() + [
                'x-vercel-blob-access' => 'private',
                'x-content-type' => $file->getMimeType() ?: 'application/octet-stream',
                'x-add-random-suffix' => '0',
            ])
            ->withBody($bytes, $file->getMimeType() ?: 'application/octet-stream')
            ->timeout(50)
            ->put(self::API.'/?'.http_build_query(['pathname' => $pathname]));

        $url = $response->json('url');
        if (! $response->successful() || ! is_string($url) || ! self::isUrl($url)
            || strtolower((string) parse_url($url, PHP_URL_HOST)) !== self::host()) {
            throw new RuntimeException('Unable to store the attachment in private Blob storage (HTTP '.$response->status().').');
        }

        return $url;
    }

    public static function read(string $url): ?string
    {
        self::assertOwnUrl($url);
        $response = Http::withToken(self::token())->timeout(50)->get($url);
        if ($response->notFound()) {
            return null;
        }
        if (! $response->successful()) {
            throw new RuntimeException('Unable to read the private attachment (HTTP '.$response->status().').');
        }

        return $response->body();
    }

    public static function size(string $url): ?int
    {
        self::assertOwnUrl($url);
        $response = Http::withToken(self::token())->timeout(15)->head($url);
        if (! $response->successful()) {
            return null;
        }

        $length = $response->header('Content-Length');

        return is_numeric($length) ? (int) $length : null;
    }

    public static function delete(string $url): bool
    {
        self::assertOwnUrl($url);
        $response = Http::withToken(self::token())
            ->withHeaders(self::apiHeaders())
            ->timeout(30)
            ->post(self::API.'/delete', ['urls' => [$url]]);

        return $response->successful();
    }

    private static function assertOwnUrl(string $url): void
    {
        if (! self::isUrl($url) || strtolower((string) parse_url($url, PHP_URL_HOST)) !== self::host()) {
            throw new RuntimeException('Invalid private Blob URL.');
        }
    }

    private static function apiHeaders(): array
    {
        return [
            'x-api-version' => '12',
            'x-vercel-blob-store-id' => self::storeId(),
            'x-api-blob-request-id' => self::storeId().':'.now()->getTimestampMs().':'.bin2hex(random_bytes(8)),
            'x-api-blob-request-attempt' => '0',
        ];
    }

    private static function host(): string
    {
        return strtolower(self::storeId()).'.private.blob.vercel-storage.com';
    }

    private static function storeId(): string
    {
        $parts = explode('_', self::token());
        $storeId = $parts[3] ?? '';
        if (! preg_match('/^[a-z0-9]+$/i', $storeId)) {
            throw new RuntimeException('Invalid private Blob storage configuration.');
        }

        return $storeId;
    }

    private static function token(): string
    {
        $token = config('services.vercel_blob.token');
        if (! is_string($token) || $token === '') {
            throw new RuntimeException('Private Blob storage is not configured.');
        }

        return $token;
    }
}
