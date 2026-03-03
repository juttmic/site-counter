<?php

namespace App\Services\Skins\Renderers;

use App\Services\Skins\SkinRendererInterface;

/**
 * Skin style: "rounded_panel_cards"
 * Renders countdown as rounded panel cards with number boxes, labels, and optional shadow.
 * Supports visibilityLayouts for dynamic canvas sizing based on showDays/showSeconds.
 */
class RoundedPanelCardsRenderer implements SkinRendererInterface
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

        $segmentGap = max(0, (int) ($skin['segmentGap'] ?? 22));
        $panelRadius = max(0, (int) ($skin['panelRadius'] ?? 28));
        $cardRadius = max(0, (int) ($skin['cardRadius'] ?? 16));
        $numberBoxRadius = max(0, (int) ($skin['numberBoxRadius'] ?? 14));
        $numberBoxBorderWidth = max(0, (int) ($skin['numberBoxBorderWidth'] ?? 3));
        $shadowOffsetY = (int) ($skin['shadowOffsetY'] ?? 8);
        $shadowBlur = max(0, (int) ($skin['shadowBlur'] ?? 18));

        $cardBg = $this->allocColor($im, $skin['cardBackground'] ?? '#EDF1F5');
        $numberBoxBg = $this->allocColor($im, $skin['numberBoxBackground'] ?? '#FFFFFF');
        $numberBoxBorderCol = $this->allocColor($im, $skin['numberBoxBorderColor'] ?? '#69B7CB');
        $shadowCol = $this->allocColor($im, $skin['shadowColor'] ?? '#D9E3EB');

        $segments = count($units);
        $totalGap = ($segments + 1) * $segmentGap;
        $cardWidth = (int) floor(($width - $totalGap) / $segments);
        $cardHeight = $height - 2 * $segmentGap;
        $cardY = $segmentGap;

        for ($i = 0; $i < $segments; $i++) {
            $cardX = $segmentGap + $i * ($cardWidth + $segmentGap);

            // Shadow (simple offset rect, no blur for GD compatibility)
            if ($shadowOffsetY > 0 && $shadowBlur >= 0) {
                $shadowX = $cardX + 2;
                $shadowY = $cardY + $shadowOffsetY;
                $this->fillRoundedRect($im, $shadowX, $shadowY, $cardWidth, $cardHeight, $cardRadius, $shadowCol);
            }

            // Card background
            $this->fillRoundedRect($im, $cardX, $cardY, $cardWidth, $cardHeight, $cardRadius, $cardBg);

            // Number box: border then fill
            $boxPad = (int) round($cardWidth * 0.08);
            $boxW = $cardWidth - 2 * $boxPad;
            $boxH = (int) round($cardHeight * 0.55);
            $boxX = $cardX + $boxPad;
            $boxY = $cardY + (int) round($cardHeight * 0.12);

            if ($numberBoxBorderWidth > 0) {
                $borderR = min($numberBoxRadius + $numberBoxBorderWidth, (int) floor(min($boxW, $boxH) / 2));
                $this->fillRoundedRect($im, $boxX, $boxY, $boxW, $boxH, $borderR, $numberBoxBorderCol);
            }
            $innerPad = $numberBoxBorderWidth;
            $innerX = $boxX + $innerPad;
            $innerY = $boxY + $innerPad;
            $innerW = $boxW - 2 * $innerPad;
            $innerH = $boxH - 2 * $innerPad;
            $innerR = max(0, $numberBoxRadius - $innerPad);
            $this->fillRoundedRect($im, $innerX, $innerY, $innerW, $innerH, $innerR, $numberBoxBg);
        }

        // Outer border
        $borderWidth = max(0, (int) ($skin['borderWidth'] ?? 2));
        if ($borderWidth > 0) {
            $borderCol = $this->allocColor($im, $skin['borderColor'] ?? '#D8E0E8');
            $this->strokeRect($im, 0, 0, $width, $height, $borderCol, $borderWidth);
        }
    }

    public function renderTimeText($im, int $secondsRemaining, array $skin, int $width, int $height, int $textCol, int $accentCol): bool
    {
        $font = (string) ($skin['fontPath'] ?? '');
        $fontSize = (int) ($skin['fontSize'] ?? 52);
        $labelFontSize = (int) ($skin['labelFontSize'] ?? 18);
        $labelCol = $this->allocColor($im, $skin['labelColor'] ?? '#3D5268');

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

        $segmentGap = max(0, (int) ($skin['segmentGap'] ?? 22));
        $segments = count($units);
        $totalGap = ($segments + 1) * $segmentGap;
        $cardWidth = (int) floor(($width - $totalGap) / $segments);
        $cardHeight = $height - 2 * $segmentGap;
        $cardY = $segmentGap;

        $boxPad = (int) round($cardWidth * 0.08);
        $boxW = $cardWidth - 2 * $boxPad;
        $boxH = (int) round($cardHeight * 0.55);
        $boxY = $cardY + (int) round($cardHeight * 0.12);
        $numberY = $boxY + (int) round($boxH * 0.38);
        $labelY = $boxY + $boxH + (int) round($labelFontSize * 0.8);

        if (is_file($font)) {
            for ($i = 0; $i < $segments; $i++) {
                $cardX = $segmentGap + $i * ($cardWidth + $segmentGap);
                $centerX = (int) ($cardX + $cardWidth / 2);

                $numberText = sprintf('%02d', $units[$i]['value']);
                $numberWidth = $this->textBoxWidth($numberText, $font, $fontSize);
                $numberX = (int) ($centerX - $numberWidth / 2);
                imagettftext($im, $fontSize, 0, $numberX, $numberY, $textCol, $font, $numberText);

                $label = $units[$i]['label'];
                $labelWidth = $this->textBoxWidth($label, $font, $labelFontSize);
                $labelX = (int) ($centerX - $labelWidth / 2);
                imagettftext($im, $labelFontSize, 0, $labelX, $labelY, $labelCol, $font, $label);
            }
        } else {
            $builtIn = 5;
            $labelBuiltIn = 3;
            for ($i = 0; $i < $segments; $i++) {
                $cardX = $segmentGap + $i * ($cardWidth + $segmentGap);
                $centerX = (int) ($cardX + $cardWidth / 2);

                $numberText = sprintf('%02d', $units[$i]['value']);
                $nW = imagefontwidth($builtIn) * strlen($numberText);
                imagestring($im, $builtIn, (int) ($centerX - $nW / 2), $numberY - 12, $numberText, $textCol);

                $label = $units[$i]['label'];
                $lW = imagefontwidth($labelBuiltIn) * strlen($label);
                imagestring($im, $labelBuiltIn, (int) ($centerX - $lW / 2), $labelY - 10, $label, $labelCol);
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

    private function fillRoundedRect($im, int $x, int $y, int $w, int $h, int $r, int $color): void
    {
        $r = min($r, (int) floor(min($w, $h) / 2));
        if ($r <= 0) {
            imagefilledrectangle($im, $x, $y, $x + $w, $y + $h, $color);
            return;
        }
        imagefilledrectangle($im, $x + $r, $y, $x + $w - $r, $y + $h, $color);
        imagefilledrectangle($im, $x, $y + $r, $x + $r, $y + $h - $r, $color);
        imagefilledrectangle($im, $x + $w - $r, $y + $r, $x + $w, $y + $h - $r, $color);
        imagefilledellipse($im, $x + $r, $y + $r, 2 * $r, 2 * $r, $color);
        imagefilledellipse($im, $x + $w - $r, $y + $r, 2 * $r, 2 * $r, $color);
        imagefilledellipse($im, $x + $r, $y + $h - $r, 2 * $r, 2 * $r, $color);
        imagefilledellipse($im, $x + $w - $r, $y + $h - $r, 2 * $r, 2 * $r, $color);
    }

    private function strokeRect($im, int $x, int $y, int $w, int $h, int $color, int $thickness): void
    {
        if ($thickness <= 0) {
            return;
        }
        imagefilledrectangle($im, $x, $y, $x + $w, $y + $thickness, $color);
        imagefilledrectangle($im, $x, $y + $h - $thickness, $x + $w, $y + $h, $color);
        imagefilledrectangle($im, $x, $y + $thickness, $x + $thickness, $y + $h - $thickness, $color);
        imagefilledrectangle($im, $x + $w - $thickness, $y + $thickness, $x + $w, $y + $h - $thickness, $color);
    }

    private function textBoxWidth(string $text, string $fontPath, int $fontSize): int
    {
        $box = imagettfbbox($fontSize, 0, $fontPath, $text) ?: [0, 0, 0, 0, 0, 0, 0, 0];
        $minX = min($box[0], $box[2], $box[4], $box[6]);
        $maxX = max($box[0], $box[2], $box[4], $box[6]);
        return (int) ($maxX - $minX);
    }
}
