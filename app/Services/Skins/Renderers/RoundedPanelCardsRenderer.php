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
        $accentBorderWidth = max(0, min(3, (int) ($skin['numberBoxBorderWidth'] ?? 3)));
        $neutralBorderWidth = 1;
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
            $boxBorderW = $isHighlighted ? $accentBorderWidth : $neutralBorderWidth;

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
        $fontSize = (int) ($skin['fontSize'] ?? 52);
        $labelFontSize = (int) ($skin['labelFontSize'] ?? 18);
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

        $numberCenterY = $layout['cardY'] + $layout['boxPadY'] + $layout['boxH'] / 2;
        // Place labels using a proportional gap so the combined
        // number + label block is vertically centered within the card.
        $labelGap = (int) round($layout['cardHeight'] * 0.16);
        $labelY = $layout['cardY'] + $layout['boxPadY'] + $layout['boxH'] + $labelGap;

        if (is_file($font)) {
            for ($i = 0; $i < $segments; $i++) {
                $cardX = $layout['cardX'] + $i * ($layout['cardWidth'] + $layout['segmentGap']);
                $centerX = (int) ($cardX + $layout['cardWidth'] / 2);

                $value = $units[$i]['value'];
                $label = $units[$i]['label'];
                $isDays = ($label === 'DAYS');
                $numberText = $isDays ? (string) $value : sprintf('%02d', $value);

                $numberWidth = $this->textBoxWidth($numberText, $font, $fontSize);
                $numberBaseline = (int) round($numberCenterY - $this->textVerticalCenterOffset($numberText, $font, $fontSize));
                imagettftext($im, $fontSize, 0, (int) ($centerX - $numberWidth / 2), $numberBaseline, $textCol, $font, $numberText);

                $labelWidth = $this->textBoxWidth($label, $font, $labelFontSize);
                $labelBaseline = (int) round($labelY - $this->textVerticalCenterOffset($label, $font, $labelFontSize));
                imagettftext($im, $labelFontSize, 0, (int) ($centerX - $labelWidth / 2), $labelBaseline, $labelCol, $font, $label);
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
        $innerPad = 16;

        $contentX = $panel['panelX'] + $panel['borderWidth'] + $innerPad;
        $contentY = $panel['panelY'] + $panel['borderWidth'] + $innerPad;
        $contentW = $panel['panelW'] - 2 * ($panel['borderWidth'] + $innerPad);
        $contentH = $panel['panelH'] - 2 * ($panel['borderWidth'] + $innerPad);

        // Cards: slightly shorter than full content height so that
        // number boxes and labels together sit more vertically centered.
        $cardHeight = (int) round($contentH * 0.82);
        $totalGap = ($segments - 1) * $segmentGap;
        $cardWidth = (int) (($contentW - $totalGap) / $segments)-30;
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
