<?php

namespace App\Services\Skins\Renderers;

use App\Services\Skins\SkinRendererInterface;

class EmojiBadgeRenderer implements SkinRendererInterface
{
    public function renderStyle($im, array $skin, int $width, int $height, int $bgCol, int $textCol, int $accentCol, int $secondsRemaining): void
    {
        // Filled badges for each unit; emoji will be drawn via text layer
        imagefilledrectangle($im, 0, 0, $width, $height, $bgCol);
        $segments = 4;
        $unitWidth = $width / $segments;
        $centerY = (int) ($height / 2);
        $radius = (int) round(min($unitWidth / 2.6, $height / 2.6));
        for ($i = 0; $i < $segments; $i++) {
            $cx = (int) ($unitWidth * $i + $unitWidth / 2);
            imagefilledellipse($im, $cx, $centerY, $radius * 2, $radius * 2, $accentCol);
        }
    }

    public function renderTimeText($im, int $secondsRemaining, array $skin, int $width, int $height, int $textCol, int $accentCol): bool
    {
        // Attempt to draw emojis with TTF; if font lacks emojis, they appear as monochrome glyphs
        $font = (string) ($skin['fontPath'] ?? '');
        $fontSize = (int) ($skin['fontSize'] ?? 28);
        $labelSize = max(10, (int) round($fontSize * 0.28));
        $emojiSize = max(14, (int) round($fontSize * 0.9));

        $days = intdiv($secondsRemaining, 86400);
        $hours = intdiv($secondsRemaining % 86400, 3600);
        $minutes = intdiv($secondsRemaining % 3600, 60);
        $seconds = $secondsRemaining % 60;

        $units = [];
        if (($skin['showDays'] ?? true) === true)    { $units[] = ['v'=>$days,   'l'=>'DAYS',    'e'=>"📅"]; }
        if (($skin['showHours'] ?? true) === true)   { $units[] = ['v'=>$hours,  'l'=>'HOURS',   'e'=>"⏰"]; }
        if (($skin['showMinutes'] ?? true) === true) { $units[] = ['v'=>$minutes,'l'=>'MINUTES', 'e'=>"⏱️"]; }
        if (($skin['showSeconds'] ?? true) === true) { $units[] = ['v'=>$seconds,'l'=>'SECONDS', 'e'=>"⚡"]; }
        if (!$units) { $units[] = ['v'=>$seconds,'l'=>'SECONDS','e'=>"⚡"]; }

        $segments = count($units);
        $unitWidth = $width / $segments;
        $centerY = (int) ($height / 2);

        if (is_file($font)) {
            for ($i = 0; $i < $segments; $i++) {
                $cx = (int) ($unitWidth * $i + $unitWidth / 2);
                $emoji = $units[$i]['e'];
                $eW = $this->w($emoji, $font, $emojiSize);
                imagettftext($im, $emojiSize, 0, (int)($cx - $eW / 2), (int)($centerY - 6), $textCol, $font, $emoji);
                $num = sprintf('%02d', $units[$i]['v']);
                $nW = $this->w($num, $font, $fontSize);
                imagettftext($im, $fontSize, 0, (int)($cx - $nW / 2), (int)($centerY + (int)round($fontSize*0.9)), $textCol, $font, $num);
            }
        } else {
            $built = 5;
            for ($i = 0; $i < $segments; $i++) {
                $cx = (int) ($unitWidth * $i + $unitWidth / 2);
                $num = sprintf('%02d', $units[$i]['v']);
                $nW = imagefontwidth($built) * strlen($num);
                imagestring($im, $built, (int)($cx - $nW / 2), (int)($centerY), $num, $textCol);
            }
        }
        return true;
    }

    private function w(string $t, string $f, int $s): int
    {
        $b = imagettfbbox($s, 0, $f, $t) ?: [0,0,0,0,0,0,0,0];
        return (int)(max($b[0],$b[2],$b[4],$b[6]) - min($b[0],$b[2],$b[4],$b[6]));
    }
}

?>


