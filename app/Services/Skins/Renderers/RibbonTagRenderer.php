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
            imagefilledpolygon($im, $points, $accentCol);
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

                // Compute combined block (number + gap + label) height and
                // center that entire block vertically within the ribbon tag.
                $numText = sprintf('%02d', $units[$i]['value']);
                $labText = $units[$i]['label'];

                $numBox = imagettfbbox($fontSize, 0, $font, $numText) ?: [0,0,0,0,0,0,0,0];
                $labBox = imagettfbbox($labelSize, 0, $font, $labText) ?: [0,0,0,0,0,0,0,0];

                $numMinY = min($numBox[1], $numBox[3], $numBox[5], $numBox[7]);
                $numMaxY = max($numBox[1], $numBox[3], $numBox[5], $numBox[7]);
                $numHeight = $numMaxY - $numMinY;
                $numCenterOffset = ($numBox[1] + $numBox[7]) / 2.0;

                $labMinY = min($labBox[1], $labBox[3], $labBox[5], $labBox[7]);
                $labMaxY = max($labBox[1], $labBox[3], $labBox[5], $labBox[7]);
                $labHeight = $labMaxY - $labMinY;
                $labCenterOffset = ($labBox[1] + $labBox[7]) / 2.0;

                // Slightly larger gap between digits and labels while keeping
                // the combined block vertically centered inside the ribbon tag.
                $gapBetween = max(4, (int) round($fontSize * 0.35));
                $blockHeight = $numHeight + $gapBetween + $labHeight;

                $blockTop = $y + (int) round(($tagH - $blockHeight) / 2);
                $numCenterY = $blockTop + $numHeight / 2.0;
                $labCenterY = $numCenterY + $numHeight / 2.0 + $gapBetween + $labHeight / 2.0;

                $numBaseline = (int) round($numCenterY - $numCenterOffset);
                $labBaseline = (int) round($labCenterY - $labCenterOffset);

                $numWidth = $this->textBoxWidth($numText, $font, $fontSize);
                imagettftext($im, $fontSize, 0, (int) ($cx - $numWidth / 2), $numBaseline, $textCol, $font, $numText);

                $labWidth = $this->textBoxWidth($labText, $font, $labelSize);
                imagettftext($im, $labelSize, 0, (int) ($cx - $labWidth / 2), $labBaseline, $textCol, $font, $labText);
            }
        } else {
            $built = 5;
            $lbl = 3;
            for ($i = 0; $i < $segments; $i++) {
                $x = $gap + $i * ($unitWidth + $gap);
                $cx = (int) ($x + ($unitWidth - 12) / 2);
                $cy = (int) ($y + $tagH / 2);

                $num = sprintf('%02d', $units[$i]['value']);
                $nW = imagefontwidth($built) * strlen($num);
                $numY = (int) round($cy - imagefontheight($built) * 0.8);
                imagestring($im, $built, (int) ($cx - $nW / 2), $numY, $num, $textCol);

                $lab = $units[$i]['label'];
                $lW = imagefontwidth($lbl) * strlen($lab);
                $labelY = (int) round($cy + imagefontheight($lbl) * 0.4);
                imagestring($im, $lbl, (int) ($cx - $lW / 2), $labelY, $lab, $textCol);
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


