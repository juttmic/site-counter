<?php

namespace App\Services;

use App\Models\Timer;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;
use App\Services\Skins\SkinRendererFactory;
use App\Services\Skins\SkinRendererInterface;
use Illuminate\Support\Facades\Log;

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
        // customTextColor, customTextDistance (or legacy margins), show_final_message are stored in skin_overrides
        if (is_array($timer->skin_overrides) && !empty($timer->skin_overrides)) {
            $skin = array_replace_recursive($skin, $timer->skin_overrides);
        }
        $skin = $this->applyFinalColorDefaults($skin);

        $showFinalMessage = (bool) ($skin['show_final_message'] ?? false);

        // Special handling when a final "Time's Up" screen is configured
        if ($showFinalMessage) {
            if ($remaining <= 5) {
                // Remaining time is 5s or less: only render the final screen as a static GIF
                $framePng = $this->renderFinalFrame($skin, $timer->custom_text, $timer->text_position ?? 'none');
                // Use a minimal non-zero duration so encoders behave correctly
                $this->encodeAnimatedGif([$framePng], $fullPath, 1);
            } else {
                // Remaining time is more than 5s: generate countdown frames without the final screen
                $frameLimit = $this->resolveFrameLimit($timer);
                $animateFor = min($frameLimit, max(0, $durationForGif));
                // Temporarily disable final message so buildFramesCapped does not append the final frame
                $skinForFrames = $skin;
                $skinForFrames['show_final_message'] = false;
                $frames = $this->buildFramesCapped($remaining, $animateFor, $skinForFrames, $timer->custom_text, $timer->text_position);
                $this->encodeAnimatedGif($frames, $fullPath, $animateFor);
            }
        } else {
            // No final screen configured: keep existing behavior
            $frameLimit = $this->resolveFrameLimit($timer);
            // Cap animation length to configured limit but start from actual remaining
            $animateFor = min($frameLimit, max(0, $durationForGif));
            $frames = $this->buildFramesCapped($remaining, $animateFor, $skin, $timer->custom_text, $timer->text_position);
            $this->encodeAnimatedGif($frames, $fullPath, $animateFor);
        }

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
        $showFinalMessage = (bool) ($skin['show_final_message'] ?? false);
        
        if ($showFinalMessage) {
            if ($remaining <= 5) {
                // Remaining time is 5s or less: only render the final screen as a static GIF
                $framePng = $this->renderFinalFrame($skin, $timer->custom_text, $timer->text_position ?? 'none');
                $this->encodeAnimatedGif([$framePng], $targetPath, 1);
                return;
            }

            // Remaining time is more than 5s: generate countdown frames without the final screen
            $frameLimit = $this->resolveFrameLimit($timer);
            $animateFor = min($frameLimit, $maxAnimateFor, max(0, $durationForGif));
            $skinForFrames = $skin;
            $skinForFrames['show_final_message'] = false;
            $frames = $this->buildFramesCapped($remaining, $animateFor, $skinForFrames, $timer->custom_text, $timer->text_position);
            $this->encodeAnimatedGif($frames, $targetPath, $animateFor);
        } else {
            // No final screen configured: keep existing behavior
            $frameLimit = $this->resolveFrameLimit($timer);
            $animateFor = min($frameLimit, $maxAnimateFor, max(0, $durationForGif));
            $frames = $this->buildFramesCapped($remaining, $animateFor, $skin, $timer->custom_text, $timer->text_position);
            $this->encodeAnimatedGif($frames, $targetPath, $animateFor);
        }
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
            $path = resource_path('timerskins/' . $skinName . '.jsonc');
        }
        if (!file_exists($path)) {
            return [
                'width' => 400,
                'height' => 160,
                'background' => '#000000',
                'textColor' => '#FFFFFF',
                'accentColor' => '#FFCC00',
                'fontSize' => 36,
                'fontPath' => resource_path('fonts/dejavu-sans.book.ttf'),
                'borderWidth' => 1,
                'borderColor' => '#FFFFFF',
            ];
        }
        $json = SkinFileHelper::load($path) ?? [];
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
            'borderWidth' => 1,
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
        $bw = (int) ($skin['borderWidth'] ?? 1);
        if ($bw === 0) { $bw = 1; }
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
        // Ensure final text is visible: if bg and text are identical, invert the text color
        $bgNorm = strtolower(ltrim((string) $skin['finalBackground'], '#'));
        $txtNorm = strtolower(ltrim((string) $skin['finalTextColor'], '#'));
        if ($bgNorm !== '' && $txtNorm !== '' && $bgNorm === $txtNorm) {
            $skin['finalTextColor'] = $this->invertHexColor((string) $skin['finalTextColor']);
        }
        return $skin;
    }

    public function renderPngFrame(int $secondsRemaining, array $skin, ?string $customText, string $textPosition): string
    {
        $skin = $this->resolveVisibilityLayout($skin);

        // Base timer dimensions from skin
        $timerW = max(120, (int) ($skin['width'] ?? 400));
        $timerH = max(80, (int) ($skin['height'] ?? 160));

        // Resolve colors and font
        [$br, $bg, $bb] = $this->hexToRgb($skin['background'] ?? '#000000');
        [$tr, $tg, $tb] = $this->hexToRgb($skin['textColor'] ?? '#FFFFFF');
        [$ar, $ag, $ab] = $this->hexToRgb($skin['accentColor'] ?? '#FFCC00');
        $font = (string) ($skin['fontPath'] ?? '');
        $fontSize = (int) ($skin['fontSize'] ?? 36);
        // Optional global font override: apply selected font to all timer text (digits, labels, custom text)
        if (!empty($skin['customTextFont'])) {
            $resolved = $this->resolveCustomTextFont($skin, $font);
            if (is_file($resolved)) {
                $font = $resolved;
                $skin['fontPath'] = $resolved;
                // Many renderers use numberFontPath when present; mirror the override there too
                $skin['numberFontPath'] = $resolved;
            }
        }

        // Render timer into off-screen surface
        $timerIm = imagecreatetruecolor($timerW, $timerH);
        $bgTimer = imagecolorallocate($timerIm, $br, $bg, $bb);
        $txtTimer = imagecolorallocate($timerIm, $tr, $tg, $tb);
        $accTimer = imagecolorallocate($timerIm, $ar, $ag, $ab);
        imagefilledrectangle($timerIm, 0, 0, $timerW, $timerH, $bgTimer);
        $this->logDraw('Timer base rectangle', [
            'shape' => 'rectangle',
            'x' => 0,
            'y' => 0,
            'width' => $timerW,
            'height' => $timerH,
            'color' => $skin['background'] ?? '#000000',
            'context' => 'timer_surface',
        ]);

        $this->logDraw('Applying skin style', [
            'style' => $skin['style'] ?? 'default',
            'timer_width' => $timerW,
            'timer_height' => $timerH,
            'seconds_remaining' => $secondsRemaining,
        ]);
        $this->applySkinStyle($timerIm, $skin, $timerW, $timerH, $bgTimer, $txtTimer, $accTimer, $secondsRemaining);

        $renderer = SkinRendererFactory::resolve($skin['style'] ?? null);
        if ($renderer instanceof SkinRendererInterface && $renderer->renderTimeText($timerIm, $secondsRemaining, $skin, $timerW, $timerH, $txtTimer, $accTimer)) {
            // handled by renderer
            $this->logDraw('Renderer handled countdown text', [
                'renderer' => get_class($renderer),
                'seconds_remaining' => $secondsRemaining,
                'timer_width' => $timerW,
                'timer_height' => $timerH,
            ]);
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
                $this->logDraw('Render countdown text (TTF)', [
                    'shape' => 'text',
                    'text' => $timeText,
                    'x' => $cx,
                    'y' => $cy,
                    'font_size' => $fontSize,
                    'font' => $font,
                    'width' => $this->textBoxWidth($timeText, $font, $fontSize),
                    'height' => $fontSize,
                ]);
                imagettftext($timerIm, $fontSize, 0, $cx, $cy, $txtColor, $font, $timeText);
            } else {
                $builtIn = 5;
                $tw = imagefontwidth($builtIn) * strlen($timeText);
                $th = imagefontheight($builtIn);
                $x = (int) (($timerW - $tw) / 2);
                $y = (int) (($timerH - $th) / 2);
                $this->logDraw('Render countdown text (GD built-in)', [
                    'shape' => 'text',
                    'text' => $timeText,
                    'x' => $x,
                    'y' => $y,
                    'font' => 'built-in-5',
                    'width' => $tw,
                    'height' => $th,
                ]);
                imagestring($timerIm, $builtIn, $x, $y, $timeText, $txtTimer);
            }
        }

        // Custom text measurement
        $hasText = ($customText !== null && $customText !== '' && $textPosition !== 'none');
        $padX = 10; // horizontal padding when text is wider than timer
        $textW = 0; $textH = 0;
        $customTextSize = isset($skin['customTextFontSize']) ? (int) $skin['customTextFontSize'] : null;
        $textSize = max(12, (int) ($customTextSize ?: round($fontSize * 0.5)));
        $textAscent = 0; // Height from baseline to top of text
        $textDescent = 0; // Height below baseline
        if ($hasText) {
            if (is_file($font)) {
                $box = imagettfbbox($textSize, 0, $font, $customText);
                $xCoords = [$box[0], $box[2], $box[4], $box[6]];
                $yCoords = [$box[1], $box[3], $box[5], $box[7]];
                $textW = (int) (max($xCoords) - min($xCoords));
                $textAscent = (int) abs(min($yCoords)); // above baseline
                $textDescent = (int) max($yCoords);     // below baseline
                $textH = $textAscent + $textDescent;
            } else {
                $bi = 3;
                $textW = imagefontwidth($bi) * strlen($customText);
                $textH = imagefontheight($bi);
                $textAscent = $textH;
                $textDescent = (int) ceil($textH * 0.25); // approximate small descender
                $textH = $textAscent + $textDescent;
            }
        }

        // Read optional overall output size overrides
        $requestedW = isset($skin['outputWidth']) ? (int) $skin['outputWidth'] : null;
        $requestedH = isset($skin['outputHeight']) ? (int) $skin['outputHeight'] : null;
        $borderWidth = (int) ($skin['borderWidth'] ?? 0);
        $edgePad = max(2, $borderWidth); // ensure border + a small safety pad
        // Distance between timer and custom text (applies above or below)
        $distance = null;
        if (isset($skin['customTextDistance'])) {
            $distance = max(0, (int) $skin['customTextDistance']);
        } elseif (isset($skin['customTextMarginTop']) || isset($skin['customTextMarginBottom'])) {
            // Backward compatibility: use the larger of legacy margins
            $legacyTop = isset($skin['customTextMarginTop']) ? max(0, (int) $skin['customTextMarginTop']) : 0;
            $legacyBottom = isset($skin['customTextMarginBottom']) ? max(0, (int) $skin['customTextMarginBottom']) : 0;
            $distance = max($legacyTop, $legacyBottom);
        }
        if ($distance === null) {
            $distance = 0;
        }

        // Allow explicit output width even if smaller than timer/text (will crop)
        $canvasW = $requestedW && $requestedW > 0
            ? max(1, $requestedW)
            : max($timerW, $hasText ? ($textW + 2 * $padX) : $timerW);

        // Base height for layout: always based on the timer height (not the output/GIF height)
        // so vertical spacing for custom text is measured relative to the timer itself.
        $contentHeight = $timerH + ($hasText ? ($textH + $distance) : 0);
        $layoutHeight = max($timerH, $contentHeight) + ($edgePad * 2);

        // Clamp distance so text + timer fit inside the layout height
        $effectiveDistance = $distance;
        if ($hasText) {
            $available = $layoutHeight - ($edgePad * 2) - $timerH - $textH;
            if ($available < 0) { $available = 0; }
            $effectiveDistance = min($distance, $available);
        }

        // Center the combined block (text + timer) within the layout height (timer-based)
        $combinedHeight = $timerH + ($hasText ? ($textH + $effectiveDistance) : 0);
        $innerTopPadding = max(0, (int) floor(($layoutHeight - ($edgePad * 2) - $combinedHeight) / 2));

        // If an explicit output height is provided, add outer padding without changing the spacing math
        $canvasH = $layoutHeight;
        $outerTopPadding = 0;
        if ($requestedH && $requestedH > 0) {
            $canvasH = max($layoutHeight, $requestedH);
            $outerTopPadding = max(0, (int) floor(($canvasH - $layoutHeight) / 2));
        }

        $topPadding = $outerTopPadding + $innerTopPadding + $edgePad;

        $offsetY = $topPadding;
        if ($hasText && $textPosition === 'top') {
            $offsetY += $textH + $effectiveDistance;
        }
        // For bottom position, timer stays at topPadding; text is drawn after timer using effectiveDistance
        // If output width was not set above, fall back to natural max
        if ((!$requestedW || $requestedW <= 0) && $requestedW !== 0) {
            $canvasW = max($canvasW, $hasText ? ($textW + 2 * $padX) : $timerW);
        }

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
        $this->logDraw('Canvas background', [
            'shape' => 'rectangle',
            'x' => 0,
            'y' => 0,
            'width' => $canvasW,
            'height' => $canvasH,
            'color' => $skin['background'] ?? '#000000',
            'context' => 'canvas',
        ]);

        $offsetX = (int) (($canvasW - $timerW) / 2);
        imagecopy($im, $timerIm, $offsetX, $offsetY, 0, 0, $timerW, $timerH);
        $this->logDraw('Place timer onto canvas', [
            'shape' => 'imagecopy',
            'source_width' => $timerW,
            'source_height' => $timerH,
            'dest_x' => $offsetX,
            'dest_y' => $offsetY,
            'canvas_width' => $canvasW,
            'canvas_height' => $canvasH,
        ]);
        // Add a 1px border around the counter area for visual separation
        $timerBorderCol = imagecolorallocate($im, $tr, $tg, $tb);
        // imagerectangle($im, $offsetX, $offsetY, $offsetX + $timerW - 1, $offsetY + $timerH - 1, $timerBorderCol);
        imagedestroy($timerIm);

        if ($hasText) {
            if (is_file($font)) {
                $tx = (int) (($canvasW - $textW) / 2);
                $ty = ($textPosition === 'top')
                    ? (int) ($topPadding + $textAscent)
                    : (int) ($offsetY + $timerH + $effectiveDistance + $textAscent);
                $this->logDraw('Render custom text (TTF)', [
                    'shape' => 'text',
                    'text' => $customText,
                    'x' => $tx,
                    'y' => $ty,
                    'font' => $font,
                    'font_size' => $textSize,
                    'width' => $textW,
                    'height' => $textH,
                    'position' => $textPosition,
                ]);
                imagettftext($im, $textSize, 0, $tx, $ty, $customTxtCol, $font, $customText);
            } else {
                $bi2 = 3;
                $builtInTextH = imagefontheight($bi2);
                $tx = (int) (($canvasW - $textW) / 2);
                $ty = ($textPosition === 'top')
                    ? (int) ($topPadding)
                    : (int) ($offsetY + $timerH + $effectiveDistance);
                $this->logDraw('Render custom text (GD built-in)', [
                    'shape' => 'text',
                    'text' => $customText,
                    'x' => $tx,
                    'y' => $ty,
                    'font' => 'built-in-3',
                    'width' => $textW,
                    'height' => $builtInTextH + (int) ceil($builtInTextH * 0.25),
                    'position' => $textPosition,
                ]);
                imagestring($im, $bi2, $tx, $ty, $customText, $customTxtCol);
            }
        }

        // Optional border around entire canvas
        if ($borderWidth > 0) {
            [$brdR, $brdG, $brdB] = $this->hexToRgb((string) ($skin['borderColor'] ?? '#FFFFFF'));
            $brdCol = imagecolorallocate($im, $brdR, $brdG, $brdB);
            $bw = min($borderWidth, (int) floor(min($canvasW, $canvasH) / 2));
            if ($bw > 0) {
                if (function_exists('imagesetthickness')) { imagesetthickness($im, $bw); }
                imagerectangle($im, (int) floor($bw/2), (int) floor($bw/2), $canvasW - 1 - (int) floor($bw/2), $canvasH - 1 - (int) floor($bw/2), $brdCol);
                $this->logDraw('Canvas border', [
                    'shape' => 'rectangle_border',
                    'x' => (int) floor($bw / 2),
                    'y' => (int) floor($bw / 2),
                    'width' => $canvasW - (int) floor($bw / 2),
                    'height' => $canvasH - (int) floor($bw / 2),
                    'thickness' => $bw,
                    'color' => $skin['borderColor'] ?? '#FFFFFF',
                ]);
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

    /**
     * Resolve visibilityLayouts when present: pick layout by showDays/showSeconds and apply width/height/segmentGap.
     */
    private function resolveVisibilityLayout(array $skin): array
    {
        $layouts = $skin['visibilityLayouts'] ?? null;
        if (!is_array($layouts) || empty($layouts)) {
            return $skin;
        }
        $showDays = ($skin['showDays'] ?? true) === true;
        $showSeconds = ($skin['showSeconds'] ?? true) === true;

        $layout = null;
        if (!$showDays && !$showSeconds && isset($layouts['daysAndSecondsHidden'])) {
            $layout = $layouts['daysAndSecondsHidden'];
        } elseif (!$showDays && isset($layouts['daysHidden'])) {
            $layout = $layouts['daysHidden'];
        } elseif (!$showSeconds && isset($layouts['secondsHidden'])) {
            $layout = $layouts['secondsHidden'];
        } elseif (isset($layouts['allVisible'])) {
            $layout = $layouts['allVisible'];
        }

        if (is_array($layout)) {
            foreach (['width', 'height', 'segmentGap', 'horizontalPadding', 'ringSize', 'connectorLength', 'framePadding', 'separatorHeight'] as $key) {
                if (isset($layout[$key])) {
                    $skin[$key] = (int) $layout[$key];
                }
            }
        }

        return $skin;
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
            $this->logDraw('Draw squared outline block', [
                'shape' => 'rectangle_outline',
                'x' => $x,
                'y' => $y,
                'width' => $w,
                'height' => $h,
            ]);
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
            $this->logDraw('Draw flip clock background', [
                'shape' => 'rectangle_filled',
                'x' => $x,
                'y' => $y,
                'width' => $w,
                'height' => $h,
                'color_context' => 'background',
            ]);
            imagefilledrectangle($im, $x, $y, $x + $w, $y + $h, $bgCol);
            $this->logDraw('Draw flip clock border', [
                'shape' => 'rectangle_outline',
                'x' => $x,
                'y' => $y,
                'width' => $w,
                'height' => $h,
                'color_context' => 'text',
            ]);
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
            $this->logDraw('Draw segmented digit block', [
                'shape' => 'rectangle_filled',
                'x' => $x,
                'y' => $y,
                'width' => $blockWidth,
                'height' => $blockHeight,
                'segment' => 'top_left',
            ]);
            imagefilledrectangle($im, $x + $blockWidth, $y, $x + $w, $y + $blockHeight, $accentCol);
            $this->logDraw('Draw segmented digit block', [
                'shape' => 'rectangle_filled',
                'x' => $x + $blockWidth,
                'y' => $y,
                'width' => $blockWidth,
                'height' => $blockHeight,
                'segment' => 'top_right',
            ]);
            imagefilledrectangle($im, $x, $y + $blockHeight, $x + $blockWidth, $y + $h, $accentCol);
            $this->logDraw('Draw segmented digit block', [
                'shape' => 'rectangle_filled',
                'x' => $x,
                'y' => $y + $blockHeight,
                'width' => $blockWidth,
                'height' => $blockHeight,
                'segment' => 'bottom_left',
            ]);
            imagefilledrectangle($im, $x + $blockWidth, $y + $blockHeight, $x + $w, $y + $h, $accentCol);
            $this->logDraw('Draw segmented digit block', [
                'shape' => 'rectangle_filled',
                'x' => $x + $blockWidth,
                'y' => $y + $blockHeight,
                'width' => $blockWidth,
                'height' => $blockHeight,
                'segment' => 'bottom_right',
            ]);
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
        $this->logDraw('Draw solid blue background', [
            'shape' => 'rectangle_filled',
            'x' => 0,
            'y' => 0,
            'width' => $width,
            'height' => $height,
        ]);
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
        $this->logDraw('Draw neon glow background', [
            'shape' => 'rectangle_filled',
            'x' => 0,
            'y' => 0,
            'width' => $width,
            'height' => $height,
        ]);
        imagefilledrectangle($im, 0, 0, $width, $height, $bgCol);
        // Add glow effect around borders
        for ($i = 1; $i <= 3; $i++) {
            $this->logDraw('Draw neon glow border', [
                'shape' => 'rectangle_outline',
                'x' => $i,
                'y' => $i,
                'width' => $width - (2 * $i),
                'height' => $height - (2 * $i),
                'layer' => $i,
            ]);
            imagerectangle($im, $i, $i, $width - $i, $height - $i, $accentCol);
        }
    }

    private function drawClassicDigital($im, int $width, int $height, int $bgCol, int $textCol): void
    {
        // Classic green digital display
        $this->logDraw('Draw classic digital background', [
            'shape' => 'rectangle_filled',
            'x' => 0,
            'y' => 0,
            'width' => $width,
            'height' => $height,
        ]);
        imagefilledrectangle($im, 0, 0, $width, $height, $bgCol);
        // Add digital display grid lines
        for ($i = 1; $i < 4; $i++) {
            $x = ($width / 4) * $i;
            $this->logDraw('Draw classic digital separator', [
                'shape' => 'line',
                'x1' => $x,
                'y1' => 0,
                'x2' => $x,
                'y2' => $height,
            ]);
            imageline($im, $x, 0, $x, $height, $textCol);
        }
    }

    private function drawCleanWhiteTimer($im, int $width, int $height, int $bgCol, int $textCol, int $accentCol): void
    {
        // White background
        $this->logDraw('Draw clean white background', [
            'shape' => 'rectangle_filled',
            'x' => 0,
            'y' => 0,
            'width' => $width,
            'height' => $height,
        ]);
        imagefilledrectangle($im, 0, 0, $width, $height, $bgCol);
        
        // Thin black border
        $this->logDraw('Draw clean white border', [
            'shape' => 'rectangle_outline',
            'x' => 0,
            'y' => 0,
            'width' => $width,
            'height' => $height,
            'thickness' => 1,
        ]);
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
                $this->logDraw('Render clean white number (TTF)', [
                    'shape' => 'text',
                    'text' => $numberText,
                    'x' => $numberX,
                    'y' => $numberY,
                    'font' => $font,
                    'font_size' => $fontSize,
                    'width' => $numberWidth,
                    'height' => $fontSize,
                    'unit' => $unit['label'],
                ]);
                imagettftext($im, $fontSize, 0, $numberX, $numberY, $textCol, $font, $numberText);
                
                // Draw label below
                $labelWidth = $this->textBoxWidth($unit['label'], $font, $labelSize);
                $labelX = $unitX - $labelWidth / 2;
                $labelY = $centerY + 20; // Below the number
                
                $this->logDraw('Render clean white label (TTF)', [
                    'shape' => 'text',
                    'text' => $unit['label'],
                    'x' => $labelX,
                    'y' => $labelY,
                    'font' => $font,
                    'font_size' => $labelSize,
                    'width' => $labelWidth,
                    'height' => $labelSize,
                    'unit' => $unit['label'],
                ]);
                imagettftext($im, $labelSize, 0, $labelX, $labelY, $accentCol, $font, $unit['label']);
                
                // Draw colon separator (except for last unit)
                if ($i < 3) {
                    $colonX = $unitX + $unitWidth / 2 - 5;
                    $colonY = $centerY - 5;
                    $this->logDraw('Render clean white colon (TTF)', [
                        'shape' => 'text',
                        'text' => ':',
                        'x' => $colonX,
                        'y' => $colonY,
                        'font' => $font,
                        'font_size' => $fontSize,
                    ]);
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
                
                $this->logDraw('Render clean white number (GD built-in)', [
                    'shape' => 'text',
                    'text' => $numberText,
                    'x' => $numberX,
                    'y' => $numberY,
                    'font' => 'built-in-5',
                    'width' => $numberWidth,
                    'height' => $numberHeight,
                    'unit' => $unit['label'],
                ]);
                imagestring($im, $builtIn, $numberX, $numberY, $numberText, $textCol);
                
                // Draw label
                $labelWidth = imagefontwidth($labelBuiltIn) * strlen($unit['label']);
                $labelHeight = imagefontheight($labelBuiltIn);
                $labelX = $unitX - $labelWidth / 2;
                $labelY = $centerY + 10;
                
                $this->logDraw('Render clean white label (GD built-in)', [
                    'shape' => 'text',
                    'text' => $unit['label'],
                    'x' => $labelX,
                    'y' => $labelY,
                    'font' => 'built-in-3',
                    'width' => $labelWidth,
                    'height' => $labelHeight,
                    'unit' => $unit['label'],
                ]);
                imagestring($im, $labelBuiltIn, $labelX, $labelY, $unit['label'], $accentCol);
                
                // Draw colon
                if ($i < 3) {
                    $colonX = $unitX + $unitWidth / 2 - 3;
                    $colonY = $centerY - 3;
                    $this->logDraw('Render clean white colon (GD built-in)', [
                        'shape' => 'text',
                        'text' => ':',
                        'x' => $colonX,
                        'y' => $colonY,
                        'font' => 'built-in-5',
                        'width' => imagefontwidth($builtIn),
                        'height' => imagefontheight($builtIn),
                    ]);
                    imagestring($im, $builtIn, $colonX, $colonY, ':', $sepCol);
                }
            }
        }
    }

    private function drawCircle($im, int $centerX, int $centerY, int $radius, int $color, bool $filled = true): void
    {
        $this->logDraw('Draw circle', [
            'shape' => $filled ? 'circle_filled' : 'circle_outline',
            'center_x' => $centerX,
            'center_y' => $centerY,
            'radius' => $radius,
        ]);
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
            $this->logDraw('Draw dotted circle point', [
                'shape' => 'circle_dot',
                'center_x' => $centerX,
                'center_y' => $centerY,
                'radius' => $radius,
                'dot_x' => $x,
                'dot_y' => $y,
                'angle' => $angle,
            ]);
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
                $this->logDraw('Draw dashed circle segment', [
                    'shape' => 'circle_dash',
                    'center_x' => $centerX,
                    'center_y' => $centerY,
                    'radius' => $radius,
                    'dot_x' => $x,
                    'dot_y' => $y,
                    'angle' => $angle,
                ]);
                imagefilledellipse($im, $x, $y, 2, 2, $color);
            }
        }
    }

    private function drawGradientCircle($im, int $centerX, int $centerY, int $radius, int $bgCol, int $accentCol): void
    {
        // Simple gradient effect
        for ($r = $radius; $r > 0; $r--) {
            $ratio = $r / $radius;
            $this->logDraw('Draw gradient circle ring', [
                'shape' => 'circle_ring',
                'center_x' => $centerX,
                'center_y' => $centerY,
                'radius' => $r,
                'ratio' => $ratio,
            ]);
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
        $timerW = max(120, (int) ($skin['width'] ?? 400));
        $timerH = max(80, (int) ($skin['height'] ?? 160));
        $padX = 10;
        $font = (string) ($skin['fontPath'] ?? '');
        $fontSize = (int) ($skin['fontSize'] ?? 36);
        $msg = (string) ($skin['finalMessage'] ?? "Time's Up");
        $requestedW = isset($skin['outputWidth']) ? (int) $skin['outputWidth'] : null;
        $requestedH = isset($skin['outputHeight']) ? (int) $skin['outputHeight'] : null;
        $borderWidth = (int) ($skin['borderWidth'] ?? 0);
        $edgePad = max(2, $borderWidth);

        // Custom text measurement
        $hasText = ($customText !== null && $customText !== '' && $textPosition !== 'none');
        $textW = 0; $textH = 0; $textAscent = 0; $textDescent = 0;
        $customTextSize = isset($skin['customTextFontSize']) ? (int) $skin['customTextFontSize'] : null;
        $textSize = max(12, (int) ($customTextSize ?: round($fontSize * 0.5)));
        if ($hasText) {
            if (is_file($font)) {
                $box = imagettfbbox($textSize, 0, $font, $customText);
                $xCoords = [$box[0], $box[2], $box[4], $box[6]];
                $yCoords = [$box[1], $box[3], $box[5], $box[7]];
                $textW = (int) (max($xCoords) - min($xCoords));
                $textAscent = (int) abs(min($yCoords));
                $textDescent = (int) max($yCoords);
                $textH = $textAscent + $textDescent;
            } else {
                $bi = 3;
                $textW = imagefontwidth($bi) * strlen($customText);
                $textH = imagefontheight($bi);
                $textAscent = $textH;
                $textDescent = (int) ceil($textH * 0.25);
                $textH = $textAscent + $textDescent;
            }
        }

        // Distance for custom text
        $distance = null;
        if (isset($skin['customTextDistance'])) {
            $distance = max(0, (int) $skin['customTextDistance']);
        } elseif (isset($skin['customTextMarginTop']) || isset($skin['customTextMarginBottom'])) {
            $legacyTop = isset($skin['customTextMarginTop']) ? max(0, (int) $skin['customTextMarginTop']) : 0;
            $legacyBottom = isset($skin['customTextMarginBottom']) ? max(0, (int) $skin['customTextMarginBottom']) : 0;
            $distance = max($legacyTop, $legacyBottom);
        }
        if ($distance === null) { $distance = 0; }

        $canvasW = max($timerW, $hasText ? ($textW + 2 * $padX) : $timerW);
        if ($requestedW && $requestedW > 0) {
            $canvasW = max(1, $requestedW);
        }

        $contentHeight = $timerH + ($hasText ? ($textH + $distance) : 0);
        $layoutHeight = max($timerH, $contentHeight) + ($edgePad * 2);

        $effectiveDistance = $distance;
        if ($hasText) {
            $available = $layoutHeight - ($edgePad * 2) - $timerH - $textH;
            if ($available < 0) { $available = 0; }
            $effectiveDistance = min($distance, $available);
        }

        $combinedHeight = $timerH + ($hasText ? ($textH + $effectiveDistance) : 0);
        $innerTopPadding = max(0, (int) floor(($layoutHeight - ($edgePad * 2) - $combinedHeight) / 2));
        $canvasH = $layoutHeight;
        $outerTopPadding = 0;
        if ($requestedH && $requestedH > 0) {
            $canvasH = max($layoutHeight, $requestedH);
            $outerTopPadding = max(0, (int) floor(($canvasH - $layoutHeight) / 2));
        }
        $topPadding = $outerTopPadding + $innerTopPadding + $edgePad;
        $offsetY = $topPadding;
        if ($hasText && $textPosition === 'top') {
            $offsetY += $textH + $effectiveDistance;
        }

        $im = imagecreatetruecolor($canvasW, $canvasH);
        $bgHex = (string) ($skin['finalBackground'] ?? $skin['background']);
        [$br, $bg, $bb] = $this->hexToRgb($bgHex);
        [$tr, $tg, $tb] = $this->hexToRgb((string) ($skin['finalTextColor'] ?? $skin['textColor']));
        $bgCol = imagecolorallocate($im, $br, $bg, $bb);
        $textCol = imagecolorallocate($im, $tr, $tg, $tb);
        imagefilledrectangle($im, 0, 0, $canvasW, $canvasH, $bgCol);
        $this->drawRoundedRect($im, 0, 0, $canvasW, $canvasH, 12, $bgCol);

        if (is_file($font)) {
            $centerY = (int) ($offsetY + $timerH / 2 + $fontSize / 2);
            $centerX = (int) ($canvasW / 2 - $this->textBoxWidth($msg, $font, $fontSize) / 2);
            $this->logDraw('Render final message (TTF)', [
                'shape' => 'text',
                'text' => $msg,
                'x' => $centerX,
                'y' => $centerY,
                'font' => $font,
                'font_size' => $fontSize,
                'width' => $this->textBoxWidth($msg, $font, $fontSize),
                'height' => $fontSize,
            ]);
            imagettftext($im, $fontSize, 0, $centerX, $centerY, $textCol, $font, $msg);
        } else {
            $builtIn = 5;
            $textWidth = imagefontwidth($builtIn) * strlen($msg);
            $textHeight = imagefontheight($builtIn);
            $x = (int) (($canvasW - $textWidth) / 2);
            $y = (int) ($offsetY + ($timerH - $textHeight) / 2);
            $this->logDraw('Render final message (GD built-in)', [
                'shape' => 'text',
                'text' => $msg,
                'x' => $x,
                'y' => $y,
                'font' => 'built-in-5',
                'width' => $textWidth,
                'height' => $textHeight,
            ]);
            imagestring($im, $builtIn, $x, $y, $msg, $textCol);
        }

        // Do not render custom text on final screen; spacing already accounted for above

        // Optional border on final frame
        if ($borderWidth > 0) {
            [$brdR, $brdG, $brdB] = $this->hexToRgb((string) ($skin['borderColor'] ?? '#FFFFFF'));
            $brdCol = imagecolorallocate($im, $brdR, $brdG, $brdB);
            $bw = min($borderWidth, (int) floor(min($canvasW, $canvasH) / 2));
            if ($bw > 0) {
                if (function_exists('imagesetthickness')) { imagesetthickness($im, $bw); }
                imagerectangle($im, (int) floor($bw/2), (int) floor($bw/2), $canvasW - 1 - (int) floor($bw/2), $canvasH - 1 - (int) floor($bw/2), $brdCol);
                $this->logDraw('Final frame border', [
                    'shape' => 'rectangle_border',
                    'x' => (int) floor($bw / 2),
                    'y' => (int) floor($bw / 2),
                    'width' => $canvasW - (int) floor($bw / 2),
                    'height' => $canvasH - (int) floor($bw / 2),
                    'thickness' => $bw,
                    'color' => $skin['borderColor'] ?? '#FFFFFF',
                ]);
                if (function_exists('imagesetthickness')) { imagesetthickness($im, 1); }
            }
        }

        ob_start();
        imagepng($im);
        $pngData = ob_get_clean();
        imagedestroy($im);
        return $pngData;
    }

    private function logDraw(string $title, array $context = []): void
    {
        try {
            Log::channel('timer_draw')->info($title, $context);
        } catch (\Throwable $e) {
            // Never allow logging failures to break rendering
        }
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

    private function invertHexColor(string $hex): string
    {
        $hex = ltrim($hex, '#');
        if (strlen($hex) === 3) {
            $hex = $hex[0].$hex[0].$hex[1].$hex[1].$hex[2].$hex[2];
        }
        if (strlen($hex) !== 6) {
            return '#FFFFFF';
        }
        $r = 255 - hexdec(substr($hex, 0, 2));
        $g = 255 - hexdec(substr($hex, 2, 2));
        $b = 255 - hexdec(substr($hex, 4, 2));
        return sprintf('#%02X%02X%02X', $r, $g, $b);
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
        $this->logDraw('Draw rounded rectangle', [
            'shape' => 'rounded_rectangle',
            'x' => $x,
            'y' => $y,
            'width' => $w,
            'height' => $h,
            'radius' => $r,
        ]);
        // Center
        imagefilledrectangle($im, $x + $r, $y, $x + $w - $r, $y + $h, $color);
        imagefilledrectangle($im, $x, $y + $r, $x + $w, $y + $h - $r, $color);
        // Corners
        imagefilledellipse($im, $x + $r, $y + $r, $r * 2, $r * 2, $color);
        imagefilledellipse($im, $x + $w - $r, $y + $r, $r * 2, $r * 2, $color);
        imagefilledellipse($im, $x + $r, $y + $h - $r, $r * 2, $r * 2, $color);
        imagefilledellipse($im, $x + $w - $r, $y + $h - $r, $r * 2, $r * 2, $color);
    }

    /**
     * Resolve the font file to use for custom text.
     * Falls back to the main skin font if a valid custom font is not configured.
     */
    private function resolveCustomTextFont(array $skin, string $fallbackFont): string
    {
        $choice = (string) ($skin['customTextFont'] ?? '');
        if ($choice === '') {
            return $fallbackFont;
        }

        $basename = basename($choice);
        $ext = strtolower(pathinfo($basename, PATHINFO_EXTENSION));
        // Only TTF/OTF are supported by GD's imagettftext; ignore others like WOFF
        if (!in_array($ext, ['ttf', 'otf'], true)) {
            return $fallbackFont;
        }

        $candidate = public_path('assets/fonts/' . $basename);
        if (is_file($candidate)) {
            return $candidate;
        }

        return $fallbackFont;
    }

    // (no-op helpers removed)
}
