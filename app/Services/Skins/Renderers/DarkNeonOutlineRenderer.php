<?php

namespace App\Services\Skins\Renderers;

use App\Services\Skins\SkinRendererInterface;

class DarkNeonOutlineRenderer implements SkinRendererInterface
{
    public function renderStyle($im, array $skin, int $width, int $height, int $bgCol, int $textCol, int $accentCol, int $secondsRemaining): void
    {
        // Dark background; glowing outlined boxes per unit
        imagefilledrectangle($im, 0, 0, $width, $height, $bgCol);
        $segments = 4;
        $gap = max(10, (int) round($width * 0.02));
        $unitWidth = (int) floor(($width - ($segments + 1) * $gap) / $segments);
        
        // Calculate reduced box height (40% of canvas height) and center it vertically
        $boxH = (int) round($height * 0.40);
        $y = (int) (($height - $boxH) / 2);

        $outer = $accentCol;
        $inner = imagecolorallocatealpha($im, 255, 255, 255, 90);

        for ($i = 0; $i < $segments; $i++) {
            $x = $gap + $i * ($unitWidth + $gap);
            imagerectangle($im, $x, $y, $x + $unitWidth, $y + $boxH, $outer);
            // inner glow (multiple rectangles)
            for ($g = 1; $g <= 4; $g++) {
                imagerectangle($im, $x + $g, $y + $g, $x + $unitWidth - $g, $y + $boxH - $g, $inner);
            }
        }
    }

    public function renderTimeText($im, int $secondsRemaining, array $skin, int $width, int $height, int $textCol, int $accentCol): bool
    {
        $font = (string) ($skin['fontPath'] ?? '');
        $fontSize = (int) ($skin['fontSize'] ?? 40);
        $labelSize = max(10, (int) round($fontSize * 0.28));

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
        $gap = max(10, (int) round($width * 0.02));
        $unitWidth = (int) floor(($width - ($segments + 1) * $gap) / $segments);
        // Center boxes within the reduced-height area
        $boxH = (int) round($height * 0.40);
        $y = (int) (($height - $boxH) / 2);

        if (is_file($font)) {
            for ($i=0;$i<$segments;$i++) {
                $x = $gap + $i * ($unitWidth + $gap);
                $cx = (int) ($x + $unitWidth / 2);
                $cy = (int) ($y + $boxH / 2);
                $num = sprintf('%02d', $units[$i]['value']);
                $nW = $this->textBoxWidth($num, $font, $fontSize);
                imagettftext($im, $fontSize, 0, (int)($cx - $nW/2), (int)($cy - 6), $textCol, $font, $num);
                $lab = $units[$i]['label'];
                $lW = $this->textBoxWidth($lab, $font, $labelSize);
                imagettftext($im, $labelSize, 0, (int)($cx - $lW/2), (int)($cy + (int) round($fontSize*0.85)), $textCol, $font, $lab);
            }
        } else {
            $built=5;$lbl=3;
            for ($i=0;$i<$segments;$i++) {
                $x = $gap + $i * ($unitWidth + $gap);
                $cx=(int)($x + $unitWidth/2);
                $cy=(int)($y + $boxH/2);
                $num=sprintf('%02d',$units[$i]['value']);
                $nW=imagefontwidth($built)*strlen($num);
                imagestring($im,$built,(int)($cx-$nW/2),(int)($cy-10),$num,$textCol);
                $lab=$units[$i]['label'];
                $lW=imagefontwidth($lbl)*strlen($lab);
                imagestring($im,$lbl,(int)($cx-$lW/2),(int)($cy+8),$lab,$textCol);
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


