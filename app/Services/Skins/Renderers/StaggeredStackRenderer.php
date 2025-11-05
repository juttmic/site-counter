<?php

namespace App\Services\Skins\Renderers;

use App\Services\Skins\SkinRendererInterface;

class StaggeredStackRenderer implements SkinRendererInterface
{
    public function renderStyle($im, array $skin, int $width, int $height, int $bgCol, int $textCol, int $accentCol, int $secondsRemaining): void
    {
        // Vertical staggered cards: each tile is vertically offset to create a stepped stack
        imagefilledrectangle($im, 0, 0, $width, $height, $bgCol);
        $segments = 4;
        $gap = max(8, (int) round($width * 0.015));
        $unitWidth = (int) floor(($width - ($segments + 1) * $gap) / $segments);
        $tileH = (int) ($height * 0.65);
        $offsetStep = (int) round($tileH * 0.12);
        $shadow = imagecolorallocatealpha($im, 0, 0, 0, 100);
        $cardCol = $accentCol;

        for ($i = 0; $i < $segments; $i++) {
            $x = $gap + $i * ($unitWidth + $gap);
            $y = (int) (($height - $tileH) / 2) + ($i % 2 === 0 ? 0 : -$offsetStep);
            // shadow
            imagefilledrectangle($im, $x + 4, $y + 6, $x + $unitWidth + 4, $y + $tileH + 6, $shadow);
            // card with small rounding effect by overlapping circles
            imagefilledrectangle($im, $x, $y, $x + $unitWidth, $y + $tileH, $cardCol);
            imagefilledellipse($im, $x + 10, $y + 10, 20, 20, $cardCol);
            imagefilledellipse($im, $x + $unitWidth - 10, $y + 10, 20, 20, $cardCol);
            imagefilledellipse($im, $x + 10, $y + $tileH - 10, 20, 20, $cardCol);
            imagefilledellipse($im, $x + $unitWidth - 10, $y + $tileH - 10, 20, 20, $cardCol);
        }
    }

    public function renderTimeText($im, int $secondsRemaining, array $skin, int $width, int $height, int $textCol, int $accentCol): bool
    {
        $font = (string) ($skin['fontPath'] ?? '');
        $fontSize = (int) ($skin['fontSize'] ?? 30);
        $labelSize = max(10, (int) round($fontSize * 0.34));

        $d = intdiv($secondsRemaining, 86400);
        $h = intdiv($secondsRemaining % 86400, 3600);
        $m = intdiv($secondsRemaining % 3600, 60);
        $s = $secondsRemaining % 60;
        $units = [];
        if (($skin['showDays'] ?? true)) { $units[] = ['value'=>$d,'label'=>'DAYS']; }
        if (($skin['showHours'] ?? true)) { $units[] = ['value'=>$h,'label'=>'HOURS']; }
        if (($skin['showMinutes'] ?? true)) { $units[] = ['value'=>$m,'label'=>'MINUTES']; }
        if (($skin['showSeconds'] ?? true)) { $units[] = ['value'=>$s,'label'=>'SECONDS']; }
        if (empty($units)) { $units[] = ['value'=>$s,'label'=>'SECONDS']; }

        $segments = count($units);
        $gap = max(8, (int) round($width * 0.015));
        $unitWidth = (int) floor(($width - ($segments + 1) * $gap) / $segments);
        $tileH = (int) ($height * 0.65);
        $offsetStep = (int) round($tileH * 0.12);
        $baseY = (int) (($height - $tileH) / 2);

        if (is_file($font)) {
            for ($i = 0; $i < $segments; $i++) {
                $x = $gap + $i * ($unitWidth + $gap);
                $y = $baseY + ($i % 2 === 0 ? 0 : -$offsetStep);
                $cx = (int) ($x + $unitWidth / 2);
                $cy = (int) ($y + $tileH / 2);
                $num = sprintf('%02d', $units[$i]['value']);
                $nW = $this->w($num, $font, $fontSize);
                imagettftext($im, $fontSize, 0, (int)($cx - $nW/2), (int)($cy - 6), $textCol, $font, $num);
                $lab = $units[$i]['label'];
                $lW = $this->w($lab, $font, $labelSize);
                imagettftext($im, $labelSize, 0, (int)($cx - $lW/2), (int)($cy + (int) round($fontSize*0.9)), $textCol, $font, $lab);
            }
        } else {
            $built=5;$lbl=3;
            for ($i=0;$i<$segments;$i++) {
                $x = $gap + $i * ($unitWidth + $gap);
                $y = $baseY + ($i % 2 === 0 ? 0 : -$offsetStep);
                $cx=(int)($x + $unitWidth/2); $cy=(int)($y + $tileH/2);
                $num=sprintf('%02d',$units[$i]['value']); $nW=imagefontwidth($built)*strlen($num);
                imagestring($im,$built,(int)($cx-$nW/2),(int)($cy-8),$num,$textCol);
                $lab=$units[$i]['label']; $lW=imagefontwidth($lbl)*strlen($lab);
                imagestring($im,$lbl,(int)($cx-$lW/2),(int)($cy+10),$lab,$textCol);
            }
        }
        return true;
    }

    private function w(string $t, string $f, int $s): int
    {
        $b = imagettfbbox($s, 0, $f, $t) ?: [0,0,0,0,0,0,0,0];
        return (int) (max($b[0], $b[2], $b[4], $b[6]) - min($b[0], $b[2], $b[4], $b[6]));
    }
}

?>


