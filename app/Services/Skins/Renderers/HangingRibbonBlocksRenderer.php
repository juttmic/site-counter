<?php

namespace App\Services\Skins\Renderers;

use App\Services\Skins\SkinRendererInterface;

/**
 * Skin style: "hanging_ribbon_blocks"
 * Renders countdown as hanging ribbon tiles: light grey block extends below, primary block (white + blue ribbon with swallowtail) floats above.
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
        $tileBottomColAlt = $this->allocColor($im, $skin['tileBottomColorAlt'] ?? '#214A75');
        $tileRadius = max(0, (int) ($skin['tileRadius'] ?? 12));
        $tileBorderCol = $this->allocColor($im, $skin['tileBorderColor'] ?? '#9BB7CB');
        $tileBorderWidth = max(0, (int) ($skin['tileBorderWidth'] ?? 2));
        $showBottomLabelBar = ($skin['showBottomLabelBar'] ?? true) === true;
        $shadowCol = $this->allocColor($im, $skin['shadowColor'] ?? '#D7E0E8');
        $shadowOffsetY = (int) ($skin['shadowOffsetY'] ?? 8);
        $hangerHeight = max(0, (int) ($skin['hangerHeight'] ?? 18));
        $hangExtension = max(4, (int) round($hangerHeight * 0.6));

        $bottomBarHeight = $showBottomLabelBar ? max(22, (int) round($tileHeight * 0.28)) : 0;
        $mainTileHeight = $tileHeight - $bottomBarHeight;
        $extendPad = 4;
        $overlap = 2;

        for ($i = 0; $i < $segments; $i++) {
            $tileX = $segmentGap + $i * ($tileWidth + $segmentGap);

            // Back layer: light grey "hanging" block (extends below and slightly wider)
            $backX = $tileX - $extendPad;
            $backY = $tileY;
            $backW = $tileWidth + 2 * $extendPad;
            $backH = $tileHeight + $hangExtension;
            $this->fillRoundedRect($im, $backX, $backY, $backW, $backH, $tileRadius + 2, $shadowCol);

            // Primary block: white value section
            $mainY = $tileY + $shadowOffsetY;
            $this->fillRoundedRect($im, $tileX, $mainY, $tileWidth, $mainTileHeight, $tileRadius, $tileBg);

            // Bottom ribbon: swallowtail shape (slight overlap to connect to white)
            if ($showBottomLabelBar && $bottomBarHeight > 0) {
                $barY = $mainY + $mainTileHeight - $bottomBarHeight - $overlap;
                $barH = $bottomBarHeight + $overlap;
                $isSeconds = ($units[$i] ?? '') === 's';
                $barCol = $isSeconds ? $tileBottomColAlt : $tileBottomCol;
                $this->fillSwallowtailRibbon($im, $tileX, $barY, $tileWidth, $barH, $tileRadius, $barCol);
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

    /**
     * Draw ribbon with swallowtail/flag bottom (inward-curving cut-outs, center tail).
     */
    private function fillSwallowtailRibbon($im, int $x, int $y, int $w, int $h, int $topRadius, int $color): void
    {
        $r = min($topRadius, (int) floor(min($w, $h) / 2));
        $tailDepth = min(10, (int) round($h * 0.35));
        $cutIn = max(8, (int) round($w * 0.1));
        $cx = (int) ($x + $w / 2);
        $bottom = $y + $h;

        $points = [
            $x, $y + $r,
            $x + $r, $y,
            $x + $w - $r, $y,
            $x + $w, $y + $r,
            $x + $w, $bottom - $tailDepth,
            $x + $w - $cutIn, $bottom - $tailDepth,
            $cx, $bottom,
            $x + $cutIn, $bottom - $tailDepth,
            $x, $bottom - $tailDepth,
        ];
        imagefilledpolygon($im, $points, 9, $color);
        imagefilledellipse($im, $x + $r, $y + $r, 2 * $r, 2 * $r, $color);
        imagefilledellipse($im, $x + $w - $r, $y + $r, 2 * $r, 2 * $r, $color);
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
        $shadowOffsetY = (int) ($skin['shadowOffsetY'] ?? 8);
        $showBottomLabelBar = ($skin['showBottomLabelBar'] ?? true) === true;
        $segments = count($units);
        $totalGap = ($segments + 1) * $segmentGap;
        $tileWidth = (int) floor(($width - $totalGap) / $segments);
        $tileHeight = $height - 2 * $segmentGap;
        $tileY = $segmentGap;
        $mainY = $tileY + $shadowOffsetY;
        $bottomBarHeight = $showBottomLabelBar ? max(22, (int) round($tileHeight * 0.28)) : 0;
        $mainTileHeight = $tileHeight - $bottomBarHeight;

        $numberY = (int) ($mainY + $mainTileHeight / 2 - 8);
        $ribbonCenterY = $mainY + $mainTileHeight - $bottomBarHeight / 2 - 1;
        $labelY = (int) ($ribbonCenterY + (int) round($labelFontSize * 0.4));

        $highlightCol = $this->allocColor($im, $skin['hangerColor'] ?? '#CAD7E2');

        if (is_file($font)) {
            for ($i = 0; $i < $segments; $i++) {
                $tileX = $segmentGap + $i * ($tileWidth + $segmentGap);
                $centerX = (int) ($tileX + $tileWidth / 2);

                $numberText = sprintf('%02d', $units[$i]['value']);
                $numberWidth = $this->textBoxWidth($numberText, $font, $fontSize);
                $numX = (int) ($centerX - $numberWidth / 2);

                $lineW = min($numberWidth + 12, (int) round($tileWidth * 0.5));
                imageline($im, $centerX - $lineW / 2, $numberY - $fontSize - 4, $centerX + $lineW / 2, $numberY - $fontSize - 4, $highlightCol);

                imagettftext($im, $fontSize, 0, $numX, $numberY, $textCol, $font, $numberText);

                if ($showBottomLabelBar) {
                    $label = $units[$i]['label'];
                    $labelWidth = $this->textBoxWidth($label, $font, $labelFontSize);
                    imagettftext($im, $labelFontSize, 0, (int) ($centerX - $labelWidth / 2), $labelY, $labelCol, $font, $label);
                } else {
                    $label = $units[$i]['label'];
                    $labelWidth = $this->textBoxWidth($label, $font, $labelFontSize);
                    $labelYAlt = (int) ($mainY + $mainTileHeight + (int) round($labelFontSize * 0.5));
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
