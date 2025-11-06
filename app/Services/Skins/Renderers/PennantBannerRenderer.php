<?php

namespace App\Services\Skins\Renderers;

use App\Services\Skins\SkinRendererInterface;

class PennantBannerRenderer implements SkinRendererInterface
{
    public function renderStyle($im, array $skin, int $width, int $height, int $bgCol, int $textCol, int $accentCol, int $secondsRemaining): void
    {
        // Redesigned: clean banner bar instead of triangular pennants
        imagefilledrectangle($im, 0, 0, $width, $height, $bgCol);

        // Accent banner across the top third
        $barH = (int) max(24, round($height * 0.28));
        imagefilledrectangle($im, 0, 0, $width, $barH, $accentCol);

        // Optional thin separator line below the bar
        $sep = imagecolorallocate($im, 220, 220, 220);
        imageline($im, 0, $barH, $width, $barH, $sep);

        // Subtle side paddings (visual margins) using background color blocks
        $pad = (int) max(6, round($width * 0.02));
        imagefilledrectangle($im, 0, 0, $pad, $barH, $bgCol);
        imagefilledrectangle($im, $width - $pad, 0, $width, $barH, $bgCol);
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


