<?php

namespace App\Services\Skins\Renderers;

use App\Services\Skins\SkinRendererInterface;
use Illuminate\Support\Str;

/**
 * Skin style: "framed_flip_board" (e.g. skin23)
 * Renders a framed flip-board countdown: outer rounded frame, four number tiles, hinge connectors
 * between tiles, labels under each tile with decorative dots left/right, and a two-layer base stand
 * (lower shadow plate + top platform). Minimal mechanical flip-board aesthetic, soft grayscale.
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

        $framePadding = max(0, (int) ($skin['framePadding'] ?? 28));
        $frameRadius = max(8, min(12, (int) ($skin['frameRadius'] ?? 10)));
        $borderWidth = max(0, min(2, (int) ($skin['borderWidth'] ?? 1)));
        $outerMargin = max(24, (int) round(min($width, $height) * 0.08));
        $borderCol = $this->allocColor($im, $skin['borderColor'] ?? '#E0E0E0');
        $frameFillHex = $skin['frameFillColor'] ?? null;
        $frameFillCol = ($frameFillHex !== null && $frameFillHex !== '') ? $this->allocColor($im, $frameFillHex) : $this->allocColor($im, '#FDFDFD');
        $tileBg = $this->allocColor($im, $skin['tileBackground'] ?? '#FFFFFF');
        $tileBorderCol = $this->allocColor($im, $skin['tileBorderColor'] ?? '#E8E8E8');
        $tileBorderWidth = max(0, min(1, (int) ($skin['tileBorderWidth'] ?? 1)));
        $tileRadius = max(4, min(6, (int) ($skin['tileRadius'] ?? 5)));
        $tileShadowCol = $this->allocColor($im, $skin['tileShadowColor'] ?? '#EBEBEB');
        $tileShadowOffset = (int) ($skin['tileShadowOffset'] ?? 2);
        $hingeCol = $this->allocColor($im, $skin['hingeColor'] ?? '#D0D0D0');
        $hingeWidth = max(12, (int) ($skin['hingeWidth'] ?? 20));
        $hingeHeight = max(4, (int) ($skin['hingeHeight'] ?? 8));
        $basePlateCol = $this->allocColor($im, $skin['basePlateColor'] ?? '#E8E8E8');
        $basePlateHeight = max(0, (int) ($skin['basePlateHeight'] ?? 16));

        $frameX = $outerMargin;
        $frameY = $outerMargin;
        $frameW = $width - 2 * $outerMargin;
        $frameH = $height - 2 * $outerMargin;
        $frameR = min($frameRadius, (int) floor(min($frameW, $frameH) / 4));

        $innerX = $frameX + $borderWidth + $framePadding;
        $innerY = $frameY + $borderWidth + $framePadding;
        $innerW = $frameW - 2 * ($borderWidth + $framePadding);
        $innerH = $frameH - 2 * ($borderWidth + $framePadding);

        $labelFontSize = (int) ($skin['labelFontSize'] ?? 16);
        $labelGap = 12;
        $basePlateGap = 12;
        $topPad = 16;
        $labelRowHeight = $labelFontSize + $labelGap;
        $dotsRowHeight = 12;
        $dotLabelGap = 4;
        $bottomRegionH = $labelRowHeight + $dotLabelGap + $dotsRowHeight + $basePlateGap + $basePlateHeight;
        $tileHeight = (int) ($innerH - $topPad - $bottomRegionH);
        $tileGap = max(12, (int) round($innerW * 0.025));
        $tileWidth = (int) (($innerW - ($segments - 1) * $tileGap) / $segments);
        $tileWidth = (int) min($tileWidth, (int) ($tileHeight * 1.25));
        $tileGap = max(12, (int) (($innerW - $segments * $tileWidth) / max(1, $segments - 1)));
        $tileY = $innerY + $topPad;
        $startX = (int) ($innerX + ($innerW - ($segments * $tileWidth + ($segments - 1) * $tileGap)) / 2);

        // 0) Very soft shadow beneath entire frame (floating effect)
        $frameShadowCol = $this->allocColor($im, $skin['frameShadowColor'] ?? '#E8E8E8');
        $frameShadowOffset = max(2, (int) ($skin['frameShadowOffset'] ?? 4));
        $this->fillRoundedRect($im, $frameX + $frameShadowOffset, $frameY + $frameShadowOffset, $frameW, $frameH, $frameR, $frameShadowCol);

        // 1) Outer frame fill + thin border (board brighter than background)
        $this->fillRoundedRect($im, $frameX, $frameY, $frameW, $frameH, $frameR, $frameFillCol);
        if ($borderWidth > 0) {
            $this->strokeRoundedRect($im, $frameX, $frameY, $frameW, $frameH, $frameR, $borderCol, $borderWidth);
        }

        // 2) Base stand: single soft shadow (wider) then one top platform (no second solid plate)
        $plateY = $innerY + $innerH - $basePlateHeight;
        $plateInset = (int) round($framePadding * 0.5);
        $plateX = $innerX + $plateInset;
        $plateW = $innerW - 2 * $plateInset;
        $plateShadowExtension = (int) ($skin['basePlateShadowExtension'] ?? 8);
        $plateShadowX = $plateX - $plateShadowExtension;
        $plateShadowW = $plateW + 2 * $plateShadowExtension;
        $plateR = min($frameRadius - 2, (int) floor($basePlateHeight / 2), 8);
        $plateR = max(4, $plateR);
        $plateShadowCol = $this->allocColor($im, $skin['basePlateShadowColor'] ?? '#E0E0E0');
        $this->fillRoundedRect($im, $plateShadowX + 2, $plateY + 2, $plateShadowW, $basePlateHeight, $plateR, $plateShadowCol);
        $this->fillRoundedRect($im, $plateX, $plateY, $plateW, $basePlateHeight, $plateR, $basePlateCol);

        // 3) Very subtle tile shadows (1–2px offset, light) or none
        if ($tileShadowOffset > 0) {
            for ($i = 0; $i < $segments; $i++) {
                $tileX = $startX + $i * ($tileWidth + $tileGap);
                $this->fillRoundedRect($im, $tileX + $tileShadowOffset, $tileY + $tileShadowOffset, $tileWidth, $tileHeight, $tileRadius, $tileShadowCol);
            }
        }

        // 4) Tiles (white, thin border, smaller radius than frame)
        for ($i = 0; $i < $segments; $i++) {
            $tileX = $startX + $i * ($tileWidth + $tileGap);
            $this->fillRoundedRect($im, $tileX, $tileY, $tileWidth, $tileHeight, $tileRadius, $tileBg);
            if ($tileBorderWidth > 0) {
                $this->strokeRoundedRect($im, $tileX, $tileY, $tileWidth, $tileHeight, $tileRadius, $tileBorderCol, $tileBorderWidth);
            }
        }

        // 5) Hinges – horizontal flat capsules between tiles (width > height)
        $hingeCenterY = (int) ($tileY + $tileHeight / 2);
        $hingeR = (int) floor(min($hingeWidth, $hingeHeight) / 2);
        for ($i = 0; $i < $segments - 1; $i++) {
            $gapCenterX = (int) ($startX + ($i + 1) * $tileWidth + $i * $tileGap + $tileGap / 2);
            $hingeX = (int) ($gapCenterX - $hingeWidth / 2);
            $hingeY = (int) ($hingeCenterY - $hingeHeight / 2);
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

        $framePadding = max(0, (int) ($skin['framePadding'] ?? 28));
        $borderWidth = max(0, min(2, (int) ($skin['borderWidth'] ?? 1)));
        $basePlateHeight = max(0, (int) ($skin['basePlateHeight'] ?? 16));
        $outerMargin = max(24, (int) round(min($width, $height) * 0.08));

        $frameX = $outerMargin;
        $frameY = $outerMargin;
        $frameW = $width - 2 * $outerMargin;
        $frameH = $height - 2 * $outerMargin;

        $innerX = $frameX + $borderWidth + $framePadding;
        $innerY = $frameY + $borderWidth + $framePadding;
        $innerW = $frameW - 2 * ($borderWidth + $framePadding);
        $innerH = $frameH - 2 * ($borderWidth + $framePadding);

        $labelGap = 12;
        $basePlateGap = 12;
        $topPad = 16;
        $labelRowHeight = $labelFontSize + $labelGap;
        $dotsRowHeight = 12;
        $dotLabelGap = 4;
        $bottomRegionH = $labelRowHeight + $dotLabelGap + $dotsRowHeight + $basePlateGap + $basePlateHeight;
        $tileHeight = (int) ($innerH - $topPad - $bottomRegionH);
        $tileGap = max(12, (int) round($innerW * 0.025));
        $tileWidth = (int) (($innerW - ($segments - 1) * $tileGap) / $segments);
        $tileWidth = (int) min($tileWidth, (int) ($tileHeight * 1.25));
        $tileGap = max(12, (int) (($innerW - $segments * $tileWidth) / max(1, $segments - 1)));
        $tileY = $innerY + $topPad;
        $startX = (int) ($innerX + ($innerW - ($segments * $tileWidth + ($segments - 1) * $tileGap)) / 2);

        $numberCenterY = $tileY + $tileHeight / 2;
        $labelCenterY = $tileY + $tileHeight + $labelGap + $labelFontSize / 2;

        $numberFont = $this->resolveNumberFont($skin);

        if (is_file($font)) {
            for ($i = 0; $i < $segments; $i++) {
                $tileX = $startX + $i * ($tileWidth + $tileGap);
                $centerX = (int) ($tileX + $tileWidth / 2);

                $value = $units[$i]['value'];
                $label = $units[$i]['label'];
                $isDays = ($label === 'DAYS');
                $numberText = $isDays ? (string) $value : sprintf('%02d', $value);
                $numberWidth = $this->textBoxWidth($numberText, $numberFont, $fontSize);
                $numberBaseline = (int) round($numberCenterY - $this->textVerticalCenterOffset($numberText, $numberFont, $fontSize));
                imagettftext($im, $fontSize, 0, (int) ($centerX - $numberWidth / 2), $numberBaseline, $textCol, $numberFont, $numberText);

                $labelWidth = $this->textBoxWidth($label, $font, $labelFontSize);
                $labelBaseline = (int) round($labelCenterY - $this->textVerticalCenterOffset($label, $font, $labelFontSize));
                imagettftext($im, $labelFontSize, 0, (int) ($centerX - $labelWidth / 2), $labelBaseline, $labelCol, $font, $label);

                if ($showLabelDots) {
                    $dotRadius = max(1, (int) ($skin['labelDotRadius'] ?? 2));
                    $dotOffset = max(2, (int) ($skin['labelDotOffset'] ?? 6));
                    $leftDotX = (int) ($centerX - $labelWidth / 2 - $dotOffset);
                    $rightDotX = (int) ($centerX + $labelWidth / 2 + $dotOffset);
                    $dotY = $labelBaseline;
                    imagefilledellipse($im, $leftDotX, $dotY, 2 * $dotRadius, 2 * $dotRadius, $dotCol);
                    imagefilledellipse($im, $rightDotX, $dotY, 2 * $dotRadius, 2 * $dotRadius, $dotCol);
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
                    $dotRadius = max(1, (int) ($skin['labelDotRadius'] ?? 2));
                    $dotOffset = max(2, (int) ($skin['labelDotOffset'] ?? 6));
                    $leftDotX = (int) ($centerX - $lW / 2 - $dotOffset);
                    $rightDotX = (int) ($centerX + $lW / 2 + $dotOffset);
                    $dotY = (int) $labelCenterY;
                    imagefilledellipse($im, $leftDotX, $dotY, 2 * $dotRadius, 2 * $dotRadius, $dotCol);
                    imagefilledellipse($im, $rightDotX, $dotY, 2 * $dotRadius, 2 * $dotRadius, $dotCol);
                }
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
