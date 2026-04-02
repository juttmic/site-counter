<?php

namespace App\Services\Skins\Renderers;

use App\Services\Skins\SkinRendererInterface;

/**
 * Skin style: "rounded_panel_cards"
 * Renders countdown as soft, modern SaaS-style rounded panel with elevated cards inside.
 * Outer panel has shadow, fill, and border. Cards contain number boxes and labels.
 * First visible unit gets accent border highlight.
 */
class RoundedPanelCardsRenderer implements SkinRendererInterface
{
    /**
     * @return array{minX: float, maxX: float, minY: float, maxY: float, width: float, height: float, ascent: float}
     */
    private function measureText(string $font, int $size, string $text): array
    {
        $box = imagettfbbox($size, 0, $font, $text) ?: [0, 0, 0, 0, 0, 0, 0, 0];
        $xs = [$box[0], $box[2], $box[4], $box[6]];
        $ys = [$box[1], $box[3], $box[5], $box[7]];
        $minX = min($xs);
        $maxX = max($xs);
        $minY = min($ys);
        $maxY = max($ys);
        return [
            'minX' => $minX,
            'maxX' => $maxX,
            'minY' => $minY,
            'maxY' => $maxY,
            'width' => $maxX - $minX,
            'height' => $maxY - $minY,
            'ascent' => -$minY,
        ];
    }

    private function fitFontSizeToBox(string $font, int $desiredSize, string $text, int $maxW, int $maxH, int $minSize = 8): int
    {
        $size = max($minSize, $desiredSize);
        while ($size > $minSize) {
            $m = $this->measureText($font, $size, $text);
            if ($m['width'] <= $maxW && $m['height'] <= $maxH) {
                break;
            }
            $size--;
        }
        return $size;
    }

    /**
     * Try to preserve requested label font size by reducing the gap first.
     *
     * @return array{gap:int, fontSize:int}
     */
    private function resolveLabelGapAndFontSize(
        string $font,
        string $label,
        int $requestedFontSize,
        int $labelMaxW,
        int $labelAreaTopBase,
        int $labelAreaBottom,
        int $desiredGap,
        int $minGap
    ): array {
        $minGap = max(0, $minGap);
        $desiredGap = max($minGap, $desiredGap);

        // First try to keep requested font size by shrinking gap.
        for ($gap = $desiredGap; $gap >= $minGap; $gap--) {
            $labelAreaTop = $labelAreaTopBase + $gap;
            $labelMaxH = max(1, $labelAreaBottom - $labelAreaTop);
            $fit = $this->fitFontSizeToBox($font, $requestedFontSize, $label, $labelMaxW, $labelMaxH, 8);
            if ($fit >= $requestedFontSize) {
                return ['gap' => $gap, 'fontSize' => $requestedFontSize];
            }
        }

        // Otherwise use the minimum gap and shrink the font to fit.
        $labelAreaTop = $labelAreaTopBase + $minGap;
        $labelMaxH = max(1, $labelAreaBottom - $labelAreaTop);
        $fit = $this->fitFontSizeToBox($font, $requestedFontSize, $label, $labelMaxW, $labelMaxH, 8);
        return ['gap' => $minGap, 'fontSize' => $fit];
    }

