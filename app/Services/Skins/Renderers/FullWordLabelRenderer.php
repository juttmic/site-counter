<?php

namespace App\Services\Skins\Renderers;

use App\Services\Skins\SkinRendererInterface;

class FullWordLabelRenderer implements SkinRendererInterface
{
    public function renderStyle($im, array $skin, int $width, int $height, int $bgCol, int $textCol, int $accentCol, int $secondsRemaining): void
    {
        // Clean white background (no border)
        imagefilledrectangle($im, 0, 0, $width, $height, $bgCol);
    }

    public function renderTimeText($im, int $secondsRemaining, array $skin, int $width, int $height, int $textCol, int $accentCol): bool
    {
        $font = $skin['fontPath'];
        $fontSize = (int) $skin['fontSize'];
        // Use a slightly smaller label for better balance
        $labelSize = max(10, (int) round($fontSize * 0.35));

        $days = intdiv($secondsRemaining, 86400);
        $hours = intdiv($secondsRemaining % 86400, 3600);
        $minutes = intdiv($secondsRemaining % 3600, 60);
        $seconds = $secondsRemaining % 60;

        // Respect checkbox flags
        $timeUnits = [];
        if (($skin['showDays'] ?? true) === true)    { $timeUnits[] = ['value' => $days, 'label' => 'DAYS']; }
        if (($skin['showHours'] ?? true) === true)   { $timeUnits[] = ['value' => $hours, 'label' => 'HOURS']; }
        if (($skin['showMinutes'] ?? true) === true) { $timeUnits[] = ['value' => $minutes, 'label' => 'MINUTES']; }
        if (($skin['showSeconds'] ?? true) === true) { $timeUnits[] = ['value' => $seconds, 'label' => 'SECONDS']; }
        if (empty($timeUnits)) { $timeUnits[] = ['value' => $seconds, 'label' => 'SECONDS']; }

        $separator = (string)($skin['separator'] ?? ':');
        if ($separator === '') { $separator = ':'; }
        $sepChar = mb_substr($separator, 0, 1);

        if (is_file($font)) {
            $unitCount = count($timeUnits);
            $unitWidth = $width / max(1, $unitCount);
            $centerY = $height / 2;

            foreach ($timeUnits as $i => $unit) {
                $unitX = $unitWidth * $i + $unitWidth / 2;
                
                // Main number
                $numberText = sprintf('%02d', $unit['value']);
                $numberBox = imagettfbbox($fontSize, 0, $font, $numberText);
                $numberWidth = abs($numberBox[4] - $numberBox[0]);
                $numberHeight = abs($numberBox[5] - $numberBox[1]);
                $numberX = (int) ($unitX - $numberWidth / 2);
                // Align baseline slightly above center for symmetry with other skins
                $numberY = (int) ($centerY - 6);
                imagettftext($im, $fontSize, 0, $numberX, $numberY, $textCol, $font, $numberText);

                // Small label below
                $labelText = $unit['label'];
                $labelBox = imagettfbbox($labelSize, 0, $font, $labelText);
                $labelWidth = abs($labelBox[4] - $labelBox[0]);
                $labelX = (int) ($unitX - $labelWidth / 2);
                // Consistent offset below number baseline
                $labelY = (int) ($centerY + (int) round($fontSize * 0.9));
                imagettftext($im, $labelSize, 0, $labelX, $labelY, $textCol, $font, $labelText);

                // Separator between units
                if ($i < $unitCount - 1) {
                    $colon = $sepChar;
                    $colonBox = imagettfbbox($fontSize, 0, $font, $colon);
                    $colonWidth = abs($colonBox[4] - $colonBox[0]);
                    $colonX = (int) ($unitX + $unitWidth / 2 - $colonWidth / 2);
                    $colonY = $numberY;
                    imagettftext($im, $fontSize, 0, $colonX, $colonY, $textCol, $font, $colon);
                }
            }
        }

        return true;
    }
}
