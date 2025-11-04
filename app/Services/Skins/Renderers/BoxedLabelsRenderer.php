<?php

namespace App\Services\Skins\Renderers;

use App\Services\Skins\SkinReaderInterface; // not used, but keep interface import consistent
use App\Services\Skins\SkinRendererInterface;

/**
 * Renders a boxed countdown with four rounded tiles, a thin divider line, large
 * two–digit numbers on top, and full-word labels below (DAYS/HOURS/MINUTES/SECONDS),
 * similar to the provided reference image.
 */
class BoxedLabelsRenderer implements SkinRendererInterface
{
    public function renderStyle($im, array $skin, int $width, int $height, int $bgCol, int $textCol, int $accentCol, int $secondsRemaining): void
    {
        // Fill background
        imagefilledrectangle($im, 0, 0, $width, $height, $bgCol);

        $segments = 4;
        $gap = max(6, (int)round($width * 0.02));
        $padY = max(8, (int)round($height * 0.08));
        $segW = (int) floor(($width - ($gap * ($segments + 1))) / $segments);
        $segH = $height - 2 * $padY;

        // Draw each tile with rounded border and divider
        for ($i = 0; $i < $segments; $i++) {
            $x1 = $gap + $i * ($segW + $gap);
            $y1 = $padY;
            $x2 = $x1 + $segW;
            $y2 = $y1 + $segH;

            // Rounded border (approx via rectangles + circles)
            $r = max(6, (int)round(min($segW, $segH) * 0.10));
            imagefilledrectangle($im, $x1 + $r, $y1, $x2 - $r, $y2, $bgCol);
            imagefilledrectangle($im, $x1, $y1 + $r, $x2, $y2 - $r, $bgCol);
            imagefilledellipse($im, $x1 + $r, $y1 + $r, $r * 2, $r * 2, $bgCol);
            imagefilledellipse($im, $x2 - $r, $y1 + $r, $r * 2, $r * 2, $bgCol);
            imagefilledellipse($im, $x1 + $r, $y2 - $r, $r * 2, $r * 2, $bgCol);
            imagefilledellipse($im, $x2 - $r, $y2 - $r, $r * 2, $r * 2, $bgCol);

        	// Outline
            imagerectangle($im, $x1-5, $y1-5, $x2+5, $y2+5, $textCol);

            // Thin divider line ~62% from top
            $divY = (int) ($y1 + $segH * 0.62);
            imageline($im, $x1 + 2, $divY, $x2 - 2, $divY, $accentCol);
        }
    }

    public function renderTimeText($im, int $secondsRemaining, array $skin, int $width, int $height, int $textCol, int $accentCol): bool
    {
        $font = $skin['fontPath'] ?? '';
        if (!is_file($font)) {
            return true; // silently skip if font missing; style still visible
        }

        $fontSize = (int)($skin['fontSize'] ?? 32);
        $labelSize = max(10, (int) round($fontSize * 0.42));

        $days = (int) floor($secondsRemaining / 86400);
        $hours = (int) floor(($secondsRemaining % 86400) / 3600);
        $minutes = (int) floor(($secondsRemaining % 3600) / 60);
        $seconds = (int) ($secondsRemaining % 60);

        $units = [
            ['value' => $days,    'label' => 'DAYS'],
            ['value' => $hours,   'label' => 'HOURS'],
            ['value' => $minutes, 'label' => 'MINUTES'],
            ['value' => $seconds, 'label' => 'SECONDS'],
        ];

        $segments = count($units);
        $gap = max(6, (int)round($width * 0.02));
        $padY = max(8, (int)round($height * 0.08));
        $segW = (int) floor(($width - ($gap * ($segments + 1))) / $segments);
        $segH = $height - 2 * $padY;
        $divY = (int) ($padY + $segH * 0.62);

        for ($i = 0; $i < $segments; $i++) {
            $x1 = $gap + $i * ($segW + $gap);

            $num = sprintf('%02d', $units[$i]['value']);
            $numBox = imagettfbbox($fontSize, 0, $font, $num);
            $numW = abs($numBox[4] - $numBox[0]);
            $numX = (int)($x1 + ($segW - $numW) / 2);
            $numY = $divY - 8; // slightly above divider
            imagettftext($im, $fontSize, 0, $numX, $numY, $textCol, $font, $num);

            $label = (string)$units[$i]['label'];
            $lblBox = imagettfbbox($labelSize, 0, $font, $label);
            $lblW = abs($lblBox[4] - $lblBox[0]);
            $lblX = (int)($x1 + ($segW - $lblW) / 2);
            $lblY = $divY + $labelSize + 6;
            imagettftext($im, $labelSize, 0, $lblX, $lblY, $textCol, $font, $label);
        }

        return true;
    }
}


