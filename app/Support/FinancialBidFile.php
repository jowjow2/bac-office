<?php

namespace App\Support;

use App\Models\BidDocument;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use RuntimeException;

/** Private, application-key encrypted storage for online financial bid files. */
class FinancialBidFile
{
    private const DISK = 'local';
    private const PREFIX = 'bid-financial/';

    /** @return array{path:string, encrypted_at:\Illuminate\Support\Carbon} */
    public function store(UploadedFile $file, int $projectId, int $bidId, string $requirementKey): array
    {
        $contents = file_get_contents($file->getRealPath());
        if (! is_string($contents)) throw new RuntimeException('Unable to read financial bid file.');

        $path = self::PREFIX.$projectId.'/'.$bidId.'/'.Str::slug($requirementKey, '_').'_'.Str::random(24).'.enc';
        if (! Storage::disk(self::DISK)->put($path, Crypt::encryptString($contents))) {
            throw new RuntimeException('Unable to securely store financial bid file.');
        }

        return ['path' => $path, 'encrypted_at' => now()];
    }

    public function contents(BidDocument|string $document, ?\Illuminate\Support\Carbon $encryptedAt = null): ?string
    {
        if ($document instanceof BidDocument) {
            $path = $document->file_path;
            $encryptedAt = $document->encrypted_at;
        } else {
            $path = $document;
        }

        if (! filled($path)) return null;

        $encryptedPrivate = str_starts_with($path, self::PREFIX);
        if ($encryptedPrivate) {
            $payload = Storage::disk(self::DISK)->exists($path) ? Storage::disk(self::DISK)->get($path) : null;
        } elseif ($encryptedAt !== null) {
            $payload = Uploads::contents($path);
        } else {
            return Uploads::contents($path);
        }

        if (! is_string($payload)) return null;

        return Crypt::decryptString($payload);
    }

    public function delete(string $path): bool
    {
        if (str_starts_with($path, self::PREFIX)) {
            return Storage::disk(self::DISK)->delete($path);
        }

        return Uploads::delete($path);
    }

    public function response(BidDocument|string $document, ?string $displayName = null, ?\Illuminate\Support\Carbon $encryptedAt = null, string $requestedDisposition = 'inline')
    {
        if ($document instanceof BidDocument) {
            $displayName = $document->original_name;
            $contents = $this->contents($document);
        } else {
            $contents = $this->contents($document, $encryptedAt);
        }

        abort_unless(is_string($contents), 404);
        $name = str_replace(['"', "\r", "\n"], '', $displayName ?: 'financial-component');
        $extension = strtolower(pathinfo($name, PATHINFO_EXTENSION));
        $contentType = $extension === 'pdf' ? 'application/pdf' : 'application/octet-stream';
        $disposition = $requestedDisposition === 'attachment' || $extension !== 'pdf' ? 'attachment' : 'inline';

        return response($contents, 200, [
            'Content-Type' => $contentType,
            'Content-Disposition' => $disposition.'; filename="'.$name.'"',
            'X-Content-Type-Options' => 'nosniff',
            'Cache-Control' => 'private, no-store, max-age=0',
            'Pragma' => 'no-cache',
        ]);
    }
}