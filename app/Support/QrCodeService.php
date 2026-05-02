<?php

namespace App\Support;

use BaconQrCode\Renderer\Image\SvgImageBackEnd;
use BaconQrCode\Renderer\ImageRenderer;
use BaconQrCode\Renderer\RendererStyle\RendererStyle;
use BaconQrCode\Writer;

class QrCodeService
{
    public function toSvg(string $content, int $size = 220, int $margin = 1): string
    {
        $renderer = new ImageRenderer(
            new RendererStyle($size, $margin),
            new SvgImageBackEnd()
        );

        return $this->decorateSvg(
            (new Writer($renderer))->writeString($content),
            $content
        );
    }

    public function toDataUri(string $content, int $size = 220, int $margin = 1): string
    {
        return 'data:image/svg+xml;base64,' . base64_encode(
            $this->toSvg($content, $size, $margin)
        );
    }

    protected function decorateSvg(string $svg, string $content): string
    {
        $encodedPayload = htmlspecialchars($content, ENT_QUOTES | ENT_XML1, 'UTF-8');
        $metadata = sprintf(
            '<desc>BAC Office bidder login QR code</desc><metadata data-bac-office-qr-format="bidder-login" data-bac-office-qr-payload="%s"></metadata>',
            $encodedPayload
        );

        $decorated = preg_replace(
            '/<svg\b([^>]*)>/',
            sprintf(
                '<svg$1 data-bac-office-qr-format="bidder-login" data-bac-office-qr-payload="%s">%s',
                $encodedPayload,
                $metadata
            ),
            $svg,
            1
        );

        return is_string($decorated) && $decorated !== '' ? $decorated : $svg;
    }
}
