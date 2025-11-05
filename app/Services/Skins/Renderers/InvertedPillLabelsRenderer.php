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

        // Geometry (now: 4 segments; each segment has TWO digit tiles)
        $segments = 4;
        $gapX = max(8, (int)round($width * 0.02));            // gap between segments
        $innerGap = max(6, (int)round($width * 0.012));        // gap between the two digit tiles inside a segment
        $padTop  = max(8, (int)round($height * 0.12));         // top padding above tiles
        $labelArea = max(20, (int)round($height * 0.26));      // reserved height for labels
        $segW = (int) floor(($width - ($gapX * ($segments + 1))) / $segments);
        $digitTileH = max(24, $height - $labelArea - $padTop); // height of digit tiles area

        // Colors
        [$br, $bg_, $bb] = $this->hexToRgb($skin['background'] ?? '#FFFFFF'); // unused aside from $bgCol param
        [$tr, $tg, $tb]  = $this->hexToRgb($skin['textColor'] ?? '#FFFFFF'); // digits color
        [$ar, $ag, $ab]  = $this->hexToRgb($skin['accentColor'] ?? '#FF3B30'); // labels color
        [$cr, $cg, $cb]  = $this->hexToRgb($skin['tileColor'] ?? '#000000'); // tile fill
        $tileCol = imagecolorallocate($im, $cr, $cg, $cb);
        $digitCol = imagecolorallocate($im, $tr, $tg, $tb);
        $labelCol = imagecolorallocate($im, $ar, $ag, $ab);
        $colonCol = imagecolorallocate($im, 0, 0, 0); // colon in dark for contrast

        // Layout orientation
        $vertical = isset($skin['layout']) && $skin['layout'] === 'vertical';

        // Draw two rounded tiles per segment (for each digit)
        for ($i = 0; $i < $segments; $i++) {
            $segX1 = $gapX + $i * ($segW + $gapX);
            $segY1 = $padTop; // consistent top padding; for vertical we still align top and renderer will compute below
            $segX2 = $segX1 + $segW;
            $segY2 = $segY1 + $digitTileH;

            $singleTileW = (int) floor(($segW - $innerGap) / 2);
            $leftX1  = $segX1;
            $leftX2  = $leftX1 + $singleTileW;
            $rightX1 = $leftX2 + $innerGap;
            $rightX2 = $segX2;
            $y1 = $segY1;
            $y2 = $segY2;

            $r = max(8, (int)round(min($singleTileW, $digitTileH) * 0.18));

            // Left digit tile
            imagefilledrectangle($im, $leftX1 + $r, $y1, $leftX2 - $r, $y2, $tileCol);
            imagefilledrectangle($im, $leftX1, $y1 + $r, $leftX2, $y2 - $r, $tileCol);
            imagefilledellipse($im, $leftX1 + $r, $y1 + $r, $r * 2, $r * 2, $tileCol);
            imagefilledellipse($im, $leftX2 - $r, $y1 + $r, $r * 2, $r * 2, $tileCol);
            imagefilledellipse($im, $leftX1 + $r, $y2 - $r, $r * 2, $r * 2, $tileCol);
            imagefilledellipse($im, $leftX2 - $r, $y2 - $r, $r * 2, $r * 2, $tileCol);

            // Right digit tile
            imagefilledrectangle($im, $rightX1 + $r, $y1, $rightX2 - $r, $y2, $tileCol);
            imagefilledrectangle($im, $rightX1, $y1 + $r, $rightX2, $y2 - $r, $tileCol);
            imagefilledellipse($im, $rightX1 + $r, $y1 + $r, $r * 2, $r * 2, $tileCol);
            imagefilledellipse($im, $rightX2 - $r, $y1 + $r, $r * 2, $r * 2, $tileCol);
            imagefilledellipse($im, $rightX1 + $r, $y2 - $r, $r * 2, $r * 2, $tileCol);
            imagefilledellipse($im, $rightX2 - $r, $y2 - $r, $r * 2, $r * 2, $tileCol);
        }

        // Colon separators between tiles
        $font = $skin['fontPath'] ?? '';
        $fontSize = (int)($skin['fontSize'] ?? 32);
        if (is_file($font)) {
            $sep = (string)($skin['separator'] ?? ':');
            if ($sep === '') { $sep = ':'; }
            // Use first visible character for placement calculations
            $sepChar = mb_substr($sep, 0, 1);
            // Choose separator color if provided; fallback to text color
            $sepCol = $textCol;
            if (!empty($skin['separatorColor']) && is_string($skin['separatorColor'])) {
                [$sr,$sg,$sb] = $this->hexToRgb($skin['separatorColor']);
                $sepCol = imagecolorallocate($im, $sr, $sg, $sb);
            }
            for ($i = 0; $i < $segments - 1; $i++) {
                $cx = $gapX + ($i + 1) * $segW + $i * $gapX + (int)($gapX / 2);
                $cy = (int)($padTop + $digitTileH * 0.50);
                $colon = $sepChar;
                $box = imagettfbbox($fontSize, 0, $font, $colon);
                // precise centering uses bbox min/max, not just width
                $minX = min($box[0], $box[2], $box[4], $box[6]);
                $maxX = max($box[0], $box[2], $box[4], $box[6]);
                $minY = min($box[1], $box[3], $box[5], $box[7]);
                $maxY = max($box[1], $box[3], $box[5], $box[7]);
                $x    = (int)($cx - ($minX + $maxX) / 2);
                $y    = (int)($cy - ($minY + $maxY) / 2); // center vertically relative to baseline
                imagettftext($im, $fontSize, 0, $x+1, $y, $sepCol, $font, $colon);
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

        // Respect checkbox flags to show/hide units
        $units = [];
        if (($skin['showDays'] ?? true) === true)    { $units[] = ['value' => $days,    'label' => 'DAYS']; }
        if (($skin['showHours'] ?? true) === true)   { $units[] = ['value' => $hours,   'label' => 'HOURS']; }
        if (($skin['showMinutes'] ?? true) === true) { $units[] = ['value' => $minutes, 'label' => 'MINUTES']; }
        if (($skin['showSeconds'] ?? true) === true) { $units[] = ['value' => $seconds, 'label' => 'SECONDS']; }
        if (empty($units)) {
            $units = [ ['value'=>$seconds,'label'=>'SECONDS'] ];
        }

        $segments = count($units);
        $gapX = max(8, (int)round($width * 0.02));
        $innerGap = max(6, (int)round($width * 0.012));
        $padTop  = max(8, (int)round($height * 0.12));
        $labelArea = max(20, (int)round($height * 0.26));
        $segW = (int) floor(($width - ($gapX * ($segments + 1))) / $segments);
        $digitTileH = max(24, $height - $labelArea - $padTop);
        $digitsCenterY  = (int) ($padTop + $digitTileH * 0.50);

        for ($i = 0; $i < $segments; $i++) {
            $segX1 = $gapX + $i * ($segW + $gapX);

            // Render each digit in its own tile area (left and right)
            $singleTileW = (int) floor(($segW - $innerGap) / 2);
            $leftX1  = $segX1;
            $rightX1 = $segX1 + $singleTileW + $innerGap;

            $num = sprintf('%02d', $units[$i]['value']);
            $leftDigit  = $num[0];
            $rightDigit = $num[1];

            // Center digits individually inside their tiles
            $leftBox = imagettfbbox($fontSize, 0, $font, $leftDigit);
            $leftW = abs($leftBox[4] - $leftBox[0]);
            $leftH = abs($leftBox[5] - $leftBox[1]);
            $leftX = (int) ($leftX1 + ($singleTileW - $leftW) / 2);
            $digitY = (int) ($digitsCenterY + $leftH / 2 - 2);
            imagettftext($im, $fontSize, 0, $leftX, $digitY, $textCol, $font, $leftDigit);

            $rightBox = imagettfbbox($fontSize, 0, $font, $rightDigit);
            $rightW = abs($rightBox[4] - $rightBox[0]);
            $rightH = abs($rightBox[5] - $rightBox[1]);
            $rightX = (int) ($rightX1 + ($singleTileW - $rightW) / 2);
            imagettftext($im, $fontSize, 0, $rightX, $digitY, $textCol, $font, $rightDigit);

            // Red label under the digit tiles (centered under the whole segment)
            $label = (string)$units[$i]['label'];
            $lblBox = imagettfbbox($labelSize, 0, $font, $label);
            $lblW = abs($lblBox[4] - $lblBox[0]);
            $lblX = (int) ($segX1 + ($segW - $lblW) / 2);
            $lblY = (int) ($padTop + $digitTileH + $labelSize + 6);
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


