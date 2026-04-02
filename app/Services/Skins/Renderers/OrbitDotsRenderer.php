<?php

namespace App\Services\Skins\Renderers;

use App\Services\Skins\SkinRendererInterface;

class OrbitDotsRenderer implements SkinRendererInterface
{
    private const ORBIT_DIVISOR_BASE = 2.6;

    private const REFERENCE_DIGIT_FONT_SIZE = 45.0;

    /**
     * Orbit radius grows with digit font size (smaller divisor for larger fonts),
     * then is clamped so all dotted rings still fit the canvas width.
     *
     * @return array{radius: int, gap: int, centerY: int, dotDiameter: int}
     */
    private function computeOrbitLayout(array $skin, int $width, int $height, int $segmentCount): array
    {
        $segments = max(1, $segmentCount);
        $digitFs = max(8, (int) ($skin['fontSize'] ?? 45));
        $orbitDivisor = self::ORBIT_DIVISOR_BASE * (self::REFERENCE_DIGIT_FONT_SIZE / $digitFs);
        $orbitDivisor = max(1.35, min(3.2, $orbitDivisor));

        $centerY = (int) ($height / 2);
        $maxRadiusFromHeight = (int) round($height / $orbitDivisor);
        $maxRadiusFromWidth = (int) floor(($width / $segments) / $orbitDivisor);
        $radius = (int) max(6, min($maxRadiusFromHeight, $maxRadiusFromWidth));

        $minGap = max(8, (int) round($width * 0.015));
        $packMaxR = (int) floor(($width - ($segments + 1) * $minGap) / (2 * $segments));
        $radius = min($radius, max(6, $packMaxR));

        $diameter = $radius * 2;
        $totalCircleWidth = $segments * $diameter;
        $availableWidth = max(0, $width - $totalCircleWidth);
        $gap = (int) floor($availableWidth / max(1, $segments + 1));

        $dotDiameter = (int) max(2, round(3 * sqrt($digitFs / self::REFERENCE_DIGIT_FONT_SIZE)));

        return [
            'radius' => $radius,
            'gap' => $gap,
            'centerY' => $centerY,
            'dotDiameter' => $dotDiameter,
        ];
    }

    public function renderStyle($im, array $skin, int $width, int $height, int $bgCol, int $textCol, int $accentCol, int $secondsRemaining): void
    {
        // Dotted orbits around each unit; respect visibility (days and seconds only toggled)
        imagefilledrectangle($im,0,0,$width,$height,$bgCol);
        $showDays = ($skin['showDays'] ?? true) === true;
        $showHours = ($skin['showHours'] ?? true) === true;
        $showMinutes = ($skin['showMinutes'] ?? true) === true;
        $showSeconds = ($skin['showSeconds'] ?? true) === true;
        $segments = ($showDays ? 1 : 0)
            + ($showHours ? 1 : 0)
            + ($showMinutes ? 1 : 0)
            + ($showSeconds ? 1 : 0);
        if ($segments < 1) {
            $segments = 1;
        }

        $layout = $this->computeOrbitLayout($skin, $width, $height, $segments);
        $radius = $layout['radius'];
        $gap = $layout['gap'];
        $centerY = $layout['centerY'];
        $dotD = $layout['dotDiameter'];

        $diameter = $radius * 2;

        $dotColor = $accentCol;
        $dotCount = 24;

        for ($i = 0; $i < $segments; $i++) {
            $centerX = $gap + $radius + $i * ($diameter + $gap);
            for ($k = 0; $k < $dotCount; $k++) {
                $angle = ($k / $dotCount) * 2 * M_PI;
                $x = (int) round($centerX + cos($angle) * $radius);
                $y = (int) round($centerY + sin($angle) * $radius);
                imagefilledellipse($im, $x, $y, $dotD, $dotD, $dotColor);
            }
        }
    }

