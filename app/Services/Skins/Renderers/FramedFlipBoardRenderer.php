<?php

namespace App\Services\Skins\Renderers;

use App\Services\Skins\SkinRendererInterface;
use Illuminate\Support\Str;

/**
 * Skin style: "framed_flip_board" (e.g. skin23)
 * Cards: cool light gray, soft dual shadow (outer drop + inner highlight), medium-large corners,
 * bold geometric sans-serif, monochromatic cool grays with dark slate accent, soft neumorphic emboss.
 */
class FramedFlipBoardRenderer implements SkinRendererInterface
{
    /**
     * @return array{minX: float, maxX: float, minY: float, maxY: float, width: float, height: float}
     */
    private function measureText(string $font, int $size, string $text): array
    {
        $box = imagettfbbox($size, 0, $font, $text) ?: [0, 0, 0, 0, 0, 0, 0, 0];
        $xs = [$box[0], $box[2], $box[4], $box[6]];
        $ys = [$box[1], $box[3], $box[5], $box[7]];
        $minX = min($xs);
        $maxX = max($xs);
        $minY = min($ys);
        $maxY = max($ys);
        return [
            'minX' => $minX,
            'maxX' => $maxX,
            'minY' => $minY,
            'maxY' => $maxY,
            'width' => $maxX - $minX,
            'height' => $maxY - $minY,
        ];
    }

    private function fitFontSizeToBox(string $font, int $desiredSize, string $sampleText, int $maxW, int $maxH, int $minSize = 8): int
    {
        $size = max($minSize, $desiredSize);
        while ($size > $minSize) {
            $m = $this->measureText($font, $size, $sampleText);
            if ($m['width'] <= $maxW && $m['height'] <= $maxH) {
                break;
            }
            $size--;
        }
        return $size;
    }

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

        $framePadding = max(0, (int) ($skin['framePadding'] ?? 12));
        $frameRadius = max(4, min(8, (int) ($skin['frameRadius'] ?? 6)));
        $outerMargin = 0;
        $frameFillHex = $skin['frameFillColor'] ?? null;
        $frameFillCol = $bgCol;
        $tileBg = $this->allocColor($im, $skin['tileBackground'] ?? '#F5F6F8');
        $tileBorderCol = $this->allocColor($im, $skin['tileBorderColor'] ?? '#E5E7ED');
        $tileBorderWidth = max(0, (int) ($skin['tileBorderWidth'] ?? 1));
        $tileBevelHighlight = $this->allocColor($im, $skin['tileBevelHighlight'] ?? '#F4F6F9');
        $tileBevelShadow = $this->allocColor($im, $skin['tileBevelShadow'] ?? '#D0D8E2');
        // Respect Tile Radius control (0..30); clamp only for safety.
        $tileRadius = max(0, min(30, (int) ($skin['tileRadius'] ?? 14)));
        $tileShadowCol = $this->allocColor($im, $skin['tileShadowColor'] ?? '#D6DCE4');
        $tileShadowOffset = 4;
        $hingeCol = $this->allocColor($im, $skin['hingeColor'] ?? '#FAFAFA');
        $hingeBorderCol = $this->allocColor($im, $skin['hingeBorderColor'] ?? '#9B9CA7');

        $frameX = $outerMargin;
        $frameY = $outerMargin;
        $frameW = $width - 2 * $outerMargin;
        $frameH = $height - 2 * $outerMargin;
        $frameR = min($frameRadius, (int) floor(min($frameW, $frameH) / 4));

        $innerX = $frameX + $framePadding;
        $innerY = $frameY + $framePadding;
        $innerW = $frameW - 2 * $framePadding;
        $innerH = $frameH - 2 * $framePadding;

        $labelFontSize = (int) ($skin['labelFontSize'] ?? 11);
        $labelGap = (int) ($skin['labelGap'] ?? 10);
        $tileGap = max(4, (int) round($innerW * 0.015));
        $tileWidth = (int) (($innerW - ($segments - 1) * $tileGap) / $segments);
        $availableHeight = (int) ($innerH * 0.8);
        $rawHeight = min($availableHeight, (int) ($tileWidth * 0.85));
        $tileHeight = (int) ($rawHeight * 0.8);
        $tileGap = max(4, (int) (($innerW - $segments * $tileWidth) / max(1, $segments - 1)));

