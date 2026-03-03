<?php

namespace App\Services\Skins\Renderers;

use App\Services\Skins\SkinRendererInterface;

/**
 * Skin style: "hanging_ribbon_blocks"
 * Renders countdown as hanging ribbon tiles with hanger bar, main tile, and optional bottom label bar.
 */
class HangingRibbonBlocksRenderer implements SkinRendererInterface
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

        $segmentGap = max(0, (int) ($skin['segmentGap'] ?? 18));
        $segments = count($units);
        $totalGap = ($segments + 1) * $segmentGap;
        $tileWidth = (int) floor(($width - $totalGap) / $segments);
        $tileHeight = $height - 2 * $segmentGap;
        $tileY = $segmentGap;

        $tileBg = $this->allocColor($im, $skin['tileBackground'] ?? '#FFFFFF');
        $tileBottomCol = $this->allocColor($im, $skin['tileBottomColor'] ?? '#7EB0CF');
        $tileRadius = max(0, (int) ($skin['tileRadius'] ?? 12));
        $tileBorderCol = $this->allocColor($im, $skin['tileBorderColor'] ?? '#9BB7CB');
        $tileBorderWidth = max(0, (int) ($skin['tileBorderWidth'] ?? 2));
        $hangerCol = $this->allocColor($im, $skin['hangerColor'] ?? '#CAD7E2');
        $hangerWidth = max(0, (int) ($skin['hangerWidth'] ?? 10));
        $hangerHeight = max(0, (int) ($skin['hangerHeight'] ?? 18));
        $showBottomLabelBar = ($skin['showBottomLabelBar'] ?? true) === true;
        $shadowCol = $this->allocColor($im, $skin['shadowColor'] ?? '#D7E0E8');
        $shadowOffsetY = (int) ($skin['shadowOffsetY'] ?? 8);

        $bottomBarHeight = $showBottomLabelBar ? max(20, (int) round($tileHeight * 0.22)) : 0;
        $mainTileHeight = $tileHeight - $hangerHeight - $bottomBarHeight;

        for ($i = 0; $i < $segments; $i++) {
            $tileX = $segmentGap + $i * ($tileWidth + $segmentGap);

            // Shadow
            $shadowX = $tileX + 3;
            $shadowY = $tileY + $shadowOffsetY;
            $this->fillRoundedRect($im, $shadowX, $shadowY + $hangerHeight, $tileWidth, $mainTileHeight + $bottomBarHeight, $tileRadius, $shadowCol);

            // Hanger bar (top)
            $hangerX = (int) ($tileX + $tileWidth / 2 - $hangerWidth / 2);
            imagefilledrectangle($im, $hangerX, $tileY, $hangerX + $hangerWidth, $tileY + $hangerHeight, $hangerCol);

            // Main tile body
            $mainY = $tileY + $hangerHeight;
            $this->fillRoundedRect($im, $tileX, $mainY, $tileWidth, $mainTileHeight, $tileRadius, $tileBg);

            // Bottom label bar (ribbon tail)
            if ($showBottomLabelBar && $bottomBarHeight > 0) {
                $barY = $mainY + $mainTileHeight - $bottomBarHeight;
                $barR = min($tileRadius, (int) floor($bottomBarHeight / 2));
                imagefilledrectangle($im, $tileX, $barY, $tileX + $tileWidth, $barY + $bottomBarHeight - $barR, $tileBottomCol);
                imagefilledellipse($im, $tileX + $barR, $barY + $bottomBarHeight - $barR, 2 * $barR, 2 * $barR, $tileBottomCol);
                imagefilledellipse($im, $tileX + $tileWidth - $barR, $barY + $bottomBarHeight - $barR, 2 * $barR, 2 * $barR, $tileBottomCol);
            }

            if ($tileBorderWidth > 0) {
                $this->strokeRoundedRect($im, $tileX, $mainY, $tileWidth, $mainTileHeight, $tileRadius, $tileBorderCol, $tileBorderWidth);
            }
        }

        // Outer border
        $borderWidth = max(0, (int) ($skin['borderWidth'] ?? 2));
        if ($borderWidth > 0) {
            $borderCol = $this->allocColor($im, $skin['borderColor'] ?? '#8DB0C8');
            $this->strokeRect($im, 0, 0, $width, $height, $borderCol, $borderWidth);
        }
    }

    public function renderTimeText($im, int $secondsRemaining, array $skin, int $width, int $height, int $textCol, int $accentCol): bool
    {
        $font = (string) ($skin['fontPath'] ?? '');
        $fontSize = (int) ($skin['fontSize'] ?? 48);
        $labelFontSize = (int) ($skin['labelFontSize'] ?? 16);
        $labelCol = $this->allocColor($im, $skin['labelColor'] ?? '#FFFFFF');

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

        $segmentGap = max(0, (int) ($skin['segmentGap'] ?? 18));
        $hangerHeight = max(0, (int) ($skin['hangerHeight'] ?? 18));
        $showBottomLabelBar = ($skin['showBottomLabelBar'] ?? true) === true;
        $segments = count($units);
        $totalGap = ($segments + 1) * $segmentGap;
        $tileWidth = (int) floor(($width - $totalGap) / $segments);
        $tileHeight = $height - 2 * $segmentGap;
        $tileY = $segmentGap;
        $bottomBarHeight = $showBottomLabelBar ? max(20, (int) round($tileHeight * 0.22)) : 0;
        $mainTileHeight = $tileHeight - $hangerHeight - $bottomBarHeight;

        $numberY = (int) ($tileY + $hangerHeight + $mainTileHeight / 2 - 8);
        $labelY = (int) ($tileY + $hangerHeight + $mainTileHeight - $bottomBarHeight / 2 + (int) round($labelFontSize * 0.4));

        if (is_file($font)) {
            for ($i = 0; $i < $segments; $i++) {
                $tileX = $segmentGap + $i * ($tileWidth + $segmentGap);
                $centerX = (int) ($tileX + $tileWidth / 2);

                $numberText = sprintf('%02d', $units[$i]['value']);
                $numberWidth = $this->textBoxWidth($numberText, $font, $fontSize);
                imagettftext($im, $fontSize, 0, (int) ($centerX - $numberWidth / 2), $numberY, $textCol, $font, $numberText);

                if ($showBottomLabelBar) {
                    $label = $units[$i]['label'];
                    $labelWidth = $this->textBoxWidth($label, $font, $labelFontSize);
                    imagettftext($im, $labelFontSize, 0, (int) ($centerX - $labelWidth / 2), $labelY, $labelCol, $font, $label);
                } else {
                    $label = $units[$i]['label'];
                    $labelWidth = $this->textBoxWidth($label, $font, $labelFontSize);
                    $labelYAlt = (int) ($tileY + $hangerHeight + $mainTileHeight + (int) round($labelFontSize * 0.5));
                    imagettftext($im, $labelFontSize, 0, (int) ($centerX - $labelWidth / 2), $labelYAlt, $labelCol, $font, $label);
                }
            }
        } else {
            $builtIn = 5;
            $labelBuiltIn = 3;
            for ($i = 0; $i < $segments; $i++) {
                $tileX = $segmentGap + $i * ($tileWidth + $segmentGap);
                $centerX = (int) ($tileX + $tileWidth / 2);

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

    private function strokeRoundedRect($im, int $x, int $y, int $w, int $h, int $r, int $color, int $thickness): void
    {
        for ($t = 0; $t < $thickness; $t++) {
            $ox = $x + $t;
            $oy = $y + $t;
            $ow = $w - 2 * $t;
            $oh = $h - 2 * $t;
            $ir = max(0, $r - $t);
            if ($ir <= 0) {
                imagerectangle($im, $ox, $oy, $ox + $ow, $oy + $oh, $color);
            } else {
                $this->drawRoundedRectOutline($im, $ox, $oy, $ow, $oh, $ir, $color);
            }
        }
    }

    private function drawRoundedRectOutline($im, int $x, int $y, int $w, int $h, int $r, int $color): void
    {
        imageline($im, $x + $r, $y, $x + $w - $r, $y, $color);
        imageline($im, $x + $r, $y + $h, $x + $w - $r, $y + $h, $color);
        imageline($im, $x, $y + $r, $x, $y + $h - $r, $color);
        imageline($im, $x + $w, $y + $r, $x + $w, $y + $h - $r, $color);
        imagearc($im, $x + $r, $y + $r, 2 * $r, 2 * $r, 180, 270, $color);
        imagearc($im, $x + $w - $r, $y + $r, 2 * $r, 2 * $r, 270, 360, $color);
        imagearc($im, $x + $r, $y + $h - $r, 2 * $r, 2 * $r, 90, 180, $color);
        imagearc($im, $x + $w - $r, $y + $h - $r, 2 * $r, 2 * $r, 0, 90, $color);
    }

    private function textBoxWidth(string $text, string $fontPath, int $fontSize): int
    {
        $box = imagettfbbox($fontSize, 0, $fontPath, $text) ?: [0, 0, 0, 0, 0, 0, 0, 0];
        $minX = min($box[0], $box[2], $box[4], $box[6]);
        $maxX = max($box[0], $box[2], $box[4], $box[6]);
        return (int) ($maxX - $minX);
    }
}
