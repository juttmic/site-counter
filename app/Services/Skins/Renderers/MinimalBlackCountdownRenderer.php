<?php

namespace App\Services\Skins\Renderers;

use App\Services\Skins\SkinRendererInterface;

class MinimalBlackCountdownRenderer implements SkinRendererInterface
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
        $labelSize = max(10, (int) round($fontSize * 0.4));

        $days = intdiv($secondsRemaining, 86400);
        $hours = intdiv($secondsRemaining % 86400, 3600);
        $minutes = intdiv($secondsRemaining % 3600, 60);
        $seconds = $secondsRemaining % 60;

        $timeUnits = [
            ['value' => $days, 'label' => 'D'],
            ['value' => $hours, 'label' => 'H'],
            ['value' => $minutes, 'label' => 'M'],
            ['value' => $seconds, 'label' => 'S']
        ];

        if (is_file($font)) {
            $unitCount = count($timeUnits);
            $unitWidth = $width / $unitCount;
            $centerY = $height / 2;

            foreach ($timeUnits as $i => $unit) {
                $unitX = $unitWidth * $i + $unitWidth / 2;
                
                // Main number
                $numberText = sprintf('%02d', $unit['value']);
                $numberBox = imagettfbbox($fontSize, 0, $font, $numberText);
                $numberWidth = abs($numberBox[4] - $numberBox[0]);
                $numberHeight = abs($numberBox[5] - $numberBox[1]);
                $numberX = (int) ($unitX - $numberWidth / 2);
                $numberY = (int) ($centerY + $numberHeight/2);
                imagettftext($im, $fontSize, 0, $numberX, $numberY, $textCol, $font, $numberText);

                // Small label below
                $labelText = $unit['label'];
                $labelBox = imagettfbbox($labelSize, 0, $font, $labelText);
                $labelWidth = abs($labelBox[4] - $labelBox[0]);
                $labelX = (int) ($unitX + $numberWidth / 1.5);
                $labelY = (int) ($centerY + $numberHeight / 2);
                imagettftext($im, $labelSize, 0, $labelX, $labelY, $textCol, $font, $labelText);

                // Colon separator
                if ($i < $unitCount - 1) {
                    $colon = ':';
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
