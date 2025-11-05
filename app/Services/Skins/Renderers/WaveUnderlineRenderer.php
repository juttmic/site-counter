<?php

namespace App\Services\Skins\Renderers;

use App\Services\Skins\SkinRendererInterface;

class WaveUnderlineRenderer implements SkinRendererInterface
{
    public function renderStyle($im, array $skin, int $width, int $height, int $bgCol, int $textCol, int $accentCol, int $secondsRemaining): void
    {
        imagefilledrectangle($im,0,0,$width,$height,$bgCol);
        $segments=4; $unitW=$width/$segments; $baseline=(int)($height*0.65);
        $amp=(int)max(4,(int)round($height*0.06)); $freq=12; // points
        for($i=0;$i<$segments;$i++){
            $start=(int)($unitW*$i + $unitW*0.15); $end=(int)($unitW*($i+1) - $unitW*0.15);
            $prevX=$start; $prevY=$baseline;
            for($x=$start;$x<=$end;$x++){
                $t=($x-$start)/max(1,($end-$start));
                $y=(int)($baseline + sin($t*M_PI*2)*$amp);
                imageline($im,$prevX,$prevY,$x,$y,$accentCol);
                $prevX=$x; $prevY=$y;
            }
        }
    }

    public function renderTimeText($im, int $secondsRemaining, array $skin, int $width, int $height, int $textCol, int $accentCol): bool
    {
        $font=(string)($skin['fontPath']??''); $fs=(int)($skin['fontSize']??34); $ls=max(10,(int)round($fs*0.3));
        $d=intdiv($secondsRemaining,86400); $h=intdiv($secondsRemaining%86400,3600); $m=intdiv($secondsRemaining%3600,60); $s=$secondsRemaining%60;
        $u=[]; if(($skin['showDays']??true))$u[]=['v'=>$d,'l'=>'DAYS']; if(($skin['showHours']??true))$u[]=['v'=>$h,'l'=>'HOURS']; if(($skin['showMinutes']??true))$u[]=['v'=>$m,'l'=>'MINUTES']; if(($skin['showSeconds']??true))$u[]=['v'=>$s,'l'=>'SECONDS']; if(!$u)$u[]=['v'=>$s,'l'=>'SECONDS'];
        $seg=count($u); $unitW=$width/$seg; $cy=(int)($height*0.55);
        if(is_file($font)){
            for($i=0;$i<$seg;$i++){ $cx=(int)($unitW*$i + $unitW/2); $num=sprintf('%02d',$u[$i]['v']); $nW=$this->w($num,$font,$fs); imagettftext($im,$fs,0,(int)($cx-$nW/2),$cy,$textCol,$font,$num); $lab=$u[$i]['l']; $lW=$this->w($lab,$font,$ls); imagettftext($im,$ls,0,(int)($cx-$lW/2),(int)($cy+(int)round($fs*0.9)),$textCol,$font,$lab); }
        } else { $b=5;$l=3; for($i=0;$i<$seg;$i++){ $cx=(int)($unitW*$i + $unitW/2); $num=sprintf('%02d',$u[$i]['v']); $nW=imagefontwidth($b)*strlen($num); imagestring($im,$b,(int)($cx-$nW/2),$cy-10,$num,$textCol); $lab=$u[$i]['l']; $lW=imagefontwidth($l)*strlen($lab); imagestring($im,$l,(int)($cx-$lW/2),(int)($cy+8),$lab,$textCol);} }
        return true;
    }
    private function w(string $t,string $f,int $s):int{ $b=imagettfbbox($s,0,$f,$t)?:[0,0,0,0,0,0,0,0]; return (int)(max($b[0],$b[2],$b[4],$b[6])-min($b[0],$b[2],$b[4],$b[6])); }
}

?>


