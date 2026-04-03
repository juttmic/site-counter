<?php

namespace App\Services\Skins\Renderers;

use App\Services\Skins\SkinRendererInterface;

class SlateRoundedBarRenderer implements SkinRendererInterface
{
    /** Share of canvas height for the ribbon; bar is vertically centered. */
    private const BAR_HEIGHT_RATIO = 0.78;

    public function renderStyle($im, array $skin, int $width, int $height, int $bgCol, int $textCol, int $accentCol, int $secondsRemaining): void
    {
        // Background already filled by caller; draw just the rounded bar with dividers
        imagefilledrectangle($im, 0, 0, $width, $height, $bgCol);

        $barHeight = (int) round($height * self::BAR_HEIGHT_RATIO);
        $barY = (int) (($height - $barHeight) / 2);
        $barPaddingX = 6;
        $barWidth = $width - (2 * $barPaddingX);
        
        // Use stronger rounding for the bar corners for a pill-like look
        $radius = (int) max(12, round($barHeight / 2.0));
        $barCol = $accentCol;
        $this->fillRoundedRect($im, $barPaddingX, $barY, $barWidth, $barHeight, $radius, $barCol);

        // Vertical separators between segments (within the bar); respect visibility
        $showDays = ($skin['showDays'] ?? true) === true;
        $showHours = ($skin['showHours'] ?? true) === true;
        $showMinutes = ($skin['showMinutes'] ?? true) === true;
        $showSeconds = ($skin['showSeconds'] ?? true) === true;
        $segments = ($showDays ? 1 : 0)
            + ($showHours ? 1 : 0)
            + ($showMinutes ? 1 : 0)
            + ($showSeconds ? 1 : 0);
        if ($segments < 1) {
            $segments = 1;
        }

        // Use a narrower content width inside the bar to bring segments closer.
        $contentInset = (int) round($barWidth * 0.10);
        $contentStartX = $barPaddingX + $contentInset;
        $contentWidth = max(1, $barWidth - 2 * $contentInset);
        $unitWidth = $contentWidth / $segments;

        $sepCol = imagecolorallocatealpha($im, 255, 255, 255, 70);
        $sepInset = (int) max(4, round($barHeight * 0.065));
        for ($i = 1; $i < $segments; $i++) {
            $x = (int) round($contentStartX + $unitWidth * $i);
            imageline($im, $x, $barY + $sepInset, $x, $barY + $barHeight - $sepInset, $sepCol);
        }
    }

    public function renderTimeText($im, int $secondsRemaining, array $skin, int $width, int $height, int $textCol, int $accentCol): bool
    {
        $font = (string) ($skin['fontPath'] ?? '');
        // Reduce font size by 25% (from original)
        $fontSize = (int) round(($skin['fontSize'] ?? 25));
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

        // Same bar geometry as renderStyle
        $barHeight = (int) round($height * self::BAR_HEIGHT_RATIO);
        $barY = (int) (($height - $barHeight) / 2);
        $centerY = (int) ($barY + ($barHeight / 2));
        $barPaddingX = 6;
        $barWidth = $width - (2 * $barPaddingX);
        $contentInset = (int) round($barWidth * 0.10);
        $contentStartX = $barPaddingX + $contentInset;
        $contentWidth = max(1, $barWidth - 2 * $contentInset);
        $unitWidth = $contentWidth / $segments;

        if (is_file($font)) {
            for ($i = 0; $i < $segments; $i++) {
                $cx = (int) ($contentStartX + $unitWidth * $i + $unitWidth / 2);

                $numText = sprintf('%02d', $units[$i]['value']);
                $labText = $units[$i]['label'];

                // Measure number and label
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

                // More space between number and label (while keeping the combined block centered)
                $gapBetween = max(3, (int) round($fontSize * 0.30));
                $blockHeight = $numHeight + $gapBetween + $labHeight;

                // Center combined number+label block within the bar height
                $blockTop = $barY + (int) round(($barHeight - $blockHeight) / 2);
                $numCenterY = $blockTop + $numHeight / 2.0;
                $labCenterY = $numCenterY + $numHeight / 2.0 + $gapBetween + $labHeight / 2.0;

                $numBaseline = (int) round($numCenterY - $numCenterOffset);
                $labBaseline = (int) round($labCenterY - $labCenterOffset);

                $nW = $this->textBoxWidth($numText, $font, $fontSize);
                imagettftext($im, $fontSize, 0, (int) ($cx - $nW / 2), $numBaseline, $textCol, $font, $numText);

                $lW = $this->textBoxWidth($labText, $font, $labelSize);
                imagettftext($im, $labelSize, 0, (int) ($cx - $lW / 2), $labBaseline, $textCol, $font, $labText);
            }
        } else {
            $built=5;$lbl=3;
            for ($i=0;$i<$segments;$i++) {
                $cx=(int)($unitWidth*$i + $unitWidth/2);
                $num=sprintf('%02d',$units[$i]['value']);
                $nW=imagefontwidth($built)*strlen($num);
                $lab=$units[$i]['label'];
                $lW=imagefontwidth($lbl)*strlen($lab);
                $nH = imagefontheight($built);
                $lH = imagefontheight($lbl);
                $gapBetween = max(3, (int) round($nH * 0.6));
                $blockH = $nH + $gapBetween + $lH;
                $blockTop = (int) round($centerY - ($blockH / 2));
                imagestring($im,$built,(int)($cx-$nW/2),$blockTop,$num,$textCol);
                imagestring($im,$lbl,(int)($cx-$lW/2),$blockTop + $nH + $gapBetween,$lab,$textCol);
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


