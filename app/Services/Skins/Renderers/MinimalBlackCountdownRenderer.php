<?php

namespace App\Services\Skins\Renderers;

use App\Services\Skins\SkinRendererInterface;

class MinimalBlackCountdownRenderer implements SkinRendererInterface
{
    private function measureText(string $font, int $size, string $text): array
    {
        $box = imagettfbbox($size, 0, $font, $text);
        $xs = [$box[0], $box[2], $box[4], $box[6]];
        $ys = [$box[1], $box[3], $box[5], $box[7]];
        $minX = min($xs);
        $maxX = max($xs);
        $minY = min($ys);
        $maxY = max($ys);

        return [
            'minX' => $minX,
            'maxX' => $maxX,
            'minY' => $minY,
            'maxY' => $maxY,
            'width' => $maxX - $minX,
            'height' => $maxY - $minY,
        ];
    }

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
            // Use custom separator color if provided, else fallback to text color
            $sepCol = $textCol;
            if (!empty($skin['separatorColor']) && is_string($skin['separatorColor'])) {
                [$sr,$sg,$sb] = $this->hexToRgb($skin['separatorColor']);
                $sepCol = imagecolorallocate($im, $sr, $sg, $sb);
            }
            $unitCount = count($timeUnits);
            $centerY = $height / 2;

            $labelGap = max(2, (int) round($fontSize * 0.12));
            $sepText = $sepChar;
            $sepMetrics = $this->measureText($font, $fontSize, $sepText);
            $sepWidth = (int) $sepMetrics['width'];
            $sepGap = max(6, (int) round($fontSize * 0.25));

            $segments = [];
            $totalWidth = 0;
            $maxNumberHeight = 0;
            foreach ($timeUnits as $unit) {
                $numberText = sprintf('%02d', $unit['value']);
                $numberMetrics = $this->measureText($font, $fontSize, $numberText);
                $numberWidth = (int) $numberMetrics['width'];
                $numberHeight = (int) $numberMetrics['height'];
                $maxNumberHeight = max($maxNumberHeight, $numberHeight);

                $labelText = $unit['label'];
                $labelMetrics = $this->measureText($font, $labelSize, $labelText);
                $labelWidth = (int) $labelMetrics['width'];

                $segmentWidth = $numberWidth + $labelGap + $labelWidth;
                $segments[] = [
                    'numberText' => $numberText,
                    'labelText' => $labelText,
                    'numberMetrics' => $numberMetrics,
                    'labelMetrics' => $labelMetrics,
                    'segmentWidth' => $segmentWidth,
                ];

                $totalWidth += $segmentWidth;
            }
            if ($unitCount > 1) {
                $totalWidth += ($unitCount - 1) * (($sepGap * 2) + $sepWidth);
            }

            $startX = (int) round(($width - $totalWidth) / 2);
            $numberY = (int) round($centerY + ($maxNumberHeight / 2));

            $x = $startX;
            foreach ($segments as $i => $seg) {
                $numberX = (int) round($x - $seg['numberMetrics']['minX']);
                imagettftext($im, $fontSize, 0, $numberX, $numberY, $textCol, $font, $seg['numberText']);

                $labelLeft = $x + $seg['numberMetrics']['width'] + $labelGap;
                $labelX = (int) round($labelLeft - $seg['labelMetrics']['minX']);
                imagettftext($im, $labelSize, 0, $labelX, $numberY, $textCol, $font, $seg['labelText']);

                $x += $seg['segmentWidth'];
                if ($i < $unitCount - 1) {
                    $x += $sepGap;
                    $sepX = (int) round($x - $sepMetrics['minX']);
                    imagettftext($im, $fontSize, 0, $sepX, $numberY, $sepCol, $font, $sepText);
                    $x += $sepWidth + $sepGap;
                }
            }
        }

        return true;
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
