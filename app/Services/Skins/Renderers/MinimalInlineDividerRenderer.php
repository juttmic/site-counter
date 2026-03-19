<?php

namespace App\Services\Skins\Renderers;

use App\Services\Skins\SkinRendererInterface;

/**
 * Skin style: "minimal_inline_divider"
 * Renders countdown as minimal inline segments with vertical dividers between units.
 */
class MinimalInlineDividerRenderer implements SkinRendererInterface
{
    /**
     * Horizontal layout (percent of full canvas width):
     * - 3% margin left, 3% margin right (6% total)
     * - Inner band 94%: up to 4 segments × 20.5% + up to 3 divider slots × 4% = 94%
     * - Actual divider stroke uses skin separatorWidth; remainder of each 4% slot is padding.
     * Visible row is centered in the inner band when fewer than 4 segments.
     */
    private function computePercentLayout(int $width, int $segments, int $innerPadding): array
    {
        $segments = max(1, $segments);
        // Integer basis (20.5‰, 4‰, 3‰ of width) — one segW / divSlotW for every cell, no drift.
        $segW = (int) round(($width * 205) / 1000);
        $divSlotW = (int) round(($width * 40) / 1000);
        $margin = (int) round(($width * 30) / 1000);
        $segW = max(1, $segW);
        $divSlotW = max(1, $divSlotW);

        $bandWidth = max(1, $width - 2 * $margin);
        $contentW = $segments * $segW + max(0, $segments - 1) * $divSlotW;
        $slack = max(0, $bandWidth - $contentW);
        $startX = $margin + intdiv($slack, 2);

        $segmentOuterLeft = [];
        $segmentOuterRight = [];
        $innerLeft = [];
        $innerRight = [];
        $dividerSlots = [];

        $step = $segW + $divSlotW;
        for ($i = 0; $i < $segments; $i++) {
            $l = $startX + $i * $step;
            $segmentOuterLeft[$i] = $l;
            $segmentOuterRight[$i] = $l + $segW - 1;
            $innerLeft[$i] = min($segmentOuterRight[$i], $segmentOuterLeft[$i] + $innerPadding);
            $innerRight[$i] = max($segmentOuterLeft[$i], $segmentOuterRight[$i] - $innerPadding);
        }
        for ($i = 0; $i < $segments - 1; $i++) {
            $divLeft = $startX + $i * $step + $segW;
            $dividerSlots[] = ['left' => $divLeft, 'right' => $divLeft + $divSlotW - 1];
        }

        return [
            'segW' => $segW,
            'divSlotW' => $divSlotW,
            'margin' => $margin,
            'startX' => $startX,
            'segmentOuterLeft' => $segmentOuterLeft,
            'segmentOuterRight' => $segmentOuterRight,
            'innerLeft' => $innerLeft,
            'innerRight' => $innerRight,
            'dividerSlots' => $dividerSlots,
        ];
    }

    /**
     * @return array{minX: float, maxX: float, minY: float, maxY: float, width: float, height: float}
     */
    private function measureText(string $font, int $size, string $text): array
    {
        $box = imagettfbbox($size, 0, $font, $text) ?: [0, 0, 0, 0, 0, 0, 0, 0];
        $xs = [$box[0], $box[2], $box[4], $box[6]];
        $ys = [$box[1], $box[3], $box[5], $box[7]];

        return [
            'minX' => min($xs),
            'maxX' => max($xs),
            'minY' => min($ys),
            'maxY' => max($ys),
            'width' => max($xs) - min($xs),
            'height' => max($ys) - min($ys),
        ];
    }

    public function renderStyle($im, array $skin, int $width, int $height, int $bgCol, int $textCol, int $accentCol, int $secondsRemaining): void
    {
        imagefilledrectangle($im, 0, 0, $width, $height, $bgCol);

        $showDays = ($skin['showDays'] ?? true) === true;
        $showHours = ($skin['showHours'] ?? true) === true;
        $showMinutes = ($skin['showMinutes'] ?? true) === true;
        $showSeconds = ($skin['showSeconds'] ?? true) === true;

        $units = [];
        if ($showDays) { $units[] = 'd'; }
        if ($showHours) { $units[] = 'h'; }
        if ($showMinutes) { $units[] = 'm'; }
        if ($showSeconds) { $units[] = 's'; }
        if (empty($units)) { $units = ['s']; }

        $separatorWidth = max(1, (int) ($skin['separatorWidth'] ?? 2));
        $separatorHeight = max(10, (int) ($skin['separatorHeight'] ?? 86));
        $separatorCol = $this->allocColor($im, $skin['separatorColor'] ?? '#CBD3DC');

        $segments = count($units);
        $innerPadding = max(0, (int) ($skin['innerPadding'] ?? 6));
        $layout = $this->computePercentLayout($width, $segments, $innerPadding);

        $centerY = (int) ($height / 2);
        $sepTop = (int) ($centerY - $separatorHeight / 2);
        $sepBottom = (int) ($centerY + $separatorHeight / 2);

        foreach ($layout['dividerSlots'] as $idx => $slot) {
            $slotLeft = $slot['left'];
            $slotRight = $slot['right'];
            $slotW = $slotRight - $slotLeft + 1;
            $barW = min($separatorWidth, $slotW);
            $sepLeft = $slotLeft + (int) floor(($slotW - $barW) / 2);
            $sepRight = $sepLeft + $barW - 1;

            imagefilledrectangle($im, $sepLeft, $sepTop, $sepRight, $sepBottom, $separatorCol);
        }

    }