        // Match the vertical layout used in renderTimeText so that
        // distance from canvas top to tiles and from labels to bottom is equal.
        $approxLabelHeight = max($labelFontSize, (int) round($labelFontSize * 1.1));
        $totalBlockHeight = $tileHeight + $labelGap + $approxLabelHeight;
        $topMargin = max(4, (int) floor(($height - $totalBlockHeight) / 2));

        $tileY = $topMargin;
        $startX = (int) ($innerX + ($innerW - ($segments * $tileWidth + ($segments - 1) * $tileGap)) / 2);

        // No outer frame: just draw the tiles and their shadows directly.
        for ($i = 0; $i < $segments; $i++) {
            $tileX = $startX + $i * ($tileWidth + $tileGap);
            $this->fillRoundedRect($im, $tileX + $tileShadowOffset, $tileY + $tileShadowOffset, $tileWidth, $tileHeight, $tileRadius, $tileShadowCol);
        }

        for ($i = 0; $i < $segments; $i++) {
            $tileX = $startX + $i * ($tileWidth + $tileGap);
            $this->fillRoundedRect($im, $tileX, $tileY, $tileWidth, $tileHeight, $tileRadius, $tileBg);
            $this->drawNeumorphicEmboss($im, $tileX, $tileY, $tileWidth, $tileHeight, $tileRadius, $tileBevelHighlight, $tileBevelShadow);
            if ($tileBorderWidth > 0) {
                $this->strokeRoundedRect($im, $tileX, $tileY, $tileWidth, $tileHeight, $tileRadius, $tileBorderCol, $tileBorderWidth);
            }
        }

