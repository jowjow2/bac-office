<?php

namespace App\Support;

use BaconQrCode\Renderer\Image\SvgImageBackEnd;
use BaconQrCode\Renderer\ImageRenderer;
use BaconQrCode\Renderer\RendererStyle\RendererStyle;
use BaconQrCode\Writer;

class QrCodeService
{
    public function toSvg(string $content, int $size = 180, int $margin = 1): string
    {
        $renderer = new ImageRenderer(
            new RendererStyle($size, $margin),
            new SvgImageBackEnd()
        );

        return (new Writer($renderer))->writeString($content);
    }

    public function toDataUri(string $content, int $size = 180, int $margin = 1): string
    {
        return 'data:image/svg+xml;base64,' . base64_encode(
            $this->toSvg($content, $size, $margin)
        );
    }
}
