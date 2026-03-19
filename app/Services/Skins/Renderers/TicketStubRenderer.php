<?php

namespace App\Services\Skins\Renderers;

use App\Services\Skins\SkinRendererInterface;

class TicketStubRenderer implements SkinRendererInterface
{
    public function renderStyle($im, array $skin, int $width, int $height, int $bgCol, int $textCol, int $accentCol, int $secondsRemaining): void
    {
        // Ticket stubs with perforated dots between units; respect visibility
        imagefilledrectangle($im, 0, 0, $width, $height, $bgCol);
        $showDays = ($skin['showDays'] ?? true) === true;
        $showHours = ($skin['showHours'] ?? true) === true;
        $showMinutes = ($skin['showMinutes'] ?? true) === true;
        $showSeconds = ($skin['showSeconds'] ?? true) === true;
        $segments = ($showDays ? 1 : 0) + ($showHours ? 1 : 0) + ($showMinutes ? 1 : 0) + ($showSeconds ? 1 : 0);
        if ($segments < 1) { $segments = 1; }
        $gap = max(8, (int)round($width*0.015));
        $unitW = (int)floor(($width-($segments+1)*$gap)/$segments);
        $h = $height-2*$gap;
        $y = $gap;
        $hole= imagecolorallocate($im, 255,255,255);
        for($i=0;$i<$segments;$i++){ $x=$gap+$i*($unitW+$gap); imagefilledrectangle($im,$x,$y,$x+$unitW,$y+$h,$accentCol);
            // semi-circular notches on left/right edges
            imagefilledellipse($im, $x, (int)($y+$h/4), 10, 10, $bgCol);
            imagefilledellipse($im, $x+$unitW, (int)($y+$h*3/4), 10, 10, $bgCol);
            // perforation dots on right edge
            for($d=6;$d<$h;$d+=14){ imagefilledellipse($im, $x+$unitW, $y+$d, 4, 4, $hole); }
        }
    }

    public function renderTimeText($im, int $secondsRemaining, array $skin, int $width, int $height, int $textCol, int $accentCol): bool
    {
        $font = (string) ($skin['fontPath'] ?? '');
        $fs = (int) ($skin['fontSize'] ?? 28);
        $ls = max(10, (int) round($fs * 0.34));

        $days = intdiv($secondsRemaining, 86400);
        $hours = intdiv($secondsRemaining % 86400, 3600);
        $minutes = intdiv($secondsRemaining % 3600, 60);
        $seconds = $secondsRemaining % 60;

        $units = [];
        if (($skin['showDays'] ?? true) === true) {
            $units[] = ['v' => $days, 'l' => 'DAYS'];
        }
        if (($skin['showHours'] ?? true) === true) {
            $units[] = ['v' => $hours, 'l' => 'HOURS'];
        }
        if (($skin['showMinutes'] ?? true) === true) {
            $units[] = ['v' => $minutes, 'l' => 'MINUTES'];
        }
        if (($skin['showSeconds'] ?? true) === true) {
            $units[] = ['v' => $seconds, 'l' => 'SECONDS'];
        }
        if (empty($units)) {
            $units[] = ['v' => $seconds, 'l' => 'SECONDS'];
        }

        $segments = count($units);
        $gap = max(8, (int) round($width * 0.015));
        $unitW = (int) floor(($width - ($segments + 1) * $gap) / $segments);
        $y = $gap;
        $h = $height - 2 * $gap;

        // Shared vertical gap factor between number and label (slightly increased)
        $gapFactor = 0.62;
        if (is_file($font)) {
            for ($i = 0; $i < $segments; $i++) {
                $x = $gap + $i * ($unitW + $gap);
                $cx = (int) ($x + $unitW / 2);

                $numText = sprintf('%02d', $units[$i]['v']);
                $labText = $units[$i]['l'];

                // Measure number and label
                $numBox = imagettfbbox($fs, 0, $font, $numText) ?: [0,0,0,0,0,0,0,0];
                $labBox = imagettfbbox($ls, 0, $font, $labText) ?: [0,0,0,0,0,0,0,0];

                $numMinY = min($numBox[1], $numBox[3], $numBox[5], $numBox[7]);
                $numMaxY = max($numBox[1], $numBox[3], $numBox[5], $numBox[7]);
                $numHeight = $numMaxY - $numMinY;
                $numCenterOffset = ($numBox[1] + $numBox[7]) / 2.0;

                $labMinY = min($labBox[1], $labBox[3], $labBox[5], $labBox[7]);
                $labMaxY = max($labBox[1], $labBox[3], $labBox[5], $labBox[7]);
                $labHeight = $labMaxY - $labMinY;
                $labCenterOffset = ($labBox[1] + $labBox[7]) / 2.0;

                // Larger gap between number and label (3x original)
                $gapBetween = max(3, (int) round($fs * $gapFactor));
                $blockHeight = $numHeight + $gapBetween + $labHeight;

                // Center combined number+label block within the ticket stub height
                $blockTop = $y + (int) round(($h - $blockHeight) / 2);
                $numCenterY = $blockTop + $numHeight / 2.0;
                $labCenterY = $numCenterY + $numHeight / 2.0 + $gapBetween + $labHeight / 2.0;

                $numBaseline = (int) round($numCenterY - $numCenterOffset);
                $labBaseline = (int) round($labCenterY - $labCenterOffset);

                $nW = $this->w($numText, $font, $fs);
                imagettftext($im, $fs, 0, (int) ($cx - $nW / 2), $numBaseline, $textCol, $font, $numText);

                $lW = $this->w($labText, $font, $ls);
                imagettftext($im, $ls, 0, (int) ($cx - $lW / 2), $labBaseline, $textCol, $font, $labText);
            }
        } else {
            $numberFontId = 5;
            $labelFontId = 3;
            for ($i = 0; $i < $segments; $i++) {
                $x = $gap + $i * ($unitW + $gap);
                $cx = (int) ($x + $unitW / 2);
                $cy = (int) ($y + $h / 2);

                $num = sprintf('%02d', $units[$i]['v']);
                $numH = imagefontheight($numberFontId);
                $lab = $units[$i]['l'];
                $labH = imagefontheight($labelFontId);
                $gapBetween = max(3, (int) round($fs * $gapFactor));
                $blockHeight = $numH + $gapBetween + $labH;
                $blockTop = $cy - (int) round($blockHeight / 2);

                $numY = $blockTop;
                $labY = $blockTop + $numH + $gapBetween;

                $nW = imagefontwidth($numberFontId) * strlen($num);
                imagestring($im, $numberFontId, (int) ($cx - $nW / 2), $numY, $num, $textCol);

                $lW = imagefontwidth($labelFontId) * strlen($lab);
                imagestring($im, $labelFontId, (int) ($cx - $lW / 2), $labY, $lab, $textCol);
            }
        }
        return true;
    }
    private function w(string $t,string $f,int $s):int{ $b=imagettfbbox($s,0,$f,$t)?:[0,0,0,0,0,0,0,0]; return (int)(max($b[0],$b[2],$b[4],$b[6])-min($b[0],$b[2],$b[4],$b[6])); }
}

?>