        if (($skin['showHinges'] ?? true) === true) {
            $hingeCenterY = (int) ($tileY + $tileHeight / 2);
            $hingeHeight = max(8, (int) ($skin['hingeHeight'] ?? (int) ($tileHeight * 0.28)));
            $hingeHeight = min($hingeHeight, $tileHeight - 4);
            for ($i = 0; $i < $segments; $i++) {
                $tileX = $startX + $i * ($tileWidth + $tileGap);
                $this->drawHingeVerticalD($im, $tileX, $hingeCenterY, $hingeHeight, true, $hingeCol, $hingeBorderCol);
                $this->drawHingeVerticalD($im, $tileX + $tileWidth, $hingeCenterY, $hingeHeight, false, $hingeCol, $hingeBorderCol);
            }
        }
    }

    /**
     * Draw a vertical D-shaped hinge protruding from the tile edge.
     * Flat vertical side touches the tile (sharp 90° corners), semicircle protrudes outward.
     *
     * @param int $edgeX X of the tile edge (left edge for left hinge, right edge for right hinge)
     * @param int $centerY Vertical center of the hinge
     * @param int $hingeHeight Vertical size of the hinge (diameter of semicircle)
     * @param bool $protrudeLeft True = hinge extends left (for left edge), false = extends right (for right edge)
     */
    private function drawHingeVerticalD($im, int $edgeX, int $centerY, int $hingeHeight, bool $protrudeLeft, int $fillCol, int $borderCol): void
    {
        $r = (int) floor($hingeHeight / 2);
        if ($r <= 0) {
            return;
        }
        $d = 2 * $r;
        $topY = $centerY - $r;
        $bottomY = $centerY + $r;

        if ($protrudeLeft) {
            $cx = $edgeX - $r;
            imagefilledarc($im, $cx, $centerY, $d, $d, 90, 270, $fillCol, IMG_ARC_PIE);
            imageline($im, $edgeX, $topY, $edgeX, $bottomY, $borderCol);
            imagearc($im, $cx, $centerY, $d, $d, 90, 270, $borderCol);
        } else {
            $cx = $edgeX + $r;
            imagefilledarc($im, $cx, $centerY, $d, $d, 270, 90, $fillCol, IMG_ARC_PIE);
            imageline($im, $edgeX, $topY, $edgeX, $bottomY, $borderCol);
            imagearc($im, $cx, $centerY, $d, $d, 270, 90, $borderCol);
        }
    }

    private function drawNeumorphicEmboss($im, int $x, int $y, int $w, int $h, int $r, int $highlightCol, int $shadowCol): void
    {
        $r = min($r, (int) floor(min($w, $h) / 2));
        if ($r <= 0) {
            imageline($im, $x, $y, $x + $w, $y, $highlightCol);
            imageline($im, $x, $y, $x, $y + $h, $highlightCol);
            imageline($im, $x, $y + $h - 1, $x + $w, $y + $h - 1, $shadowCol);
            imageline($im, $x + $w - 1, $y, $x + $w - 1, $y + $h, $shadowCol);
            return;
        }
        for ($i = 0; $i < 2; $i++) {
            $ox = $x + $i;
            $oy = $y + $i;
            $ow = $w - 2 * $i;
            $oh = $h - 2 * $i;
            $ir = max(0, $r - $i);
            if ($i === 0) {
                imageline($im, $ox + $ir, $oy, $ox + $ow - $ir, $oy, $highlightCol);
                imageline($im, $ox, $oy + $ir, $ox, $oy + $oh - $ir, $highlightCol);
            } else {
                imageline($im, $ox + $ir, $oy + $oh - 1, $ox + $ow - $ir, $oy + $oh - 1, $shadowCol);
                imageline($im, $ox + $ow - 1, $oy + $ir, $ox + $ow - 1, $oy + $oh - $ir, $shadowCol);
            }
        }
    }

    public function renderTimeText($im, int $secondsRemaining, array $skin, int $width, int $height, int $textCol, int $accentCol): bool
    {
        $font = (string) ($skin['fontPath'] ?? '');
        $labelFontSize = (int) ($skin['labelFontSize'] ?? 11);
        $labelCol = $this->allocColor($im, $skin['labelColor'] ?? '#5C6B7A');

        if ($secondsRemaining <= 0) {
            $finalMsg = (string) ($skin['finalMessage'] ?? "Time's Up");
            $finalTextCol = $skin['finalTextColor'] ?? null;
            $drawCol = ($finalTextCol !== null && $finalTextCol !== '') ? $this->allocColor($im, $finalTextCol) : $textCol;
            $finalFontSize = (int) ($skin['fontSize'] ?? 36);
            if (is_file($font)) {
                $box = imagettfbbox($finalFontSize, 0, $font, $finalMsg) ?: [0, 0, 0, 0, 0, 0, 0, 0];
                $tw = (int) (max($box[0], $box[2], $box[4], $box[6]) - min($box[0], $box[2], $box[4], $box[6]));
                $x = (int) (($width - $tw) / 2);
                $y = (int) ($height / 2 + $finalFontSize / 2);
                imagettftext($im, $finalFontSize, 0, $x, $y, $drawCol, $font, $finalMsg);
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

        $framePadding = max(0, (int) ($skin['framePadding'] ?? 12));
        $outerMargin = 0;

        $frameX = $outerMargin;
        $frameY = $outerMargin;
        $frameW = $width - 2 * $outerMargin;
        $frameH = $height - 2 * $outerMargin;

        $innerX = $frameX + $framePadding;
        $innerY = $frameY + $framePadding;
        $innerW = $frameW - 2 * $framePadding;
        $innerH = $frameH - 2 * $framePadding;

        $labelGap = (int) ($skin['labelGap'] ?? 10);
        $tileGap = max(4, (int) round($innerW * 0.015));
        $tileWidth = (int) (($innerW - ($segments - 1) * $tileGap) / $segments);
        $availableHeight = (int) ($innerH * 0.8);
        $rawHeight = min($availableHeight, (int) ($tileWidth * 0.85));
        $tileHeight = (int) ($rawHeight * 0.8);
        $tileGap = max(4, (int) (($innerW - $segments * $tileWidth) / max(1, $segments - 1)));

        // Compute a layout where the distance from the canvas top to the tiles
        // and from the bottom of the labels to the canvas bottom are the same.
        $approxLabelHeight = max($labelFontSize, (int) round($labelFontSize * 1.1));
        $totalBlockHeight = $tileHeight + $labelGap + $approxLabelHeight;
        $topMargin = max(4, (int) floor(($height - $totalBlockHeight) / 2));

        $tileY = $topMargin;
        $startX = (int) ($innerX + ($innerW - ($segments * $tileWidth + ($segments - 1) * $tileGap)) / 2);

        $numberFont = $this->resolveNumberFont($skin);
        $requestedDigitFontSize = (int) ($skin['fontSize'] ?? 36);
        $digitPad = max(6, (int) round($tileHeight * 0.12));
        $digitMaxW = max(1, $tileWidth - 2 * $digitPad);
        $digitMaxH = max(1, $tileHeight - 2 * $digitPad);
        $fontSize = $this->fitFontSizeToBox($numberFont, $requestedDigitFontSize, '88', $digitMaxW, $digitMaxH, 8);

        if (is_file($font)) {
            for ($i = 0; $i < $segments; $i++) {
                $tileX = $startX + $i * ($tileWidth + $tileGap);
                $centerX = (int) ($tileX + $tileWidth / 2);

                $value = $units[$i]['value'];
                $label = $units[$i]['label'];
                $numberText = sprintf('%02d', $value);

                // Label metrics for precise centering in its allocated region
                $labBox = imagettfbbox($labelFontSize, 0, $font, $label) ?: [0,0,0,0,0,0,0,0];
                $labMinY = min($labBox[1], $labBox[3], $labBox[5], $labBox[7]);
                $labMaxY = max($labBox[1], $labBox[3], $labBox[5], $labBox[7]);
                $labHeight = $labMaxY - $labMinY;
                $labCenterOffset = ($labBox[1] + $labBox[7]) / 2.0;

                // Number centered within the tile
                $numberCenterY = $tileY + $tileHeight / 2.0;
                $numCenterOffset = $this->textVerticalCenterOffset($numberText, $numberFont, $fontSize);
                $numberBaseline = (int) round($numberCenterY - $numCenterOffset);

                // Label centered within its own box just below the tile
                $labelRegionTop = $tileY + $tileHeight + $labelGap;
                $labelRegionCenterY = $labelRegionTop + $approxLabelHeight / 2.0;
                $labelBaseline = (int) round($labelRegionCenterY - $labCenterOffset);

                $numberWidth = $this->textBoxWidth($numberText, $numberFont, $fontSize);
                imagettftext($im, $fontSize, 0, (int) ($centerX - $numberWidth / 2), $numberBaseline, $textCol, $numberFont, $numberText);

                $labelWidth = $this->textBoxWidth($label, $font, $labelFontSize);
                imagettftext($im, $labelFontSize, 0, (int) ($centerX - $labelWidth / 2), $labelBaseline, $labelCol, $font, $label);
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
                $numberText = sprintf('%02d', $value);
                $nW = imagefontwidth($builtIn) * strlen($numberText);
                imagestring($im, $builtIn, (int) ($centerX - $nW / 2), $numberY, $numberText, $textCol);

                $lW = imagefontwidth($labelBuiltIn) * strlen($label);
                imagestring($im, $labelBuiltIn, (int) ($centerX - $lW / 2), (int) ($labelCenterY - 10), $label, $labelCol);
            }
        }

        return true;
    }

    private function resolveNumberFont(array $skin): string
    {
        $numberFont = (string) ($skin['numberFontPath'] ?? '');
        if ($numberFont !== '') {
            if (is_file($numberFont)) {
                return $numberFont;
            }
            if (Str::startsWith($numberFont, 'resources/')) {
                $try = resource_path(Str::after($numberFont, 'resources/'));
                if (is_file($try)) {
                    return $try;
                }
            }
            $try = resource_path('fonts/' . basename($numberFont));
            if (is_file($try)) {
                return $try;
            }
        }
        $base = (string) ($skin['fontPath'] ?? '');
        if ($base !== '' && is_file($base)) {
            return $base;
        }
        $try = resource_path('fonts/dejavu-sans.bold.ttf');
        return is_file($try) ? $try : resource_path('fonts/dejavu-sans.book.ttf');
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