    public function renderStyle($im, array $skin, int $width, int $height, int $bgCol, int $textCol, int $accentCol, int $secondsRemaining): void
    {
        imagefilledrectangle($im, 0, 0, $width, $height, $bgCol);

        if ($secondsRemaining <= 0) {
            $finalBg = $skin['finalBackground'] ?? null;
            if ($finalBg !== null && $finalBg !== '') {
                $finalBgCol = $this->allocColor($im, $finalBg);
                imagefilledrectangle($im, 0, 0, $width, $height, $finalBgCol);
            }
            return;
        }

        $units = $this->getVisibleUnits($skin);
        $segments = count($units);
        if ($segments === 0) {
            $this->drawPanelOnly($im, $skin, $width, $height, $bgCol);
            return;
        }

        $layout = $this->computeLayout($skin, $width, $height, $segments);
        if ($layout === null) {
            return;
        }

        // Cards only – no outer rounded panel/background box
        $cardBg = $this->allocColor($im, $skin['cardBackground'] ?? '#EDF1F5');
        $numberBoxBg = $this->allocColor($im, $skin['numberBoxBackground'] ?? '#FFFFFF');
        $accentBorderCol = $this->allocColor($im, (string) ($skin['numberBoxBorderColor'] ?? $skin['accentColor'] ?? '#69B7CB'));
        $neutralBorderCol = $this->allocColor($im, $skin['borderColor'] ?? '#D8E0E8');
        // Border width should apply to every segment; only the color is "highlighted" on first segment.
        $borderWidth = max(0, min(10, (int) ($skin['numberBoxBorderWidth'] ?? 3)));
        $numberBoxRadius = max(0, (int) ($skin['numberBoxRadius'] ?? 14));
        $cardShadowCol = $this->allocColor($im, $skin['shadowColor'] ?? '#D9E3EB');

        for ($i = 0; $i < $segments; $i++) {
            $cardX = $layout['cardX'] + $i * ($layout['cardWidth'] + $layout['segmentGap']);
            $cardY = $layout['cardY'];

            // Card subtle shadow (lift effect)
            $this->fillRoundedRect($im, $cardX + 1, $cardY + 2, $layout['cardWidth'], $layout['cardHeight'], $layout['cardRadius'], $cardShadowCol);

            // Card background
            $this->fillRoundedRect($im, $cardX, $cardY, $layout['cardWidth'], $layout['cardHeight'], $layout['cardRadius'], $cardBg);

            // Number box (border + fill)
            $boxX = $cardX + $layout['boxPadX'];
            $boxY = $cardY + $layout['boxPadY'];
            $boxW = $layout['boxW'];
            $boxH = $layout['boxH'];
            $isHighlighted = ($i === 0);
            $boxBorderCol = $isHighlighted ? $accentBorderCol : $neutralBorderCol;
            $boxBorderW = $borderWidth;

            if ($boxBorderW > 0) {
                $borderR = min($numberBoxRadius + $boxBorderW, (int) floor(min($boxW, $boxH) / 2));
                $this->fillRoundedRect($im, $boxX, $boxY, $boxW, $boxH, $borderR, $boxBorderCol);
            }
            $innerPad = $boxBorderW;
            $innerX = $boxX + $innerPad;
            $innerY = $boxY + $innerPad;
            $innerW = $boxW - 2 * $innerPad;
            $innerH = $boxH - 2 * $innerPad;
            $innerR = max(0, $numberBoxRadius - $innerPad);
            $this->fillRoundedRect($im, $innerX, $innerY, $innerW, $innerH, $innerR, $numberBoxBg);
        }
    }

