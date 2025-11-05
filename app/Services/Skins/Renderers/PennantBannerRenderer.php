<?php

namespace App\Services\Skins\Renderers;

use App\Services\Skins\SkinRendererInterface;

class PennantBannerRenderer implements SkinRendererInterface
{
    public function renderStyle($im, array $skin, int $width, int $height, int $bgCol, int $textCol, int $accentCol, int $secondsRemaining): void
    {
        // A string line with triangular pennants per unit
        imagefilledrectangle($im,0,0,$width,$height,$bgCol);
        $segments=4; $unitW=$width/$segments; $mid=(int)($height*0.35);
        $lineCol=imagecolorallocate($im,120,120,120);
        imageline($im, (int)($unitW*0.1), $mid, (int)($width - $unitW*0.1), $mid, $lineCol);
        for($i=0;$i<$segments;$i++){
            $cx=(int)($unitW*$i + $unitW/2);
            $topY=$mid; $bottomY=(int)($mid + $height*0.25);
            $pts=[ $cx-18,$topY,  $cx+18,$topY,  $cx,$bottomY ];
            imagefilledpolygon($im,$pts,3,$accentCol);
        }
    }

    public function renderTimeText($im, int $secondsRemaining, array $skin, int $width, int $height, int $textCol, int $accentCol): bool
    {
        $font=(string)($skin['fontPath']??''); $fs=(int)($skin['fontSize']??28); $ls=max(10,(int)round($fs*0.3));
        $d=intdiv($secondsRemaining,86400); $h=intdiv($secondsRemaining%86400,3600); $m=intdiv($secondsRemaining%3600,60); $s=$secondsRemaining%60;
        $u=[]; if(($skin['showDays']??true))$u[]=['v'=>$d,'l'=>'DAYS']; if(($skin['showHours']??true))$u[]=['v'=>$h,'l'=>'HOURS']; if(($skin['showMinutes']??true))$u[]=['v'=>$m,'l'=>'MINUTES']; if(($skin['showSeconds']??true))$u[]=['v'=>$s,'l'=>'SECONDS']; if(!$u)$u[]=['v'=>$s,'l'=>'SECONDS'];
        $seg=count($u); $unitW=$width/$seg; $baseline=(int)($height*0.8);
        if(is_file($font)){
            for($i=0;$i<$seg;$i++){ $cx=(int)($unitW*$i + $unitW/2); $num=sprintf('%02d',$u[$i]['v']); $nW=$this->w($num,$font,$fs); imagettftext($im,$fs,0,(int)($cx-$nW/2),(int)($baseline-6),$textCol,$font,$num); $lab=$u[$i]['l']; $lW=$this->w($lab,$font,$ls); imagettftext($im,$ls,0,(int)($cx-$lW/2),(int)($baseline+(int)round($fs*0.8)),$textCol,$font,$lab); }
        } else { $b=5;$l=3; for($i=0;$i<$seg;$i++){ $cx=(int)($unitW*$i + $unitW/2); $num=sprintf('%02d',$u[$i]['v']); $nW=imagefontwidth($b)*strlen($num); imagestring($im,$b,(int)($cx-$nW/2),(int)($baseline-12),$num,$textCol); $lab=$u[$i]['l']; $lW=imagefontwidth($l)*strlen($lab); imagestring($im,$l,(int)($cx-$lW/2),(int)($baseline+6),$lab,$textCol);} }
        return true;
    }
    private function w(string $t,string $f,int $s):int{ $b=imagettfbbox($s,0,$f,$t)?:[0,0,0,0,0,0,0,0]; return (int)(max($b[0],$b[2],$b[4],$b[6])-min($b[0],$b[2],$b[4],$b[6])); }
}

?>