    public function renderTimeText($im, int $secondsRemaining, array $skin, int $width, int $height, int $textCol, int $accentCol): bool
    {
        $font = (string) ($skin['fontPath'] ?? '');
        $fs = (int) ($skin['fontSize'] ?? 30);
        $labelFsRaw = $skin['labelFontSize'] ?? null;
        $ls = ($labelFsRaw !== null && $labelFsRaw !== '')
            ? max(8, (int) $labelFsRaw)
            : max(10, (int) round($fs * 0.3));

        $days = intdiv($secondsRemaining, 86400);
        $hours = intdiv($secondsRemaining % 86400, 3600);
        $minutes = intdiv($secondsRemaining % 3600, 60);
        $seconds = $secondsRemaining % 60;

        $units = [];
        if (($skin['showDays'] ?? true)) {
            $units[] = ['v' => $days, 'l' => 'DAYS'];
        }
        if (($skin['showHours'] ?? true)) {
            $units[] = ['v' => $hours, 'l' => 'HOURS'];
        }
        if (($skin['showMinutes'] ?? true)) {
            $units[] = ['v' => $minutes, 'l' => 'MINUTES'];
        }
        if (($skin['showSeconds'] ?? true)) {
            $units[] = ['v' => $seconds, 'l' => 'SECONDS'];
        }
        if (!$units) {
            $units[] = ['v' => $seconds, 'l' => 'SECONDS'];
        }

        $segmentCount = count($units);
        $layout = $this->computeOrbitLayout($skin, $width, $height, $segmentCount);
        $radius = $layout['radius'];
        $gap = $layout['gap'];
        $centerY = $layout['centerY'];
        $diameter = $radius * 2;

        if (is_file($font)) {
            // Maintain the original vertical gap between number and label,
            // only adjust both together so the combined block is vertically centered.
            $originalGap = (int) round($fs * 0.9);

            for ($i = 0; $i < $segmentCount; $i++) {
                $cx = $gap + $radius + $i * ($diameter + $gap);

                $num = sprintf('%02d', $units[$i]['v']);
                $lab = $units[$i]['l'];

                $numBox = imagettfbbox($fs, 0, $font, $num) ?: [0, 0, 0, 0, 0, 0, 0, 0];
                $labBox = imagettfbbox($ls, 0, $font, $lab) ?: [0, 0, 0, 0, 0, 0, 0, 0];

                $numMinY = min($numBox[1], $numBox[3], $numBox[5], $numBox[7]);
                $numMaxY = max($numBox[1], $numBox[3], $numBox[5], $numBox[7]);
                $numHeight = $numMaxY - $numMinY;
                $numCenterOffset = ($numBox[1] + $numBox[7]) / 2.0;

                $labMinY = min($labBox[1], $labBox[3], $labBox[5], $labBox[7]);
                $labMaxY = max($labBox[1], $labBox[3], $labBox[5], $labBox[7]);
                $labHeight = $labMaxY - $labMinY;
                $labCenterOffset = ($labBox[1] + $labBox[7]) / 2.0;

                // Keep the vertical distance between number and label roughly the same as before.
                $gapBetween = max(2, $originalGap);
                $blockHeight = $numHeight + $gapBetween + $labHeight;

                $blockTop = $centerY - (int) round($blockHeight / 2);
                $numCenterY = $blockTop + $numHeight / 2.0;
                $labCenterY = $numCenterY + $numHeight / 2.0 + $gapBetween + $labHeight / 2.0;

                $numBaseline = (int) round($numCenterY - $numCenterOffset);
                $labBaseline = (int) round($labCenterY - $labCenterOffset);

                $nW = $this->w($num, $font, $fs);
                imagettftext($im, $fs, 0, (int) ($cx - $nW / 2), $numBaseline, $textCol, $font, $num);

                $lW = $this->w($lab, $font, $ls);
                imagettftext($im, $ls, 0, (int) ($cx - $lW / 2), $labBaseline, $textCol, $font, $lab);
            }
        } else {
            $b = 5;
            $l = 3;
            for ($i = 0; $i < $segmentCount; $i++) {
                $cx = $gap + $radius + $i * ($diameter + $gap);

                $num = sprintf('%02d', $units[$i]['v']);
                $lab = $units[$i]['l'];

                $numH = imagefontheight($b);
                $labH = imagefontheight($l);
                $gapBetween = 10; // keep original bitmap gap
                $blockHeight = $numH + $gapBetween + $labH;
                $blockTop = $centerY - (int) round($blockHeight / 2);

                $numY = $blockTop;
                $labY = $blockTop + $numH + $gapBetween;

                $nW = imagefontwidth($b) * strlen($num);
                imagestring($im, $b, (int) ($cx - $nW / 2), $numY, $num, $textCol);

                $lW = imagefontwidth($l) * strlen($lab);
                imagestring($im, $l, (int) ($cx - $lW / 2), $labY, $lab, $textCol);
            }
        }
        return true;
    }
    private function w(string $t,string $f,int $s):int{ $b=imagettfbbox($s,0,$f,$t)?:[0,0,0,0,0,0,0,0]; return (int)(max($b[0],$b[2],$b[4],$b[6])-min($b[0],$b[2],$b[4],$b[6])); }
}

?>


