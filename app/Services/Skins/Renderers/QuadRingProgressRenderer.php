<?php

namespace App\Services\Skins\Renderers;

use App\Services\Skins\SkinRendererInterface;

class QuadRingProgressRenderer implements SkinRendererInterface
{
    public function renderStyle($im, array $skin, int $width, int $height, int $bgCol, int $textCol, int $accentCol, int $secondsRemaining): void
    {
        // Four annular progress rings (not full circles) with gaps
        imagefilledrectangle($im, 0, 0, $width, $height, $bgCol);
        $segments = 4;
        $unitWidth = $width / $segments;
        $centerY = (int) ($height / 2);
        $radius = (int) round(min($unitWidth / 2.6, $height / 2.6));
        $thick = (int) max(6, (int) ($skin['ringThickness'] ?? 10));
        $bgRing = imagecolorallocate($im, 220, 220, 220);

        for ($i = 0; $i < $segments; $i++) {
            $cx = (int) ($unitWidth * $i + $unitWidth / 2);
            // Base ring
            $this->thickArc($im, $cx, $centerY, $radius, 0, 360, $bgRing, $thick);
            // Progress ring (static visual, independent of remaining seconds for 1-frame look)
            $this->thickArc($im, $cx, $centerY, $radius, 270, 90, $accentCol, $thick);
        }
    }

    public function renderTimeText($im, int $secondsRemaining, array $skin, int $width, int $height, int $textCol, int $accentCol): bool
    {
        $font = (string) ($skin['fontPath'] ?? '');
        $fontSize = (int) ($skin['fontSize'] ?? 28);
        $labelSize = max(10, (int) round($fontSize * 0.32));

        $showDays = ($skin['showDays'] ?? true) === true;
        $showHours = ($skin['showHours'] ?? true) === true;
        $showMinutes = ($skin['showMinutes'] ?? true) === true;
        $showSeconds = ($skin['showSeconds'] ?? true) === true;

        $days = intdiv($secondsRemaining, 86400);
        $hours = intdiv($secondsRemaining % 86400, 3600);
        $minutes = intdiv($secondsRemaining % 3600, 60);
        $seconds = $secondsRemaining % 60;

        $units = [];
        if ($showDays) { $units[] = ['value' => $days, 'label' => 'DAYS']; }
        if ($showHours) { $units[] = ['value' => $hours, 'label' => 'HOURS']; }
        if ($showMinutes) { $units[] = ['value' => $minutes, 'label' => 'MINUTES']; }
        if ($showSeconds) { $units[] = ['value' => $seconds, 'label' => 'SECONDS']; }
        if (empty($units)) { $units[] = ['value' => $seconds, 'label' => 'SECONDS']; }

        $segments = count($units);
        $unitWidth = $width / $segments;
        $centerY = (int) ($height / 2);

        if (is_file($font)) {
            for ($i = 0; $i < $segments; $i++) {
                $cx = (int) ($unitWidth * $i + $unitWidth / 2);
                $numberText = sprintf('%02d', $units[$i]['value']);
                $nW = $this->textBoxWidth($numberText, $font, $fontSize);
                imagettftext($im, $fontSize, 0, (int)($cx - $nW / 2), (int)($centerY - 4), $textCol, $font, $numberText);
                $label = $units[$i]['label'];
                $lW = $this->textBoxWidth($label, $font, $labelSize);
                imagettftext($im, $labelSize, 0, (int)($cx - $lW / 2), (int)($centerY + (int) round($fontSize * 0.9)), $textCol, $font, $label);
            }
        } else {
            $built = 5; $lbl = 3;
            for ($i = 0; $i < $segments; $i++) {
                $cx = (int) ($unitWidth * $i + $unitWidth / 2);
                $numberText = sprintf('%02d', $units[$i]['value']);
                $nW = imagefontwidth($built) * strlen($numberText);
                imagestring($im, $built, (int)($cx - $nW / 2), (int)($centerY - 8), $numberText, $textCol);
                $label = $units[$i]['label'];
                $lW = imagefontwidth($lbl) * strlen($label);
                imagestring($im, $lbl, (int)($cx - $lW / 2), (int)($centerY + 10), $label, $textCol);
            }
        }

        return true;
    }

