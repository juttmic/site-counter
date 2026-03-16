<?php

namespace App\Services\Skins\Renderers;

use App\Services\Skins\SkinRendererInterface;

/**
 * Skin style: "minimal_inline_divider"
 * Renders countdown as minimal inline segments with vertical dividers between units.
 */
class MinimalInlineDividerRenderer implements SkinRendererInterface
{
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

        // Horizontal spacing between sections (Days/Hours/Minutes/Seconds).
        // Use a very small default gap so red segment borders are nearly touching.
        $segmentGap = max(0, (int) ($skin['segmentGap'] ?? 0));
        $separatorWidth = max(1, (int) ($skin['separatorWidth'] ?? 2));
        $separatorHeight = max(10, (int) ($skin['separatorHeight'] ?? 86));
        $separatorCol = $this->allocColor($im, $skin['separatorColor'] ?? '#CBD3DC');

        $segments = count($units);
        $totalGap = ($segments - 1) * ($segmentGap + $separatorWidth);
        // When segmentGap is 0, segmentWidth partitions the full width so
        // borders from adjacent segments can meet cleanly.
        $segmentWidth = (int) floor(($width - $totalGap) / $segments);
        $centerY = (int) ($height / 2);
        $sepTop = (int) ($centerY - $separatorHeight / 2);
        $sepBottom = (int) ($centerY + $separatorHeight / 2);

        // Draw vertical separators between segments.
        for ($i = 0; $i < $segments - 1; $i++) {
            $sepX = (int) (
                ($i + 1) * $segmentWidth
                + ($i + 1) * $segmentGap
                + $i * $separatorWidth
                + $separatorWidth / 2
            );
            $sepLeft = (int) ($sepX - $separatorWidth / 2);
            imagefilledrectangle($im, $sepLeft, $sepTop, $sepLeft + $separatorWidth, $sepBottom, $separatorCol);
        }

        // Draw a thin red border around each segment area.
        $borderColor = imagecolorallocate($im, 219, 0, 0);
        for ($i = 0; $i < $segments; $i++) {
            $segmentLeft = $i * ($segmentWidth + $segmentGap + $separatorWidth);
            $segmentRight = $segmentLeft + $segmentWidth - 1;
            $segmentTop = 0;
            $segmentBottom = $height - 1;
            imagerectangle($im, $segmentLeft, $segmentTop, $segmentRight, $segmentBottom, $borderColor);
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

        // Match spacing with renderStyle so dividers and text align.
        $segmentGap = max(0, (int) ($skin['segmentGap'] ?? 0));
        $separatorWidth = max(1, (int) ($skin['separatorWidth'] ?? 2));
        $segments = count($units);
        $totalGap = ($segments - 1) * ($segmentGap + $separatorWidth);
        $segmentWidth = (int) floor(($width - $totalGap) / $segments);

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
                $segX = $i * ($segmentWidth + $segmentGap + $separatorWidth);
                $centerX = (int) ($segX + $segmentWidth / 2);

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
                $segX = $i * ($segmentWidth + $segmentGap + $separatorWidth);
                $centerX = (int) ($segX + $segmentWidth / 2);

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
