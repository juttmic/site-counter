<?php

namespace App\Services\Skins\Renderers;

use App\Services\Skins\SkinRendererInterface;

class TealCircleBadgesRenderer implements SkinRendererInterface
{
    public function renderStyle($im, array $skin, int $width, int $height, int $bgCol, int $textCol, int $accentCol, int $secondsRemaining): void
    {
        // Fill background
        imagefilledrectangle($im, 0, 0, $width, $height, $bgCol);

        // Draw outlined circles as badges; respect visibility (days and seconds only toggled)
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

        $centerY = (int) round($height / 2);
        $unitWidth = $width / $segments;
        $radius = (int) round(min($unitWidth / 2.6, $height / 2.2));
        $diameter = $radius * 2;

        // Compute a tighter gap between circles while keeping radius unchanged.
        $totalCircleWidth = $segments * $diameter;
        $availableWidth = max(0, $width - $totalCircleWidth);
        $gap = (int) floor($availableWidth / max(1, $segments + 1));
        $stroke = (int) max(2, (int) ($skin['circleStroke'] ?? 6));

        for ($i = 0; $i < $segments; $i++) {
            $centerX = $gap + $radius + $i * ($diameter + $gap);
            // White (or bg) circle fill
            imagefilledellipse($im, $centerX, $centerY, $radius * 2, $radius * 2, imagecolorallocatealpha($im, 255, 255, 255, 0));
            // Draw border using smooth vector-style circle if Imagick available
            $this->drawCircle($im, $centerX, $centerY, $radius, $accentCol, $stroke);
        }
    }

    public function renderTimeText($im, int $secondsRemaining, array $skin, int $width, int $height, int $textCol, int $accentCol): bool
    {
        $font = (string) ($skin['fontPath'] ?? '');
        $fontSize = (int) ($skin['fontSize'] ?? 34);
        $labelFsRaw = $skin['labelFontSize'] ?? null;
        $labelSize = ($labelFsRaw !== null && $labelFsRaw !== '')
            ? max(8, (int) $labelFsRaw)
            : max(10, (int) round($fontSize * 0.38));

        $showDays    = ($skin['showDays'] ?? true) === true;
        $showHours   = ($skin['showHours'] ?? true) === true;
        $showMinutes = ($skin['showMinutes'] ?? true) === true;
        $showSeconds = ($skin['showSeconds'] ?? true) === true;

        $days = intdiv($secondsRemaining, 86400);
        $hours = intdiv($secondsRemaining % 86400, 3600);
        $minutes = intdiv($secondsRemaining % 3600, 60);
        $seconds = $secondsRemaining % 60;

        $units = [];
        if ($showDays)    { $units[] = ['value' => $days,    'label' => 'DAYS']; }
        if ($showHours)   { $units[] = ['value' => $hours,   'label' => 'HOURS']; }
        if ($showMinutes) { $units[] = ['value' => $minutes, 'label' => 'MINUTES']; }
        if ($showSeconds) { $units[] = ['value' => $seconds, 'label' => 'SECONDS']; }
        if (empty($units)) { $units[] = ['value' => $seconds, 'label' => 'SECONDS']; }

        $segments = count($units);
        $unitWidth = $width / max(1, $segments);
        $centerY = (int) round($height / 2);

        // Match circle layout from renderStyle so spacing is consistent.
        $radius = (int) round(min($unitWidth / 2.6, $height / 2.2));
        $diameter = $radius * 2;
        $totalCircleWidth = $segments * $diameter;
        $availableWidth = max(0, $width - $totalCircleWidth);
        $gap = (int) floor($availableWidth / max(1, $segments + 1));

        if (is_file($font)) {
            // Gap factor to control vertical spacing between number and label
            $gapFactor = 0.96;

            for ($i = 0; $i < $segments; $i++) {
                $unitX = $gap + $radius + $i * ($diameter + $gap);

                $numberText = sprintf('%02d', $units[$i]['value']);
                $labelText = $units[$i]['label'];

                // Measure number and label boxes
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

                // Center combined number+label block vertically around centerY
                $blockTop = $centerY - (int) round($blockHeight / 2);
                $numberCenterY = $blockTop + $numberHeight / 2.0;
                $labelCenterY = $numberCenterY + $numberHeight / 2.0 + $gapBetween + $labelHeight / 2.0;

                $numberBaseline = (int) round($numberCenterY - $numberCenterOffset);
                $labelBaseline = (int) round($labelCenterY - $labelCenterOffset);

                $numberWidth = $this->textBoxWidth($numberText, $font, $fontSize);
                $numberX = (int) ($unitX - $numberWidth / 2);
                imagettftext($im, $fontSize, 0, $numberX, $numberBaseline, $textCol, $font, $numberText);

                $labelWidth = $this->textBoxWidth($labelText, $font, $labelSize);
                $labelX = (int) ($unitX - $labelWidth / 2);
                imagettftext($im, $labelSize, 0, $labelX, $labelBaseline, $textCol, $font, $labelText);
            }
        } else {
            $builtIn = 5; // bigger
            $labelBuiltIn = 3;
            for ($i = 0; $i < $segments; $i++) {
                $unitX = $gap + $radius + $i * ($diameter + $gap);
                $numberText = sprintf('%02d', $units[$i]['value']);
                $nW = imagefontwidth($builtIn) * strlen($numberText);
                $nH = imagefontheight($builtIn);
                $label = $units[$i]['label'];
                $lW = imagefontwidth($labelBuiltIn) * strlen($label);
                $lH = imagefontheight($labelBuiltIn);

                $gapBetween = max(3, (int) round($nH * 0.6));
                $blockHeight = $nH + $gapBetween + $lH;
                $blockTop = (int) round($centerY - ($blockHeight / 2));
                $numberY = $blockTop;
                $labelY = $blockTop + $nH + $gapBetween;

                imagestring($im, $builtIn, (int)($unitX - $nW / 2), $numberY, $numberText, $textCol);
                imagestring($im, $labelBuiltIn, (int)($unitX - $lW / 2), $labelY, $label, $textCol);
            }
        }

        return true;
    }