    public function renderTimeText($im, int $secondsRemaining, array $skin, int $width, int $height, int $textCol, int $accentCol): bool
    {
        $font = (string) ($skin['fontPath'] ?? '');
        $requestedFontSize = (int) ($skin['fontSize'] ?? 52);
        $requestedLabelFontSize = (int) ($skin['labelFontSize'] ?? 18);
        $labelCol = $this->allocColor($im, $skin['labelColor'] ?? '#3D5268');

        if ($secondsRemaining <= 0) {
            $finalMsg = (string) ($skin['finalMessage'] ?? "Time's Up");
            $finalTextCol = $skin['finalTextColor'] ?? null;
            $drawCol = ($finalTextCol !== null && $finalTextCol !== '') ? $this->allocColor($im, $finalTextCol) : $textCol;
            if (is_file($font)) {
                $box = imagettfbbox($fontSize, 0, $font, $finalMsg) ?: [0, 0, 0, 0, 0, 0, 0, 0];
                $tw = (int) (max($box[0], $box[2], $box[4], $box[6]) - min($box[0], $box[2], $box[4], $box[6]));
                $x = (int) (($width - $tw) / 2);
                $y = (int) ($height / 2 + $fontSize / 2);
                imagettftext($im, $fontSize, 0, $x, $y, $drawCol, $font, $finalMsg);
            } else {
                $tw = imagefontwidth(5) * strlen($finalMsg);
                $th = imagefontheight(5);
                imagestring($im, 5, (int) (($width - $tw) / 2), (int) (($height - $th) / 2), $finalMsg, $drawCol);
            }
            return true;
        }

        $units = $this->getVisibleUnitsWithValues($skin, $secondsRemaining);
        $segments = count($units);
        if ($segments === 0) {
            return true;
        }

        $layout = $this->computeLayout($skin, $width, $height, $segments);
        if ($layout === null) {
            return true;
        }

        $boxY = $layout['cardY'] + $layout['boxPadY'];
        $boxBottom = $boxY + $layout['boxH'];
        $cardBottom = $layout['cardY'] + $layout['cardHeight'];

        // Keep existing layout, but compute text areas inside it.
        $labelGap = (int) round($layout['cardHeight'] * 0.16);
        $bottomPad = max(6, (int) round($layout['cardHeight'] * 0.10));

        if (is_file($font)) {
            for ($i = 0; $i < $segments; $i++) {
                $cardX = $layout['cardX'] + $i * ($layout['cardWidth'] + $layout['segmentGap']);
                $centerX = (int) ($cardX + $layout['cardWidth'] / 2);

                $value = $units[$i]['value'];
                $label = $units[$i]['label'];
                $isDays = ($label === 'DAYS');
                $numberText = $isDays ? (string) $value : sprintf('%02d', $value);

                // Keep paddings/margins as-is, but auto-fit fonts inside their allocated areas
                $boxInnerPad = max(4, (int) round($layout['boxH'] * 0.10));
                $numberAreaLeft = $cardX + $layout['boxPadX'] + $boxInnerPad;
                $numberAreaRight = $cardX + $layout['boxPadX'] + $layout['boxW'] - $boxInnerPad;
                $numberAreaTop = $boxY + $boxInnerPad;
                $numberAreaBottom = $boxBottom - $boxInnerPad;
                $numberMaxW = max(1, $numberAreaRight - $numberAreaLeft);
                $numberMaxH = max(1, $numberAreaBottom - $numberAreaTop);
                $numberFs = $this->fitFontSizeToBox($font, $requestedFontSize, $numberText, $numberMaxW, $numberMaxH, 8);

                $nm = $this->measureText($font, $numberFs, $numberText);
                $numberCenterY = ($numberAreaTop + $numberAreaBottom) / 2.0;
                $numberX = (int) round($centerX - ($nm['width'] / 2) - $nm['minX']);
                $numberBaseline = (int) round($numberCenterY - ($nm['minY'] + $nm['maxY']) / 2);
                imagettftext($im, $numberFs, 0, $numberX, $numberBaseline, $textCol, $font, $numberText);

                $labelAreaTopBase = $boxBottom;
                $labelAreaBottom = $cardBottom - $bottomPad;
                $labelMaxW = max(1, $layout['cardWidth'] - 12);
                $resolved = $this->resolveLabelGapAndFontSize(
                    $font,
                    $label,
                    $requestedLabelFontSize,
                    $labelMaxW,
                    $labelAreaTopBase,
                    $labelAreaBottom,
                    $labelGap,
                    4
                );
                $labelFs = $resolved['fontSize'];
                $labelAreaTop = $labelAreaTopBase + $resolved['gap'];

                $lm = $this->measureText($font, $labelFs, $label);
                $labelCenterY = ($labelAreaTop + $labelAreaBottom) / 2.0;
                $labelX = (int) round($centerX - ($lm['width'] / 2) - $lm['minX']);
                $labelBaseline = (int) round($labelCenterY - ($lm['minY'] + $lm['maxY']) / 2);
                imagettftext($im, $labelFs, 0, $labelX, $labelBaseline, $labelCol, $font, $label);
            }
        } else {
            $builtIn = 5;
            $labelBuiltIn = 3;
            $numberY = (int) ($numberCenterY - 12);
            for ($i = 0; $i < $segments; $i++) {
                $cardX = $layout['cardX'] + $i * ($layout['cardWidth'] + $layout['segmentGap']);
                $centerX = (int) ($cardX + $layout['cardWidth'] / 2);

                $value = $units[$i]['value'];
                $label = $units[$i]['label'];
                $isDays = ($label === 'DAYS');
                $numberText = $isDays ? (string) $value : sprintf('%02d', $value);

                $nW = imagefontwidth($builtIn) * strlen($numberText);
                imagestring($im, $builtIn, (int) ($centerX - $nW / 2), $numberY, $numberText, $textCol);

                $lW = imagefontwidth($labelBuiltIn) * strlen($label);
                imagestring($im, $labelBuiltIn, (int) ($centerX - $lW / 2), (int) ($labelY - 10), $label, $labelCol);
            }
        }

        return true;
    }

