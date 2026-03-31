<?php

namespace App\Services\Skins\Renderers;

use App\Services\Skins\SkinRendererInterface;

/**
 * Skin style: "circular_progress_rings"
 * Renders countdown as circular progress rings with track, optional dotted ring, inner circle, and connectors.
 */
class CircularProgressRingsRenderer implements SkinRendererInterface
{
    /** Full ring = 60 segments so the same numeric value matches the same arc for days/hours/minutes/seconds. */
    private const RING_SEGMENTS = 60;

    public function renderStyle($im, array $skin, int $width, int $height, int $bgCol, int $textCol, int $accentCol, int $secondsRemaining): void
    {
        imagefilledrectangle($im, 0, 0, $width, $height, $bgCol);

        $showDays = ($skin['showDays'] ?? true) === true;
        $showHours = ($skin['showHours'] ?? true) === true;
        $showMinutes = ($skin['showMinutes'] ?? true) === true;
        $showSeconds = ($skin['showSeconds'] ?? true) === true;

        $units = [];
        if ($showDays) { $units[] = 'd'; }
        if ($showHours) { $units[] = 'h'; }
        if ($showMinutes) { $units[] = 'm'; }
        if ($showSeconds) { $units[] = 's'; }
        if (empty($units)) { $units = ['s']; }

        $days = intdiv($secondsRemaining, 86400);
        $hours = intdiv($secondsRemaining % 86400, 3600);
        $minutes = intdiv($secondsRemaining % 3600, 60);
        $seconds = $secondsRemaining % 60;

        $values = [$days, $hours, $minutes, $seconds];

        // Ring size control: scale radius relative to a sensible base size
        $ringSizeControl = max(80, min(300, (int) ($skin['ringSize'] ?? 176)));
        $ringSizeBase = 176;
        $ringStroke = max(1, (int) ($skin['ringStroke'] ?? 7));
        $ringTrackCol = $this->allocColor($im, $skin['ringTrackColor'] ?? '#D8E1E9');
        $innerCircleCol = $this->allocColor($im, $skin['innerCircleColor'] ?? '#F9FBFD');
        $innerBorderCol = $this->allocColor($im, $skin['innerBorderColor'] ?? '#D6DEE6');
        $innerBorderWidth = max(0, (int) ($skin['innerBorderWidth'] ?? 2));
        $dottedRing = ($skin['dottedRing'] ?? true) === true;
        $dotColor = $this->allocColor($im, $skin['dotColor'] ?? '#CFD7E1');
        $dotSize = max(1, (int) ($skin['dotSize'] ?? 5));
        $dotGap = max(1, (int) ($skin['dotGap'] ?? 7));
        $connectorCol = $this->allocColor($im, $skin['connectorColor'] ?? '#AEBAC6');
        $connectorWidth = max(1, (int) ($skin['connectorWidth'] ?? 6));
        $connectorLength = max(0, (int) ($skin['connectorLength'] ?? 16));

        $segments = count($units);
        $padX = max(0, (int) ($skin['horizontalPadding'] ?? 80));
        $availableWidth = $width - 2 * $padX;
        $unitWidth = (int) floor($availableWidth / $segments);
        $centerY = (int) ($height / 2);
        // Base radius derived from layout, then scaled by Ring Size control
        $baseRadius = (int) round(min($unitWidth, $height) * 0.40);
        $scale = max(0.5, min(1.5, $ringSizeControl / $ringSizeBase));
        $radius = max(10, (int) round($baseRadius * $scale));

        for ($i = 0; $i < $segments; $i++) {
            $cx = (int) ($padX + $unitWidth * $i + $unitWidth / 2);
            $unitIdx = ['d' => 0, 'h' => 1, 'm' => 2, 's' => 3][$units[$i]] ?? 0;
            $value = $values[$unitIdx];
            $progress = min(1.0, max(0.0, (float) $value / self::RING_SEGMENTS));

            // Track (full circle background)
            $this->thickArc($im, $cx, $centerY, $radius, 0, 360, $ringTrackCol, $ringStroke);

            // Optional dotted ring (outside the track)
            if ($dottedRing) {
                $this->drawDottedRing($im, $cx, $centerY, $radius + (int) round($ringStroke / 2), $dotColor, $dotSize, $dotGap);
            }

            // Progress arc (from top 270° clockwise; full ring when progress=1)
            if ($progress > 0.0001) {
                if ($progress >= 0.999) {
                    $this->thickArc($im, $cx, $centerY, $radius, 0, 360, $accentCol, $ringStroke);
                } else {
                    $endAngle = 270 - (360 * $progress);
                    $this->thickArc($im, $cx, $centerY, $radius, 270, $endAngle, $accentCol, $ringStroke);
                }
            }

            // Inner circle
            $innerR = max(2, $radius - $ringStroke - 4);
            imagefilledellipse($im, $cx, $centerY, $innerR * 2, $innerR * 2, $innerCircleCol);

            // Inner border
            if ($innerBorderWidth > 0) {
                $this->thickArc($im, $cx, $centerY, $innerR - $innerBorderWidth, 0, 360, $innerBorderCol, $innerBorderWidth);
            }

            // Connector to next ring (vertical bar between units)
            if ($i < $segments - 1 && $connectorLength > 0) {
                $connCenterX = (int) ($padX + $unitWidth * ($i + 1));
                $connLeft = (int) ($connCenterX - $connectorWidth / 2);
                $connTop = (int) ($centerY - $connectorLength / 2);
                $connBottom = (int) ($centerY + $connectorLength / 2);
                imagefilledrectangle($im, $connLeft, $connTop, $connLeft + $connectorWidth, $connBottom, $connectorCol);
            }

            
        }
    }

