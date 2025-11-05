<?php

namespace App\Services\Skins\Renderers;

use App\Services\Skins\SkinRendererInterface;

class SlateRoundedBarRenderer implements SkinRendererInterface
{
    public function renderStyle($im, array $skin, int $width, int $height, int $bgCol, int $textCol, int $accentCol, int $secondsRemaining): void
    {
        // Single rounded bar across with vertical dividers
        imagefilledrectangle($im, 0, 0, $width, $height, $bgCol);
        $radius = (int) max(10, round($height / 2.8));
        $barCol = $accentCol;
        $this->fillRoundedRect($im, 6, 6, $width - 12, $height - 12, $radius, $barCol);

        // Vertical separators between segments
        $segments = 4;
        $unitWidth = $width / $segments;
        $sepCol = imagecolorallocatealpha($im, 255, 255, 255, 70);
        for ($i = 1; $i < $segments; $i++) {
            $x = (int) round($unitWidth * $i);
            imageline($im, $x, 10, $x, $height - 10, $sepCol);
        }
    }

    public function renderTimeText($im, int $secondsRemaining, array $skin, int $width, int $height, int $textCol, int $accentCol): bool
    {
        $font = (string) ($skin['fontPath'] ?? '');
        $fontSize = (int) ($skin['fontSize'] ?? 36);
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
            for ($i=0;$i<$segments;$i++) {
                $cx = (int) ($unitWidth * $i + $unitWidth / 2);
                $num = sprintf('%02d', $units[$i]['value']);
                $nW = $this->textBoxWidth($num, $font, $fontSize);
                imagettftext($im, $fontSize, 0, (int)($cx - $nW/2), (int)($centerY - 4), $textCol, $font, $num);
                $lab = $units[$i]['label'];
                $lW = $this->textBoxWidth($lab, $font, $labelSize);
                imagettftext($im, $labelSize, 0, (int)($cx - $lW/2), (int)($centerY + (int) round($fontSize*0.9)), $textCol, $font, $lab);
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

    private function fillRoundedRect($im, int $x, int $y, int $w, int $h, int $r, int $color): void
    {
        imagefilledrectangle($im, $x + $r, $y, $x + $w - $r, $y + $h, $color);
        imagefilledrectangle($im, $x, $y + $r, $x + $r, $y + $h - $r, $color);
        imagefilledrectangle($im, $x + $w - $r, $y + $r, $x + $w, $y + $h - $r, $color);
        imagefilledellipse($im, $x + $r, $y + $r, 2 * $r, 2 * $r, $color);
        imagefilledellipse($im, $x + $w - $r, $y + $r, 2 * $r, 2 * $r, $color);
        imagefilledellipse($im, $x + $r, $y + $h - $r, 2 * $r, 2 * $r, $color);
        imagefilledellipse($im, $x + $w - $r, $y + $h - $r, 2 * $r, 2 * $r, $color);
    }
}

?>


