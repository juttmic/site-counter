<?php

namespace App\Services\Skins\Renderers;

use App\Services\Skins\SkinRendererInterface;

class TealCircleBadgesRenderer implements SkinRendererInterface
{
    public function renderStyle($im, array $skin, int $width, int $height, int $bgCol, int $textCol, int $accentCol, int $secondsRemaining): void
    {
        // Fill background
        imagefilledrectangle($im, 0, 0, $width, $height, $bgCol);

        // Draw 4 outlined circles as badges
        $segments = 4;
        $unitWidth = $width / $segments;
        $centerY = (int) round($height / 2);
        $radius = (int) round(min($unitWidth / 2.6, $height / 2.2));
        $stroke = (int) max(2, (int) ($skin['circleStroke'] ?? 6));

        for ($i = 0; $i < $segments; $i++) {
            $centerX = (int) round($unitWidth * $i + $unitWidth / 2);
            // White (or bg) circle fill
            imagefilledellipse($im, $centerX, $centerY, $radius * 2, $radius * 2, imagecolorallocatealpha($im, 255, 255, 255, 0));
            // Draw border by multiple concentric ellipses for a thicker stroke
            for ($s = 0; $s < $stroke; $s++) {
                imageellipse($im, $centerX, $centerY, $radius * 2 - $s, $radius * 2 - $s, $accentCol);
            }
        }
    }

    public function renderTimeText($im, int $secondsRemaining, array $skin, int $width, int $height, int $textCol, int $accentCol): bool
    {
        $font = (string) ($skin['fontPath'] ?? '');
        $fontSize = (int) ($skin['fontSize'] ?? 34);
        $labelSize = max(10, (int) round($fontSize * 0.38));

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

        if (is_file($font)) {
            for ($i = 0; $i < $segments; $i++) {
                $unitX = (int) round($unitWidth * $i + $unitWidth / 2);
                $numberText = sprintf('%d', $units[$i]['value']);
                $numberWidth = $this->textBoxWidth($numberText, $font, $fontSize);
                $numberX = (int) ($unitX - $numberWidth / 2);
                $numberY = (int) ($centerY - 6);
                imagettftext($im, $fontSize, 0, $numberX, $numberY, $textCol, $font, $numberText);

                $label = $units[$i]['label'];
                $labelWidth = $this->textBoxWidth($label, $font, $labelSize);
                $labelX = (int) ($unitX - $labelWidth / 2);
                $labelY = (int) ($centerY + (int) round($fontSize * 0.9));
                imagettftext($im, $labelSize, 0, $labelX, $labelY, $accentCol, $font, $label);
            }
        } else {
            $builtIn = 5; // bigger
            $labelBuiltIn = 3;
            for ($i = 0; $i < $segments; $i++) {
                $unitX = (int) round($unitWidth * $i + $unitWidth / 2);
                $numberText = sprintf('%d', $units[$i]['value']);
                $nW = imagefontwidth($builtIn) * strlen($numberText);
                $nH = imagefontheight($builtIn);
                imagestring($im, $builtIn, (int)($unitX - $nW / 2), (int)($centerY - $nH / 2), $numberText, $textCol);

                $label = $units[$i]['label'];
                $lW = imagefontwidth($labelBuiltIn) * strlen($label);
                imagestring($im, $labelBuiltIn, (int)($unitX - $lW / 2), (int)($centerY + 14), $label, $accentCol);
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