    public function renderTimeText($im, int $secondsRemaining, array $skin, int $width, int $height, int $textCol, int $accentCol): bool
    {
        $font = (string) ($skin['fontPath'] ?? '');
        $fontSize = (int) ($skin['fontSize'] ?? 56);
        $labelFontSize = (int) ($skin['labelFontSize'] ?? 18);
        $labelCol = $this->allocColor($im, $skin['labelColor'] ?? '#506478');

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
        $padX = max(0, (int) ($skin['horizontalPadding'] ?? 80));
        $availableWidth = $width - 2 * $padX;
        $unitWidth = (int) floor($availableWidth / $segments);
        $centerY = (int) ($height / 2);

        if (is_file($font)) {
            // Compute a vertical layout where the combined number + label block
            // is centered within the ring. Keep a comfortable gap between them.
            $numberSample = '00';
            $labelSample = 'HOURS';
            $numBox = imagettfbbox($fontSize, 0, $font, $numberSample) ?: [0, 0, 0, 0, 0, 0, 0, 0];
            $labBox = imagettfbbox($labelFontSize, 0, $font, $labelSample) ?: [0, 0, 0, 0, 0, 0, 0, 0];

            $numberHeight = (float) ($numBox[1] - $numBox[7]);
            $labelHeight = (float) ($labBox[1] - $labBox[7]);
            $gap = (int) round($fontSize * 0.18);
            $totalBlockHeight = $numberHeight + $gap + $labelHeight;

            // Slight upward bias so top and bottom spacing inside the ring feel equal.
            $verticalBias = (float) ($fontSize * 0.15);
            $blockTop = (float) max(0, $centerY - $totalBlockHeight / 2.0 - $verticalBias);
            $numberCenterY = $blockTop + $numberHeight / 2.0;
            $labelCenterY = $blockTop + $numberHeight + $gap + $labelHeight / 2.0;

            for ($i = 0; $i < $segments; $i++) {
                $cx = (int) ($padX + $unitWidth * $i + $unitWidth / 2);

                $numberText = sprintf('%02d', $units[$i]['value']);
                $numberWidth = $this->textBoxWidth($numberText, $font, $fontSize);
                $numberBaseline = (int) round(
                    $numberCenterY - $this->textVerticalCenterOffset($numberText, $font, $fontSize)
                );
                imagettftext(
                    $im,
                    $fontSize,
                    0,
                    (int) ($cx - $numberWidth / 2),
                    $numberBaseline,
                    $textCol,
                    $font,
                    $numberText
                );

                $label = $units[$i]['label'];
                $labelWidth = $this->textBoxWidth($label, $font, $labelFontSize);
                $labelBaseline = (int) round(
                    $labelCenterY - $this->textVerticalCenterOffset($label, $font, $labelFontSize)
                );
                imagettftext(
                    $im,
                    $labelFontSize,
                    0,
                    (int) ($cx - $labelWidth / 2),
                    $labelBaseline,
                    $labelCol,
                    $font,
                    $label
                );
            }
        } else {
            $builtIn = 5;
            $labelBuiltIn = 3;

            $numH = imagefontheight($builtIn);
            $labH = imagefontheight($labelBuiltIn);
            $gap = 8;
            $totalBlockHeight = $numH + $gap + $labH;
            $blockTop = (int) max(0, $centerY - $totalBlockHeight / 2.0);
            $numberY = $blockTop;
            $labelY = $blockTop + $numH + $gap;

            for ($i = 0; $i < $segments; $i++) {
                $cx = (int) ($padX + $unitWidth * $i + $unitWidth / 2);

                $numberText = sprintf('%02d', $units[$i]['value']);
                $nW = imagefontwidth($builtIn) * strlen($numberText);
                imagestring($im, $builtIn, (int) ($cx - $nW / 2), $numberY, $numberText, $textCol);

                $label = $units[$i]['label'];
                $lW = imagefontwidth($labelBuiltIn) * strlen($label);
                imagestring($im, $labelBuiltIn, (int) ($cx - $lW / 2), $labelY, $label, $labelCol);
            }
        }

        return true;
    }

