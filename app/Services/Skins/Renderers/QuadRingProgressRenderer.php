<?php

namespace App\Services\Skins\Renderers;

use App\Services\Skins\SkinRendererInterface;

class QuadRingProgressRenderer implements SkinRendererInterface
{
    public function renderStyle($im, array $skin, int $width, int $height, int $bgCol, int $textCol, int $accentCol, int $secondsRemaining): void
    {
        // Four annular progress rings (not full circles) with gaps
        imagefilledrectangle($im, 0, 0, $width, $height, $bgCol);
        $segments = 4;
        $unitWidth = $width / $segments;
        $centerY = (int) ($height / 2);
        $radius = (int) round(min($unitWidth / 2.6, $height / 2.6));
        $thick = (int) max(6, (int) ($skin['ringThickness'] ?? 10));
        $bgRing = imagecolorallocate($im, 220, 220, 220);

        for ($i = 0; $i < $segments; $i++) {
            $cx = (int) ($unitWidth * $i + $unitWidth / 2);
            // Base ring
            $this->thickArc($im, $cx, $centerY, $radius, 0, 360, $bgRing, $thick);
            // Progress ring (static visual, independent of remaining seconds for 1-frame look)
            $this->thickArc($im, $cx, $centerY, $radius, 270, 90, $accentCol, $thick);
        }
    }

    public function renderTimeText($im, int $secondsRemaining, array $skin, int $width, int $height, int $textCol, int $accentCol): bool
    {
        $font = (string) ($skin['fontPath'] ?? '');
        $fontSize = (int) ($skin['fontSize'] ?? 28);
        $labelSize = max(10, (int) round($fontSize * 0.32));

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
        $unitWidth = $width / $segments;
        $centerY = (int) ($height / 2);

        if (is_file($font)) {
            for ($i = 0; $i < $segments; $i++) {
                $cx = (int) ($unitWidth * $i + $unitWidth / 2);
                $numberText = sprintf('%02d', $units[$i]['value']);
                $nW = $this->textBoxWidth($numberText, $font, $fontSize);
                imagettftext($im, $fontSize, 0, (int)($cx - $nW / 2), (int)($centerY - 4), $textCol, $font, $numberText);
                $label = $units[$i]['label'];
                $lW = $this->textBoxWidth($label, $font, $labelSize);
                imagettftext($im, $labelSize, 0, (int)($cx - $lW / 2), (int)($centerY + (int) round($fontSize * 0.9)), $accentCol, $font, $label);
            }
        } else {
            $built = 5; $lbl = 3;
            for ($i = 0; $i < $segments; $i++) {
                $cx = (int) ($unitWidth * $i + $unitWidth / 2);
                $numberText = sprintf('%02d', $units[$i]['value']);
                $nW = imagefontwidth($built) * strlen($numberText);
                imagestring($im, $built, (int)($cx - $nW / 2), (int)($centerY - 8), $numberText, $textCol);
                $label = $units[$i]['label'];
                $lW = imagefontwidth($lbl) * strlen($label);
                imagestring($im, $lbl, (int)($cx - $lW / 2), (int)($centerY + 10), $label, $accentCol);
            }
        }

        return true;
    }

    private function thickArc($im, int $cx, int $cy, int $r, int $start, int $end, int $color, int $thickness): void
    {
        imagesetthickness($im, 1);
        for ($t = 0; $t < $thickness; $t++) {
            imagearc($im, $cx, $cy, ($r * 2) - $t, ($r * 2) - $t, $start, $end, $color);
        }
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