    private function drawCircle($im, int $cx, int $cy, int $r, int $color, int $thickness): void
    {
        // Use ImagickDraw for vector-style smooth circles if available
        if (class_exists(\Imagick::class) && class_exists(\ImagickDraw::class)) {
            $this->drawCircleImagick($im, $cx, $cy, $r, $color, $thickness);
            return;
        }
        
        // Fallback to GD for compatibility
        $this->drawCircleGD($im, $cx, $cy, $r, $color, $thickness);
    }

    /**
     * Draw smooth vector-style circle using ImagickDraw
     * @param resource $im GD image resource
     * @param int $cx Center X coordinate
     * @param int $cy Center Y coordinate
     * @param int $r Radius
     * @param int $color GD color index
     * @param int $thickness Stroke width in pixels
     */
    private function drawCircleImagick($im, int $cx, int $cy, int $r, int $color, int $thickness): void
    {
        // Get image dimensions
        $width = imagesx($im);
        $height = imagesy($im);
        
        // Extract RGB from GD color index
        $rgb = @imagecolorsforindex($im, $color);
        if ($rgb === false) {
            // Fallback to GD if color extraction fails
            $this->drawCircleGD($im, $cx, $cy, $r, $color, $thickness);
            return;
        }
        $colorHex = sprintf('#%02x%02x%02x', $rgb['red'], $rgb['green'], $rgb['blue']);
        
        // Convert GD image to Imagick
        ob_start();
        imagepng($im);
        $pngData = ob_get_clean();
        /** @var \Imagick $imagick */
        $imagick = new \Imagick();
        $imagick->readImageBlob($pngData);
        $imagick->setImageFormat('png');
        
        // Create ImagickDraw for vector-style circle
        /** @var \ImagickDraw $draw */
        $draw = new \ImagickDraw();
        $draw->setStrokeColor($colorHex);
        $draw->setFillColor('none');
        $draw->setStrokeWidth((float)$thickness);
        $draw->setStrokeLinecap(\Imagick::LINECAP_ROUND);
        $draw->setStrokeLinejoin(\Imagick::LINEJOIN_ROUND);
        
        // Calculate bounding box for the circle
        $sx = (float)($cx - $r);
        $sy = (float)($cy - $r);
        $ex = (float)($cx + $r);
        $ey = (float)($cy + $r);
        
        // Draw full circle as two semicircles for clean rendering
        $draw->arc($sx, $sy, $ex, $ey, 0.0, 180.0);
        $draw->arc($sx, $sy, $ex, $ey, 180.0, 360.0);
        
        // Draw the circle onto the Imagick image
        $imagick->drawImage($draw);
        
        // Convert back to GD format
        $imagick->setImageFormat('png');
        $newPngData = $imagick->getImageBlob();
        $newIm = imagecreatefromstring($newPngData);
        
        if ($newIm !== false) {
            // Copy the result back to original image
            imagecopy($im, $newIm, 0, 0, 0, 0, $width, $height);
            imagedestroy($newIm);
        }
        
        $imagick->destroy();
        $draw->destroy();
    }

    private function drawCircleGD($im, int $cx, int $cy, int $r, int $color, int $thickness): void
    {
        // Fallback GD implementation for compatibility
        // Draw border by multiple concentric ellipses for a thicker stroke
        for ($s = 0; $s < $thickness; $s++) {
            imageellipse($im, $cx, $cy, $r * 2 - $s, $r * 2 - $s, $color);
        }
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