    private function drawPanelOnly($im, array $skin, int $width, int $height, int $bgCol): void
    {
        // For this style we no longer render a separate rounded panel; just leave the flat background.
        // So nothing extra to draw here beyond the already-filled canvas.
        return;
    }

    private function computeLayout(array $skin, int $width, int $height, int $segments): ?array
    {
        $panel = $this->computePanelBounds($skin, $width, $height);
        $segmentGap = max(12, (int) round(($skin['segmentGap'] ?? 22) * 0.75));
        $cardRadius = max(0, (int) ($skin['cardRadius'] ?? 16));
        $innerPad = 12;

        $contentX = $panel['panelX'] + $panel['borderWidth'] + $innerPad;
        $contentY = $panel['panelY'] + $panel['borderWidth'] + $innerPad;
        $contentW = $panel['panelW'] - 2 * ($panel['borderWidth'] + $innerPad);
        $contentH = $panel['panelH'] - 2 * ($panel['borderWidth'] + $innerPad);

        // Cards: slightly shorter than full content height so that
        // number boxes and labels together sit more vertically centered.
        $cardHeight = (int) round($contentH * 0.98);
        $totalGap = ($segments - 1) * $segmentGap;
        $cardWidth = (int) (($contentW - $totalGap) / $segments);
        // Center card block vertically within content area
        $cardY = $contentY + (int) round(($contentH - $cardHeight) / 2);

        // Number box: balanced padding, leave more room below for label text
        $boxPadX = (int) round($cardWidth * 0.08);
        $boxPadY = (int) round($cardHeight * 0.16);
        $boxW = $cardWidth - 2 * $boxPadX;
        $boxH = (int) round($cardHeight * 0.50); // box height – leave more room for labels
        $cardX = $contentX + (int) (($contentW - ($segments * $cardWidth + $totalGap)) / 2);

        return array_merge($panel, [
            'segmentGap' => $segmentGap,
            'cardRadius' => $cardRadius,
            'cardX' => $cardX,
            'cardY' => $cardY,
            'cardWidth' => $cardWidth,
            'cardHeight' => $cardHeight,
            'boxPadX' => $boxPadX,
            'boxPadY' => $boxPadY,
            'boxW' => $boxW,
            'boxH' => $boxH,
        ]);
    }

    private function computePanelBounds(array $skin, int $width, int $height): array
    {
        $panelRadius = max(0, (int) ($skin['panelRadius'] ?? 28));
        $borderWidth = max(0, min(1, (int) ($skin['borderWidth'] ?? 1)));
        $outerMargin = max(33, (int) round(min($width, $height) * 0.10));

        $panelX = $outerMargin;
        $panelY = $outerMargin-20;
        $panelW = $width - 2 * $outerMargin;
        $panelH = $height - 2 * $outerMargin+40;
        $panelR = min($panelRadius, (int) floor(min($panelW, $panelH) / 4));

        return [
            'panelX' => $panelX,
            'panelY' => $panelY,
            'panelW' => $panelW,
            'panelH' => $panelH,
            'panelR' => $panelR,
            'borderWidth' => $borderWidth,
        ];
    }

    private function getVisibleUnits(array $skin): array
    {
        $units = [];
        if (($skin['showDays'] ?? true) === true) { $units[] = 'd'; }
        if (($skin['showHours'] ?? true) === true) { $units[] = 'h'; }
        if (($skin['showMinutes'] ?? true) === true) { $units[] = 'm'; }
        if (($skin['showSeconds'] ?? true) === true) { $units[] = 's'; }
        return empty($units) ? ['s'] : $units;
    }

