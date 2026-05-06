<?php

namespace App\Support;

use Illuminate\Filesystem\FilesystemAdapter;
use Illuminate\Http\File;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Throwable;

class Uploads
{
    public static function diskName(): string
    {
        return (string) config('filesystems.uploads_disk', config('filesystems.default', 'local'));
    }

    public static function store(File|UploadedFile $file, string $directory, string $filename): string
    {
        $path = static::disk()->putFileAs($directory, $file, $filename);

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

        $disk = static::disk();

        try {
            return $disk->temporaryUrl($path, now()->addMinutes(30));
        } catch (Throwable) {
            return $disk->url($path);
        }
    }

    public static function download(string $path, ?string $downloadName = null): BinaryFileResponse|StreamedResponse
    {
        if (static::isLegacyPublicPath($path)) {
            $fullPath = public_path($path);
            abort_unless(is_file($fullPath), 404);

            return response()->download($fullPath, $downloadName);
        }

        $disk = static::disk();
        abort_unless($disk->exists($path), 404);

        return $disk->download($path, $downloadName);
    }

    public static function inline(string $path, ?string $displayName = null, ?string $contentType = null)
    {
        if (static::isLegacyPublicPath($path)) {
            $fullPath = public_path($path);
            abort_unless(is_file($fullPath), 404);

            $contents = file_get_contents($fullPath);
            abort_unless($contents !== false, 404);
        } else {
            $disk = static::disk();
            abort_unless($disk->exists($path), 404);

            $contents = $disk->get($path);
        }

        $safeName = str_replace(['"', "\r", "\n"], '', $displayName ?: basename($path));

        return response($contents, 200, [
            'Content-Type' => $contentType ?: 'application/octet-stream',
            'Content-Disposition' => 'inline; filename="' . $safeName . '"',
            'X-Content-Type-Options' => 'nosniff',
        ]);
    }

    public static function contents(?string $path): ?string
    {
        if (! filled($path) || filter_var($path, FILTER_VALIDATE_URL)) {
            return null;
        }

        if (static::isLegacyPublicPath($path)) {
            $fullPath = public_path($path);

            if (! is_file($fullPath)) {
                return null;
            }

            $contents = file_get_contents($fullPath);

            return $contents === false ? null : $contents;
        }

        $disk = static::disk();

        return $disk->exists($path) ? $disk->get($path) : null;
    }

    public static function delete(?string $path): bool
    {
        if (! filled($path) || filter_var($path, FILTER_VALIDATE_URL)) {
            return false;
        }

        if (static::isLegacyPublicPath($path)) {
            $fullPath = public_path($path);

            return is_file($fullPath) ? @unlink($fullPath) : false;
        }

        $disk = static::disk();

        return $disk->exists($path) ? $disk->delete($path) : false;
    }

    public static function fileName(?string $path, ?string $originalName = null): ?string
    {
        if (filled($originalName)) {
            return $originalName;
        }

        return filled($path) ? basename($path) : null;
    }

    public static function extension(?string $path, ?string $originalName = null): ?string
    {
        $source = filled($originalName) ? $originalName : $path;

        if (! filled($source)) {
            return null;
        }

        $parsedPath = parse_url($source, PHP_URL_PATH);
        $pathForExtension = is_string($parsedPath) && $parsedPath !== '' ? $parsedPath : $source;

        $extension = strtolower((string) pathinfo($pathForExtension, PATHINFO_EXTENSION));

        return $extension !== '' ? $extension : null;
    }

    public static function isLegacyPublicPath(?string $path): bool
    {
        return filled($path) && str_starts_with($path, 'uploads/');
    }

    protected static function disk(): FilesystemAdapter
    {
        /** @var FilesystemAdapter $disk */
        $disk = Storage::disk(static::diskName());

        return $disk;
    }
}
