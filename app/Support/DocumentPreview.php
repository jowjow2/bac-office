<?php

namespace App\Support;

use DOMDocument;
use DOMNode;
use DOMXPath;
use ZipArchive;

class DocumentPreview
{
    public static function forUpload(?string $path, ?string $displayName = null): array
    {
        $originalUrl = Uploads::url($path);
        $title = Uploads::fileName($path, $displayName) ?? 'Uploaded file';
        $extension = Uploads::extension($path, $displayName);

        if (! filled($path) || ! filled($originalUrl)) {
            return [
                'available' => false,
                'mode' => 'missing',
                'title' => $title,
                'original_url' => null,
                'message' => 'No uploaded file is available for preview.',
            ];
        }

        return match ($extension) {
            'pdf' => [
                'available' => true,
                'mode' => 'embed',
                'title' => $title,
                'original_url' => $originalUrl,
                'embed_url' => $originalUrl,
                'message' => null,
            ],
            'jpg', 'jpeg', 'png', 'gif', 'webp', 'bmp', 'svg' => [
                'available' => true,
                'mode' => 'image',
                'title' => $title,
                'original_url' => $originalUrl,
                'embed_url' => $originalUrl,
                'message' => null,
            ],
            'txt', 'csv', 'json', 'xml', 'log', 'md' => [
                'available' => true,
                'mode' => 'text',
                'title' => $title,
                'original_url' => $originalUrl,
                'html' => static::renderPlainText($path),
                'message' => null,
            ],
            'docx' => static::renderDocxPreview($path, $title, $originalUrl),
            default => [
                'available' => true,
                'mode' => 'unsupported',
                'title' => $title,
                'original_url' => $originalUrl,
                'message' => 'This file type cannot be fully previewed here, but you can still open the original file.',
            ],
        };
    }

    protected static function renderPlainText(string $path): string
    {
        $contents = Uploads::contents($path) ?? '';

        if (! mb_check_encoding($contents, 'UTF-8')) {
            $contents = mb_convert_encoding($contents, 'UTF-8', 'UTF-8, ISO-8859-1, Windows-1252');
        }

        $lines = preg_split("/\\r\\n|\\r|\\n/", trim($contents));

        if (! is_array($lines)) {
            $lines = [];
        }

        if ($lines === []) {
            return '<p>No preview text available for this file.</p>';
        }

        $paragraphs = array_map(
            static fn (string $line): string => '<p>' . e($line === '' ? ' ' : $line) . '</p>',
            array_slice($lines, 0, 300)
        );

        return implode('', $paragraphs);
    }

    protected static function renderDocxPreview(string $path, string $title, string $originalUrl): array
    {
        $html = static::extractDocxHtml($path);

        if ($html !== null) {
            return [
                'available' => true,
                'mode' => 'text',
                'title' => $title,
                'original_url' => $originalUrl,
                'html' => $html,
                'message' => null,
            ];
        }

        return [
            'available' => true,
            'mode' => 'unsupported',
            'title' => $title,
            'original_url' => $originalUrl,
            'message' => 'The DOCX file could not be rendered here, but you can still open the original file.',
        ];
    }

    protected static function extractDocxHtml(string $path): ?string
    {
        $contents = Uploads::contents($path);

        if ($contents === null || $contents === '') {
            return null;
        }

        $tempFile = tempnam(sys_get_temp_dir(), 'bac-docx-');

        if (! is_string($tempFile) || $tempFile === '') {
            return null;
        }

        if (file_put_contents($tempFile, $contents) === false) {
            @unlink($tempFile);

            return null;
        }

        $zip = new ZipArchive();
        $opened = $zip->open($tempFile);

        if ($opened !== true) {
            @unlink($tempFile);

            return null;
        }

        $documentXml = $zip->getFromName('word/document.xml');
        $zip->close();
        @unlink($tempFile);

        if (! is_string($documentXml) || trim($documentXml) === '') {
            return null;
        }

        $dom = new DOMDocument();

        if (! @ $dom->loadXML($documentXml)) {
            return null;
        }

        $xpath = new DOMXPath($dom);
        $xpath->registerNamespace('w', 'http://schemas.openxmlformats.org/wordprocessingml/2006/main');

        $paragraphs = [];
        $paragraphNodes = $xpath->query('//w:body/w:p');

        if ($paragraphNodes === false) {
            return null;
        }

        foreach ($paragraphNodes as $paragraphNode) {
            if (! $paragraphNode instanceof DOMNode) {
                continue;
            }

            $fragments = [];
            $inlineNodes = $xpath->query('.//w:t | .//w:tab | .//w:br', $paragraphNode);

            if ($inlineNodes === false) {
                continue;
            }

            foreach ($inlineNodes as $inlineNode) {
                if (! $inlineNode instanceof DOMNode) {
                    continue;
                }

                if ($inlineNode->localName === 't') {
                    $fragments[] = $inlineNode->textContent;
                    continue;
                }

                if ($inlineNode->localName === 'tab') {
                    $fragments[] = "\t";
                    continue;
                }

                if ($inlineNode->localName === 'br') {
                    $fragments[] = "\n";
                }
            }

            $text = trim(preg_replace("/[ \t]+/u", ' ', implode('', $fragments)) ?? '');

            if ($text !== '') {
                $paragraphs[] = '<p>' . e($text) . '</p>';
            }
        }

        return $paragraphs === [] ? null : implode('', $paragraphs);
    }
}