    private function getVisibleUnitsWithValues(array $skin, int $secondsRemaining): array
    {
        $days = intdiv($secondsRemaining, 86400);
        $hours = intdiv($secondsRemaining % 86400, 3600);
        $minutes = intdiv($secondsRemaining % 3600, 60);
        $seconds = $secondsRemaining % 60;

        $units = [];
        if (($skin['showDays'] ?? true) === true) { $units[] = ['value' => $days, 'label' => 'DAYS']; }
        if (($skin['showHours'] ?? true) === true) { $units[] = ['value' => $hours, 'label' => 'HOURS']; }
        if (($skin['showMinutes'] ?? true) === true) { $units[] = ['value' => $minutes, 'label' => 'MINUTES']; }
        if (($skin['showSeconds'] ?? true) === true) { $units[] = ['value' => $seconds, 'label' => 'SECONDS']; }
        return empty($units) ? [['value' => $seconds, 'label' => 'SECONDS']] : $units;
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

    private function fillRoundedRect($im, int $x, int $y, int $w, int $h, int $r, int $color): void
    {
        $r = min($r, (int) floor(min($w, $h) / 2));
        if ($r <= 0) {
            imagefilledrectangle($im, $x, $y, $x + $w, $y + $h, $color);
            return;
        }
        imagefilledrectangle($im, $x + $r, $y, $x + $w - $r, $y + $h, $color);
        imagefilledrectangle($im, $x, $y + $r, $x + $r, $y + $h - $r, $color);
        imagefilledrectangle($im, $x + $w - $r, $y + $r, $x + $w, $y + $h - $r, $color);
        imagefilledellipse($im, $x + $r, $y + $r, 2 * $r, 2 * $r, $color);
        imagefilledellipse($im, $x + $w - $r, $y + $r, 2 * $r, 2 * $r, $color);
        imagefilledellipse($im, $x + $r, $y + $h - $r, 2 * $r, 2 * $r, $color);
        imagefilledellipse($im, $x + $w - $r, $y + $h - $r, 2 * $r, 2 * $r, $color);
    }

    private function strokeRoundedRect($im, int $x, int $y, int $w, int $h, int $r, int $color, int $thickness): void
    {
        for ($t = 0; $t < $thickness; $t++) {
            $ox = $x + $t;
            $oy = $y + $t;
            $ow = $w - 2 * $t;
            $oh = $h - 2 * $t;
            $ir = max(0, $r - $t);
            if ($ir <= 0) {
                imagerectangle($im, $ox, $oy, $ox + $ow, $oy + $oh, $color);
            } else {
                $this->drawRoundedRectOutline($im, $ox, $oy, $ow, $oh, $ir, $color);
            }
        }
    }

    private function drawRoundedRectOutline($im, int $x, int $y, int $w, int $h, int $r, int $color): void
    {
        imageline($im, $x + $r, $y, $x + $w - $r, $y, $color);
        imageline($im, $x + $r, $y + $h, $x + $w - $r, $y + $h, $color);
        imageline($im, $x, $y + $r, $x, $y + $h - $r, $color);
        imageline($im, $x + $w, $y + $r, $x + $w, $y + $h - $r, $color);
        imagearc($im, $x + $r, $y + $r, 2 * $r, 2 * $r, 180, 270, $color);
        imagearc($im, $x + $w - $r, $y + $r, 2 * $r, 2 * $r, 270, 360, $color);
        imagearc($im, $x + $r, $y + $h - $r, 2 * $r, 2 * $r, 90, 180, $color);
        imagearc($im, $x + $w - $r, $y + $h - $r, 2 * $r, 2 * $r, 0, 90, $color);
    }

    private function textBoxWidth(string $text, string $fontPath, int $fontSize): int
    {
        $box = imagettfbbox($fontSize, 0, $fontPath, $text) ?: [0, 0, 0, 0, 0, 0, 0, 0];
        $minX = min($box[0], $box[2], $box[4], $box[6]);
        $maxX = max($box[0], $box[2], $box[4], $box[6]);
        return (int) ($maxX - $minX);
    }

    private function textVerticalCenterOffset(string $text, string $fontPath, int $fontSize): float
    {
        $box = imagettfbbox($fontSize, 0, $fontPath, $text) ?: [0, 0, 0, 0, 0, 0, 0, 0];
        return ($box[7] + $box[1]) / 2.0;
    }
}
