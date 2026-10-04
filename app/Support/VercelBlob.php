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

    /** Files sent or fetched at the same time. */
    private const PARALLEL = 4;

    /** @var list<array{pathname: string, contents: string, type: string}>|null */
    private static ?array $pendingWrites = null;

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

        // A busy or briefly unavailable store (429/5xx) gets two more tries.
        $attempt = 0;
        do {
            $response = Http::withToken(self::token())
                ->withHeaders(self::apiHeaders() + [
                    'x-vercel-blob-access' => 'private',
                    'x-content-type' => $file->getMimeType() ?: 'application/octet-stream',
                    'x-add-random-suffix' => '0',
                ])
                ->withBody($bytes, $file->getMimeType() ?: 'application/octet-stream')
                ->timeout(50)
                ->put(self::API.'/?'.http_build_query(['pathname' => $pathname]));
            $retry = ($response->status() === 429 || $response->serverError()) && ++$attempt < 3;
            if ($retry) {
                usleep(400000 * $attempt);
            }
        } while ($retry);

        $url = $response->json('url');
        if (! $response->successful() || ! is_string($url) || ! self::isUrl($url)
            || strtolower((string) parse_url($url, PHP_URL_HOST)) !== self::host()) {
            // The store's own error code (e.g. a suspended store or a reached limit), never the token.
            $reason = $response->json('error.code') ?: $response->json('error.message') ?: '';
            throw new RuntimeException('Unable to store the attachment in private Blob storage (HTTP '.$response->status().($reason !== '' ? ', '.$reason : '').').');
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

    /**
     * A short-lived token that lets the browser upload one file straight to
     * Blob storage (@vercel/blob "upload"), so large files never pass through
     * the serverless function, whose request body is limited to 4.5 MB.
     * Same format as the SDK's generateClientTokenFromReadWriteToken.
     *
     * @param  list<string>  $allowedContentTypes
     */
    public static function clientToken(string $pathname, int $maximumSizeInBytes, array $allowedContentTypes, int $validForSeconds = 300): string
    {
        $payload = base64_encode((string) json_encode(array_filter([
            'pathname' => ltrim($pathname, '/'),
            'maximumSizeInBytes' => $maximumSizeInBytes,
            'allowedContentTypes' => $allowedContentTypes === [] ? null : array_values($allowedContentTypes),
            'addRandomSuffix' => true,
            'validUntil' => (int) round(microtime(true) * 1000) + $validForSeconds * 1000,
        ], fn ($value) => $value !== null), JSON_UNESCAPED_SLASHES));
        $signature = hash_hmac('sha256', $payload, self::token());

        return 'vercel_blob_client_'.self::storeId().'_'.base64_encode($signature.'.'.$payload);
    }

    /**
     * Files the browser uploaded straight to this store (with a clientToken),
     * fetched into temporary uploaded files so they get the same checks and
     * storage as posted files. Only references under $folder are accepted.
     * Pass the returned URLs and temp paths to discardUploads() afterwards.
     *
     * @param  array<string, mixed>  $references  key => blob URL
     * @param  array<string, mixed>  $names  key => original file name
     * @return array{0: array<string, UploadedFile>, 1: list<string>, 2: list<string>}
     *
     * @throws \Illuminate\Validation\ValidationException
     */
    public static function pullUploads(array $references, array $names, string $folder, string $field): array
    {
        if ($references === []) {
            return [[], [], []];
        }
        if (! self::enabled()) {
            throw \Illuminate\Validation\ValidationException::withMessages([$field => 'Direct uploads are not available here. Attach the files again.']);
        }

        foreach ($references as $key => $url) {
            if (! is_string($key) || ! is_string($url) || ! self::isOwnUrlUnder($url, $folder)) {
                throw \Illuminate\Validation\ValidationException::withMessages([$field => 'An uploaded file could not be verified. Attach it again.']);
            }
        }

        $urls = array_values($references);
        $temps = [];
        $files = [];
        try {
            // Fetched a few at a time in parallel, each streamed straight to a temp file.
            $targets = [];
            foreach ($references as $key => $url) {
                $temp = tempnam(sys_get_temp_dir(), 'upl');
                $temps[] = $temp;
                $targets[$key] = $temp;
            }
            $responses = Http::pool(function (\Illuminate\Http\Client\Pool $pool) use ($references, $targets) {
                foreach ($references as $key => $url) {
                    $pool->as((string) $key)->withToken(self::token())->sink($targets[$key])->timeout(50)->get($url);
                }
            }, self::PARALLEL);

            foreach ($references as $key => $url) {
                $response = $responses[(string) $key] ?? null;
                if ($response instanceof \Illuminate\Http\Client\Response && $response->notFound()) {
                    throw \Illuminate\Validation\ValidationException::withMessages([$field.'.'.$key => 'An uploaded file was not found. Attach it again.']);
                }
                if (! $response instanceof \Illuminate\Http\Client\Response || ! $response->successful()) {
                    throw new RuntimeException('Unable to read the private attachment.');
                }
                $name = basename(str_replace('\\', '/', (string) ($names[$key] ?? basename((string) parse_url($url, PHP_URL_PATH)))));
                $files[$key] = new UploadedFile($targets[$key], $name !== '' ? $name : 'document.pdf', null, null, true);
            }
        } catch (\Throwable $exception) {
            self::discardUploads($urls, $temps);

            throw $exception;
        }

        return [$files, $urls, $temps];
    }

    /** Removes the temporary copies and the direct uploads once their contents are stored. */
    public static function discardUploads(array $urls, array $temps): void
    {
        foreach ($temps as $path) {
            @unlink($path);
        }
        $urls = array_values(array_filter($urls, fn ($url) => is_string($url) && self::isUrl($url)
            && strtolower((string) parse_url($url, PHP_URL_HOST)) === self::host()));
        foreach (array_chunk($urls, 100) as $chunk) {
            try {
                Http::withToken(self::token())
                    ->withHeaders(self::apiHeaders())
                    ->timeout(30)
                    ->post(self::API.'/delete', ['urls' => $chunk]);
            } catch (\Throwable) {
                // Left in the private store; nothing links to it.
            }
        }
    }

    /** Whether a URL is a file in this store under the given folder. */
    public static function isOwnUrlUnder(string $url, string $folder): bool
    {
        if (! self::isUrl($url) || strtolower((string) parse_url($url, PHP_URL_HOST)) !== self::host()) {
            return false;
        }
        $path = rawurldecode(ltrim((string) parse_url($url, PHP_URL_PATH), '/'));

        return str_starts_with($path, trim($folder, '/').'/') && ! str_contains($path, '..');
    }

    /*
     * Path-based access for the "vercel-blob" filesystem driver (VercelBlobAdapter):
     * a pathname maps to a fixed URL in this store, so the database keeps the
     * same relative paths as on a local disk.
     */

    public static function urlFor(string $pathname): string
    {
        return 'https://'.self::host().'/'.implode('/', array_map('rawurlencode', explode('/', ltrim($pathname, '/'))));
    }

    /**
     * Runs $callback with Blob writes collected and sent a few at a time in parallel
     * instead of one after another. Every write is confirmed before this returns;
     * a failed write throws, so a surrounding transaction rolls back.
     */
    public static function batchWrites(callable $callback): mixed
    {
        if (self::$pendingWrites !== null) {
            return $callback();
        }

        self::$pendingWrites = [];
        try {
            $result = $callback();
            self::flushWrites();

            return $result;
        } finally {
            self::$pendingWrites = null;
        }
    }

    private static function flushWrites(): void
    {
        $writes = self::$pendingWrites ?? [];
        if ($writes === []) {
            return;
        }
        self::$pendingWrites = [];

        $responses = Http::pool(function (\Illuminate\Http\Client\Pool $pool) use ($writes) {
            foreach ($writes as $index => $write) {
                self::writeRequest($pool->as((string) $index), $write['type'])
                    ->withBody($write['contents'], $write['type'])
                    ->put(self::API.'/?'.http_build_query(['pathname' => $write['pathname']]));
            }
        }, self::PARALLEL);

        foreach (array_keys($writes) as $index) {
            $response = $responses[(string) $index] ?? null;
            $url = $response instanceof \Illuminate\Http\Client\Response ? $response->json('url') : null;
            if (! $response instanceof \Illuminate\Http\Client\Response || ! $response->successful() || ! is_string($url) || ! self::isUrl($url)) {
                throw new RuntimeException('Unable to store the file in private Blob storage.');
            }
        }
    }

    private static function writeRequest(\Illuminate\Http\Client\PendingRequest $request, string $type): \Illuminate\Http\Client\PendingRequest
    {
        return $request->withToken(self::token())
            ->withHeaders(self::apiHeaders() + [
                'x-vercel-blob-access' => 'private',
                'x-content-type' => $type,
                'x-add-random-suffix' => '0',
                // Same semantics as a disk: writing a path again replaces the file.
                'x-allow-overwrite' => '1',
            ])
            ->timeout(50);
    }

    public static function putContents(string $pathname, string $contents, ?string $mimeType = null): string
    {
        $type = $mimeType ?: 'application/octet-stream';
        if (self::$pendingWrites !== null) {
            self::$pendingWrites[] = ['pathname' => ltrim($pathname, '/'), 'contents' => $contents, 'type' => $type];
            if (count(self::$pendingWrites) >= self::PARALLEL) {
                self::flushWrites();
            }

            return self::urlFor($pathname);
        }
        $response = Http::withToken(self::token())
            ->withHeaders(self::apiHeaders() + [
                'x-vercel-blob-access' => 'private',
                'x-content-type' => $type,
                'x-add-random-suffix' => '0',
                // Same semantics as a disk: writing a path again replaces the file.
                'x-allow-overwrite' => '1',
            ])
            ->withBody($contents, $type)
            ->timeout(50)
            ->put(self::API.'/?'.http_build_query(['pathname' => ltrim($pathname, '/')]));

        $url = $response->json('url');
        if (! $response->successful() || ! is_string($url) || ! self::isUrl($url)) {
            throw new RuntimeException('Unable to store the file in private Blob storage (HTTP '.$response->status().').');
        }

        return $url;
    }

    /** @return array{size: ?int, type: ?string, modified: ?int}|null null when the file does not exist */
    public static function head(string $pathname): ?array
    {
        $response = Http::withToken(self::token())->timeout(15)->head(self::urlFor($pathname));
        if ($response->status() === 404) {
            return null;
        }
        if (! $response->successful()) {
            throw new RuntimeException('Unable to check the private file (HTTP '.$response->status().').');
        }

        $length = $response->header('Content-Length');
        $modified = $response->header('Last-Modified');

        return [
            'size' => is_numeric($length) ? (int) $length : null,
            'type' => $response->header('Content-Type') ?: null,
            'modified' => $modified !== '' ? (strtotime($modified) ?: null) : null,
        ];
    }

    public static function readPath(string $pathname): ?string
    {
        return self::read(self::urlFor($pathname));
    }

    public static function deletePaths(array $pathnames): bool
    {
        if ($pathnames === []) {
            return true;
        }
        $response = Http::withToken(self::token())
            ->withHeaders(self::apiHeaders())
            ->timeout(30)
            ->post(self::API.'/delete', ['urls' => array_map([self::class, 'urlFor'], $pathnames)]);

        return $response->successful();
    }

    /** @return list<array{pathname: string, size: ?int, modified: ?int}> */
    public static function list(string $prefix, int $limit = 1000): array
    {
        $blobs = [];
        $cursor = null;
        do {
            $response = Http::withToken(self::token())
                ->withHeaders(self::apiHeaders())
                ->timeout(30)
                ->get(self::API, array_filter(['prefix' => ltrim($prefix, '/'), 'limit' => min($limit, 1000), 'cursor' => $cursor]));
            if (! $response->successful()) {
                throw new RuntimeException('Unable to list private files (HTTP '.$response->status().').');
            }
            foreach ((array) $response->json('blobs', []) as $blob) {
                $blobs[] = [
                    'pathname' => (string) ($blob['pathname'] ?? ''),
                    'size' => isset($blob['size']) ? (int) $blob['size'] : null,
                    'modified' => isset($blob['uploadedAt']) ? (strtotime((string) $blob['uploadedAt']) ?: null) : null,
                ];
            }
            $cursor = $response->json('hasMore') ? $response->json('cursor') : null;
        } while ($cursor && count($blobs) < $limit);

        return $blobs;
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
