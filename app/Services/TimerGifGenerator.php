<?php

namespace App\Services;

use App\Models\Timer;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\File;

class TimerGifGenerator
{
    public function generateAndStore(Timer $timer): string
    {
        $userId = $timer->user_id;
        $directory = public_path("timers/{$userId}");
        if (!File::exists($directory)) {
            File::makeDirectory($directory, 0755, true);
        }

        $unique = bin2hex(random_bytes(5));
        $filename = $unique . '.gif';
        $fullPath = $directory . DIRECTORY_SEPARATOR . $filename;

        $remaining = $timer->remainingSecondsFromNow();
        // For reload-based timers, use the full duration; for fixed-date, limit to 60s for performance
        if ($timer->isReloadBased()) {
            $durationForGif = max(1, $remaining);
        } else {
            $durationForGif = min(60, max(1, $remaining));
        }

        $skin = $this->loadSkin($timer->skin);
        // Override skin size with timer-specific dimensions if provided
        if (!empty($timer->width) && !empty($timer->height)) {
            $skin['width'] = max(120, min(1200, (int) $timer->width));
            $skin['height'] = max(80, min(800, (int) $timer->height));
            // Adjust font size proportionally
            $skin['fontSize'] = max(12, (int) round(($skin['height']) * 0.28));
        }

        $frames = $this->buildFrames($durationForGif, $skin, $timer->custom_text, $timer->text_position);

        $this->encodeAnimatedGif($frames, $fullPath);

        $url = "/timers/{$userId}/{$filename}";
        return $url;
    }

    public function regenerateForCache(Timer $timer, string $targetPath): void
    {
        $remaining = $timer->remainingSecondsFromNow();
        // For reload-based timers, use the full duration; for fixed-date, limit to 60s for performance
        if ($timer->isReloadBased()) {
            $durationForGif = max(1, $remaining);
        } else {
            $durationForGif = min(60, max(1, $remaining));
        }
        $skin = $this->loadSkin($timer->skin);
        $frames = $this->buildFrames($durationForGif, $skin, $timer->custom_text, $timer->text_position);
        $this->encodeAnimatedGif($frames, $targetPath);
    }

    private function loadSkin(string $skinName): array
    {
        $path = resource_path('timerskins/' . $skinName . '.json');
        if (!file_exists($path)) {
            return [
                'width' => 400,
                'height' => 160,
                'background' => '#000000',
                'textColor' => '#FFFFFF',
                'accentColor' => '#FFCC00',
                'fontSize' => 36,
                'fontPath' => resource_path('fonts/DejaVuSans.ttf'),
            ];
        }
        $json = json_decode(file_get_contents($path), true) ?: [];
        $skin = array_merge([
            'width' => 400,
            'height' => 160,
            'background' => '#000000',
            'textColor' => '#FFFFFF',
            'accentColor' => '#FFCC00',
            'fontSize' => 36,
            'fontPath' => resource_path('fonts/DejaVuSans.ttf'),
        ], $json);
        // Validate
        $skin['width'] = max(120, min(1200, (int) ($skin['width'] ?? 400)));
        $skin['height'] = max(80, min(800, (int) ($skin['height'] ?? 160)));
        $skin['fontSize'] = max(12, (int) ($skin['fontSize'] ?? 36));
        if (empty($skin['background']) || !is_string($skin['background'])) $skin['background'] = '#000000';
        if (empty($skin['textColor']) || !is_string($skin['textColor'])) $skin['textColor'] = '#FFFFFF';
        if (empty($skin['accentColor']) || !is_string($skin['accentColor'])) $skin['accentColor'] = '#FFCC00';
        if (empty($skin['fontPath']) || !is_string($skin['fontPath'])) $skin['fontPath'] = resource_path('fonts/DejaVuSans.ttf');
        return $skin;
    }

    private function buildFrames(int $seconds, array $skin, ?string $customText, string $textPosition): array
    {
        $frames = [];
        for ($i = $seconds; $i >= 0; $i--) {
            $frames[] = $this->renderPngFrame($i, $skin, $customText, $textPosition);
        }
        if ($seconds === 0) {
            $frames[] = $this->renderFinalFrame($skin, $customText, $textPosition);
        }
        return $frames;
    }

