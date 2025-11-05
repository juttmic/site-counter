<?php

namespace App\Services\Skins\Renderers;

use App\Services\Skins\SkinRendererInterface;

class ArcGaugeRenderer implements SkinRendererInterface
{
    public function renderStyle($im, array $skin, int $width, int $height, int $bgCol, int $textCol, int $accentCol, int $secondsRemaining): void
    {
        // Semi-circle gauge for each unit (top arc)
        imagefilledrectangle($im, 0, 0, $width, $height, $bgCol);
        $segments = 4;
        $unitWidth = $width / $segments;
        $radius = (int) round(min($unitWidth / 2.2, $height * 0.8));
        $thickness = (int) max(6, (int) ($skin['arcThickness'] ?? 12));
        $base = imagecolorallocate($im, 220, 220, 220);
        for ($i=0; $i<$segments; $i++) {
            $cx = (int) ($unitWidth * $i + $unitWidth/2);
            $cy = (int) ($height * 0.7);
            $this->thickArc($im, $cx, $cy, $radius, 180, 360, $base, $thickness);
            $this->thickArc($im, $cx, $cy, $radius, 200, 340, $accentCol, $thickness);
        }
    }

    public function renderTimeText($im, int $secondsRemaining, array $skin, int $width, int $height, int $textCol, int $accentCol): bool
    {
        $font = (string) ($skin['fontPath'] ?? '');
        $fontSize = (int) ($skin['fontSize'] ?? 28);
        $labelSize = max(10, (int) round($fontSize * 0.3));
        $d = intdiv($secondsRemaining, 86400);
        $h = intdiv($secondsRemaining % 86400, 3600);
        $m = intdiv($secondsRemaining % 3600, 60);
        $s = $secondsRemaining % 60;
        $u = [];
        if (($skin['showDays'] ?? true)) $u[]=['value'=>$d,'label'=>'DAYS'];
        if (($skin['showHours'] ?? true)) $u[]=['value'=>$h,'label'=>'HOURS'];
        if (($skin['showMinutes'] ?? true)) $u[]=['value'=>$m,'label'=>'MINUTES'];
        if (($skin['showSeconds'] ?? true)) $u[]=['value'=>$s,'label'=>'SECONDS'];
        if (!$u) $u[]=['value'=>$s,'label'=>'SECONDS'];

        $segments = count($u);
        $unitWidth = $width / $segments;
        $baseline = (int) ($height * 0.7);

        if (is_file($font)) {
            for ($i=0;$i<$segments;$i++) {
                $cx = (int) ($unitWidth*$i + $unitWidth/2);
                $num=sprintf('%02d',$u[$i]['value']);
                $nW=$this->w($num,$font,$fontSize);
                imagettftext($im,$fontSize,0,(int)($cx-$nW/2),(int)($baseline-10),$textCol,$font,$num);
                $lab=$u[$i]['label'];
                $lW=$this->w($lab,$font,$labelSize);
                imagettftext($im,$labelSize,0,(int)($cx-$lW/2),(int)($baseline+ (int)round($fontSize*0.9)),$accentCol,$font,$lab);
            }
        } else {
            $b=5;$l=3;
            for ($i=0;$i<$segments;$i++) {
                $cx=(int)($unitWidth*$i + $unitWidth/2);
                $num=sprintf('%02d',$u[$i]['value']); $nW=imagefontwidth($b)*strlen($num);
                imagestring($im,$b,(int)($cx-$nW/2),(int)($baseline-14),$num,$textCol);
                $lab=$u[$i]['label']; $lW=imagefontwidth($l)*strlen($lab);
                imagestring($im,$l,(int)($cx-$lW/2),(int)($baseline+8),$lab,$accentCol);
            }
        }

        return true;
    }

    private function thickArc($im, int $cx, int $cy, int $r, int $start, int $end, int $color, int $thickness): void
    {
        for ($t=0;$t<$thickness;$t++) imagearc($im,$cx,$cy,($r*2)-$t,($r*2)-$t,$start,$end,$color);
    }
    private function w(string $t, string $f, int $s): int
    { $b=imagettfbbox($s,0,$f,$t)?:[0,0,0,0,0,0,0,0]; return (int)(max($b[0],$b[2],$b[4],$b[6]) - min($b[0],$b[2],$b[4],$b[6])); }
}

?>


