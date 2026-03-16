<?php

namespace App\Services\Skins\Renderers;

use App\Services\Skins\SkinRendererInterface;

class HexTileRenderer implements SkinRendererInterface
{
    public function renderStyle($im, array $skin, int $width, int $height, int $bgCol, int $textCol, int $accentCol, int $secondsRemaining): void
    {
        // Hexagonal tiles in a row; respect visibility
        imagefilledrectangle($im, 0, 0, $width, $height, $bgCol);
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

        // Fix hex tile size based on available height only, then derive minimal gap from width.
        $verticalMargin = max(4, (int) round($height * 0.08));
        $diameter = max(10, $height - 2 * $verticalMargin);
        $radius = (int) floor($diameter / 2);

        // totalWidth = segments * diameter + (segments + 1) * gap  =>  gap = (width - segments*diameter)/(segments+1)
        $rawGap = (int) floor(($width - $segments * $diameter) / max(1, $segments + 1));
        $gap = max(0, $rawGap);

        $tileY = $verticalMargin;

        // Thin red rectangular border color for each hex tile
        $borderColor = imagecolorallocate($im, 219, 0, 0);

        // for ($i = 0; $i < $segments; $i++) {
        //     $centerX = $gap + $radius + $i * ($diameter + $gap);
        //     $centerY = $tileY + $radius;
        //     $points = $this->regularHexagonPoints($centerX, $centerY, $radius);

        //     // Filled hex tile
        //     imagefilledpolygon($im, $points, 6, $accentCol);

        //     // Thin red rectangular border around the hex tile
        //     $leftX = $centerX - $radius;
        //     $rightX = $centerX + $radius;
        //     $topY = $tileY;
        //     $bottomY = $tileY + 2 * $radius;
        //     imagerectangle($im, $leftX, $topY, $rightX, $bottomY, $borderColor);
        // }
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

        // Match spacing and sizing with renderStyle so geometry stays consistent.
        $verticalMargin = max(4, (int) round($height * 0.08));
        $diameter = max(10, $height - 2 * $verticalMargin);
        $radius = (int) floor($diameter / 2);

        $rawGap = (int) floor(($width - $segments * $diameter) / max(1, $segments + 1));
        $gap = max(0, $rawGap);

        $tileY = $verticalMargin;

        // Gap factor to control distance between number and label
        $gapFactor = 0.55;

        if (is_file($font)) {
            for ($i = 0; $i < $segments; $i++) {
                $centerX = $gap + $radius + $i * ($diameter + $gap);
                $centerY = $tileY + $radius;

                $numberText = sprintf('%02d', $units[$i]['value']);
                $labelText = $units[$i]['label'];

                $numberBox = imagettfbbox($fontSize, 0, $font, $numberText) ?: [0, 0, 0, 0, 0, 0, 0, 0];
                $labelBox = imagettfbbox($labelSize, 0, $font, $labelText) ?: [0, 0, 0, 0, 0, 0, 0, 0];

                $numberMinY = min($numberBox[1], $numberBox[3], $numberBox[5], $numberBox[7]);
                $numberMaxY = max($numberBox[1], $numberBox[3], $numberBox[5], $numberBox[7]);
                $numberHeight = $numberMaxY - $numberMinY;
                $numberCenterOffset = ($numberBox[1] + $numberBox[7]) / 2.0;

                $labelMinY = min($labelBox[1], $labelBox[3], $labelBox[5], $labelBox[7]);
                $labelMaxY = max($labelBox[1], $labelBox[3], $labelBox[5], $labelBox[7]);
                $labelHeight = $labelMaxY - $labelMinY;
                $labelCenterOffset = ($labelBox[1] + $labelBox[7]) / 2.0;

                $gapBetween = max(3, (int) round($fontSize * $gapFactor));
                $blockHeight = $numberHeight + $gapBetween + $labelHeight;

                $blockTop = $centerY - (int) round($blockHeight / 2);
                $numberCenterY = $blockTop + $numberHeight / 2.0;
                $labelCenterY = $numberCenterY + $numberHeight / 2.0 + $gapBetween + $labelHeight / 2.0;

                $numberBaseline = (int) round($numberCenterY - $numberCenterOffset);
                $labelBaseline = (int) round($labelCenterY - $labelCenterOffset);

                $numberWidth = $this->textBoxWidth($numberText, $font, $fontSize);
                imagettftext(
                    $im,
                    $fontSize,
                    0,
                    (int) ($centerX - $numberWidth / 2),
                    $numberBaseline,
                    $textCol,
                    $font,
                    $numberText
                );

                $labelWidth = $this->textBoxWidth($labelText, $font, $labelSize);
                imagettftext(
                    $im,
                    $labelSize,
                    0,
                    (int) ($centerX - $labelWidth / 2),
                    $labelBaseline,
                    $textCol,
                    $font,
                    $labelText
                );
            }
        } else {
            $numberFontId = 5;
            $labelFontId = 3;
            for ($i = 0; $i < $segments; $i++) {
                $centerX = $gap + $radius + $i * ($diameter + $gap);
                $centerY = $tileY + $radius;

                $numberText = sprintf('%02d', $units[$i]['value']);
                $labelText = $units[$i]['label'];

                $numberHeight = imagefontheight($numberFontId);
                $labelHeight = imagefontheight($labelFontId);

                $gapBetween = max(3, (int) round($fontSize * $gapFactor));
                $blockHeight = $numberHeight + $gapBetween + $labelHeight;
                $blockTop = $centerY - (int) round($blockHeight / 2);

                $numberY = $blockTop;
                $labelY = $blockTop + $numberHeight + $gapBetween;

                $numberWidth = imagefontwidth($numberFontId) * strlen($numberText);
                imagestring(
                    $im,
                    $numberFontId,
                    (int) ($centerX - $numberWidth / 2),
                    $numberY,
                    $numberText,
                    $textCol
                );

                $labelWidth = imagefontwidth($labelFontId) * strlen($labelText);
                imagestring(
                    $im,
                    $labelFontId,
                    (int) ($centerX - $labelWidth / 2),
                    $labelY,
                    $labelText,
                    $textCol
                );
            }
        }

        return true;
    }

    private function regularHexagonPoints(int $centerX, int $centerY, int $radius): array
    {
        $points = [];
        // Pointy-topped regular hexagon: one vertex at top, one at bottom,
        // vertical corners at left and right. All six sides share the same radius.
        for ($i = 0; $i < 6; $i++) {
            $angle = deg2rad(60 * $i - 90);
            $x = $centerX + $radius * cos($angle);
            $y = $centerY + $radius * sin($angle);
            $points[] = (int) round($x);
            $points[] = (int) round($y);
        }

        return $points;
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


