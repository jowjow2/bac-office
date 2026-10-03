<?php

namespace App\Support;

use League\Flysystem\Config;
use League\Flysystem\DirectoryAttributes;
use League\Flysystem\FileAttributes;
use League\Flysystem\FilesystemAdapter;
use League\Flysystem\UnableToCheckExistence;
use League\Flysystem\UnableToCopyFile;
use League\Flysystem\UnableToDeleteDirectory;
use League\Flysystem\UnableToDeleteFile;
use League\Flysystem\UnableToMoveFile;
use League\Flysystem\UnableToReadFile;
use League\Flysystem\UnableToRetrieveMetadata;
use League\Flysystem\UnableToSetVisibility;
use League\Flysystem\UnableToWriteFile;
use League\Flysystem\Visibility;
use Throwable;

/**
 * Laravel disk on private Vercel Blob storage, for serverless deployments whose
 * own filesystem is read-only. Paths stay relative ("project-documents/x.pdf");
 * each disk keeps its files under its own prefix. Files are never public: the
 * app serves them through signed links (see the "files.blob" route).
 */
class VercelBlobAdapter implements FilesystemAdapter
{
    public function __construct(private readonly string $prefix = '') {}

    private function pathname(string $path): string
    {
        $path = ltrim(str_replace('\\', '/', $path), '/');

        return $this->prefix === '' ? $path : trim($this->prefix, '/').'/'.$path;
    }

    private function relative(string $pathname): string
    {
        $prefix = $this->prefix === '' ? '' : trim($this->prefix, '/').'/';

        return $prefix !== '' && str_starts_with($pathname, $prefix) ? substr($pathname, strlen($prefix)) : $pathname;
    }

    public function fileExists(string $path): bool
    {
        try {
            return VercelBlob::head($this->pathname($path)) !== null;
        } catch (Throwable $exception) {
            throw UnableToCheckExistence::forLocation($path, $exception);
        }
    }

    public function directoryExists(string $path): bool
    {
        try {
            return VercelBlob::list(rtrim($this->pathname($path), '/').'/', 1) !== [];
        } catch (Throwable $exception) {
            throw UnableToCheckExistence::forLocation($path, $exception);
        }
    }

    public function write(string $path, string $contents, Config $config): void
    {
        try {
            VercelBlob::putContents($this->pathname($path), $contents, $config->get('mimetype') ?: $this->guessMimeType($path, $contents));
        } catch (Throwable $exception) {
            throw UnableToWriteFile::atLocation($path, $exception->getMessage(), $exception);
        }
    }

    public function writeStream(string $path, $contents, Config $config): void
    {
        $data = stream_get_contents($contents);
        if ($data === false) {
            throw UnableToWriteFile::atLocation($path, 'Unable to read the stream.');
        }
        $this->write($path, $data, $config);
    }

    public function read(string $path): string
    {
        try {
            $contents = VercelBlob::readPath($this->pathname($path));
        } catch (Throwable $exception) {
            throw UnableToReadFile::fromLocation($path, $exception->getMessage(), $exception);
        }
        if ($contents === null) {
            throw UnableToReadFile::fromLocation($path, 'File not found.');
        }

        return $contents;
    }

    public function readStream(string $path)
    {
        $stream = fopen('php://temp', 'w+b');
        fwrite($stream, $this->read($path));
        rewind($stream);

        return $stream;
    }

    public function delete(string $path): void
    {
        try {
            if (! VercelBlob::deletePaths([$this->pathname($path)])) {
                throw UnableToDeleteFile::atLocation($path, 'Blob storage refused the delete.');
            }
        } catch (UnableToDeleteFile $exception) {
            throw $exception;
        } catch (Throwable $exception) {
            throw UnableToDeleteFile::atLocation($path, $exception->getMessage(), $exception);
        }
    }

    public function deleteDirectory(string $path): void
    {
        try {
            $pathnames = array_column(VercelBlob::list(rtrim($this->pathname($path), '/').'/', 10000), 'pathname');
            foreach (array_chunk($pathnames, 100) as $chunk) {
                VercelBlob::deletePaths($chunk);
            }
        } catch (Throwable $exception) {
            throw UnableToDeleteDirectory::atLocation($path, $exception->getMessage(), $exception);
        }
    }

    public function createDirectory(string $path, Config $config): void
    {
        // Blob storage has no directories; paths are created with their files.
    }

    public function setVisibility(string $path, string $visibility): void
    {
        if ($visibility !== Visibility::PRIVATE) {
            throw UnableToSetVisibility::atLocation($path, 'Files on this disk are always private.');
        }
    }

    public function visibility(string $path): FileAttributes
    {
        return new FileAttributes($path, null, Visibility::PRIVATE);
    }

    public function mimeType(string $path): FileAttributes
    {
        $meta = $this->metadata($path, 'mimeType');

        return new FileAttributes($path, null, null, null, $meta['type'] ?: $this->guessMimeType($path, ''));
    }

    public function lastModified(string $path): FileAttributes
    {
        return new FileAttributes($path, null, null, $this->metadata($path, 'lastModified')['modified']);
    }

    public function fileSize(string $path): FileAttributes
    {
        return new FileAttributes($path, $this->metadata($path, 'fileSize')['size']);
    }

    public function listContents(string $path, bool $deep): iterable
    {
        $base = rtrim($this->pathname($path), '/');
        $base = $base === '' ? '' : $base.'/';
        $directories = [];

        foreach (VercelBlob::list($base, 10000) as $blob) {
            $relative = $this->relative($blob['pathname']);
            $rest = substr($blob['pathname'], strlen($base));
            if (! $deep && str_contains($rest, '/')) {
                $directory = $this->relative($base.strtok($rest, '/'));
                if (! isset($directories[$directory])) {
                    $directories[$directory] = true;
                    yield new DirectoryAttributes($directory);
                }

                continue;
            }

            yield new FileAttributes($relative, $blob['size'], Visibility::PRIVATE, $blob['modified']);
        }
    }

    public function move(string $source, string $destination, Config $config): void
    {
        try {
            $this->copy($source, $destination, $config);
            $this->delete($source);
        } catch (Throwable $exception) {
            throw UnableToMoveFile::fromLocationTo($source, $destination, $exception);
        }
    }

    public function copy(string $source, string $destination, Config $config): void
    {
        try {
            $this->write($destination, $this->read($source), $config);
        } catch (Throwable $exception) {
            throw UnableToCopyFile::fromLocationTo($source, $destination, $exception);
        }
    }

    /** @return array{size: ?int, type: ?string, modified: ?int} */
    private function metadata(string $path, string $attribute): array
    {
        try {
            $meta = VercelBlob::head($this->pathname($path));
        } catch (Throwable $exception) {
            throw UnableToRetrieveMetadata::create($path, $attribute, $exception->getMessage(), $exception);
        }
        if ($meta === null) {
            throw UnableToRetrieveMetadata::create($path, $attribute, 'File not found.');
        }

        return $meta;
    }

    private function guessMimeType(string $path, string $contents): string
    {
        $byExtension = [
            'pdf' => 'application/pdf', 'png' => 'image/png', 'jpg' => 'image/jpeg', 'jpeg' => 'image/jpeg',
            'doc' => 'application/msword', 'docx' => 'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
            'xls' => 'application/vnd.ms-excel', 'xlsx' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            'txt' => 'text/plain', 'svg' => 'image/svg+xml',
        ];

        return $byExtension[strtolower(pathinfo($path, PATHINFO_EXTENSION))] ?? 'application/octet-stream';
    }
}
