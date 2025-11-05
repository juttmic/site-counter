<?php

namespace App\Services\Skins\Renderers;

use App\Services\Skins\SkinRendererInterface;

class TapeReelRenderer implements SkinRendererInterface
{
    public function renderStyle($im, array $skin, int $width, int $height, int $bgCol, int $textCol, int $accentCol, int $secondsRemaining): void
    {
        // Cassette tape-like reels behind each unit
        imagefilledrectangle($im, 0, 0, $width, $height, $bgCol);
        $segments=4; $unitW=$width/$segments; $cy=(int)($height/2); $r=(int)round(min($unitW/3,$height/3));
        $hub = imagecolorallocate($im, 230,230,230);
        for($i=0;$i<$segments;$i++){ $cx=(int)($unitW*$i + $unitW/2);
            // tape body
            imagefilledrectangle($im,(int)($cx-$r*1.4),(int)($cy-$r*1.2),(int)($cx+$r*1.4),(int)($cy+$r*1.2),$accentCol);
            // reels
            imagefilledellipse($im,$cx,(int)($cy-$r*0.5),(int)($r*1.1),(int)($r*1.1),$hub);
            imagefilledellipse($im,$cx,(int)($cy+$r*0.5),(int)($r*1.1),(int)($r*1.1),$hub);
        }
    }

    public function renderTimeText($im, int $secondsRemaining, array $skin, int $width, int $height, int $textCol, int $accentCol): bool
    {
        $font=(string)($skin['fontPath']??''); $fs=(int)($skin['fontSize']??26); $ls=max(10,(int)round($fs*0.34));
        $d=intdiv($secondsRemaining,86400); $h=intdiv($secondsRemaining%86400,3600); $m=intdiv($secondsRemaining%3600,60); $s=$secondsRemaining%60;
        $u=[]; if(($skin['showDays']??true))$u[]=['v'=>$d,'l'=>'DAYS']; if(($skin['showHours']??true))$u[]=['v'=>$h,'l'=>'HOURS']; if(($skin['showMinutes']??true))$u[]=['v'=>$m,'l'=>'MINUTES']; if(($skin['showSeconds']??true))$u[]=['v'=>$s,'l'=>'SECONDS']; if(!$u)$u[]=['v'=>$s,'l'=>'SECONDS'];
        $seg=count($u); $unitW=$width/$seg; $cy=(int)($height/2);
        if(is_file($font)){
            for($i=0;$i<$seg;$i++){ $cx=(int)($unitW*$i + $unitW/2);
                $num=sprintf('%02d',$u[$i]['v']); $nW=$this->w($num,$font,$fs); imagettftext($im,$fs,0,(int)($cx-$nW/2),(int)($cy-2),$textCol,$font,$num);
                $lab=$u[$i]['l']; $lW=$this->w($lab,$font,$ls); imagettftext($im,$ls,0,(int)($cx-$lW/2),(int)($cy+(int)round($fs*0.9)),$textCol,$font,$lab);
            }
        } else { $b=5;$l=3; for($i=0;$i<$seg;$i++){ $cx=(int)($unitW*$i + $unitW/2); $num=sprintf('%02d',$u[$i]['v']); $nW=imagefontwidth($b)*strlen($num); imagestring($im,$b,(int)($cx-$nW/2),(int)($cy-8),$num,$textCol); $lab=$u[$i]['l']; $lW=imagefontwidth($l)*strlen($lab); imagestring($im,$l,(int)($cx-$lW/2),(int)($cy+10),$lab,$textCol);} }
        return true;
    }
    private function w(string $t,string $f,int $s):int{ $b=imagettfbbox($s,0,$f,$t)?:[0,0,0,0,0,0,0,0]; return (int)(max($b[0],$b[2],$b[4],$b[6])-min($b[0],$b[2],$b[4],$b[6])); }
}

?>


