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
        $segW = (int) round($width * 0.205);
        $divSlotW = (int) round($width * 0.04);
        $margin = (int) round($width * 0.03);
        $bandWidth = max(1, $width - 2 * $margin);
        $contentW = $segments * $segW + max(0, $segments - 1) * $divSlotW;
        $startX = $margin + (int) round(($bandWidth - $contentW) / 2.0);

        $segmentOuterLeft = [];
        $segmentOuterRight = [];
        $innerLeft = [];
        $innerRight = [];
        $dividerSlots = [];

        $x = $startX;
        for ($i = 0; $i < $segments; $i++) {
            $segmentOuterLeft[$i] = $x;
            $segmentOuterRight[$i] = $x + $segW - 1;
            $innerLeft[$i] = min($segmentOuterRight[$i], $segmentOuterLeft[$i] + $innerPadding);
            $innerRight[$i] = max($segmentOuterLeft[$i], $segmentOuterRight[$i] - $innerPadding);
            $x += $segW;
            if ($i < $segments - 1) {
                $dividerSlots[] = ['left' => $x, 'right' => $x + $divSlotW - 1];
                $x += $divSlotW;
            }
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

        $red = imagecolorallocate($im, 255, 0, 0);

        foreach ($layout['dividerSlots'] as $idx => $slot) {
            $slotLeft = $slot['left'];
            $slotRight = $slot['right'];
            $slotW = $slotRight - $slotLeft + 1;
            $barW = min($separatorWidth, $slotW);
            $sepLeft = $slotLeft + (int) floor(($slotW - $barW) / 2);
            $sepRight = $sepLeft + $barW - 1;

            imagefilledrectangle($im, $sepLeft, $sepTop, $sepRight, $sepBottom, $separatorCol);
            imagerectangle($im, $slotLeft, $sepTop, $slotRight, $sepBottom, $red);
        }

        for ($i = 0; $i < $segments; $i++) {
            $l = $layout['segmentOuterLeft'][$i];
            $r = $layout['segmentOuterRight'][$i];
            imagerectangle($im, $l, 0, $r, $height - 1, $red);
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
        $innerLeft = $layout['innerLeft'];
        $innerRight = $layout['innerRight'];

        if (is_file($font)) {
            $numberSample = '00';
            $labelSample = 'HOURS';
            $numBox = imagettfbbox($fontSize, 0, $font, $numberSample) ?: [0, 0, 0, 0, 0, 0, 0, 0];
            $labBox = imagettfbbox($labelFontSize, 0, $font, $labelSample) ?: [0, 0, 0, 0, 0, 0, 0, 0];
            $numberHeight = (float) ($numBox[1] - $numBox[7]);
            $labelHeight = (float) ($labBox[1] - $labBox[7]);
            $gap = (int) round($fontSize * 0.18);
            $totalBlockHeight = $numberHeight + $gap + $labelHeight;
            $blockTop = (float) max(0, ($height - $totalBlockHeight) / 2);
            $numberCenterY = $blockTop + $numberHeight / 2;
            $labelCenterY = $blockTop + $numberHeight + $gap + $labelHeight / 2;

            for ($i = 0; $i < $segments; $i++) {
                $centerX = (int) round(($innerLeft[$i] + $innerRight[$i]) / 2.0);

                $numberText = sprintf('%02d', $units[$i]['value']);
                $numberWidth = $this->textBoxWidth($numberText, $font, $fontSize);
                $numberBaseline = (int) round($numberCenterY - $this->textVerticalCenterOffset($numberText, $font, $fontSize));
                imagettftext($im, $fontSize, 0, (int) ($centerX - $numberWidth / 2), $numberBaseline, $textCol, $font, $numberText);

                $label = $units[$i]['label'];
                $labelWidth = $this->textBoxWidth($label, $font, $labelFontSize);
                $labelBaseline = (int) round($labelCenterY - $this->textVerticalCenterOffset($label, $font, $labelFontSize));
                imagettftext($im, $labelFontSize, 0, (int) ($centerX - $labelWidth / 2), $labelBaseline, $labelCol, $font, $label);
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
                $centerX = (int) round(($innerLeft[$i] + $innerRight[$i]) / 2.0);

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

    private function textBoxWidth(string $text, string $fontPath, int $fontSize): int
    {
        $box = imagettfbbox($fontSize, 0, $fontPath, $text) ?: [0, 0, 0, 0, 0, 0, 0, 0];
        $minX = min($box[0], $box[2], $box[4], $box[6]);
        $maxX = max($box[0], $box[2], $box[4], $box[6]);
        return (int) ($maxX - $minX);
    }

    private function textVerticalCenterOffset(string $text, string $fontPath, int $fontSize): float
    {
        $box = imagettfbbox($fontSize, 0, $fontPath, $text) ?: [0, 0, 0, 0, 0, 0, 0, 0];
        return ($box[7] + $box[1]) / 2.0;
    }
}