    private function renderPngFrame(int $secondsRemaining, array $skin, ?string $customText, string $textPosition): string
    {
        $width = max(120, (int) $skin['width']);
        $height = max(80, (int) $skin['height']);

        $im = imagecreatetruecolor($width, $height);
        [$br, $bg, $bb] = $this->hexToRgb($skin['background']);
        [$tr, $tg, $tb] = $this->hexToRgb($skin['textColor']);

        $bgCol = imagecolorallocate($im, $br, $bg, $bb);
        $textCol = imagecolorallocate($im, $tr, $tg, $tb);
        // Rounded background for nicer skins
        $this->drawRoundedRect($im, 0, 0, $width, $height, 12, $bgCol);

        $timeText = $this->formatSeconds($secondsRemaining);
        $font = $skin['fontPath'];
        $fontSize = (int) $skin['fontSize'];

        if (is_file($font)) {
            $centerY = (int) ($height / 2 + $fontSize / 2);
            $centerX = (int) ($width / 2 - $this->textBoxWidth($timeText, $font, $fontSize) / 2);
            imagettftext($im, $fontSize, 0, $centerX, $centerY, $textCol, $font, $timeText);
        } else {
            // Fallback to built-in font
            $builtIn = 5; // GD built-in font size
            $textWidth = imagefontwidth($builtIn) * strlen($timeText);
            $textHeight = imagefontheight($builtIn);
            $x = (int) (($width - $textWidth) / 2);
            $y = (int) (($height - $textHeight) / 2);
            imagestring($im, $builtIn, $x, $y, $timeText, $textCol);
        }

        if ($customText && $textPosition !== 'none') {
            if (is_file($font)) {
                $smallSize = max(12, (int) round($fontSize * 0.5));
                $textWidth = $this->textBoxWidth($customText, $font, $smallSize);
                $x = max(5, (int) (($width - $textWidth) / 2));
                $y = $textPosition === 'top' ? (int) (10 + $smallSize) : (int) ($height - 10);
                imagettftext($im, $smallSize, 0, $x, $y, $textCol, $font, $customText);
            } else {
                $builtIn = 3;
                $textWidth = imagefontwidth($builtIn) * strlen($customText);
                $x = max(5, (int) (($width - $textWidth) / 2));
                $y = $textPosition === 'top' ? 5 : (int) ($height - imagefontheight($builtIn) - 5);
                imagestring($im, $builtIn, $x, $y, $customText, $textCol);
            }
        }

        ob_start();
        imagepng($im);
        $pngData = ob_get_clean();
        imagedestroy($im);
        return $pngData;
    }

    private function renderFinalFrame(array $skin, ?string $customText, string $textPosition): string
    {
        $width = max(120, (int) $skin['width']);
        $height = max(80, (int) $skin['height']);

        $im = imagecreatetruecolor($width, $height);
        [$br, $bg, $bb] = $this->hexToRgb($skin['background']);
        [$tr, $tg, $tb] = $this->hexToRgb($skin['textColor']);
        $bgCol = imagecolorallocate($im, $br, $bg, $bb);
        $textCol = imagecolorallocate($im, $tr, $tg, $tb);
        $this->drawRoundedRect($im, 0, 0, $width, $height, 12, $bgCol);

        $font = $skin['fontPath'];
        $fontSize = (int) $skin['fontSize'];
        $msg = "Time's Up";
        if (is_file($font)) {
            $centerY = (int) ($height / 2 + $fontSize / 2);
            $centerX = (int) ($width / 2 - $this->textBoxWidth($msg, $font, $fontSize) / 2);
            imagettftext($im, $fontSize, 0, $centerX, $centerY, $textCol, $font, $msg);
        } else {
            $builtIn = 5;
            $textWidth = imagefontwidth($builtIn) * strlen($msg);
            $textHeight = imagefontheight($builtIn);
            $x = (int) (($width - $textWidth) / 2);
            $y = (int) (($height - $textHeight) / 2);
            imagestring($im, $builtIn, $x, $y, $msg, $textCol);
        }

        if ($customText && $textPosition !== 'none') {
            if (is_file($font)) {
                $smallSize = max(12, (int) round($fontSize * 0.5));
                $textWidth = $this->textBoxWidth($customText, $font, $smallSize);
                $x = max(5, (int) (($width - $textWidth) / 2));
                $y = $textPosition === 'top' ? (int) (10 + $smallSize) : (int) ($height - 10);
                imagettftext($im, $smallSize, 0, $x, $y, $textCol, $font, $customText);
            } else {
                $builtIn = 3;
                $textWidth = imagefontwidth($builtIn) * strlen($customText);
                $x = max(5, (int) (($width - $textWidth) / 2));
                $y = $textPosition === 'top' ? 5 : (int) ($height - imagefontheight($builtIn) - 5);
                imagestring($im, $builtIn, $x, $y, $customText, $textCol);
            }
        }

        ob_start();
        imagepng($im);
        $pngData = ob_get_clean();
        imagedestroy($im);
        return $pngData;
    }

