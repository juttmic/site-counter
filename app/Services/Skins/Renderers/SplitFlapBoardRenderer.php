<?php

namespace App\Services\Skins\Renderers;

use App\Services\Skins\SkinRendererInterface;

class SplitFlapBoardRenderer implements SkinRendererInterface
{
    public function renderStyle($im, array $skin, int $width, int $height, int $bgCol, int $textCol, int $accentCol, int $secondsRemaining): void
    {
        // Airport split-flap style: panels with a hinge line
        imagefilledrectangle($im, 0, 0, $width, $height, $bgCol);

        $segments = 4;
        $gap = max(6, (int) round($width * 0.015));
        $unitWidth = (int) floor(($width - ($segments + 1) * $gap) / $segments);
        $panelH = $height - 2 * $gap;
        $y = $gap;
        
        // Use accentColor for the panel background (red by default)
        $panelCol = $accentCol;
        
        // Create darker frame and hinge colors for contrast
        // Get RGB from skin's accentColor hex value and darken it
        $accentHex = $skin['accentColor'] ?? '#DB0000';
        [$r, $g, $b] = $this->hexToRgb($accentHex);
        
        // Darken for frame (70% of original)
        $frameR = (int) ($r * 0.7);
        $frameG = (int) ($g * 0.7);
        $frameB = (int) ($b * 0.7);
        $frameCol = imagecolorallocate($im, $frameR, $frameG, $frameB);
        
        // Darken for hinge (50% of original)
        $hingeR = (int) ($r * 0.5);
        $hingeG = (int) ($g * 0.5);
        $hingeB = (int) ($b * 0.5);
        $hingeCol = imagecolorallocate($im, $hingeR, $hingeG, $hingeB);

        for ($i = 0; $i < $segments; $i++) {
            $x = $gap + $i * ($unitWidth + $gap);
            imagefilledrectangle($im, $x, $y, $x + $unitWidth, $y + $panelH, $panelCol);
            // frame
            imagerectangle($im, $x, $y, $x + $unitWidth, $y + $panelH, $frameCol);
            // hinge line
            $mid = (int) ($y + $panelH / 2);
            imageline($im, $x, $mid, $x + $unitWidth, $mid, $hingeCol);
        }
    }
    
    /**
     * Convert hex color to RGB array
     */
    private function hexToRgb(string $hex): array
    {
        $hex = ltrim($hex, '#');
        if (strlen($hex) === 3) {
            $hex = $hex[0].$hex[0].$hex[1].$hex[1].$hex[2].$hex[2];
        }
        return [
            hexdec(substr($hex, 0, 2)),
            hexdec(substr($hex, 2, 2)),
            hexdec(substr($hex, 4, 2)),
        ];
    }

    public function renderTimeText($im, int $secondsRemaining, array $skin, int $width, int $height, int $textCol, int $accentCol): bool
    {
        $font = (string) ($skin['fontPath'] ?? '');
        $fontSize = (int) ($skin['fontSize'] ?? 40);
        $labelSize = max(10, (int) round($fontSize * 0.28));

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

        $segments = count($units);
        $gap = max(6, (int) round($width * 0.015));
        $unitWidth = (int) floor(($width - ($segments + 1) * $gap) / $segments);
        $panelH = $height - 2 * $gap;
        $y = $gap;
        $mid = (int) ($y + $panelH / 2);

        if (is_file($font)) {
            for ($i = 0; $i < $segments; $i++) {
                $x = $gap + $i * ($unitWidth + $gap);
                // top and bottom halves with slight offset for the split flap feel (numbers appear centered overall)
                $numberText = sprintf('%02d', $units[$i]['value']);
                $nW = $this->textBoxWidth($numberText, $font, $fontSize);
                $nX = (int) ($x + $unitWidth / 2 - $nW / 2);
                $nY = (int) ($mid - 6);
                imagettftext($im, $fontSize, 0, $nX, $nY, $textCol, $font, $numberText);

                $label = $units[$i]['label'];
                $lW = $this->textBoxWidth($label, $font, $labelSize);
                $lX = (int) ($x + $unitWidth / 2 - $lW / 2);
                $lY = (int) ($mid + (int) round($fontSize * 0.9));
                imagettftext($im, $labelSize, 0, $lX, $lY, $textCol, $font, $label);
            }
        } else {
            $built = 5; $lbl = 3;
            for ($i = 0; $i < $segments; $i++) {
                $x = $gap + $i * ($unitWidth + $gap);
                $numberText = sprintf('%02d', $units[$i]['value']);
                $nW = imagefontwidth($built) * strlen($numberText);
                imagestring($im, $built, (int)($x + $unitWidth / 2 - $nW / 2), (int)($mid - 10), $numberText, $textCol);
                $label = $units[$i]['label'];
                $lW = imagefontwidth($lbl) * strlen($label);
                imagestring($im, $lbl, (int)($x + $unitWidth / 2 - $lW / 2), (int)($mid + 8), $label, $textCol);
            }
        }

        return true;
    }

    private function textBoxWidth(string $text, string $fontPath, int $fontSize): int
    {
        $box = imagettfbbox($fontSize, 0, $fontPath, $text) ?: [0,0,0,0,0,0,0,0];
        $minX = min($box[0], $box[2], $box[4], $box[6]);
        $maxX = max($box[0], $box[2], $box[4], $box[6]);
        return (int) ($maxX - $minX);
    }
}

?>