    public function renderTimeText($im, int $secondsRemaining, array $skin, int $width, int $height, int $textCol, int $accentCol): bool
    {
        $font = (string) ($skin['fontPath'] ?? '');
        $fontSize = (int) ($skin['fontSize'] ?? 64);
        $labelFontSize = (int) ($skin['labelFontSize'] ?? 16);
        $labelCol = $this->allocColor($im, $skin['labelColor'] ?? '#56697C');

        $showDays = ($skin['showDays'] ?? true) === true;
        $showHours = ($skin['showHours'] ?? true) === true;
        $showMinutes = ($skin['showMinutes'] ?? true) === true;
        $showSeconds = ($skin['showSeconds'] ?? true) === true;

        $days = intdiv($secondsRemaining, 86400);
        $hours = intdiv($secondsRemaining % 86400, 3600);
        $minutes = intdiv($secondsRemaining % 3600, 60);
        $seconds = $secondsRemaining % 60;

        $units = [];
        if ($showDays) { $units[] = ['value' => $days, 'label' => 'DAYS']; }
        if ($showHours) { $units[] = ['value' => $hours, 'label' => 'HOURS']; }
        if ($showMinutes) { $units[] = ['value' => $minutes, 'label' => 'MINUTES']; }
        if ($showSeconds) { $units[] = ['value' => $seconds, 'label' => 'SECONDS']; }
        if (empty($units)) { $units[] = ['value' => $seconds, 'label' => 'SECONDS']; }

        $innerPadding = max(0, (int) ($skin['innerPadding'] ?? 6));
        $segments = count($units);
        $layout = $this->computePercentLayout($width, $segments, $innerPadding);
        $outerLeft = $layout['segmentOuterLeft'];
        $outerRight = $layout['segmentOuterRight'];

        if (is_file($font)) {
            $numberSample = '00';
            $labelSample = 'SECONDS';
            $numM = $this->measureText($font, $fontSize, $numberSample);
            $labM = $this->measureText($font, $labelFontSize, $labelSample);
            $numberHeight = $numM['height'];
            $labelHeight = $labM['height'];
            // Double the space between digits and labels while keeping the whole block centered.
            $gap = (int) round($fontSize * 0.36);
            $totalBlockHeight = $numberHeight + $gap + $labelHeight;
            $blockTop = (float) max(0, ($height - $totalBlockHeight) / 2);
            $numberCenterY = $blockTop + $numberHeight / 2;
            $labelCenterY = $blockTop + $numberHeight + $gap + $labelHeight / 2;

            for ($i = 0; $i < $segments; $i++) {
                $segmentCenterX = ($outerLeft[$i] + $outerRight[$i]) / 2.0;

                $numberText = sprintf('%02d', $units[$i]['value']);
                $nm = $this->measureText($font, $fontSize, $numberText);
                $nx = (int) round($segmentCenterX - ($nm['width'] / 2) - $nm['minX']);
                $nBaseline = (int) round($numberCenterY - ($nm['minY'] + $nm['maxY']) / 2);
                imagettftext($im, $fontSize, 0, $nx, $nBaseline, $textCol, $font, $numberText);

                $label = $units[$i]['label'];
                $lm = $this->measureText($font, $labelFontSize, $label);
                $lx = (int) round($segmentCenterX - ($lm['width'] / 2) - $lm['minX']);
                $lBaseline = (int) round($labelCenterY - ($lm['minY'] + $lm['maxY']) / 2);
                imagettftext($im, $labelFontSize, 0, $lx, $lBaseline, $labelCol, $font, $label);
            }
        } else {
            $builtIn = 5;
            $labelBuiltIn = 3;
            $numH = imagefontheight($builtIn);
            $labH = imagefontheight($labelBuiltIn);
            $gap = 8;
            $totalBlockHeight = $numH + $gap + $labH;
            $blockTop = (int) (($height - $totalBlockHeight) / 2);
            $numberY = $blockTop;
            $labelY = $blockTop + $numH + $gap;
            for ($i = 0; $i < $segments; $i++) {
                $centerX = (int) round(($outerLeft[$i] + $outerRight[$i]) / 2.0);

                $numberText = sprintf('%02d', $units[$i]['value']);
                $nW = imagefontwidth($builtIn) * strlen($numberText);
                imagestring($im, $builtIn, (int) ($centerX - $nW / 2), $numberY, $numberText, $textCol);

                $label = $units[$i]['label'];
                $lW = imagefontwidth($labelBuiltIn) * strlen($label);
                imagestring($im, $labelBuiltIn, (int) ($centerX - $lW / 2), $labelY, $label, $labelCol);
            }
        }

        return true;
    }

    private function allocColor($im, string $hex): int
    {
        $hex = ltrim($hex, '#');
        if (strlen($hex) === 6) {
            $r = (int) hexdec(substr($hex, 0, 2));
            $g = (int) hexdec(substr($hex, 2, 2));
            $b = (int) hexdec(substr($hex, 4, 2));
            return imagecolorallocate($im, $r, $g, $b);
        }
        return imagecolorallocate($im, 255, 255, 255);
    }
}
