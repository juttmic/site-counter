<?php

namespace App\Services\Skins\Renderers;

use App\Services\Skins\SkinRendererInterface;

/**
 * Skin style: "hanging_ribbon_blocks"
 * Renders countdown as horizontal ribbon tiles.
 * Tiles have white number area and colored label bar.
 */
class HangingRibbonBlocksRenderer implements SkinRendererInterface
{
    public function renderStyle($im, array $skin, int $width, int $height, int $bgCol, int $textCol, int $accentCol, int $secondsRemaining): void
    {
        imagefilledrectangle($im, 0, 0, $width, $height, $bgCol);

        if ($secondsRemaining <= 0) {
            $finalBg = $skin['finalBackground'] ?? null;
            if ($finalBg !== null && $finalBg !== '') {
                $finalBgCol = $this->allocColor($im, $finalBg);
                imagefilledrectangle($im, 0, 0, $width, $height, $finalBgCol);
            }
            return;
        }

        $units = $this->getVisibleUnits($skin);
        $segments = count($units);
        if ($segments === 0) {
            return;
        }

        $segmentGap = max(0, (int) ($skin['segmentGap'] ?? 18));
        $padX = max(20, (int) round($width * 0.03));
        $availableWidth = $width - 2 * $padX;
        $totalGap = ($segments - 1) * $segmentGap;
        $tileWidth = (int) floor(($availableWidth - $totalGap) / $segments);
        $startX = $padX + (int) (($availableWidth - ($segments * $tileWidth + $totalGap)) / 2);

        $verticalPad = max(8, (int) round($height * 0.05));
        $tileHeight = $height - 2 * $verticalPad;
        $tileY = $verticalPad;

        $tileBg = $this->allocColor($im, $skin['tileBackground'] ?? '#FFFFFF');
        $tileBottomCol = $this->allocColor($im, $skin['tileBottomColor'] ?? '#7EB0CF');
        $tileBottomColAlt = $this->allocColor($im, $skin['tileBottomColorAlt'] ?? '#214A75');
        $tileRadius = max(0, (int) ($skin['tileRadius'] ?? 12));
        $tileBorderCol = $this->allocColor($im, $skin['tileBorderColor'] ?? '#9BB7CB');
        $tileBorderWidth = max(0, (int) ($skin['tileBorderWidth'] ?? 2));
        $showBottomLabelBar = ($skin['showBottomLabelBar'] ?? true) === true;
        $shadowCol = $this->allocColor($im, $skin['shadowColor'] ?? '#D7E0E8');
        $shadowOffsetY = (int) ($skin['shadowOffsetY'] ?? 0);

        $bottomBarRatio = 0.30;
        $bottomBarHeight = $showBottomLabelBar ? max(18, (int) round($tileHeight * $bottomBarRatio)) : 0;
        $mainTileHeight = $tileHeight - $bottomBarHeight;

        for ($i = 0; $i < $segments; $i++) {
            $tileX = (int) ($startX + $i * ($tileWidth + $segmentGap));
            $isLastVisible = ($i === $segments - 1);
            $barCol = $isLastVisible ? $tileBottomColAlt : $tileBottomCol;

            // Unified tile: one outer rounded box for both digit and label areas
            //    Fill full tile with label bar color, then overlay white for number area
            if ($showBottomLabelBar && $bottomBarHeight > 0) {
                $this->fillRoundedRect($im, $tileX, $tileY, $tileWidth, $tileHeight, $tileRadius, $barCol);
                $this->fillRoundedRect($im, $tileX, $tileY, $tileWidth, $mainTileHeight, $tileRadius, $tileBg);
            } else {
                $this->fillRoundedRect($im, $tileX, $tileY, $tileWidth, $tileHeight, $tileRadius, $tileBg);
            }

            // Tile border (around full unified tile)
            if ($tileBorderWidth > 0) {
                $this->strokeRoundedRect($im, $tileX, $tileY, $tileWidth, $tileHeight, $tileRadius, $tileBorderCol, $tileBorderWidth);
            }
        }

        // No outer border around the entire canvas – only the tiles themselves are visible.
    }