    private function encodeAnimatedGif(array $pngFrames, string $outputPath): void
    {
        // Try Imagick first if available
        if (class_exists(\Imagick::class)) {
            $imagick = new \Imagick();
            $imagick->setFormat('gif');
            foreach ($pngFrames as $frame) {
                $frameImagick = new \Imagick();
                $frameImagick->readImageBlob($frame);
                $frameImagick->setImageFormat('gif');
                $frameImagick->setImageDelay(100); // 10 fps -> 0.1s per frame
                $imagick->addImage($frameImagick);
            }
            $imagick = $imagick->coalesceImages();
            $imagick->writeImages($outputPath, true);
            return;
        }

        // Fallback to pure-PHP encoder
        $encoder = new GifCreator();
        $durations = array_fill(0, count($pngFrames), 10); // 10 = 0.1s per frame
        $encoder->create($pngFrames, $durations, 0);
        File::put($outputPath, $encoder->getGif());
    }

    private function hexToRgb(string $hex): array
    {
        $hex = ltrim($hex, '#');
        if (strlen($hex) === 3) {
            $hex = $hex[0].$hex[0].$hex[1].$hex[1].$hex[2].$hex[2];
        }
        return [
            hexdec(substr($hex, 0, 2)),
            hexdec(substr($hex, 2, 2)),
            hexdec(substr($hex, 4, 2)),
        ];
    }

    private function textBoxWidth(string $text, string $fontPath, int $fontSize): int
    {
        $box = imagettfbbox($fontSize, 0, $fontPath, $text) ?: [0,0,0,0,0,0,0,0];
        $minX = min($box[0], $box[2], $box[4], $box[6]);
        $maxX = max($box[0], $box[2], $box[4], $box[6]);
        return (int) ($maxX - $minX);
    }

    private function formatSeconds(int $seconds): string
    {
        $hours = intdiv($seconds, 3600);
        $minutes = intdiv($seconds % 3600, 60);
        $secs = $seconds % 60;
        return sprintf('%02d:%02d:%02d', $hours, $minutes, $secs);
    }

    private function drawRoundedRect($im, int $x, int $y, int $w, int $h, int $r, int $color): void
    {
        // Clamp radius
        $r = max(0, min((int) floor(min($w, $h) / 2), $r));
        // Center
        imagefilledrectangle($im, $x + $r, $y, $x + $w - $r, $y + $h, $color);
        imagefilledrectangle($im, $x, $y + $r, $x + $w, $y + $h - $r, $color);
        // Corners
        imagefilledellipse($im, $x + $r, $y + $r, $r * 2, $r * 2, $color);
        imagefilledellipse($im, $x + $w - $r, $y + $r, $r * 2, $r * 2, $color);
        imagefilledellipse($im, $x + $r, $y + $h - $r, $r * 2, $r * 2, $color);
        imagefilledellipse($im, $x + $w - $r, $y + $h - $r, $r * 2, $r * 2, $color);
    }
}


