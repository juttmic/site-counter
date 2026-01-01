<?php

namespace App\Services;

use App\Models\Timer;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;
use App\Services\Skins\SkinRendererFactory;
use App\Services\Skins\SkinRendererInterface;

class TimerGifGenerator
{
    public function generateAndStore(Timer $timer): string
    {
        $userId = $timer->user_id;
        $directory = public_path("timers/{$userId}");
        if (!File::exists($directory)) {
            File::makeDirectory($directory, 0755, true);
        }

        $existingUrl = $timer->gif_url;
        $filename = null;
        if (!empty($existingUrl)) {
            $path = parse_url($existingUrl, PHP_URL_PATH) ?: $existingUrl;
            $candidate = basename($path);
            if (is_string($candidate) && $candidate !== '') {
                $filename = $candidate;
            }
        }

        if (!$filename || pathinfo($filename, PATHINFO_EXTENSION) !== 'gif') {
            $unique = bin2hex(random_bytes(5));
            $filename = $unique . '.gif';
        }

        $fullPath = $directory . DIRECTORY_SEPARATOR . $filename;

        $remaining = $timer->remainingSecondsFromNow();
        
        // For fixed-date timers: if expired, show only "Time's Up" or 0 seconds based on setting; otherwise countdown from remaining time
        // For reload-based timers: always show full countdown from duration
        if ($timer->isFixedDate()) {
            if ($remaining <= 0) {
                // Timer expired - show final frame or 0 seconds based on show_final_message setting
                $durationForGif = 0;
            } else {
                // For fixed-date timers, limit GIF to 5 minutes max for performance
                // The GIF will regenerate when accessed to show updated remaining time
                $durationForGif = min(300, max(1, $remaining));
            }
        } else {
            // Reload-based: use full duration (always shows the same countdown)
            $durationForGif = max(1, $remaining);
        }

        $skin = $this->loadSkin($timer->skin);
        // Apply per-timer overrides if present (preferred source for width/height and other settings)
        // customTextColor, customTextMarginTop, customTextMarginBottom, show_final_message are stored in skin_overrides
        if (is_array($timer->skin_overrides) && !empty($timer->skin_overrides)) {
            $skin = array_replace_recursive($skin, $timer->skin_overrides);
        }
        $skin = $this->applyFinalColorDefaults($skin);

        $frameLimit = $this->resolveFrameLimit($timer);
        // Cap animation length to configured limit but start from actual remaining
        $animateFor = min($frameLimit, max(0, $durationForGif));
        $frames = $this->buildFramesCapped($remaining, $animateFor, $skin, $timer->custom_text, $timer->text_position);

        $this->encodeAnimatedGif($frames, $fullPath, $animateFor);

        $url = "/timers/{$userId}/{$filename}";
        return $url;
    }

    public function regenerateForCache(Timer $timer, string $targetPath, int $maxAnimateFor = 60): void
    {
        $remaining = $timer->remainingSecondsFromNow();
        
        // For fixed-date timers: if expired, show only "Time's Up" or 0 seconds based on setting; otherwise countdown from remaining time
        // For reload-based timers: always show full countdown from duration
        if ($timer->isFixedDate()) {
            if ($remaining <= 0) {
                // Timer expired - show final frame or 0 seconds based on show_final_message setting
                $durationForGif = 0;
            } else {
                // For fixed-date timers, limit GIF to 5 minutes max for performance
                // The GIF will regenerate when accessed to show updated remaining time
                $durationForGif = min(300, max(1, $remaining));
            }
        } else {
            // Reload-based: use full duration (always shows the same countdown)
            $durationForGif = max(1, $remaining);
        }
        $skin = $this->loadSkin($timer->skin);
        // Apply per-timer overrides if present (customTextColor, margins, show_final_message are in skin_overrides)
        if (is_array($timer->skin_overrides) && !empty($timer->skin_overrides)) {
            $skin = array_replace_recursive($skin, $timer->skin_overrides);
        }
        $skin = $this->applyFinalColorDefaults($skin);
        
        $frameLimit = $this->resolveFrameLimit($timer);
        $animateFor = min($frameLimit, $maxAnimateFor, max(0, $durationForGif));
        $frames = $this->buildFramesCapped($remaining, $animateFor, $skin, $timer->custom_text, $timer->text_position);
        $this->encodeAnimatedGif($frames, $targetPath, $animateFor);
    }

    /**
     * Render a 1-frame PNG preview for a given skin into the provided output path.
     */
    public function renderSkinPreview(string $skinName, string $outputPath, ?string $customText = null, string $textPosition = 'none', ?string $customTextColor = null, array $overrides = []): void
    {
        $skin = $this->loadSkin($skinName);
        if ($customTextColor) {
            $skin['customTextColor'] = $customTextColor;
        }
        if (!empty($overrides)) {
            $skin = array_replace_recursive($skin, $overrides);
        }
        $skin = $this->applyFinalColorDefaults($skin);
        // Use an illustrative sample remaining time (12h 34m 56s)
        $sampleSeconds = (12 * 3600) + (34 * 60) + 56;
        $png = $this->renderPngFrame($sampleSeconds, $skin, $customText, $textPosition);
        // Normalize height across previews while preserving aspect ratio
        $targetHeight = 120; // px
        $img = imagecreatefromstring($png);
        if ($img !== false) {
            $origW = imagesx($img);
            $origH = imagesy($img);
            $ratio = $origW / max(1, $origH);
            $targetWidth = (int) round($targetHeight * $ratio);
            $dst = imagecreatetruecolor($targetWidth, $targetHeight);
            imagecopyresampled($dst, $img, 0, 0, 0, 0, $targetWidth, $targetHeight, $origW, $origH);
            ob_start();
            imagepng($dst);
            $png = ob_get_clean();
            imagedestroy($dst);
            imagedestroy($img);
        }
        \Illuminate\Support\Facades\File::ensureDirectoryExists(dirname($outputPath));
        \Illuminate\Support\Facades\File::put($outputPath, $png);
    }

