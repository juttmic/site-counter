<?php

namespace App\Services\Skins\Renderers;

use App\Services\Skins\SkinRendererInterface;

class GradientBarStacksRenderer implements SkinRendererInterface
{
    public function renderStyle($im, array $skin, int $width, int $height, int $bgCol, int $textCol, int $accentCol, int $secondsRemaining): void
    {
        // Vertical gradient bars with rounded caps behind text
        imagefilledrectangle($im, 0, 0, $width, $height, $bgCol);
        $segments = 4;
        $unitWidth = $width / $segments;
        $maxBarH = (int) ($height * 0.8);
        $baseY = (int) ($height * 0.9);
        [$ar,$ag,$ab] = [($accentCol >> 16) & 255, ($accentCol >> 8) & 255, $accentCol & 255];

        for ($i = 0; $i < $segments; $i++) {
            $cx = (int) ($unitWidth * $i + $unitWidth / 2);
            $barW = (int) max(24, $unitWidth * 0.35);
            $h = (int) ($maxBarH * (0.4 + 0.15 * $i)); // varied heights, decorative
            $top = $baseY - $h;
            // gradient from lighter to accent
            for ($y = 0; $y < $h; $y++) {
                $ratio = $y / max(1, $h);
                $r = (int) min(255, $ar + (255 - $ar) * (0.25 - 0.25 * $ratio));
                $g = (int) min(255, $ag + (255 - $ag) * (0.25 - 0.25 * $ratio));
                $b = (int) min(255, $ab + (255 - $ab) * (0.25 - 0.25 * $ratio));
                $col = imagecolorallocate($im, $r, $g, $b);
                imageline($im, (int)($cx - $barW/2), $top + $y, (int)($cx + $barW/2), $top + $y, $col);
            }
            // Rounded cap
            imagefilledellipse($im, $cx, $top, $barW, (int) max(10, $barW * 0.5), $accentCol);
        }
    }

    public function renderTimeText($im, int $secondsRemaining, array $skin, int $width, int $height, int $textCol, int $accentCol): bool
    {
        $font = (string) ($skin['fontPath'] ?? '');
        $fontSize = (int) ($skin['fontSize'] ?? 28);
        $labelSize = max(10, (int) round($fontSize * 0.32));
        $days = intdiv($secondsRemaining, 86400);
        $hours = intdiv($secondsRemaining % 86400, 3600);
        $minutes = intdiv($secondsRemaining % 3600, 60);
        $seconds = $secondsRemaining % 60;
        $units = [];
        if (($skin['showDays'] ?? true) === true) { $units[] = ['value'=>$days,'label'=>'DAYS']; }
        if (($skin['showHours'] ?? true) === true) { $units[] = ['value'=>$hours,'label'=>'HOURS']; }
        if (($skin['showMinutes'] ?? true) === true) { $units[] = ['value'=>$minutes,'label'=>'MINUTES']; }
        if (($skin['showSeconds'] ?? true) === true) { $units[] = ['value'=>$seconds,'label'=>'SECONDS']; }
        if (empty($units)) { $units[] = ['value'=>$seconds,'label'=>'SECONDS']; }

        $segments = count($units);
        $unitWidth = $width / $segments;
        $centerY = (int) ($height / 2);

        if (is_file($font)) {
            for ($i = 0; $i < $segments; $i++) {
                $cx = (int) ($unitWidth * $i + $unitWidth / 2);
                $num = sprintf('%02d', $units[$i]['value']);
                $nW = $this->textBoxWidth($num, $font, $fontSize);
                imagettftext($im, $fontSize, 0, (int)($cx - $nW / 2), (int)($centerY - 4), $textCol, $font, $num);
                $lab = $units[$i]['label'];
                $lW = $this->textBoxWidth($lab, $font, $labelSize);
                imagettftext($im, $labelSize, 0, (int)($cx - $lW / 2), (int)($centerY + (int) round($fontSize * 0.9)), $textCol, $font, $lab);
            }
        } else {
            $built=5;$lbl=3;
            for ($i=0;$i<$segments;$i++) {
                $cx=(int)($unitWidth*$i + $unitWidth/2);
                $num=sprintf('%02d',$units[$i]['value']);
                $nW=imagefontwidth($built)*strlen($num);
                imagestring($im,$built,(int)($cx-$nW/2),(int)($centerY-8),$num,$textCol);
                $lab=$units[$i]['label'];
                $lW=imagefontwidth($lbl)*strlen($lab);
                imagestring($im,$lbl,(int)($cx-$lW/2),(int)($centerY+10),$lab,$textCol);
            }
        }

        return true;
    }

    private function textBoxWidth(string $text, string $fontPath, int $fontSize): int
    {
        $box = imagettfbbox($fontSize, 0, $fontPath, $text) ?: [0,0,0,0,0,0,0,0];
        $minX = min($box[0], $box[2], $box[4], $box[6]);
        $maxX = max($box[0], $box[2], $box[4], $box[6]);
        return (int) ($maxX - $minX);
    }
}

?>


