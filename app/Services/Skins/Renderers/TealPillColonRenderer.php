<?php

namespace App\Services\Skins\Renderers;

use App\Services\Skins\SkinRendererInterface;

class TealPillColonRenderer implements SkinRendererInterface
{
    public function renderStyle($im, array $skin, int $width, int $height, int $bgCol, int $textCol, int $accentCol, int $secondsRemaining): void
    {
        // Background is the accent (teal) color as a large rounded pill
        $this->fillRoundedRect($im, 0, 0, $width, $height, max(10, (int) round($height / 2.5)), $accentCol);
        // Add a subtle inner highlight line using text color at low alpha to mimic glossy look (optional)
    }

    public function renderTimeText($im, int $secondsRemaining, array $skin, int $width, int $height, int $textCol, int $accentCol): bool
    {
        $font = (string) ($skin['fontPath'] ?? '');
        $fontSize = (int) ($skin['fontSize'] ?? 36);
        // Slightly smaller than numbers, visible but not dominant
        $labelSize = max(10, (int) round($fontSize * 0.32));

        $showDays    = ($skin['showDays'] ?? true) === true;
        $showHours   = ($skin['showHours'] ?? true) === true;
        $showMinutes = ($skin['showMinutes'] ?? true) === true;
        $showSeconds = ($skin['showSeconds'] ?? true) === true;

        $days = intdiv($secondsRemaining, 86400);
        $hours = intdiv($secondsRemaining % 86400, 3600);
        $minutes = intdiv($secondsRemaining % 3600, 60);
        $seconds = $secondsRemaining % 60;

        $units = [];
        if ($showDays)    { $units[] = ['value' => $days,    'label' => 'DAYS']; }
        if ($showHours)   { $units[] = ['value' => $hours,   'label' => 'HOURS']; }
        if ($showMinutes) { $units[] = ['value' => $minutes, 'label' => 'MINUTES']; }
        if ($showSeconds) { $units[] = ['value' => $seconds, 'label' => 'SECONDS']; }
        if (empty($units)) { $units[] = ['value' => $seconds, 'label' => 'SECONDS']; }

        $segments = count($units);
        $unitWidth = $width / max(1, $segments);
        $centerY = (int) round($height / 2);

        // Foreground (numbers/labels) use text color; separators prefer separatorColor if provided
        if (is_file($font)) {
            $sepCol = $textCol;
            if (!empty($skin['separatorColor']) && is_string($skin['separatorColor'])) {
                [$sr,$sg,$sb] = $this->textToRgb($skin['separatorColor']);
                $sepCol = imagecolorallocate($im, $sr, $sg, $sb);
            }
            for ($i = 0; $i < $segments; $i++) {
                $unitX = (int) round($unitWidth * $i + $unitWidth / 2);
                $numberText = sprintf('%02d', $units[$i]['value']);
                $numberWidth = $this->textBoxWidth($numberText, $font, $fontSize);
                $numberX = (int) ($unitX - $numberWidth / 2);
                $numberY = (int) ($centerY - 6);
                imagettftext($im, $fontSize, 0, $numberX, $numberY, $textCol, $font, $numberText);

                $label = $units[$i]['label'];
                $labelWidth = $this->textBoxWidth($label, $font, $labelSize);
                $labelX = (int) ($unitX - $labelWidth / 2);
                $labelY = (int) ($centerY + (int) round($fontSize * 0.9));
                imagettftext($im, $labelSize, 0, $labelX, $labelY, $textCol, $font, $label);

                if ($i < $segments - 1) {
                    $sep = ":";
                    $sepWidth = $this->textBoxWidth($sep, $font, $fontSize);
                    $sepX = (int) ($unitX + $unitWidth / 2 - $sepWidth / 2);
                    $sepY = (int) ($centerY - 6);
                    imagettftext($im, $fontSize, 0, $sepX, $sepY, $sepCol, $font, $sep);
                }
            }
        } else {
            $builtIn = 5;
            $labelBuiltIn = 3;
            for ($i = 0; $i < $segments; $i++) {
                $unitX = (int) round($unitWidth * $i + $unitWidth / 2);
                $numberText = sprintf('%02d', $units[$i]['value']);
                $nW = imagefontwidth($builtIn) * strlen($numberText);
                $nH = imagefontheight($builtIn);
                imagestring($im, $builtIn, (int)($unitX - $nW / 2), (int)($centerY - $nH / 2), $numberText, $textCol);

                $label = $units[$i]['label'];
                $lW = imagefontwidth($labelBuiltIn) * strlen($label);
                imagestring($im, $labelBuiltIn, (int)($unitX - $lW / 2), (int)($centerY + 14), $label, $textCol);

                if ($i < $segments - 1) {
                    $sep = ":";
                    $sW = imagefontwidth($builtIn) * strlen($sep);
                    $sepColor = $textCol;
                    imagestring($im, $builtIn, (int)($unitX + $unitWidth / 2 - $sW / 2), (int)($centerY - $nH / 2), $sep, $sepColor);
                }
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

    private function textToRgb(string $hex): array
    {
        $hex = ltrim($hex, '#');
        if (strlen($hex) === 3) {
            $hex = $hex[0].$hex[0].$hex[1].$hex[1].$hex[2].$hex[2];
        }
        return [
            hexdec(substr($hex,0,2)),
            hexdec(substr($hex,2,2)),
            hexdec(substr($hex,4,2)),
        ];
    }

    private function fillRoundedRect($im, int $x, int $y, int $w, int $h, int $r, int $color): void
    {
        // Draw central rectangle
        imagefilledrectangle($im, $x + $r, $y, $x + $w - $r, $y + $h, $color);
        // Left and right rectangles
        imagefilledrectangle($im, $x, $y + $r, $x + $r, $y + $h - $r, $color);
        imagefilledrectangle($im, $x + $w - $r, $y + $r, $x + $w, $y + $h - $r, $color);
        // Four circles for corners
        imagefilledellipse($im, $x + $r, $y + $r, 2 * $r, 2 * $r, $color);
        imagefilledellipse($im, $x + $w - $r, $y + $r, 2 * $r, 2 * $r, $color);
        imagefilledellipse($im, $x + $r, $y + $h - $r, 2 * $r, 2 * $r, $color);
        imagefilledellipse($im, $x + $w - $r, $y + $h - $r, 2 * $r, 2 * $r, $color);
    }
}

?>