    /**
     * Render a short animated GIF preview for a given skin.
     * Generates a looping countdown preview that restarts after the animation duration.
     * Never shows "Time's Up" - instead loops continuously by counting down and restarting.
     * 
     * @param int $seconds Starting countdown time in seconds (e.g., 90000 for ~25 hours)
     * @param int $animateFor Duration to animate in seconds (default 30, max 60)
     */
    public function renderSkinGifPreview(string $skinName, string $outputPath, int $seconds = 6, ?string $customText = null, string $textPosition = 'none', ?string $customTextColor = null, array $overrides = [], ?int $animateFor = null): void
    {
        $skin = $this->loadSkin($skinName);
        if ($customTextColor) {
            $skin['customTextColor'] = $customTextColor;
        }
        if (!empty($overrides)) {
            $skin = array_replace_recursive($skin, $overrides);
        }
        
        // Use provided seconds as starting countdown time
        $start = max(1, $seconds);
        
        // Use provided animateFor or default to 30 seconds (max 60 for previews)
        $animationDuration = $animateFor ?? 30;
        $animationDuration = min(60, max(1, $animationDuration));
        
        // Build frames counting down from start for animationDuration seconds
        $frames = [];
        $step = 1; // 1 second per frame for smooth preview
        
        // Count down from start for animationDuration seconds
        // This will show: start, start-1, start-2, ..., start-animationDuration
        // The GIF will loop naturally back to the first frame (start value)
        for ($elapsed = 0; $elapsed <= $animationDuration; $elapsed += $step) {
            $remaining = max(0, $start - $elapsed);
            // Never show "Time's Up" - just show the countdown, even if it reaches 0
            $frames[] = $this->renderPngFrame($remaining, $skin, $customText, $textPosition);
        }
        
        // The GIF will loop naturally back to the first frame (start value)
        // This creates a continuous countdown that restarts seamlessly
        
        \Illuminate\Support\Facades\File::ensureDirectoryExists(dirname($outputPath));
        $this->encodeAnimatedGif($frames, $outputPath, $animationDuration);
    }

