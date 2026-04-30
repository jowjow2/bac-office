<?php

namespace App\Support;

use Illuminate\Support\Facades\Storage;
use Throwable;

class Uploads
{
    public static function diskName(): string
    {
        return (string) config('filesystems.uploads_disk', config('filesystems.default', 'local'));
    }

    public static function store($file, string $directory, string $filename): string
    {
        $path = Storage::disk(static::diskName())->putFileAs($directory, $file, $filename);

        if (! is_string($path) || $path === '') {
            throw new \RuntimeException('Unable to store uploaded file.');
        }

        return $path;
    }

    public static function url(?string $path): ?string
    {
        if (! filled($path)) {
            return null;
        }

        if (filter_var($path, FILTER_VALIDATE_URL)) {
            return $path;
        }

        if (static::isLegacyPublicPath($path)) {
            return asset($path);
        }

        $disk = Storage::disk(static::diskName());

        try {
            return $disk->temporaryUrl($path, now()->addMinutes(30));
        } catch (Throwable) {
            return $disk->url($path);
        }
    }

    public static function download(string $path, ?string $downloadName = null)
    {
        if (static::isLegacyPublicPath($path)) {
            $fullPath = public_path($path);
            abort_unless(is_file($fullPath), 404);

            return response()->download($fullPath, $downloadName);
        }

        $disk = Storage::disk(static::diskName());
        abort_unless($disk->exists($path), 404);

        return $disk->download($path, $downloadName);
    }

    public static function fileName(?string $path, ?string $originalName = null): ?string
    {
        if (filled($originalName)) {
            return $originalName;
        }

        return filled($path) ? basename($path) : null;
    }

    public static function isLegacyPublicPath(?string $path): bool
    {
        return filled($path) && str_starts_with($path, 'uploads/');
    }
}
