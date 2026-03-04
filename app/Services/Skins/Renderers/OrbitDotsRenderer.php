<?php

namespace App\Services\Skins\Renderers;

use App\Services\Skins\SkinRendererInterface;

class OrbitDotsRenderer implements SkinRendererInterface
{
    public function renderStyle($im, array $skin, int $width, int $height, int $bgCol, int $textCol, int $accentCol, int $secondsRemaining): void
    {
        // Dotted orbits around each unit; respect visibility (days and seconds only toggled)
        imagefilledrectangle($im,0,0,$width,$height,$bgCol);
        $showDays = ($skin['showDays'] ?? true) === true;
        $showHours = ($skin['showHours'] ?? true) === true;
        $showMinutes = ($skin['showMinutes'] ?? true) === true;
        $showSeconds = ($skin['showSeconds'] ?? true) === true;
        $segments = ($showDays ? 1 : 0) + ($showHours ? 1 : 0) + ($showMinutes ? 1 : 0) + ($showSeconds ? 1 : 0);
        if ($segments < 1) {
            $segments = 1;
        }
        $unitW = $width / $segments;
        $cy = (int)($height/2);
        $r = (int)round(min($unitW/2.6,$height/2.6));
        $dot = $accentCol; $dots=24;
        for($i=0;$i<$segments;$i++){
            $cx=(int)($unitW*$i + $unitW/2);
            for($k=0;$k<$dots;$k++){
                $ang=($k/$dots)*2*M_PI; $x=(int)round($cx + cos($ang)*$r); $y=(int)round($cy + sin($ang)*$r);
                imagefilledellipse($im,$x,$y,3,3,$dot);
            }
        }
    }

    public function renderTimeText($im, int $secondsRemaining, array $skin, int $width, int $height, int $textCol, int $accentCol): bool
    {
        $font=(string)($skin['fontPath']??''); $fs=(int)($skin['fontSize']??30); $ls=max(10,(int)round($fs*0.3));
        $d=intdiv($secondsRemaining,86400); $h=intdiv($secondsRemaining%86400,3600); $m=intdiv($secondsRemaining%3600,60); $s=$secondsRemaining%60;
        $u=[]; if(($skin['showDays']??true))$u[]=['v'=>$d,'l'=>'DAYS']; if(($skin['showHours']??true))$u[]=['v'=>$h,'l'=>'HOURS']; if(($skin['showMinutes']??true))$u[]=['v'=>$m,'l'=>'MINUTES']; if(($skin['showSeconds']??true))$u[]=['v'=>$s,'l'=>'SECONDS']; if(!$u)$u[]=['v'=>$s,'l'=>'SECONDS'];
        $seg=count($u); $unitW=$width/$seg; $cy=(int)($height/2);
        if(is_file($font)){
            for($i=0;$i<$seg;$i++){ $cx=(int)($unitW*$i + $unitW/2); $num=sprintf('%02d',$u[$i]['v']); $nW=$this->w($num,$font,$fs); imagettftext($im,$fs,0,(int)($cx-$nW/2),(int)($cy-4),$textCol,$font,$num); $lab=$u[$i]['l']; $lW=$this->w($lab,$font,$ls); imagettftext($im,$ls,0,(int)($cx-$lW/2),(int)($cy+(int)round($fs*0.9)),$textCol,$font,$lab); }
        } else { $b=5;$l=3; for($i=0;$i<$seg;$i++){ $cx=(int)($unitW*$i + $unitW/2); $num=sprintf('%02d',$u[$i]['v']); $nW=imagefontwidth($b)*strlen($num); imagestring($im,$b,(int)($cx-$nW/2),(int)($cy-8),$num,$textCol); $lab=$u[$i]['l']; $lW=imagefontwidth($l)*strlen($lab); imagestring($im,$l,(int)($cx-$lW/2),(int)($cy+10),$lab,$textCol);} }
        return true;
    }
    private function w(string $t,string $f,int $s):int{ $b=imagettfbbox($s,0,$f,$t)?:[0,0,0,0,0,0,0,0]; return (int)(max($b[0],$b[2],$b[4],$b[6])-min($b[0],$b[2],$b[4],$b[6])); }
}

?>