    public function renderTimeText($im, int $secondsRemaining, array $skin, int $width, int $height, int $textCol, int $accentCol): bool
    {
        $font = (string) ($skin['fontPath'] ?? '');
        $fontSize = (int) ($skin['fontSize'] ?? 48);
        $labelFontSize = (int) ($skin['labelFontSize'] ?? 16);
        $numberCol = ($skin['textColor'] ?? null) !== null && ($skin['textColor'] ?? '') !== ''
            ? $this->allocColor($im, $skin['textColor']) : $textCol;
        $labelCol = $this->allocColor($im, $skin['labelColor'] ?? '#FFFFFF');

        if ($secondsRemaining <= 0) {
            $finalMsg = (string) ($skin['finalMessage'] ?? "Time's Up");
            $finalTextCol = $skin['finalTextColor'] ?? null;
            $drawCol = ($finalTextCol !== null && $finalTextCol !== '') ? $this->allocColor($im, $finalTextCol) : $textCol;
            if (is_file($font)) {
                $box = imagettfbbox($fontSize, 0, $font, $finalMsg) ?: [0, 0, 0, 0, 0, 0, 0, 0];
                $tw = (int) (max($box[0], $box[2], $box[4], $box[6]) - min($box[0], $box[2], $box[4], $box[6]));
                $x = (int) (($width - $tw) / 2);
                $y = (int) ($height / 2 + $fontSize / 2);
                imagettftext($im, $fontSize, 0, $x, $y, $drawCol, $font, $finalMsg);
            } else {
                $tw = imagefontwidth(5) * strlen($finalMsg);
                $th = imagefontheight(5);
                imagestring($im, 5, (int) (($width - $tw) / 2), (int) (($height - $th) / 2), $finalMsg, $drawCol);
            }
            return true;
        }

        $units = $this->getVisibleUnitsWithValues($skin, $secondsRemaining);
        $segments = count($units);
        if ($segments === 0) {
            return true;
        }

        $segmentGap = max(0, (int) ($skin['segmentGap'] ?? 18));
        $padX = max(20, (int) round($width * 0.03));
        $availableWidth = $width - 2 * $padX;
        $totalGap = ($segments - 1) * $segmentGap;
        $tileWidth = (int) floor(($availableWidth - $totalGap) / $segments);
        $startX = $padX + (int) (($availableWidth - ($segments * $tileWidth + $totalGap)) / 2);

        $verticalPad = max(8, (int) round($height * 0.05));
        $tileHeight = $height - 2 * $verticalPad;
        $tileY = $verticalPad;
        $showBottomLabelBar = ($skin['showBottomLabelBar'] ?? true) === true;
        $bottomBarRatio = 0.30;
        $bottomBarHeight = $showBottomLabelBar ? max(18, (int) round($tileHeight * $bottomBarRatio)) : 0;
        $mainTileHeight = $tileHeight - $bottomBarHeight;
        $numberCenterY = $tileY + $mainTileHeight / 2;
        $labelCenterY = $showBottomLabelBar
            ? $tileY + $mainTileHeight + $bottomBarHeight / 2
            : $tileY + $mainTileHeight - (int) round($labelFontSize * 0.5);

        if (is_file($font)) {
            for ($i = 0; $i < $segments; $i++) {
                $tileX = (int) ($startX + $i * ($tileWidth + $segmentGap));
                $centerX = (int) ($tileX + $tileWidth / 2);

                $value = $units[$i]['value'];
                $label = $units[$i]['label'];
                $numberText = sprintf('%02d', $value);
                $numberWidth = $this->textBoxWidth($numberText, $font, $fontSize);
                $numberBaseline = (int) ($numberCenterY - $this->textVerticalCenterOffset($numberText, $font, $fontSize));
                imagettftext($im, $fontSize, 0, (int) ($centerX - $numberWidth / 2), $numberBaseline, $numberCol, $font, $numberText);

                $labelWidth = $this->textBoxWidth($label, $font, $labelFontSize);
                $labelBaseline = (int) ($labelCenterY - $this->textVerticalCenterOffset($label, $font, $labelFontSize));
                imagettftext($im, $labelFontSize, 0, (int) ($centerX - $labelWidth / 2), $labelBaseline, $labelCol, $font, $label);
            }
        } else {
            $builtIn = 5;
            $labelBuiltIn = 3;
            $numberY = (int) ($numberCenterY - 12);
            $labelY = (int) ($labelCenterY - 10);
            for ($i = 0; $i < $segments; $i++) {
                $tileX = (int) ($startX + $i * ($tileWidth + $segmentGap));
                $centerX = (int) ($tileX + $tileWidth / 2);
                $value = $units[$i]['value'];
                $label = $units[$i]['label'];
                $numberText = sprintf('%02d', $value);
                $nW = imagefontwidth($builtIn) * strlen($numberText);
                imagestring($im, $builtIn, (int) ($centerX - $nW / 2), $numberY, $numberText, $numberCol);
                $lW = imagefontwidth($labelBuiltIn) * strlen($label);
                imagestring($im, $labelBuiltIn, (int) ($centerX - $lW / 2), $labelY, $label, $labelCol);
            }
        }

        return true;
    }