    private function thickArc($im, int $cx, int $cy, int $r, int $start, int $end, int $color, int $thickness): void
    {
        // Use ImagickDraw for vector-style smooth arcs if available
        if (class_exists(\Imagick::class) && class_exists(\ImagickDraw::class)) {
            $this->thickArcImagick($im, $cx, $cy, $r, $start, $end, $color, $thickness);
            return;
        }
        
        // Fallback to GD for compatibility
        $this->thickArcGD($im, $cx, $cy, $r, $start, $end, $color, $thickness);
    }

    /**
     * Draw smooth vector-style arc using ImagickDraw
     * @param resource $im GD image resource
     * @param int $cx Center X coordinate
     * @param int $cy Center Y coordinate
     * @param int $r Radius
     * @param int $start Start angle in degrees (0 = 3 o'clock, counter-clockwise)
     * @param int $end End angle in degrees
     * @param int $color GD color index
     * @param int $thickness Stroke width in pixels
     */
    private function thickArcImagick($im, int $cx, int $cy, int $r, int $start, int $end, int $color, int $thickness): void
    {
        // Get image dimensions
        $width = imagesx($im);
        $height = imagesy($im);
        
        // Extract RGB from GD color index
        $rgb = @imagecolorsforindex($im, $color);
        if ($rgb === false) {
            // Fallback to GD if color extraction fails
            $this->thickArcGD($im, $cx, $cy, $r, $start, $end, $color, $thickness);
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
        
        // Create ImagickDraw for vector-style arc
        /** @var \ImagickDraw $draw */
        $draw = new \ImagickDraw();
        $draw->setStrokeColor($colorHex);
        $draw->setFillColor('none');
        $draw->setStrokeWidth((float)$thickness);
        $draw->setStrokeLinecap(\Imagick::LINECAP_ROUND);
        $draw->setStrokeLinejoin(\Imagick::LINEJOIN_ROUND);
        
        // Normalize angles: both GD and Imagick use degrees, 0° = 3 o'clock, counter-clockwise
        $startAngle = (float)$start;
        $endAngle = (float)$end;
        
        // Calculate bounding box for the arc (ellipse bounding rectangle)
        $sx = (float)($cx - $r);
        $sy = (float)($cy - $r);
        $ex = (float)($cx + $r);
        $ey = (float)($cy + $r);
        
        // Handle full circle (360 degrees or more)
        $angleDiff = abs($endAngle - $startAngle);
        if ($angleDiff >= 360 || $angleDiff <= -360) {
            // Draw full circle as two semicircles for clean rendering
            $draw->arc($sx, $sy, $ex, $ey, 0.0, 180.0);
            $draw->arc($sx, $sy, $ex, $ey, 180.0, 360.0);
        } else {
            // Normalize angles to 0-360 range for consistency
            $startAngle = fmod($startAngle + 360.0, 360.0);
            $endAngle = fmod($endAngle + 360.0, 360.0);
            
            // Draw the arc segment
            $draw->arc($sx, $sy, $ex, $ey, $startAngle, $endAngle);
        }
        
        // Draw the arc onto the Imagick image
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

    private function thickArcGD($im, int $cx, int $cy, int $r, int $start, int $end, int $color, int $thickness): void
    {
        // Fallback GD implementation for compatibility
        $outerRadius = $r * 2;
        $innerRadius = max(2, ($r - $thickness) * 2);
        
        // Get background color from the image
        $bgCol = imagecolorat($im, 0, 0);
        
        // For full circles (360 degrees), use filled ellipse approach
        if (($end - $start) >= 360 || ($end - $start) <= -360) {
            // Draw outer filled circle
            imagefilledellipse($im, $cx, $cy, $outerRadius, $outerRadius, $color);
            // Cut out inner circle
            if ($innerRadius > 0) {
                imagefilledellipse($im, $cx, $cy, $innerRadius, $innerRadius, $bgCol);
            }
        } else {
            // For partial arcs, use filled arc (pie slice) approach
            // Draw outer filled arc (pie slice from center to outer radius)
            imagefilledarc($im, $cx, $cy, $outerRadius, $outerRadius, $start, $end, $color, IMG_ARC_PIE);
            
            // Draw inner filled arc in background color to create the ring effect
            if ($innerRadius > 0) {
                imagefilledarc($im, $cx, $cy, $innerRadius, $innerRadius, $start, $end, $bgCol, IMG_ARC_PIE);
            }
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


