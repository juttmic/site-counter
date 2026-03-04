<?php

namespace App\Services\Skins\Renderers;

use App\Services\Skins\SkinRendererInterface;

class TealSquareTilesRenderer implements SkinRendererInterface
{
    public function renderStyle($im, array $skin, int $width, int $height, int $bgCol, int $textCol, int $accentCol, int $secondsRemaining): void
    {
        // Background fill
        imagefilledrectangle($im, 0, 0, $width, $height, $bgCol);

        // Draw teal squares; respect visibility (days and seconds only toggled)
        $showDays = ($skin['showDays'] ?? true) === true;
        $showHours = ($skin['showHours'] ?? true) === true;
        $showMinutes = ($skin['showMinutes'] ?? true) === true;
        $showSeconds = ($skin['showSeconds'] ?? true) === true;
        $segments = ($showDays ? 1 : 0) + ($showHours ? 1 : 0) + ($showMinutes ? 1 : 0) + ($showSeconds ? 1 : 0);
        if ($segments < 1) {
            $segments = 1;
        }
        $gap = max(4, (int) round($width * 0.01));
        $unitWidth = (int) floor(($width - ($segments + 1) * $gap) / $segments);
        $tileSize = min($unitWidth, $height - 2 * $gap);
        $y = (int) round(($height - $tileSize) / 2);

        for ($i = 0; $i < $segments; $i++) {
            $x = $gap + $i * ($unitWidth + $gap) + (int) floor(($unitWidth - $tileSize) / 2);
            imagefilledrectangle($im, $x, $y, $x + $tileSize, $y + $tileSize, $accentCol);
        }
    }

    public function renderTimeText($im, int $secondsRemaining, array $skin, int $width, int $height, int $textCol, int $accentCol): bool
    {
        $font = (string) ($skin['fontPath'] ?? '');
        $fontSize = (int) ($skin['fontSize'] ?? 34);
        $labelSize = max(10, (int) round($fontSize * 0.33));

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
        $gap = max(4, (int) round($width * 0.01));
        $unitWidth = (int) floor(($width - ($segments + 1) * $gap) / $segments);
        $tileSize = min($unitWidth, $height - 2 * $gap);
        $tileY = (int) round(($height - $tileSize) / 2);

        if (is_file($font)) {
            for ($i = 0; $i < $segments; $i++) {
                $tileX = $gap + $i * ($unitWidth + $gap) + (int) floor(($unitWidth - $tileSize) / 2);
                $centerX = (int) ($tileX + $tileSize / 2);
                $centerY = (int) ($tileY + $tileSize / 2);

                $numberText = sprintf('%02d', $units[$i]['value']);
                $numberWidth = $this->textBoxWidth($numberText, $font, $fontSize);
                $numberX = (int) ($centerX - $numberWidth / 2);
                $numberY = (int) ($centerY - 6);
                imagettftext($im, $fontSize, 0, $numberX, $numberY, $textCol, $font, $numberText);

                $label = $units[$i]['label'];
                $labelWidth = $this->textBoxWidth($label, $font, $labelSize);
                $labelX = (int) ($centerX - $labelWidth / 2);
                $labelY = (int) ($centerY + (int) round($fontSize * 0.8));
                imagettftext($im, $labelSize, 0, $labelX, $labelY, $textCol, $font, $label);
            }
        } else {
            $builtIn = 5;
            $labelBuiltIn = 3;
            for ($i = 0; $i < $segments; $i++) {
                $tileX = $gap + $i * ($unitWidth + $gap) + (int) floor(($unitWidth - $tileSize) / 2);
                $centerX = (int) ($tileX + $tileSize / 2);
                $centerY = (int) ($tileY + $tileSize / 2);

                $numberText = sprintf('%02d', $units[$i]['value']);
                $nW = imagefontwidth($builtIn) * strlen($numberText);
                $nH = imagefontheight($builtIn);
                imagestring($im, $builtIn, (int)($centerX - $nW / 2), (int)($centerY - $nH / 2), $numberText, $textCol);

                $label = $units[$i]['label'];
                $lW = imagefontwidth($labelBuiltIn) * strlen($label);
                imagestring($im, $labelBuiltIn, (int)($centerX - $lW / 2), (int)($centerY + 12), $label, $textCol);
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