    public function loadSkin(string $skinName): array
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
                'fontPath' => resource_path('fonts/dejavu-sans.book.ttf'),
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
            'fontPath' => resource_path('fonts/dejavu-sans.book.ttf'),
            // Optional customizations with sensible defaults
            'finalMessage' => "Time's Up",
            'finalBackground' => null, // defaults to background if null
            'finalTextColor' => null,  // defaults to textColor if null
            'borderWidth' => 0,
            'borderColor' => '#FFFFFF',
        ], $json);
        // Validate
        $skin['width'] = max(120, min(1200, (int) ($skin['width'] ?? 400)));
        $skin['height'] = max(80, min(800, (int) ($skin['height'] ?? 160)));
        $skin['fontSize'] = max(12, (int) ($skin['fontSize'] ?? 36));
        if (empty($skin['background']) || !is_string($skin['background'])) $skin['background'] = '#000000';
        if (empty($skin['textColor']) || !is_string($skin['textColor'])) $skin['textColor'] = '#FFFFFF';
        if (empty($skin['accentColor']) || !is_string($skin['accentColor'])) $skin['accentColor'] = '#FFCC00';
        if (empty($skin['fontPath']) || !is_string($skin['fontPath'])) {
            $skin['fontPath'] = resource_path('fonts/dejavu-sans.book.ttf');
        }
        // Normalize fontPath to an absolute, web-safe path
        $fontPath = (string) $skin['fontPath'];
        if (!is_file($fontPath)) {
            // Common case: JSON uses "resources/..."
            if (Str::startsWith($fontPath, 'resources/')) {
                $relative = Str::after($fontPath, 'resources/');
                $tryPath = resource_path($relative);
                if (is_file($tryPath)) {
                    $fontPath = $tryPath;
                }
            }
        }
        if (!is_file($fontPath)) {
            // Try fonts directory with just filename
            $basename = basename($fontPath);
            $tryPath = resource_path('fonts/' . $basename);
            if (is_file($tryPath)) {
                $fontPath = $tryPath;
            }
        }
        if (!is_file($fontPath)) {
            // Final fallback
            $fontPath = resource_path('fonts/dejavu-sans.book.ttf');
        }
        $skin['fontPath'] = $fontPath;
        // Normalize borderWidth
        $bw = (int) ($skin['borderWidth'] ?? 0);
        $skin['borderWidth'] = max(0, min(50, $bw));
        if (empty($skin['borderColor']) || !is_string($skin['borderColor'])) {
            $skin['borderColor'] = '#FFFFFF';
        }
        return $skin;
    }

    private function buildFrames(int $seconds, array $skin, ?string $customText, string $textPosition): array
    {
        $frames = [];
        
        // If timer expired (seconds = 0), show only "Time's Up" frame
        if ($seconds <= 0) {
            $frames[] = $this->renderFinalFrame($skin, $customText, $textPosition);
            return $frames;
        }
        
        // Optimize frame generation for better performance
        // For durations up to 60s, show every second
        // For 1-5 minutes, show every 2 seconds  
        // For longer durations, show every 5 seconds
        if ($seconds <= 60) {
            $step = 1;
        } elseif ($seconds <= 300) {
            $step = 2;
        } else {
            $step = 5;
        }
        
        // Generate frames counting down from seconds to 0
        for ($i = $seconds; $i > 0; $i -= $step) {
            $frames[] = $this->renderPngFrame($i, $skin, $customText, $textPosition);
        }
        
        // Always include the 0 second frame
        $frames[] = $this->renderPngFrame(0, $skin, $customText, $textPosition);
        
        // Always include the final "Time's Up" frame
        $frames[] = $this->renderFinalFrame($skin, $customText, $textPosition);
        
        return $frames;
    }

    /**
     * Build frames starting from the actual remaining time but animating for at most $animateSeconds.
     * This preserves the real countdown value while capping GIF duration.
     */
    private function buildFramesCapped(int $startingRemaining, int $animateSeconds, array $skin, ?string $customText, string $textPosition): array
    {
        $frames = [];
        $showFinalMessage = (bool) ($skin['show_final_message'] ?? false);
        
        // If nothing to animate, show final state(s)
        if ($startingRemaining <= 0) {
            if ($showFinalMessage) {
                $frames[] = $this->renderFinalFrame($skin, $customText, $textPosition);
            } else {
                // Show 0 seconds instead of final message
                $frames[] = $this->renderPngFrame(0, $skin, $customText, $textPosition);
            }
            return $frames;
        }
        $animateSeconds = max(0, min(60, $animateSeconds));
        if ($animateSeconds === 0) {
            $frames[] = $this->renderPngFrame($startingRemaining, $skin, $customText, $textPosition);
            return $frames;
        }

        // Step policy identical to original, using the capped duration
        if ($animateSeconds <= 60) {
            $step = 1;
        } elseif ($animateSeconds <= 300) {
            $step = 2;
        } else {
            $step = 5;
        }

        // Render from startingRemaining down for animateSeconds seconds
        for ($elapsed = 0; $elapsed < $animateSeconds; $elapsed += $step) {
            $remaining = max(0, $startingRemaining - $elapsed);
            $frames[] = $this->renderPngFrame($remaining, $skin, $customText, $textPosition);
        }

        // Always include the last animated second frame
        $lastRemaining = max(0, $startingRemaining - $animateSeconds);
        $frames[] = $this->renderPngFrame($lastRemaining, $skin, $customText, $textPosition);
        
        // Only add final frame if show_final_message is enabled
        // If disabled: reload-based timers will loop naturally, fixed-date will show 0 seconds
        if ($lastRemaining === 0 && $showFinalMessage) {
            // If we reached zero and final message is enabled, include the Time's Up frame
            $frames[] = $this->renderFinalFrame($skin, $customText, $textPosition);
        }
        return $frames;
    }

    private function applyFinalColorDefaults(array $skin): array
    {
        $bg = (string) ($skin['background'] ?? '#000000');
        $txt = (string) ($skin['textColor'] ?? '#FFFFFF');
        if (empty($skin['finalBackground'])) {
            $skin['finalBackground'] = $bg;
        }
        if (empty($skin['finalTextColor'])) {
            $skin['finalTextColor'] = $txt;
        }
        return $skin;
    }

    public function renderPngFrame(int $secondsRemaining, array $skin, ?string $customText, string $textPosition): string
    {
        // Base timer dimensions from skin
        $timerW = max(120, (int) ($skin['width'] ?? 400));
        $timerH = max(80, (int) ($skin['height'] ?? 160));

        // Resolve colors and font
        [$br, $bg, $bb] = $this->hexToRgb($skin['background'] ?? '#000000');
        [$tr, $tg, $tb] = $this->hexToRgb($skin['textColor'] ?? '#FFFFFF');
        [$ar, $ag, $ab] = $this->hexToRgb($skin['accentColor'] ?? '#FFCC00');
        $font = $skin['fontPath'] ?? '';
        $fontSize = (int) ($skin['fontSize'] ?? 36);

        // Render timer into off-screen surface
        $timerIm = imagecreatetruecolor($timerW, $timerH);
        $bgTimer = imagecolorallocate($timerIm, $br, $bg, $bb);
        $txtTimer = imagecolorallocate($timerIm, $tr, $tg, $tb);
        $accTimer = imagecolorallocate($timerIm, $ar, $ag, $ab);
        imagefilledrectangle($timerIm, 0, 0, $timerW, $timerH, $bgTimer);

        $this->applySkinStyle($timerIm, $skin, $timerW, $timerH, $bgTimer, $txtTimer, $accTimer, $secondsRemaining);

        $renderer = SkinRendererFactory::resolve($skin['style'] ?? null);
        if ($renderer instanceof SkinRendererInterface && $renderer->renderTimeText($timerIm, $secondsRemaining, $skin, $timerW, $timerH, $txtTimer, $accTimer)) {
            // handled by renderer
        } else {
            // Fallback timer text layout
            $sep = (string)($skin['separator'] ?? ':');
            if ($sep === '') { $sep = ':'; }
            $days = intdiv($secondsRemaining, 86400);
            $hours = intdiv($secondsRemaining % 86400, 3600);
            $minutes = intdiv($secondsRemaining % 3600, 60);
            $secs = $secondsRemaining % 60;
            $parts = [];
            if (($skin['showDays'] ?? true) === true)    { $parts[] = sprintf('%02d', $days); }
            if (($skin['showHours'] ?? true) === true)   { $parts[] = sprintf('%02d', $hours); }
            if (($skin['showMinutes'] ?? true) === true) { $parts[] = sprintf('%02d', $minutes); }
            if (($skin['showSeconds'] ?? true) === true) { $parts[] = sprintf('%02d', $secs); }
            if (empty($parts)) { $parts[] = sprintf('%02d', $secs); }
            $timeText = implode($sep, $parts);

            if (is_file($font)) {
                $cx = (int) ($timerW / 2 - $this->textBoxWidth($timeText, $font, $fontSize) / 2);
                $cy = (int) ($timerH / 2 + $fontSize / 2);
                $txtColor = $txtTimer;
                imagettftext($timerIm, $fontSize, 0, $cx, $cy, $txtColor, $font, $timeText);
            } else {
                $builtIn = 5;
                $tw = imagefontwidth($builtIn) * strlen($timeText);
                $th = imagefontheight($builtIn);
                $x = (int) (($timerW - $tw) / 2);
                $y = (int) (($timerH - $th) / 2);
                imagestring($timerIm, $builtIn, $x, $y, $timeText, $txtTimer);
            }
        }

        // Custom text measurement
        $hasText = ($customText !== null && $customText !== '' && $textPosition !== 'none');
        $padX = 10; // horizontal padding when text is wider than timer
        $textW = 0; $textH = 0; $textSize = max(12, (int) round($fontSize * 0.5));
        $textHeightFromBaseline = 0; // Height from baseline to top of text
        if ($hasText) {
            if (is_file($font)) {
                $box = imagettfbbox($textSize, 0, $font, $customText);
                $textW = abs($box[4] - $box[0]);
                $textH = abs($box[5] - $box[1]);
                // Get the actual text height from baseline to top (for positioning)
                // box[1] is typically negative, representing distance from baseline to top
                $textHeightFromBaseline = abs($box[7] - $box[1]); // Distance from baseline to top
            } else {
                $bi = 3;
                $textW = imagefontwidth($bi) * strlen($customText);
                $textH = imagefontheight($bi);
                $textHeightFromBaseline = $textH;
            }
        }

        // Read optional overall output size overrides
        $requestedW = isset($skin['outputWidth']) ? (int) $skin['outputWidth'] : null;
        $requestedH = isset($skin['outputHeight']) ? (int) $skin['outputHeight'] : null;
        // Read optional top/bottom margins for custom text
        $marginTop = isset($skin['customTextMarginTop']) ? max(0, (int)$skin['customTextMarginTop']) : 10;
        $marginBottom = isset($skin['customTextMarginBottom']) ? max(0, (int)$skin['customTextMarginBottom']) : 10;

        $canvasW = max($timerW, $hasText ? ($textW + 2 * $padX) : $timerW);
        $extraTop = ($hasText && $textPosition === 'top') ? ($textH + $marginTop) : 0;
        $extraBottom = ($hasText && $textPosition === 'bottom') ? ($textH + $marginBottom) : 0;
        $canvasH = $extraTop + $timerH + $extraBottom;
        if ($requestedW && $requestedW > 0) { $canvasW = max($canvasW, $requestedW); }
        if ($requestedH && $requestedH > 0) { $canvasH = max($canvasH, $requestedH); }

        // Compose final image
        $im = imagecreatetruecolor($canvasW, $canvasH);
        $bgCol = imagecolorallocate($im, $br, $bg, $bb);
        $txtCol = imagecolorallocate($im, $tr, $tg, $tb);
        // Choose color for custom text: default to skin textColor unless customTextColor is provided
        $ctr = $tr; $ctg = $tg; $ctb = $tb;
        if (!empty($skin['customTextColor'])) {
            [$ctr, $ctg, $ctb] = $this->hexToRgb($skin['customTextColor']);
        }
        $customTxtCol = imagecolorallocate($im, $ctr, $ctg, $ctb);
        imagefilledrectangle($im, 0, 0, $canvasW, $canvasH, $bgCol);

        $offsetX = (int) (($canvasW - $timerW) / 2);
        $offsetY = $extraTop;
        imagecopy($im, $timerIm, $offsetX, $offsetY, 0, 0, $timerW, $timerH);
        imagedestroy($timerIm);

        if ($hasText) {
            if (is_file($font)) {
                $tx = (int) (($canvasW - $textW) / 2);
                if ($textPosition === 'top') {
                    // Text at top: marginTop is space from top, then position text baseline
                    // Y coordinate in imagettftext is the baseline, so we add textHeightFromBaseline
                    $ty = (int) ($marginTop + $textHeightFromBaseline);
                } else {
                    // Text at bottom: timer ends at offsetY + timerH, then marginBottom space, then text
                    // Y coordinate in imagettftext is the baseline
                    $ty = (int) ($offsetY + $timerH + $marginBottom + $textHeightFromBaseline);
                }
                imagettftext($im, $textSize, 0, $tx, $ty, $customTxtCol, $font, $customText);
            } else {
                $bi2 = 3;
                $builtInTextH = imagefontheight($bi2);
                $tx = (int) (($canvasW - $textW) / 2);
                if ($textPosition === 'top') {
                    // Text at top: marginTop is space from top, then text
                    $ty = (int) ($marginTop + $builtInTextH);
                } else {
                    // Text at bottom: timer ends at offsetY + timerH, then marginBottom space, then text
                    $ty = (int) ($offsetY + $timerH + $marginBottom + $builtInTextH);
                }
                imagestring($im, $bi2, $tx, $ty, $customText, $customTxtCol);
            }
        }

        // Optional border around entire canvas
        $borderWidth = (int) ($skin['borderWidth'] ?? 0);
        if ($borderWidth > 0) {
            [$brdR, $brdG, $brdB] = $this->hexToRgb((string) ($skin['borderColor'] ?? '#FFFFFF'));
            $brdCol = imagecolorallocate($im, $brdR, $brdG, $brdB);
            $bw = min($borderWidth, (int) floor(min($canvasW, $canvasH) / 2));
            if ($bw > 0) {
                if (function_exists('imagesetthickness')) { imagesetthickness($im, $bw); }
                imagerectangle($im, (int) floor($bw/2), (int) floor($bw/2), $canvasW - 1 - (int) floor($bw/2), $canvasH - 1 - (int) floor($bw/2), $brdCol);
                if (function_exists('imagesetthickness')) { imagesetthickness($im, 1); }
            }
        }

        ob_start();
        imagepng($im);
        $pngData = ob_get_clean();
        imagedestroy($im);
        return $pngData;
    }

    private function resolveFrameLimit(Timer $timer): int
    {
        $limit = (int) ($timer->animation_frame_limit ?? 20);
        return max(10, min(60, $limit));
    }

    private function applySkinStyle($im, array $skin, int $width, int $height, int $bgCol, int $textCol, int $accentCol, int $secondsRemaining): void
    {
        $style = $skin['style'] ?? 'default';
        // First, try pluggable renderer by style
        $renderer = SkinRendererFactory::resolve($style);
        if ($renderer instanceof SkinRendererInterface) {
            $renderer->renderStyle($im, $skin, $width, $height, $bgCol, $textCol, $accentCol, $secondsRemaining);
            return;
        }

        switch ($style) {
            case 'circular_progress':
                $this->drawCircularProgress($im, $width, $height, $bgCol, $accentCol, $secondsRemaining);
                break;
            case 'squared_outline':
                $this->drawSquaredOutline($im, $width, $height, $bgCol, $textCol);
                break;
            case 'flip_clock':
            case 'flip_clock_white':
                $this->drawFlipClock($im, $width, $height, $bgCol, $textCol);
                break;
            case 'solid_circular':
                $this->drawSolidCircular($im, $width, $height, $bgCol, $accentCol);
                break;
            case 'rounded_square_outline':
                $this->drawRoundedSquareOutline($im, $width, $height, $bgCol, $accentCol);
                break;
            case 'segmented_digit':
                $this->drawSegmentedDigit($im, $width, $height, $bgCol, $accentCol);
                break;
            case 'dotted_circular_progress':
                $this->drawDottedCircularProgress($im, $width, $height, $bgCol, $accentCol, $secondsRemaining);
                break;
            case 'solid_blue_background':
                $this->drawSolidBlueBackground($im, $width, $height, $accentCol);
                break;
            case 'dashed_circular_progress':
                $this->drawDashedCircularProgress($im, $width, $height, $bgCol, $accentCol, $secondsRemaining);
                break;
            case 'gradient_circular':
                $this->drawGradientCircular($im, $width, $height, $bgCol, $accentCol, $secondsRemaining);
                break;
            case 'neon_glow':
                $this->drawNeonGlow($im, $width, $height, $bgCol, $textCol, $accentCol);
                break;
            case 'classic_digital':
                $this->drawClassicDigital($im, $width, $height, $bgCol, $textCol);
                break;
            case 'clean_white_timer':
                $this->drawCleanWhiteTimer($im, $width, $height, $bgCol, $textCol, $accentCol);
                break;
            default:
                // Default rounded rectangle
                $this->drawRoundedRect($im, 0, 0, $width, $height, 12, $bgCol);
                break;
        }
    }

    private function drawCircularProgress($im, int $width, int $height, int $bgCol, int $accentCol, int $secondsRemaining): void
    {
        // Draw circular progress indicators for each time unit
        $unitWidth = $width / 4;
        $centerY = $height / 2;
        $radius = min($unitWidth / 3, $height / 4);
        
        for ($i = 0; $i < 4; $i++) {
            $centerX = $unitWidth * $i + $unitWidth / 2;
            $this->drawCircle($im, $centerX, $centerY, $radius, $bgCol);
            $this->drawCircle($im, $centerX, $centerY, $radius, $accentCol, false);
        }
    }

    private function drawSquaredOutline($im, int $width, int $height, int $bgCol, int $textCol): void
    {
        $unitWidth = $width / 4;
        for ($i = 0; $i < 4; $i++) {
            $x = $unitWidth * $i + 5;
            $y = 5;
            $w = $unitWidth - 10;
            $h = $height - 10;
            imagerectangle($im, $x, $y, $x + $w, $y + $h, $textCol);
        }
    }

    private function drawFlipClock($im, int $width, int $height, int $bgCol, int $textCol): void
    {
        $unitWidth = $width / 4;
        for ($i = 0; $i < 4; $i++) {
            $x = $unitWidth * $i + 5;
            $y = 5;
            $w = $unitWidth - 10;
            $h = $height - 10;
            imagefilledrectangle($im, $x, $y, $x + $w, $y + $h, $bgCol);
            imagerectangle($im, $x, $y, $x + $w, $y + $h, $textCol);
        }
    }

    private function drawSolidCircular($im, int $width, int $height, int $bgCol, int $accentCol): void
    {
        $unitWidth = $width / 4;
        $centerY = $height / 2;
        $radius = min($unitWidth / 3, $height / 4);
        
        for ($i = 0; $i < 4; $i++) {
            $centerX = $unitWidth * $i + $unitWidth / 2;
            $this->drawCircle($im, $centerX, $centerY, $radius, $accentCol);
        }
    }

    private function drawRoundedSquareOutline($im, int $width, int $height, int $bgCol, int $accentCol): void
    {
        $unitWidth = $width / 4;
        for ($i = 0; $i < 4; $i++) {
            $x = $unitWidth * $i + 5;
            $y = 5;
            $w = $unitWidth - 10;
            $h = $height - 10;
            $this->drawRoundedRect($im, $x, $y, $w, $h, 8, $bgCol);
            $this->drawRoundedRect($im, $x, $y, $w, $h, 8, $accentCol);
        }
    }

    private function drawSegmentedDigit($im, int $width, int $height, int $bgCol, int $accentCol): void
    {
        $unitWidth = $width / 4;
        for ($i = 0; $i < 4; $i++) {
            $x = $unitWidth * $i + 5;
            $y = 5;
            $w = $unitWidth - 10;
            $h = $height - 10;
            // Draw segmented digit blocks
            $blockWidth = $w / 2;
            $blockHeight = $h / 2;
            imagefilledrectangle($im, $x, $y, $x + $blockWidth, $y + $blockHeight, $accentCol);
            imagefilledrectangle($im, $x + $blockWidth, $y, $x + $w, $y + $blockHeight, $accentCol);
            imagefilledrectangle($im, $x, $y + $blockHeight, $x + $blockWidth, $y + $h, $accentCol);
            imagefilledrectangle($im, $x + $blockWidth, $y + $blockHeight, $x + $w, $y + $h, $accentCol);
        }
    }

    private function drawDottedCircularProgress($im, int $width, int $height, int $bgCol, int $accentCol, int $secondsRemaining): void
    {
        $unitWidth = $width / 4;
        $centerY = $height / 2;
        $radius = min($unitWidth / 3, $height / 4);
        
        for ($i = 0; $i < 4; $i++) {
            $centerX = $unitWidth * $i + $unitWidth / 2;
            $this->drawDottedCircle($im, $centerX, $centerY, $radius, $accentCol);
        }
    }

    private function drawSolidBlueBackground($im, int $width, int $height, int $accentCol): void
    {
        imagefilledrectangle($im, 0, 0, $width, $height, $accentCol);
    }

    private function drawDashedCircularProgress($im, int $width, int $height, int $bgCol, int $accentCol, int $secondsRemaining): void
    {
        $unitWidth = $width / 4;
        $centerY = $height / 2;
        $radius = min($unitWidth / 3, $height / 4);
        
        for ($i = 0; $i < 4; $i++) {
            $centerX = $unitWidth * $i + $unitWidth / 2;
            $this->drawDashedCircle($im, $centerX, $centerY, $radius, $accentCol);
        }
    }

    private function drawGradientCircular($im, int $width, int $height, int $bgCol, int $accentCol, int $secondsRemaining): void
    {
        $unitWidth = $width / 4;
        $centerY = $height / 2;
        $radius = min($unitWidth / 3, $height / 4);
        
        for ($i = 0; $i < 4; $i++) {
            $centerX = $unitWidth * $i + $unitWidth / 2;
            $this->drawGradientCircle($im, $centerX, $centerY, $radius, $bgCol, $accentCol);
        }
    }

    private function drawNeonGlow($im, int $width, int $height, int $bgCol, int $textCol, int $accentCol): void
    {
        // Dark background with neon glow effect
        imagefilledrectangle($im, 0, 0, $width, $height, $bgCol);
        // Add glow effect around borders
        for ($i = 1; $i <= 3; $i++) {
            imagerectangle($im, $i, $i, $width - $i, $height - $i, $accentCol);
        }
    }

    private function drawClassicDigital($im, int $width, int $height, int $bgCol, int $textCol): void
    {
        // Classic green digital display
        imagefilledrectangle($im, 0, 0, $width, $height, $bgCol);
        // Add digital display grid lines
        for ($i = 1; $i < 4; $i++) {
            $x = ($width / 4) * $i;
            imageline($im, $x, 0, $x, $height, $textCol);
        }
    }

    private function drawCleanWhiteTimer($im, int $width, int $height, int $bgCol, int $textCol, int $accentCol): void
    {
        // White background
        imagefilledrectangle($im, 0, 0, $width, $height, $bgCol);
        
        // Thin black border
        imagerectangle($im, 0, 0, $width - 1, $height - 1, $textCol);
    }

    private function renderCleanWhiteTimerText($im, int $secondsRemaining, array $skin, int $width, int $height, int $textCol, int $accentCol): void
    {
        $font = $skin['fontPath'];
        $fontSize = (int) $skin['fontSize'];
        $labelSize = max(12, (int) round($fontSize * 0.3)); // Smaller font for labels
        
        // Calculate time components
        $days = intdiv($secondsRemaining, 86400);
        $hours = intdiv($secondsRemaining % 86400, 3600);
        $minutes = intdiv($secondsRemaining % 3600, 60);
        $seconds = $secondsRemaining % 60;
        
        // Time units and labels
        $timeUnits = [
            ['value' => $days, 'label' => 'D'],
            ['value' => $hours, 'label' => 'H'],
            ['value' => $minutes, 'label' => 'M'],
            ['value' => $seconds, 'label' => 'S']
        ];
        
        // Calculate positions
        $unitWidth = $width / 4;
        $centerY = $height / 2;
        
        // Determine separator color for fallback renderer
        $sepCol = $textCol;
        if (!empty($skin['separatorColor'])) {
            [$sr,$sg,$sb] = $this->hexToRgb($skin['separatorColor']);
            $sepCol = imagecolorallocate($im, $sr, $sg, $sb);
        }

        if (is_file($font)) {
            for ($i = 0; $i < 4; $i++) {
                $unit = $timeUnits[$i];
                $unitX = $unitWidth * $i + $unitWidth / 2;
                
                // Format number with leading zero
                $numberText = sprintf('%02d', $unit['value']);
                
                // Calculate text position
                $numberWidth = $this->textBoxWidth($numberText, $font, $fontSize);
                $numberX = $unitX - $numberWidth / 2;
                $numberY = $centerY - 10; // Slightly above center
                
                // Draw number
                imagettftext($im, $fontSize, 0, $numberX, $numberY, $textCol, $font, $numberText);
                
                // Draw label below
                $labelWidth = $this->textBoxWidth($unit['label'], $font, $labelSize);
                $labelX = $unitX - $labelWidth / 2;
                $labelY = $centerY + 20; // Below the number
                
                imagettftext($im, $labelSize, 0, $labelX, $labelY, $accentCol, $font, $unit['label']);
                
                // Draw colon separator (except for last unit)
                if ($i < 3) {
                    $colonX = $unitX + $unitWidth / 2 - 5;
                    $colonY = $centerY - 5;
                    imagettftext($im, $fontSize, 0, $colonX, $colonY, $sepCol, $font, ':');
                }
            }
        } else {
            // Fallback to built-in font
            $builtIn = 5;
            $labelBuiltIn = 3;
            
            for ($i = 0; $i < 4; $i++) {
                $unit = $timeUnits[$i];
                $unitX = $unitWidth * $i + $unitWidth / 2;
                
                $numberText = sprintf('%02d', $unit['value']);
                $numberWidth = imagefontwidth($builtIn) * strlen($numberText);
                $numberHeight = imagefontheight($builtIn);
                $numberX = $unitX - $numberWidth / 2;
                $numberY = $centerY - $numberHeight / 2 - 5;
                
                imagestring($im, $builtIn, $numberX, $numberY, $numberText, $textCol);
                
                // Draw label
                $labelWidth = imagefontwidth($labelBuiltIn) * strlen($unit['label']);
                $labelHeight = imagefontheight($labelBuiltIn);
                $labelX = $unitX - $labelWidth / 2;
                $labelY = $centerY + 10;
                
                imagestring($im, $labelBuiltIn, $labelX, $labelY, $unit['label'], $accentCol);
                
                // Draw colon
                if ($i < 3) {
                    $colonX = $unitX + $unitWidth / 2 - 3;
                    $colonY = $centerY - 3;
                    imagestring($im, $builtIn, $colonX, $colonY, ':', $sepCol);
                }
            }
        }
    }

    private function drawCircle($im, int $centerX, int $centerY, int $radius, int $color, bool $filled = true): void
    {
        if ($filled) {
            imagefilledellipse($im, $centerX, $centerY, $radius * 2, $radius * 2, $color);
        } else {
            imageellipse($im, $centerX, $centerY, $radius * 2, $radius * 2, $color);
        }
    }

    private function drawDottedCircle($im, int $centerX, int $centerY, int $radius, int $color): void
    {
        // Draw dotted circle
        for ($angle = 0; $angle < 360; $angle += 10) {
            $x = $centerX + $radius * cos(deg2rad($angle));
            $y = $centerY + $radius * sin(deg2rad($angle));
            imagefilledellipse($im, $x, $y, 3, 3, $color);
        }
    }

    private function drawDashedCircle($im, int $centerX, int $centerY, int $radius, int $color): void
    {
        // Draw dashed circle
        for ($angle = 0; $angle < 360; $angle += 5) {
            $x = $centerX + $radius * cos(deg2rad($angle));
            $y = $centerY + $radius * sin(deg2rad($angle));
            if ($angle % 10 < 5) {
                imagefilledellipse($im, $x, $y, 2, 2, $color);
            }
        }
    }

    private function drawGradientCircle($im, int $centerX, int $centerY, int $radius, int $bgCol, int $accentCol): void
    {
        // Simple gradient effect
        for ($r = $radius; $r > 0; $r--) {
            $ratio = $r / $radius;
            $color = imagecolorallocate($im, 
                (int) (($ratio * 255) + ((1 - $ratio) * 0)),
                (int) (($ratio * 255) + ((1 - $ratio) * 0)),
                (int) (($ratio * 255) + ((1 - $ratio) * 255))
            );
            imagefilledellipse($im, $centerX, $centerY, $r * 2, $r * 2, $color);
        }
    }

    private function renderFinalFrame(array $skin, ?string $customText, string $textPosition): string
    {
        $width = max(120, (int) $skin['width']);
        $height = max(80, (int) $skin['height']);

        $im = imagecreatetruecolor($width, $height);
        // Background: allow override via finalBackground
        $bgHex = (string) ($skin['finalBackground'] ?? $skin['background']);
        [$br, $bg, $bb] = $this->hexToRgb($bgHex);
        [$tr, $tg, $tb] = $this->hexToRgb((string) ($skin['finalTextColor'] ?? $skin['textColor']));
        $bgCol = imagecolorallocate($im, $br, $bg, $bb);
        $textCol = imagecolorallocate($im, $tr, $tg, $tb);
        $this->drawRoundedRect($im, 0, 0, $width, $height, 12, $bgCol);

        $font = $skin['fontPath'];
        $fontSize = (int) $skin['fontSize'];
        $msg = (string) ($skin['finalMessage'] ?? "Time's Up");
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

        // Optional border on final frame
        $borderWidth = (int) ($skin['borderWidth'] ?? 0);
        if ($borderWidth > 0) {
            [$brdR, $brdG, $brdB] = $this->hexToRgb((string) ($skin['borderColor'] ?? '#FFFFFF'));
            $brdCol = imagecolorallocate($im, $brdR, $brdG, $brdB);
            $bw = min($borderWidth, (int) floor(min($width, $height) / 2));
            if ($bw > 0) {
                if (function_exists('imagesetthickness')) { imagesetthickness($im, $bw); }
                imagerectangle($im, (int) floor($bw/2), (int) floor($bw/2), $width - 1 - (int) floor($bw/2), $height - 1 - (int) floor($bw/2), $brdCol);
                if (function_exists('imagesetthickness')) { imagesetthickness($im, 1); }
            }
        }

        ob_start();
        imagepng($im);
        $pngData = ob_get_clean();
        imagedestroy($im);
        return $pngData;
    }

    private function encodeAnimatedGif(array $pngFrames, string $outputPath, int $totalSeconds): void
    {
        // Calculate frame delay based on step size (must match buildFrames logic)
        // For durations up to 60s, show every second
        // For 1-5 minutes, show every 2 seconds  
        // For longer durations, show every 5 seconds
        if ($totalSeconds <= 60) {
            $step = 1;
        } elseif ($totalSeconds <= 300) {
            $step = 2;
        } else {
            $step = 5;
        }
        $frameDelay = 100 * $step; // Convert to centiseconds (100 = 1 second)
        
        // Try Imagick first if available
        if (class_exists(\Imagick::class)) {
            $imagick = new \Imagick();
            $imagick->setFormat('gif');
            foreach ($pngFrames as $frame) {
                $frameImagick = new \Imagick();
                $frameImagick->readImageBlob($frame);
                $frameImagick->setImageFormat('gif');
                $frameImagick->setImageDelay($frameDelay); // centiseconds per frame
                $frameImagick->setImageDispose(\Imagick::DISPOSE_BACKGROUND);
                $imagick->addImage($frameImagick);
            }
            $imagick = $imagick->coalesceImages();
            foreach ($imagick as $img) {
                $img->setImageIterations(0); // loop forever
            }
            $imagick->writeImages($outputPath, true);
            return;
        }

        // Fallback to pure-PHP encoder
        $encoder = new GifCreator();
        $durations = array_fill(0, count($pngFrames), $frameDelay);
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
        $days = intdiv($seconds, 86400);
        $hours = intdiv($seconds % 86400, 3600);
        $minutes = intdiv($seconds % 3600, 60);
        $secs = $seconds % 60;
        
        if ($days > 0) {
            return sprintf('%02d:%02d:%02d:%02d', $days, $hours, $minutes, $secs);
        } else {
            return sprintf('%02d:%02d:%02d', $hours, $minutes, $secs);
        }
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

    // (no-op helpers removed)
}
