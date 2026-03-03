<?php

namespace App\Services\Skins\Renderers;

use App\Services\Skins\SkinRendererInterface;

/**
 * Skin style: "framed_flip_board"
 * Renders countdown as framed flip-board tiles with hinges, base plate, and optional label dots.
 */
class FramedFlipBoardRenderer implements SkinRendererInterface
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

        $framePadding = max(0, (int) ($skin['framePadding'] ?? 26));
        $frameRadius = max(0, (int) ($skin['frameRadius'] ?? 10));
        $borderWidth = max(0, (int) ($skin['borderWidth'] ?? 3));
        $borderCol = $this->allocColor($im, $skin['borderColor'] ?? '#D5DEE7');
        $tileBg = $this->allocColor($im, $skin['tileBackground'] ?? '#FFFFFF');
        $tileBorderCol = $this->allocColor($im, $skin['tileBorderColor'] ?? '#CBD5DE');
        $tileBorderWidth = max(0, (int) ($skin['tileBorderWidth'] ?? 2));
        $tileRadius = max(0, (int) ($skin['tileRadius'] ?? 10));
        $hingeCol = $this->allocColor($im, $skin['hingeColor'] ?? '#C6D0DA');
        $hingeWidth = max(0, (int) ($skin['hingeWidth'] ?? 10));
        $hingeHeight = max(0, (int) ($skin['hingeHeight'] ?? 18));
        $basePlateCol = $this->allocColor($im, $skin['basePlateColor'] ?? '#D6E0E8');
        $basePlateHeight = max(0, (int) ($skin['basePlateHeight'] ?? 18));
        $shadowCol = $this->allocColor($im, $skin['tileShadowColor'] ?? '#DCE4EB');

        $segments = count($units);
        $contentX = $borderWidth + $framePadding;
        $contentY = $borderWidth + $framePadding;
        $contentW = $width - 2 * ($borderWidth + $framePadding);
        $contentH = $height - 2 * ($borderWidth + $framePadding);
        $tileHeight = $contentH - $basePlateHeight;
        $tileGap = max(4, (int) round($contentW * 0.02));
        $tileWidth = (int) floor(($contentW - ($segments - 1) * $tileGap) / $segments);

        // Outer frame
        if ($borderWidth > 0) {
            $this->strokeRect($im, 0, 0, $width, $height, $borderCol, $borderWidth);
        }

        for ($i = 0; $i < $segments; $i++) {
            $tileX = $contentX + $i * ($tileWidth + $tileGap);
            $tileY = $contentY;

            // Shadow
            $shadowOffset = 4;
            $this->fillRoundedRect($im, $tileX + $shadowOffset, $tileY + $shadowOffset, $tileWidth, $tileHeight, $tileRadius, $shadowCol);

            // Tile
            $this->fillRoundedRect($im, $tileX, $tileY, $tileWidth, $tileHeight, $tileRadius, $tileBg);

            if ($tileBorderWidth > 0) {
                $this->strokeRoundedRect($im, $tileX, $tileY, $tileWidth, $tileHeight, $tileRadius, $tileBorderCol, $tileBorderWidth);
            }

            // Hinge line
            $hingeY = (int) ($tileY + $tileHeight / 2);
            $hingeLeft = (int) ($tileX + $tileWidth / 2 - $hingeWidth / 2);
            imagefilledrectangle($im, $hingeLeft, $hingeY - (int) ($hingeHeight / 2), $hingeLeft + $hingeWidth, $hingeY + (int) ($hingeHeight / 2), $hingeCol);
        }

        // Base plate
        if ($basePlateHeight > 0) {
            $plateY = $contentY + $contentH - $basePlateHeight;
            $this->fillRoundedRect($im, $contentX, $plateY, $contentW, $basePlateHeight, min($frameRadius, (int) ($basePlateHeight / 2)), $basePlateCol);
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

        $framePadding = max(0, (int) ($skin['framePadding'] ?? 26));
        $borderWidth = max(0, (int) ($skin['borderWidth'] ?? 3));
        $basePlateHeight = max(0, (int) ($skin['basePlateHeight'] ?? 18));
        $segments = count($units);
        $contentX = $borderWidth + $framePadding;
        $contentY = $borderWidth + $framePadding;
        $contentW = $width - 2 * ($borderWidth + $framePadding);
        $contentH = $height - 2 * ($borderWidth + $framePadding);
        $tileHeight = $contentH - $basePlateHeight;
        $tileGap = max(4, (int) round($contentW * 0.02));
        $tileWidth = (int) floor(($contentW - ($segments - 1) * $tileGap) / $segments);

        $numberY = (int) ($contentY + $tileHeight / 2 - 8);
        $labelY = (int) ($contentY + $tileHeight / 2 + (int) round($fontSize * 0.7));

        if (is_file($font)) {
            for ($i = 0; $i < $segments; $i++) {
                $tileX = $contentX + $i * ($tileWidth + $tileGap);
                $centerX = (int) ($tileX + $tileWidth / 2);

                $numberText = sprintf('%02d', $units[$i]['value']);
                $numberWidth = $this->textBoxWidth($numberText, $font, $fontSize);
                imagettftext($im, $fontSize, 0, (int) ($centerX - $numberWidth / 2), $numberY, $textCol, $font, $numberText);

                $label = $units[$i]['label'];
                $labelWidth = $this->textBoxWidth($label, $font, $labelFontSize);
                imagettftext($im, $labelFontSize, 0, (int) ($centerX - $labelWidth / 2), $labelY, $labelCol, $font, $label);

                if ($showLabelDots) {
                    $dotY = $labelY + (int) round($labelFontSize * 1.2);
                    imagefilledellipse($im, $centerX, $dotY, 4, 4, $dotCol);
                }
            }
        } else {
            $builtIn = 5;
            $labelBuiltIn = 3;
            for ($i = 0; $i < $segments; $i++) {
                $tileX = $contentX + $i * ($tileWidth + $tileGap);
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
        if ($thickness <= 0) return;
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
