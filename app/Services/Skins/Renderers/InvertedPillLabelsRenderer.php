<?php

namespace App\Services\Skins\Renderers;

use App\Services\Skins\SkinRendererInterface;

/**
 * Skin style: "inverted_pill_labels"
 * Renders four rounded filled tiles (dark background) with large white digits,
 * thin colon separators between tiles, and red full-word labels below each tile,
 * matching the provided reference screenshot.
 *
 * Recognized skin keys (with fallbacks):
 *  - width, height, background, textColor (digit color), accentColor (label/divider color)
 *  - fontPath, fontSize
 *  - tileColor (optional; defaults to #000000)
 */
class InvertedPillLabelsRenderer implements SkinRendererInterface
{
    public function renderStyle($im, array $skin, int $width, int $height, int $bgCol, int $textCol, int $accentCol, int $secondsRemaining): void
    {
        // Fill background
        imagefilledrectangle($im, 0, 0, $width, $height, $bgCol);

        // Geometry
        $segments = 4;
        $gapX = max(8, (int)round($width * 0.02));
        $padY  = max(10, (int)round($height * 0.15)); // leave room for labels
        $tileW = (int) floor(($width - ($gapX * ($segments + 1))) / $segments);
        $tileH = (int) round(($height - 2 * $padY) * 0.68); // top area for digits (above labels)

        // Colors
        [$br, $bg_, $bb] = $this->hexToRgb($skin['background'] ?? '#FFFFFF'); // unused aside from $bgCol param
        [$tr, $tg, $tb]  = $this->hexToRgb($skin['textColor'] ?? '#FFFFFF'); // digits color
        [$ar, $ag, $ab]  = $this->hexToRgb($skin['accentColor'] ?? '#FF3B30'); // labels color
        [$cr, $cg, $cb]  = $this->hexToRgb($skin['tileColor'] ?? '#000000'); // tile fill
        $tileCol = imagecolorallocate($im, $cr, $cg, $cb);
        $digitCol = imagecolorallocate($im, $tr, $tg, $tb);
        $labelCol = imagecolorallocate($im, $ar, $ag, $ab);
        $colonCol = imagecolorallocate($im, 0, 0, 0); // colon in dark for contrast

        // Draw the four rounded tiles
        for ($i = 0; $i < $segments; $i++) {
            $x1 = $gapX + $i * ($tileW + $gapX);
            $y1 = $padY - (int)round($tileH * 0.15); // raise tiles a bit to mimic screenshot spacing
            $x2 = $x1 + $tileW;
            $y2 = $y1 + $tileH;

            $r = max(8, (int)round(min($tileW, $tileH) * 0.18));
            // Rounded filled rect (approx)
            imagefilledrectangle($im, $x1 + $r, $y1, $x2 - $r, $y2, $tileCol);
            imagefilledrectangle($im, $x1, $y1 + $r, $x2, $y2 - $r, $tileCol);
            imagefilledellipse($im, $x1 + $r, $y1 + $r, $r * 2, $r * 2, $tileCol);
            imagefilledellipse($im, $x2 - $r, $y1 + $r, $r * 2, $r * 2, $tileCol);
            imagefilledellipse($im, $x1 + $r, $y2 - $r, $r * 2, $r * 2, $tileCol);
            imagefilledellipse($im, $x2 - $r, $y2 - $r, $r * 2, $r * 2, $tileCol);
        }

        // Colon separators between tiles
        $font = $skin['fontPath'] ?? '';
        $fontSize = (int)($skin['fontSize'] ?? 32);
        if (is_file($font)) {
            for ($i = 0; $i < $segments - 1; $i++) {
                $cx = $gapX + ($i + 1) * $tileW + $i * $gapX + (int)($gapX / 2);
                $cy = (int)($padY + $tileH * 0.45);
                $colon = ':';
                $box = imagettfbbox($fontSize, 0, $font, $colon);
                $cw  = abs($box[4] - $box[0]);
                $ch  = abs($box[5] - $box[1]);
                $x   = (int)($cx - $cw / 2);
                $y   = (int)($cy + $ch / 2);
                imagettftext($im, $fontSize, 0, $x, $y, $colonCol, $font, $colon);
            }
        }
    }

    public function renderTimeText($im, int $secondsRemaining, array $skin, int $width, int $height, int $textCol, int $accentCol): bool
    {
        $font = $skin['fontPath'] ?? '';
        if (!is_file($font)) {
            return true;
        }

        $fontSize  = (int)($skin['fontSize'] ?? 32);
        $labelSize = max(10, (int) round($fontSize * 0.38));

        $days    = (int) floor($secondsRemaining / 86400);
        $hours   = (int) floor(($secondsRemaining % 86400) / 3600);
        $minutes = (int) floor(($secondsRemaining % 3600) / 60);
        $seconds = (int) ($secondsRemaining % 60);

        $units = [
            ['value' => $days,    'label' => 'DAYS'],
            ['value' => $hours,   'label' => 'HOURS'],
            ['value' => $minutes, 'label' => 'MINUTES'],
            ['value' => $seconds, 'label' => 'SECONDS'],
        ];

        $segments = count($units);
        $gapX = max(8, (int)round($width * 0.02));
        $padY  = max(10, (int)round($height * 0.15));
        $tileW = (int) floor(($width - ($gapX * ($segments + 1))) / $segments);
        $tileH = (int) round(($height - 2 * $padY) * 0.68);
        $divY  = (int) ($padY + $tileH * 0.62);

        for ($i = 0; $i < $segments; $i++) {
            $x1 = $gapX + $i * ($tileW + $gapX);

            // Large digits (use text color which is intended as white on dark tile)
            $num = sprintf('%02d', $units[$i]['value']);
            $numBox = imagettfbbox($fontSize, 0, $font, $num);
            $numW = abs($numBox[4] - $numBox[0]);
            $numX = (int) ($x1 + ($tileW - $numW) / 2);
            $numY = (int) ($divY - 8);
            imagettftext($im, $fontSize, 0, $numX, $numY, $textCol, $font, $num);

            // Red label under divider
            $label = (string)$units[$i]['label'];
            $lblBox = imagettfbbox($labelSize, 0, $font, $label);
            $lblW = abs($lblBox[4] - $lblBox[0]);
            $lblX = (int) ($x1 + ($tileW - $lblW) / 2);
            $lblY = (int) ($divY + $labelSize + 6);
            imagettftext($im, $labelSize, 0, $lblX, $lblY, $accentCol, $font, $label);
        }

        return true;
    }

    private function hexToRgb(string $hex): array
    {
        $hex = ltrim($hex, '#');
        if (strlen($hex) === 3) {
            $hex = $hex[0].$hex[0].$hex[1].$hex[1].$hex[2].$hex[2];
        }
        return [
            hexdec(substr($hex, 0, 2)),
            hexdec(substr($hex, 2, 2)),
            hexdec(substr($hex, 4, 2)),
        ];
    }
}


