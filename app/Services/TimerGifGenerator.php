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
        $durationForGif = min(60, max(1, $remaining));

        $skin = $this->loadSkin($timer->skin);

        $frames = $this->buildFrames($durationForGif, $skin, $timer->custom_text, $timer->text_position);

        $this->encodeAnimatedGif($frames, $fullPath);

        $url = rtrim(config('app.url'), '/') . "/timers/{$userId}/{$filename}";
        return $url;
    }

    public function regenerateForCache(Timer $timer, string $targetPath): void
    {
        $remaining = $timer->remainingSecondsFromNow();
        $durationForGif = min(60, max(1, $remaining));
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
        return array_merge([
            'width' => 400,
            'height' => 160,
            'background' => '#000000',
            'textColor' => '#FFFFFF',
            'accentColor' => '#FFCC00',
            'fontSize' => 36,
            'fontPath' => resource_path('fonts/DejaVuSans.ttf'),
        ], $json);
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
        $width = (int) $skin['width'];
        $height = (int) $skin['height'];

        $im = imagecreatetruecolor($width, $height);
        [$br, $bg, $bb] = $this->hexToRgb($skin['background']);
        [$tr, $tg, $tb] = $this->hexToRgb($skin['textColor']);

        $bgCol = imagecolorallocate($im, $br, $bg, $bb);
        $textCol = imagecolorallocate($im, $tr, $tg, $tb);
        imagefilledrectangle($im, 0, 0, $width, $height, $bgCol);

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
        $width = (int) $skin['width'];
        $height = (int) $skin['height'];

        $im = imagecreatetruecolor($width, $height);
        [$br, $bg, $bb] = $this->hexToRgb($skin['background']);
        [$tr, $tg, $tb] = $this->hexToRgb($skin['textColor']);
        $bgCol = imagecolorallocate($im, $br, $bg, $bb);
        $textCol = imagecolorallocate($im, $tr, $tg, $tb);
        imagefilledrectangle($im, 0, 0, $width, $height, $bgCol);

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
        $minutes = intdiv($seconds, 60);
        $secs = $seconds % 60;
        return sprintf('%02d:%02d', $minutes, $secs);
    }
}