    private function allocColor($im, string $hex): int
    {
        $hex = ltrim($hex, '#');
        if (strlen($hex) === 6) {
            $r = (int) hexdec(substr($hex, 0, 2));
            $g = (int) hexdec(substr($hex, 2, 2));
            $b = (int) hexdec(substr($hex, 4, 2));
            return imagecolorallocate($im, $r, $g, $b);
        }
        return imagecolorallocate($im, 255, 255, 255);
    }

    private function thickArc($im, int $cx, int $cy, int $r, int $start, int $end, int $color, int $thickness): void
    {
        if (class_exists(\Imagick::class) && class_exists(\ImagickDraw::class)) {
            $this->thickArcImagick($im, $cx, $cy, $r, $start, $end, $color, $thickness);
            return;
        }
        $this->thickArcGD($im, $cx, $cy, $r, $start, $end, $color, $thickness);
    }

    private function thickArcImagick($im, int $cx, int $cy, int $r, int $start, int $end, int $color, int $thickness): void
    {
        $width = imagesx($im);
        $height = imagesy($im);
        $rgb = @imagecolorsforindex($im, $color);
        if ($rgb === false) {
            $this->thickArcGD($im, $cx, $cy, $r, $start, $end, $color, $thickness);
            return;
        }
        $colorHex = sprintf('#%02x%02x%02x', $rgb['red'], $rgb['green'], $rgb['blue']);

        ob_start();
        imagepng($im);
        $pngData = ob_get_clean();
        $imagick = new \Imagick();
        $imagick->readImageBlob($pngData);
        $imagick->setImageFormat('png');

        $draw = new \ImagickDraw();
        $draw->setStrokeColor($colorHex);
        $draw->setFillColor('none');
        $draw->setStrokeWidth((float) $thickness);
        $draw->setStrokeLinecap(\Imagick::LINECAP_ROUND);
        $draw->setStrokeLinejoin(\Imagick::LINEJOIN_ROUND);

        $sx = (float) ($cx - $r);
        $sy = (float) ($cy - $r);
        $ex = (float) ($cx + $r);
        $ey = (float) ($cy + $r);

        $angleDiff = abs((float) $end - (float) $start);
        if ($angleDiff >= 360 || $angleDiff <= -360) {
            $draw->arc($sx, $sy, $ex, $ey, 0.0, 180.0);
            $draw->arc($sx, $sy, $ex, $ey, 180.0, 360.0);
        } else {
            $startAngle = fmod((float) $start + 360.0, 360.0);
            $endAngle = fmod((float) $end + 360.0, 360.0);
            $draw->arc($sx, $sy, $ex, $ey, $startAngle, $endAngle);
        }

        $imagick->drawImage($draw);
        $imagick->setImageFormat('png');
        $newPngData = $imagick->getImageBlob();
        $newIm = imagecreatefromstring($newPngData);
        if ($newIm !== false) {
            imagecopy($im, $newIm, 0, 0, 0, 0, $width, $height);
            imagedestroy($newIm);
        }
        $imagick->destroy();
        $draw->destroy();
    }

    private function thickArcGD($im, int $cx, int $cy, int $r, int $start, int $end, int $color, int $thickness): void
    {
        for ($t = 0; $t < $thickness; $t++) {
            imagearc($im, $cx, $cy, ($r * 2) - $t, ($r * 2) - $t, $start, $end, $color);
        }
    }

    private function drawDottedRing($im, int $cx, int $cy, int $radius, int $color, int $dotSize, int $dotGap): void
    {
        $circumference = 2 * M_PI * $radius;
        $totalStep = $dotSize + $dotGap;
        $numDots = (int) max(8, floor($circumference / $totalStep));

        for ($i = 0; $i < $numDots; $i++) {
            $angle = 2 * M_PI * $i / $numDots - M_PI / 2;
            $dx = (int) round($radius * cos($angle));
            $dy = (int) round($radius * sin($angle));
            $px = $cx + $dx;
            $py = $cy + $dy;
            imagefilledellipse($im, $px, $py, $dotSize, $dotSize, $color);
        }
    }

    private function textBoxWidth(string $text, string $fontPath, int $fontSize): int
    {
        $box = imagettfbbox($fontSize, 0, $fontPath, $text) ?: [0, 0, 0, 0, 0, 0, 0, 0];
        $minX = min($box[0], $box[2], $box[4], $box[6]);
        $maxX = max($box[0], $box[2], $box[4], $box[6]);
        return (int) ($maxX - $minX);
    }

    /**
     * Compute the vertical center offset of text relative to the baseline.
     * This is used to align text vertically by its visual center.
     */
    private function textVerticalCenterOffset(string $text, string $fontPath, int $fontSize): float
    {
        $box = imagettfbbox($fontSize, 0, $fontPath, $text) ?: [0, 0, 0, 0, 0, 0, 0, 0];
        // Average of top and bottom Y coordinates of the bounding box.
        return ($box[7] + $box[1]) / 2.0;
    }
}
