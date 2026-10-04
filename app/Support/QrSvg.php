<?php

namespace App\Support;

use BaconQrCode\Common\ErrorCorrectionLevel;
use BaconQrCode\Encoder\Encoder;

/**
 * QR codes as SVG for the public QR images (bidder record, award, project).
 * The library's SVG renderer needs PHP's XMLWriter extension, which the
 * Vercel PHP runtime lacks (every QR image answered 500 there); this draws
 * the same encoded matrix as one SVG path with plain strings.
 */
final class QrSvg
{
    /** @param int $size rendered width and height in pixels; $margin quiet zone in modules */
    public static function render(string $content, int $size = 320, int $margin = 4): string
    {
        $matrix = Encoder::encode($content, ErrorCorrectionLevel::M())->getMatrix();
        $width = $matrix->getWidth();
        $total = $width + 2 * $margin;

        $path = '';
        for ($y = 0; $y < $width; $y++) {
            for ($x = 0; $x < $width; $x++) {
                if ($matrix->get($x, $y) === 1) {
                    $path .= 'M'.($x + $margin).' '.($y + $margin).'h1v1h-1z';
                }
            }
        }

        return '<?xml version="1.0" encoding="UTF-8"?>'."\n"
            .'<svg xmlns="http://www.w3.org/2000/svg" width="'.$size.'" height="'.$size.'" viewBox="0 0 '.$total.' '.$total.'" shape-rendering="crispEdges">'
            .'<rect width="'.$total.'" height="'.$total.'" fill="#ffffff"/>'
            .'<path fill="#000000" d="'.$path.'"/>'
            .'</svg>';
    }
}
