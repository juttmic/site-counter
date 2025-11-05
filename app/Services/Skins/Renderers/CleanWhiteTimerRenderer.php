<?php

namespace App\Services\Skins\Renderers;

use App\Services\Skins\SkinRendererInterface;

class CleanWhiteTimerRenderer implements SkinRendererInterface
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

        // Respect checkbox flags
        $timeUnits = [];
        if (($skin['showDays'] ?? true) === true)    { $timeUnits[] = ['value' => $days, 'label' => 'D']; }
        if (($skin['showHours'] ?? true) === true)   { $timeUnits[] = ['value' => $hours, 'label' => 'H']; }
        if (($skin['showMinutes'] ?? true) === true) { $timeUnits[] = ['value' => $minutes, 'label' => 'M']; }
        if (($skin['showSeconds'] ?? true) === true) { $timeUnits[] = ['value' => $seconds, 'label' => 'S']; }
        if (empty($timeUnits)) { $timeUnits[] = ['value' => $seconds, 'label' => 'S']; }

        $separator = (string)($skin['separator'] ?? ':');
        if ($separator === '') { $separator = ':'; }
        $sepChar = mb_substr($separator, 0, 1);

        if (is_file($font)) {
            $sepCol = $textCol;
            if (!empty($skin['separatorColor']) && is_string($skin['separatorColor'])) {
                [$sr,$sg,$sb] = $this->hexToRgb($skin['separatorColor']);
                $sepCol = imagecolorallocate($im, $sr, $sg, $sb);
            }
            $segments = count($timeUnits);
            for ($i = 0; $i < $segments; $i++) {
                $unitWidth = $width / max(1, $segments);
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

            if ($i < $segments - 1) {
                    $colonX = (int) ($unitX + $unitWidth / 2 - 5);
                    $colonY = (int) ($centerY - 5);
                imagettftext($im, $fontSize, 0, $colonX, $colonY, $sepCol, $font, $sepChar);
                }
            }
        } else {
            // Fallback to built-in fonts for environments without TTF
            $builtIn = 5;
            $labelBuiltIn = 3;
            $segments = count($timeUnits);
            for ($i = 0; $i < $segments; $i++) {
                $unitWidth = $width / max(1, $segments);
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

            if ($i < $segments - 1) {
                    $colonX = (int) ($unitX + $unitWidth / 2 - 3);
                    $colonY = (int) ($centerY - 3);
                imagestring($im, $builtIn, $colonX, $colonY, $sepChar, $sepCol);
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

    private function hexToRgb(string $hex): array
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
}