    private function getVisibleUnits(array $skin): array
    {
        $units = [];
        if (($skin['showDays'] ?? true) === true) { $units[] = 'd'; }
        if (($skin['showHours'] ?? true) === true) { $units[] = 'h'; }
        if (($skin['showMinutes'] ?? true) === true) { $units[] = 'm'; }
        if (($skin['showSeconds'] ?? true) === true) { $units[] = 's'; }
        return empty($units) ? ['s'] : $units;
    }

    private function getVisibleUnitsWithValues(array $skin, int $secondsRemaining): array
    {
        $days = intdiv($secondsRemaining, 86400);
        $hours = intdiv($secondsRemaining % 86400, 3600);
        $minutes = intdiv($secondsRemaining % 3600, 60);
        $seconds = $secondsRemaining % 60;

        $units = [];
        if (($skin['showDays'] ?? true) === true) { $units[] = ['value' => $days, 'label' => 'DAYS']; }
        if (($skin['showHours'] ?? true) === true) { $units[] = ['value' => $hours, 'label' => 'HOURS']; }
        if (($skin['showMinutes'] ?? true) === true) { $units[] = ['value' => $minutes, 'label' => 'MINUTES']; }
        if (($skin['showSeconds'] ?? true) === true) { $units[] = ['value' => $seconds, 'label' => 'SECONDS']; }
        return empty($units) ? [['value' => $seconds, 'label' => 'SECONDS']] : $units;
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

    private function allocDarkenedColor($im, string $hex, float $factor): int
    {
        $hex = ltrim($hex, '#');
        if (strlen($hex) !== 6) {
            return imagecolorallocate($im, 180, 180, 180);
        }
        $r = max(0, (int) (hexdec(substr($hex, 0, 2)) * (1 - $factor)));
        $g = max(0, (int) (hexdec(substr($hex, 2, 2)) * (1 - $factor)));
        $b = max(0, (int) (hexdec(substr($hex, 4, 2)) * (1 - $factor)));
        return imagecolorallocate($im, $r, $g, $b);
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

    private function textVerticalCenterOffset(string $text, string $fontPath, int $fontSize): float
    {
        $box = imagettfbbox($fontSize, 0, $fontPath, $text) ?: [0, 0, 0, 0, 0, 0, 0, 0];
        return ($box[7] + $box[1]) / 2.0;
    }
}
