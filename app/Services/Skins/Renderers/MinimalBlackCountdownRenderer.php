<?php

namespace App\Services\Skins\Renderers;

use App\Services\Skins\SkinRendererInterface;

class MinimalBlackCountdownRenderer implements SkinRendererInterface
{
    public function renderStyle($im, array $skin, int $width, int $height, int $bgCol, int $textCol, int $accentCol, int $secondsRemaining): void
    {
        // White background and thin border (replicates drawCleanWhiteTimer)
        imagefilledrectangle($im, 0, 0, $width, $height, $bgCol);
        imagerectangle($im, 0, 0, $width - 1, $height - 1, $textCol);
    }

    public function renderTimeText($im, int $secondsRemaining, array $skin, int $width, int $height, int $textCol, int $accentCol): bool
    {
        // Replicates renderCleanWhiteTimerText
        $font = $skin['fontPath'];
        $fontSize = (int) $skin['fontSize'];
        $labelSize = max(12, (int) round($fontSize * 0.3));

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
            for ($i = 0; $i < 4; $i++) {
                $unitWidth = $width / 4;
                $unitX = $unitWidth * $i + $unitWidth / 2;
                $centerY = $height / 2;

                $numberText = sprintf('%02d', $timeUnits[$i]['value']);
                $numberWidth = $this->textBoxWidth($numberText, $font, $fontSize);
                $numberX = (int) ($unitX - $numberWidth / 2);
                $numberY = (int) ($centerY - 10);
                imagettftext($im, $fontSize, 0, $numberX, $numberY, $textCol, $font, $numberText);

                $labelWidth = $this->textBoxWidth($timeUnits[$i]['label'], $font, $labelSize);
                $labelX = (int) ($unitX - $labelWidth / 2);
                $labelY = (int) ($centerY + 20);
                imagettftext($im, $labelSize, 0, $labelX, $labelY, $accentCol, $font, $timeUnits[$i]['label']);

                if ($i < 3) {
                    $colonX = (int) ($unitX + $unitWidth / 2 - 5);
                    $colonY = (int) ($centerY - 5);
                    imagettftext($im, $fontSize, 0, $colonX, $colonY, $textCol, $font, ':');
                }
            }
        } else {
            // Fallback to built-in fonts for environments without TTF
            $builtIn = 5;
            $labelBuiltIn = 3;
            for ($i = 0; $i < 4; $i++) {
                $unitWidth = $width / 4;
                $unitX = $unitWidth * $i + $unitWidth / 2;
                $centerY = $height / 2;

                $numberText = sprintf('%02d', $timeUnits[$i]['value']);
                $numberWidth = imagefontwidth($builtIn) * strlen($numberText);
                $numberHeight = imagefontheight($builtIn);
                $numberX = (int) ($unitX - $numberWidth / 2);
                $numberY = (int) ($centerY - $numberHeight / 2 - 5);
                imagestring($im, $builtIn, $numberX, $numberY, $numberText, $textCol);

                $labelWidth = imagefontwidth($labelBuiltIn) * strlen($timeUnits[$i]['label']);
                $labelX = (int) ($unitX - $labelWidth / 2);
                $labelY = (int) ($centerY + 10);
                imagestring($im, $labelBuiltIn, $labelX, $labelY, $timeUnits[$i]['label'], $accentCol);

                if ($i < 3) {
                    $colonX = (int) ($unitX + $unitWidth / 2 - 3);
                    $colonY = (int) ($centerY - 3);
                    imagestring($im, $builtIn, $colonX, $colonY, ':', $textCol);
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
}


