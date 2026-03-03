<?php

namespace App\Services\Skins\Renderers;

use App\Services\Skins\SkinRendererInterface;

/**
 * Skin style: "framed_flip_board"
 * Renders countdown as flip-board tiles inside a rounded frame, with hinges, base plate, and label dots.
 */
class FramedFlipBoardRenderer implements SkinRendererInterface
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

        $framePadding = max(0, (int) ($skin['framePadding'] ?? 26));
        $frameRadius = max(0, (int) ($skin['frameRadius'] ?? 10));
        $borderWidth = max(0, (int) ($skin['borderWidth'] ?? 3));
        $outerMargin = max(20, (int) round(min($width, $height) * 0.06));
        $borderCol = $this->allocColor($im, $skin['borderColor'] ?? '#D5DEE7');
        $frameFillCol = $bgCol;
        $tileBg = $this->allocColor($im, $skin['tileBackground'] ?? '#FFFFFF');
        $tileBorderCol = $this->allocColor($im, $skin['tileBorderColor'] ?? '#CBD5DE');
        $tileBorderWidth = max(0, (int) ($skin['tileBorderWidth'] ?? 2));
        $tileRadius = max(0, (int) ($skin['tileRadius'] ?? 10));
        $tileShadowCol = $this->allocColor($im, $skin['tileShadowColor'] ?? '#DCE4EB');
        $shadowOffset = 4;
        $hingeCol = $this->allocColor($im, $skin['hingeColor'] ?? '#C6D0DA');
        $hingeWidth = max(4, (int) ($skin['hingeWidth'] ?? 8));
        $hingeHeight = max(6, (int) ($skin['hingeHeight'] ?? 14));
        $basePlateCol = $this->allocColor($im, $skin['basePlateColor'] ?? '#D6E0E8');
        $basePlateHeight = max(0, (int) ($skin['basePlateHeight'] ?? 18));

        $frameX = $outerMargin;
        $frameY = $outerMargin;
        $frameW = $width - 2 * $outerMargin;
        $frameH = $height - 2 * $outerMargin;
        $frameR = min($frameRadius, (int) floor(min($frameW, $frameH) / 4));

        $innerX = $frameX + $borderWidth + $framePadding;
        $innerY = $frameY + $borderWidth + $framePadding;
        $innerW = $frameW - 2 * ($borderWidth + $framePadding);
        $innerH = $frameH - 2 * ($borderWidth + $framePadding);

        $labelFontSize = (int) ($skin['labelFontSize'] ?? 18);
        $labelGap = 10;
        $basePlateGap = 10;
        $topPad = 14;
        $labelRowHeight = $labelFontSize + $labelGap;
        $dotsRowHeight = 14;
        $bottomRegionH = $labelRowHeight + $dotsRowHeight + $basePlateGap + $basePlateHeight;
        $tileHeight = (int) (($innerH - $topPad - $bottomRegionH));
        $tileGap = max(12, (int) round($innerW * 0.025));
        $tileWidth = (int) (($innerW - ($segments - 1) * $tileGap) / $segments);
        $tileWidth = (int) min($tileWidth, $tileHeight * 1.5);
        $tileGap = max(12, (int) (($innerW - $segments * $tileWidth) / max(1, $segments - 1)));
        $tileY = $innerY + $topPad;
        $startX = (int) ($innerX + ($innerW - ($segments * $tileWidth + ($segments - 1) * $tileGap)) / 2);

        // 1) Outer frame fill + border (contained board with generous margins)
        $this->fillRoundedRect($im, $frameX, $frameY, $frameW, $frameH, $frameR, $frameFillCol);
        if ($borderWidth > 0) {
            $this->strokeRoundedRect($im, $frameX, $frameY, $frameW, $frameH, $frameR, $borderCol, $borderWidth);
        }

        // 2) Base plate (behind everything, at bottom of inner area)
        $plateY = $innerY + $innerH - $basePlateHeight;
        $plateInset = (int) round($framePadding * 0.4);
        $plateX = $innerX + $plateInset;
        $plateW = $innerW - 2 * $plateInset;
        $plateR = min($frameRadius, (int) floor($basePlateHeight / 2));
        $this->fillRoundedRect($im, $plateX, $plateY, $plateW, $basePlateHeight, $plateR, $basePlateCol);

        // 3) Tile shadows
        for ($i = 0; $i < $segments; $i++) {
            $tileX = $startX + $i * ($tileWidth + $tileGap);
            $this->fillRoundedRect($im, $tileX + $shadowOffset, $tileY + $shadowOffset, $tileWidth, $tileHeight, $tileRadius, $tileShadowCol);
        }

        // 4) Tiles
        for ($i = 0; $i < $segments; $i++) {
            $tileX = $startX + $i * ($tileWidth + $tileGap);
            $this->fillRoundedRect($im, $tileX, $tileY, $tileWidth, $tileHeight, $tileRadius, $tileBg);
            if ($tileBorderWidth > 0) {
                $this->strokeRoundedRect($im, $tileX, $tileY, $tileWidth, $tileHeight, $tileRadius, $tileBorderCol, $tileBorderWidth);
            }
        }

        // 5) Hinges (only between tiles, not on far left/right)
        $hingeCenterY = (int) ($tileY + $tileHeight / 2);
        for ($i = 0; $i < $segments - 1; $i++) {
            $gapCenterX = (int) ($startX + ($i + 1) * $tileWidth + $i * $tileGap + $tileGap / 2);
            $hingeX = (int) ($gapCenterX - $hingeWidth / 2);
            $hingeY = (int) ($hingeCenterY - $hingeHeight / 2);
            $hingeR = min(2, (int) floor(min($hingeWidth, $hingeHeight) / 2));
            $this->fillRoundedRect($im, $hingeX, $hingeY, $hingeWidth, $hingeHeight, $hingeR, $hingeCol);
        }
    }

    public function renderTimeText($im, int $secondsRemaining, array $skin, int $width, int $height, int $textCol, int $accentCol): bool
    {
        $font = (string) ($skin['fontPath'] ?? '');
        $fontSize = (int) ($skin['fontSize'] ?? 58);
        $labelFontSize = (int) ($skin['labelFontSize'] ?? 18);
        $labelCol = $this->allocColor($im, $skin['labelColor'] ?? '#485D71');
        $dotCol = $this->allocColor($im, $skin['dotColor'] ?? '#B8C4D0');
        $showLabelDots = ($skin['showLabelDots'] ?? true) === true;

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

        $framePadding = max(0, (int) ($skin['framePadding'] ?? 26));
        $borderWidth = max(0, (int) ($skin['borderWidth'] ?? 3));
        $basePlateHeight = max(0, (int) ($skin['basePlateHeight'] ?? 18));
        $outerMargin = max(20, (int) round(min($width, $height) * 0.06));

        $frameX = $outerMargin;
        $frameY = $outerMargin;
        $frameW = $width - 2 * $outerMargin;
        $frameH = $height - 2 * $outerMargin;

        $innerX = $frameX + $borderWidth + $framePadding;
        $innerY = $frameY + $borderWidth + $framePadding;
        $innerW = $frameW - 2 * ($borderWidth + $framePadding);
        $innerH = $frameH - 2 * ($borderWidth + $framePadding);

        $labelGap = 10;
        $basePlateGap = 10;
        $topPad = 14;
        $labelRowHeight = $labelFontSize + $labelGap;
        $dotsRowHeight = 14;
        $bottomRegionH = $labelRowHeight + $dotsRowHeight + $basePlateGap + $basePlateHeight;
        $tileHeight = (int) ($innerH - $topPad - $bottomRegionH);
        $tileGap = max(12, (int) round($innerW * 0.025));
        $tileWidth = (int) (($innerW - ($segments - 1) * $tileGap) / $segments);
        $tileWidth = (int) min($tileWidth, $tileHeight * 1.5);
        $tileGap = max(12, (int) (($innerW - $segments * $tileWidth) / max(1, $segments - 1)));
        $tileY = $innerY + $topPad;
        $startX = (int) ($innerX + ($innerW - ($segments * $tileWidth + ($segments - 1) * $tileGap)) / 2);

        $numberCenterY = $tileY + $tileHeight / 2;
        $labelCenterY = $tileY + $tileHeight + $labelGap + $labelFontSize / 2;
        $dotY = (int) ($tileY + $tileHeight + $labelRowHeight + $dotsRowHeight / 2);

        if (is_file($font)) {
            for ($i = 0; $i < $segments; $i++) {
                $tileX = $startX + $i * ($tileWidth + $tileGap);
                $centerX = (int) ($tileX + $tileWidth / 2);

                $value = $units[$i]['value'];
                $label = $units[$i]['label'];
                $isDays = ($label === 'DAYS');
                $numberText = $isDays ? (string) $value : sprintf('%02d', $value);
                $numberWidth = $this->textBoxWidth($numberText, $font, $fontSize);
                $numberBaseline = (int) round($numberCenterY - $this->textVerticalCenterOffset($numberText, $font, $fontSize));
                imagettftext($im, $fontSize, 0, (int) ($centerX - $numberWidth / 2), $numberBaseline, $textCol, $font, $numberText);

                $labelWidth = $this->textBoxWidth($label, $font, $labelFontSize);
                $labelBaseline = (int) round($labelCenterY - $this->textVerticalCenterOffset($label, $font, $labelFontSize));
                imagettftext($im, $labelFontSize, 0, (int) ($centerX - $labelWidth / 2), $labelBaseline, $labelCol, $font, $label);

                if ($showLabelDots) {
                    $dotCount = 3;
                    $dotRadius = 2;
                    $dotSpacing = 6;
                    $dotsTotalW = ($dotCount - 1) * $dotSpacing;
                    $firstDotX = (int) ($centerX - $dotsTotalW / 2);
                    for ($d = 0; $d < $dotCount; $d++) {
                        $dx = $firstDotX + $d * $dotSpacing;
                        imagefilledellipse($im, $dx, $dotY, 2 * $dotRadius, 2 * $dotRadius, $dotCol);
                    }
                }
            }
        } else {
            $builtIn = 5;
            $labelBuiltIn = 3;
            $numberY = (int) ($numberCenterY - 12);
            for ($i = 0; $i < $segments; $i++) {
                $tileX = $startX + $i * ($tileWidth + $tileGap);
                $centerX = (int) ($tileX + $tileWidth / 2);

                $value = $units[$i]['value'];
                $label = $units[$i]['label'];
                $isDays = ($label === 'DAYS');
                $numberText = $isDays ? (string) $value : sprintf('%02d', $value);
                $nW = imagefontwidth($builtIn) * strlen($numberText);
                imagestring($im, $builtIn, (int) ($centerX - $nW / 2), $numberY, $numberText, $textCol);

                $lW = imagefontwidth($labelBuiltIn) * strlen($label);
                imagestring($im, $labelBuiltIn, (int) ($centerX - $lW / 2), (int) ($labelCenterY - 10), $label, $labelCol);

                if ($showLabelDots) {
                    for ($d = -1; $d <= 1; $d++) {
                        imagefilledellipse($im, $centerX + $d * 6, $dotY, 4, 4, $dotCol);
                    }
                }
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
