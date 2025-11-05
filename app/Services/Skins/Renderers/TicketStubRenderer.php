<?php

namespace App\Services\Skins\Renderers;

use App\Services\Skins\SkinRendererInterface;

class TicketStubRenderer implements SkinRendererInterface
{
    public function renderStyle($im, array $skin, int $width, int $height, int $bgCol, int $textCol, int $accentCol, int $secondsRemaining): void
    {
        // Ticket stubs with perforated dots between units
        imagefilledrectangle($im, 0, 0, $width, $height, $bgCol);
        $segments=4; $gap=max(8,(int)round($width*0.015)); $unitW=(int)floor(($width-($segments+1)*$gap)/$segments); $h=$height-2*$gap; $y=$gap;
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
        $font=(string)($skin['fontPath']??''); $fs=(int)($skin['fontSize']??28); $ls=max(10,(int)round($fs*0.34));
        $d=intdiv($secondsRemaining,86400); $h=intdiv($secondsRemaining%86400,3600); $m=intdiv($secondsRemaining%3600,60); $s=$secondsRemaining%60;
        $u=[]; if(($skin['showDays']??true))$u[]=['v'=>$d,'l'=>'DAYS']; if(($skin['showHours']??true))$u[]=['v'=>$h,'l'=>'HOURS']; if(($skin['showMinutes']??true))$u[]=['v'=>$m,'l'=>'MINUTES']; if(($skin['showSeconds']??true))$u[]=['v'=>$s,'l'=>'SECONDS']; if(!$u)$u[]=['v'=>$s,'l'=>'SECONDS'];
        $seg=count($u); $gap=max(8,(int)round($width*0.015)); $unitW=(int)floor(($width-($seg+1)*$gap)/$seg); $y=$gap; $h=$height-2*$gap;
        if(is_file($font)){
            for($i=0;$i<$seg;$i++){ $x=$gap+$i*($unitW+$gap); $cx=(int)($x+$unitW/2); $cy=(int)($y+$h/2);
                $num=sprintf('%02d',$u[$i]['v']); $nW=$this->w($num,$font,$fs); imagettftext($im,$fs,0,(int)($cx-$nW/2),(int)($cy-6),$textCol,$font,$num);
                $lab=$u[$i]['l']; $lW=$this->w($lab,$font,$ls); imagettftext($im,$ls,0,(int)($cx-$lW/2),(int)($cy+(int)round($fs*0.9)),$textCol,$font,$lab);
            }
        } else { $b=5;$l=3; for($i=0;$i<$seg;$i++){ $x=$gap+$i*($unitW+$gap); $cx=(int)($x+$unitW/2); $cy=(int)($y+$h/2); $num=sprintf('%02d',$u[$i]['v']); $nW=imagefontwidth($b)*strlen($num); imagestring($im,$b,(int)($cx-$nW/2),(int)($cy-8),$num,$textCol); $lab=$u[$i]['l']; $lW=imagefontwidth($l)*strlen($lab); imagestring($im,$l,(int)($cx-$lW/2),(int)($cy+10),$lab,$textCol);} }
        return true;
    }
    private function w(string $t,string $f,int $s):int{ $b=imagettfbbox($s,0,$f,$t)?:[0,0,0,0,0,0,0,0]; return (int)(max($b[0],$b[2],$b[4],$b[6])-min($b[0],$b[2],$b[4],$b[6])); }
}

?>


