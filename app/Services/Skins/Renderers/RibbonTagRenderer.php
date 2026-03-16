<?php

namespace App\Services\Skins\Renderers;

use App\Services\Skins\SkinRendererInterface;

class RibbonTagRenderer implements SkinRendererInterface
{
    public function renderStyle($im, array $skin, int $width, int $height, int $bgCol, int $textCol, int $accentCol, int $secondsRemaining): void
    {
        // Ribbon tags: each unit on a label with a right notch; respect visibility
        imagefilledrectangle($im, 0, 0, $width, $height, $bgCol);
        $showDays = ($skin['showDays'] ?? true) === true;
        $showHours = ($skin['showHours'] ?? true) === true;
        $showMinutes = ($skin['showMinutes'] ?? true) === true;
        $showSeconds = ($skin['showSeconds'] ?? true) === true;
        $segments = ($showDays ? 1 : 0) + ($showHours ? 1 : 0) + ($showMinutes ? 1 : 0) + ($showSeconds ? 1 : 0);
        if ($segments < 1) { $segments = 1; }
        $gap = max(8, (int) round($width * 0.015));
        $unitWidth = (int) floor(($width - ($segments + 1) * $gap) / $segments);
        $tagH = (int) ($height * 0.7);
        $y = (int) (($height - $tagH) / 2);

        for ($i = 0; $i < $segments; $i++) {
            $x = $gap + $i * ($unitWidth + $gap);
            // main tag body (5-corner ribbon shape only; no rectangular background)
            imagefilledrectangle($im, $x, $y, $x + $unitWidth - 12, $y + $tagH, $accentCol);
            // notch triangle
            $points = [
                $x + $unitWidth - 12, $y,
                $x + $unitWidth, $y + (int)($tagH/2),
                $x + $unitWidth - 12, $y + $tagH
            ];
            imagefilledpolygon($im, $points, 3, $accentCol);
        }
    }

    public function renderTimeText($im, int $secondsRemaining, array $skin, int $width, int $height, int $textCol, int $accentCol): bool
    {
        $font = (string) ($skin['fontPath'] ?? '');
        $fontSize = (int) ($skin['fontSize'] ?? 26);
        $labelSize = max(10, (int) round($fontSize * 0.34));

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
        $gap = max(8, (int) round($width * 0.015));
        $unitWidth = (int) floor(($width - ($segments + 1) * $gap) / $segments);
        $tagH = (int) ($height * 0.7);
        $y = (int) (($height - $tagH) / 2);

        if (is_file($font)) {
            for ($i = 0; $i < $segments; $i++) {
                $x = $gap + $i * ($unitWidth + $gap);
                $cx = (int) ($x + ($unitWidth - 12) / 2);
                $cy = (int) ($y + $tagH / 2);
                $num = sprintf('%02d', $units[$i]['value']);
                $nW = $this->textBoxWidth($num, $font, $fontSize);
                imagettftext($im, $fontSize, 0, (int)($cx - $nW / 2), (int)($cy - 4), $textCol, $font, $num);
                $lab = $units[$i]['label'];
                $lW = $this->textBoxWidth($lab, $font, $labelSize);
                imagettftext($im, $labelSize, 0, (int)($cx - $lW / 2), (int)($cy + (int) round($fontSize * 0.9)), $textCol, $font, $lab);
            }
        } else {
            $built=5;$lbl=3;
            for ($i=0;$i<$segments;$i++) {
                $x = $gap + $i * ($unitWidth + $gap);
                $cx=(int)($x + ($unitWidth-12)/2);
                $cy=(int)($y + $tagH/2);
                $num=sprintf('%02d',$units[$i]['value']);
                $nW=imagefontwidth($built)*strlen($num);
                imagestring($im,$built,(int)($cx-$nW/2),(int)($cy-8),$num,$textCol);
                $lab=$units[$i]['label'];
                $lW=imagefontwidth($lbl)*strlen($lab);
                imagestring($im,$lbl,(int)($cx-$lW/2),(int)($cy+10),$lab,$textCol);
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


