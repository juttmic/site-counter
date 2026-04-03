<?php

namespace App\Http\Controllers;

use App\Jobs\GenerateTimerGif;
use App\Models\Timer;
use App\Services\TimerGifGenerator;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Storage;
use Carbon\Carbon;
use DateTimeZone;

class TimerController extends Controller
{
    public function __construct()
    {
        // $this->middleware('auth');
    }

    public function index()
    {
        $timers = Timer::where('user_id', Auth::id())->latest()->paginate(12);
        return view('dashboard', compact('timers'));
    }

    public function create()
    {
        $skins = $this->availableSkins();
        return view('timers.create', compact('skins'));
    }

    public function store(Request $request)
    {
        $data = $this->validateTimer($request);
        $data['user_id'] = Auth::id();

        // Set defaults for new timers
        if (!isset($data['timer_type']) || empty($data['timer_type'])) {
            $data['timer_type'] = 'fixed_date';
        }
        
        if ($data['timer_type'] === 'fixed_date' && empty($data['target_datetime'])) {
            // Set target date to 3 days in the future
            $data['target_datetime'] = now()->addDays(3)->format('Y-m-d\TH:i');
        }

        $timer = new Timer($data);
        $timer->gif_url = '';
        $timer->cache_key = 'timer_' . $timer->id . '_' . bin2hex(random_bytes(3));
        $timer->save();

        // Optionally generate GIF immediately when requested (e.g. from Generate GIF button)
        if ($request->boolean('generate_gif')) {
            $mode = (string) config('app.gif_generation_mode', 'queue');
            if ($mode === 'sync') {
                (new GenerateTimerGif($timer->id))->handle();
            } else {
                GenerateTimerGif::dispatch($timer->id);
            }
        }

        if ($request->expectsJson()) {
            $timer->refresh();
            $needsRegen = $timer->needsGifRegeneration();

            return response()->json([
                'id' => $timer->id,
                'gif_url' => $timer->gif_url,
                'gif_url_with_cache_bust' => $timer->gif_url_with_cache_bust,
                'needs_regen' => $needsRegen,
                'gif_ready' => !empty($timer->gif_url) && !$needsRegen,
                'share_available' => $timer->isActive() && !empty($timer->gif_url) && !$needsRegen,
                'message' => 'Timer created.',
            ]);
        }

        return redirect()->route('timers.edit', $timer)->with('status', 'Timer created!');
    }

    public function edit(Timer $timer)
    {
        $this->authorizeOwner($timer);
        $skins = $this->availableSkins();
        return view('timers.edit', compact('timer', 'skins'));
    }

    public function update(Request $request, Timer $timer)
    {
        $this->authorizeOwner($timer);
        $wasActive = $timer->status === 'active';
        $data = $this->validateTimer($request);
        $timer->fill($data);
        // If switching to inactive, drop existing GIF and cache key so embeds stop working
        if ($wasActive && ($timer->status ?? 'active') !== 'active') {
            $this->deleteGifFile($timer);
            $timer->gif_url = '';
            $timer->cache_key = null;
        }
        $timer->save();

        // Optionally generate GIF immediately when requested (e.g. from Generate GIF button)
        if ($request->boolean('generate_gif')) {
            $mode = (string) config('app.gif_generation_mode', 'queue');
            if ($mode === 'sync') {
                (new GenerateTimerGif($timer->id))->handle();
            } else {
                GenerateTimerGif::dispatch($timer->id);
            }
        }

        if ($request->expectsJson()) {
            $timer->refresh();
            $needsRegen = $timer->needsGifRegeneration();

            return response()->json([
                'id' => $timer->id,
                'gif_url' => $timer->gif_url,
                'gif_url_with_cache_bust' => $timer->gif_url_with_cache_bust,
                'needs_regen' => $needsRegen,
                'gif_ready' => !empty($timer->gif_url) && !$needsRegen,
                'share_available' => $timer->isActive() && !empty($timer->gif_url) && !$needsRegen,
                'message' => 'Timer updated.',
            ]);
        }

        return redirect()->route('timers.edit', $timer)->with('status', 'Timer updated!');
    }

    public function destroy(Timer $timer): RedirectResponse
    {
        $this->authorizeOwner($timer);
        if ($timer->cache_key) {
            Cache::forget($timer->cache_key);
        }
        $timer->delete();
        return redirect()->route('dashboard')->with('status', 'Timer deleted');
    }

    public function preview(Timer $timer)
    {
        $this->authorizeOwner($timer);
        return view('timers.preview', compact('timer'));
    }

    public function status(Timer $timer)
    {
        $this->authorizeOwner($timer);
        $needsRegen = $timer->needsGifRegeneration();

        return response()->json([
            'id' => $timer->id,
            'gif_url' => $timer->gif_url,
            'gif_url_with_cache_bust' => $timer->gif_url_with_cache_bust,
            'status' => $timer->status,
            'needs_regen' => $needsRegen,
            'gif_ready' => !empty($timer->gif_url) && !$needsRegen,
            'share_available' => $timer->isActive() && !empty($timer->gif_url) && !$needsRegen,
        ]);
    }

    /**
     * Batch GIF readiness for dashboard polling (only timers owned by the user).
     *
     * @param  list<int|string>|string|null  $ids
     */
    public function gifStatusBatch(Request $request)
    {
        $raw = $request->query('ids', '');
        $ids = is_array($raw)
            ? $raw
            : preg_split('/\s*,\s*/', (string) $raw, -1, PREG_SPLIT_NO_EMPTY);
        $ids = array_values(array_unique(array_filter(array_map('intval', $ids))));
        if (count($ids) > 40) {
            $ids = array_slice($ids, 0, 40);
        }
        if ($ids === []) {
            return response()->json(['timers' => []]);
        }

        $timers = Timer::where('user_id', Auth::id())->whereIn('id', $ids)->get();
        $out = [];

        foreach ($timers as $timer) {
            $needsRegen = $timer->needsGifRegeneration();
            $gifReady = !empty($timer->gif_url) && !$needsRegen;
            $key = (string) $timer->id;
            $embedUrl = ($gifReady && $timer->status === 'active' && !empty($timer->gif_url))
                ? url('/gettimer/'.$timer->user_id.'/'.pathinfo($timer->gif_url, PATHINFO_FILENAME)).'.gif'
                : null;

            $out[$key] = [
                'id' => $timer->id,
                'user_id' => $timer->user_id,
                'gif_url' => $timer->gif_url,
                'gif_url_with_cache_bust' => $timer->gif_url_with_cache_bust,
                'needs_regen' => $needsRegen,
                'gif_ready' => $gifReady,
                'status' => $timer->status,
                'embed_url' => $embedUrl,
            ];
        }

        return response()->json(['timers' => $out]);
    }

    public function previewFrame(Request $request, TimerGifGenerator $generator, ?Timer $timer = null)
    {
        // If timer is provided, authorize ownership
        if ($timer) {
            $this->authorizeOwner($timer);
        }

        // Preview uses relaxed validation so we can render without persisting a counter
        $data = $this->validateTimerPreview($request, $timer);

        $baseAttributes = $timer ? $timer->getAttributes() : [];
        $workingTimer = new Timer($baseAttributes);
        $workingTimer->fill($data);
        $workingTimer->user_id = $timer?->user_id ?? Auth::id();
        $workingTimer->exists = false;

        // Build skin config exactly as GIF generation would
        $remaining = $workingTimer->remainingSecondsFromNow();
        $skin = $generator->loadSkin($workingTimer->skin);
        // customTextColor, customTextDistance are stored in skin_overrides
        if (is_array($workingTimer->skin_overrides) && !empty($workingTimer->skin_overrides)) {
            $skin = array_replace_recursive($skin, $workingTimer->skin_overrides);
        }
        if (empty($skin['finalBackground']) && !empty($skin['background'])) {
            $skin['finalBackground'] = $skin['background'];
        }
        if (empty($skin['finalTextColor']) && !empty($skin['textColor'])) {
            $skin['finalTextColor'] = $skin['textColor'];
        }

        // Render a single PNG frame that matches the saved timer state
        $pngData = $generator->renderPngFrame($remaining, $skin, $workingTimer->custom_text, $workingTimer->text_position);

        // Save to a web-accessible location and return the URL as plain text.
        // This avoids sending binary image data in the response, so any PHP warnings
        // printed before output won't corrupt the PNG stream.
        $previewOwnerId = $workingTimer->user_id ?? Auth::id() ?? 'preview';
        $previewKey = $timer ? "timer-{$timer->id}" : "user-{$previewOwnerId}";
        $path = "timer-frames/previews/{$previewKey}.png";
        Storage::disk('public')->put($path, $pngData);
        // Add cache-busting query to ensure browser loads the latest frame
        $url = Storage::url($path) . '?v=' . time();

        return response(rtrim($url) . "\n")
            ->header('Content-Type', 'text/plain')
            ->header('Cache-Control', 'no-cache, no-store, must-revalidate')
            ->header('Pragma', 'no-cache')
            ->header('Expires', '0');
    }

    public function generateGif(Timer $timer)
    {
        $this->authorizeOwner($timer);

        // If GIF is already up-to-date, no need to regenerate
        if (!$timer->needsGifRegeneration() && !empty($timer->gif_url)) {
            if (request()->expectsJson()) {
                return response()->json(['ok' => true, 'message' => 'GIF is already up to date.']);
            }
            return back()->with('status', 'GIF is already up to date.');
        }

        // Explicit embed/GIF regeneration must complete in this request (not queued), regardless of GIF_GENERATION_MODE.
        (new GenerateTimerGif($timer->id))->handle();
        $timer->refresh();

        if (request()->expectsJson()) {
            $needsRegen = $timer->needsGifRegeneration();

            return response()->json([
                'ok' => true,
                'gif_url' => $timer->gif_url,
                'gif_url_with_cache_bust' => $timer->gif_url_with_cache_bust,
                'needs_regen' => $needsRegen,
                'gif_ready' => !empty($timer->gif_url) && !$needsRegen,
                'message' => 'GIF generated successfully.',
            ]);
        }

        return back()->with('status', 'GIF generated successfully.');
    }

    private function validateTimerPreview(Request $request, ?Timer $timer = null): array
    {
        // Empty strings from HTML inputs fail `integer` / `in` rules; normalize before validation.
        $patch = [];
        foreach (['duration_seconds', 'animation_frame_limit', 'text_position', 'target_datetime', 'custom_text', 'title'] as $key) {
            if ($request->has($key) && $request->input($key) === '') {
                $patch[$key] = null;
            }
        }
        if ($patch !== []) {
            $request->merge($patch);
        }

        $rules = [
            'title' => ['sometimes', 'nullable', 'string', 'max:255'],
            'timer_type' => ['sometimes', 'nullable', 'in:fixed_date,reload_based'],
            'target_datetime' => ['sometimes', 'nullable', 'string'],
            'duration_seconds' => ['sometimes', 'nullable', 'integer', 'min:1', 'max:604800'],
            'skin' => ['sometimes', 'string'],
            'text_position' => ['sometimes', 'nullable', 'in:top,bottom,none'],
            'custom_text' => ['sometimes', 'nullable', 'string', 'max:255'],
            'status' => ['sometimes', 'in:active,inactive'],
            'skin_overrides' => ['sometimes', 'nullable', 'string'],
            'animation_frame_limit' => ['sometimes', 'nullable', 'integer', 'min:10', 'max:60'],
            'client_timezone' => ['sometimes', 'nullable', 'string', 'max:64'],
        ];

        $data = $request->validate($rules);

        $data['title'] = $data['title'] ?? ($timer->title ?? 'Preview Counter');
        $data['timer_type'] = $data['timer_type'] ?? ($timer->timer_type ?? 'fixed_date');

        // Use timer_type to determine which field to prioritize
        // For reload_based: prioritize duration_seconds, ignore target_datetime
        // For fixed_date: prioritize target_datetime, ignore duration_seconds
        if ($data['timer_type'] === 'reload_based') {
            // Evergreen mode: use duration_seconds, ignore target_datetime for calculation
            $duration = $data['duration_seconds'] ?? ($timer->duration_seconds ?? null);
            if ($duration && (int)$duration > 0) {
                $data['duration_seconds'] = max(1, min((int) $duration, 604800));
                $data['target_datetime'] = null; // Clear target_datetime for reload_based
            } else {
                // No valid duration provided, default to 1 second
                $data['duration_seconds'] = 1;
                $data['target_datetime'] = null;
            }
        } else {
            // Fixed date mode: use target_datetime, ignore duration_seconds for calculation
            if (!empty($data['target_datetime'])) {
                $tz = (string) ($data['client_timezone'] ?? $request->input('client_timezone') ?? '');
                $targetUtc = $this->parseClientDateTimeToUtc((string) $data['target_datetime'], $tz);
                if ($targetUtc->lt(now()->utc())) {
                    $targetUtc = now()->utc()->addDays(3);
                }
                $data['target_datetime'] = $targetUtc;
            } elseif ($timer && $timer->target_datetime) {
                $data['target_datetime'] = $timer->target_datetime;
            } else {
                $data['target_datetime'] = now()->utc()->addDays(3);
            }
            $data['duration_seconds'] = null; // Clear duration_seconds for fixed_date
        }

        $data['skin'] = $data['skin'] ?? ($timer->skin ?? null);
        if (empty($data['skin'])) {
            throw \Illuminate\Validation\ValidationException::withMessages([
                'skin' => 'A skin is required to preview the counter.',
            ]);
        }

        $data['text_position'] = $data['text_position'] ?? ($timer->text_position ?? 'bottom');
        $data['status'] = $data['status'] ?? ($timer->status ?? 'active');
        $data['animation_frame_limit'] = (int) ($data['animation_frame_limit'] ?? ($timer->animation_frame_limit ?? 20));

        $overridesRaw = $request->input('skin_overrides');
        if (is_string($overridesRaw) && $overridesRaw !== '') {
            try {
                $decoded = json_decode($overridesRaw, true, 512, JSON_THROW_ON_ERROR);
                if (is_array($decoded)) {
                    $data['skin_overrides'] = $decoded;
                }
            } catch (\Throwable $e) {
                // Ignore malformed overrides in preview validation
            }
        } elseif ($timer && is_array($timer->skin_overrides)) {
            $data['skin_overrides'] = $timer->skin_overrides;
        }

        return $data;
    }

    private function validateTimer(Request $request): array
    {
        $rules = [
            'title' => ['required', 'string', 'max:255'],
            'timer_type' => ['nullable', 'in:fixed_date,reload_based'], // Made nullable to allow defaults
            'target_datetime' => ['nullable', 'string'],
            'duration_seconds' => ['nullable', 'integer', 'min:1', 'max:604800'],
            'skin' => ['required', 'string'],
            'text_position' => ['required', 'in:top,bottom,none'],
            'custom_text' => ['nullable', 'string', 'max:255'],
            'status' => ['nullable', 'in:active,inactive'],
            'skin_overrides' => ['nullable', 'string'],
            'animation_frame_limit' => ['nullable', 'integer', 'min:10', 'max:60'],
            'client_timezone' => ['nullable', 'string', 'max:64'],
        ];

        $data = $request->validate($rules);
        
        // Set default timer_type if not provided
        if (empty($data['timer_type'])) {
            $data['timer_type'] = 'fixed_date';
        }

        if ($data['timer_type'] === 'fixed_date') {
            $data['duration_seconds'] = null;
            if (!empty($data['target_datetime'])) {
                $tz = (string) ($data['client_timezone'] ?? $request->input('client_timezone') ?? '');
                $targetUtc = $this->parseClientDateTimeToUtc((string) $data['target_datetime'], $tz);
                if ($targetUtc->lt(now()->utc())) {
                    throw \Illuminate\Validation\ValidationException::withMessages([
                        'target_datetime' => 'The target date and time must be in the future.',
                    ]);
                }
                $data['target_datetime'] = $targetUtc;
            }
        } else {
            $data['target_datetime'] = null;
        }

        $data['status'] = $data['status'] ?? 'active';
        $data['animation_frame_limit'] = (int) ($data['animation_frame_limit'] ?? 20);

        // Decode overrides JSON if provided
        $overridesRaw = $request->input('skin_overrides');
        if (is_string($overridesRaw) && $overridesRaw !== '') {
            try {
                $decoded = json_decode($overridesRaw, true, 512, JSON_THROW_ON_ERROR);
                if (is_array($decoded)) {
                    $data['skin_overrides'] = $decoded;
                }
            } catch (\Throwable $e) {
                // Ignore malformed overrides; validation remains successful without them
            }
        }

        // If days are hidden, restrict the countdown to at most 24 hours.
        $overrides = $data['skin_overrides'] ?? [];
        $daysVisible = $overrides['showDays'] ?? true;
        if ($daysVisible === false) {
            $maxSeconds = 86400; // 24h

            if ($data['timer_type'] === 'fixed_date' && !empty($data['target_datetime'])) {
                $diff = now()->utc()->diffInSeconds($data['target_datetime'], false);
                if ($diff > $maxSeconds) {
                    throw \Illuminate\Validation\ValidationException::withMessages([
                        'target_datetime' => 'When Days are hidden, the counter duration must be 24 hours or less.',
                    ]);
                }
            }

            if ($data['timer_type'] === 'reload_based' && !empty($data['duration_seconds'])) {
                if ((int) $data['duration_seconds'] > $maxSeconds) {
                    throw \Illuminate\Validation\ValidationException::withMessages([
                        'duration_seconds' => 'When Days are hidden, the counter duration must be 24 hours or less.',
                    ]);
                }
            }
        }
        return $data;
    }

    private function parseClientDateTimeToUtc(string $value, string $timezone): Carbon
    {
        $tz = 'UTC';
        if ($timezone !== '') {
            try {
                $resolved = new DateTimeZone($timezone);
                $tz = $resolved->getName();
            } catch (\Throwable $e) {
                $tz = 'UTC';
            }
        }

        $raw = trim($value);
        if ($raw === '') {
            return now()->utc();
        }

        foreach (['Y-m-d\TH:i', 'Y-m-d\TH:i:s'] as $fmt) {
            try {
                $dt = Carbon::createFromFormat($fmt, $raw, $tz);
                if ($dt !== false) {
                    return $dt->utc();
                }
            } catch (\Throwable $e) {
                // try next format
            }
        }

        // Fallback: let Carbon parse and assume provided timezone
        return Carbon::parse($raw, $tz)->utc();
    }

    private function authorizeOwner(Timer $timer): void
    {
        if ($timer->user_id !== Auth::id()) {
            abort(403);
        }
    }

    private function availableSkins(): array
    {
        $dir = resource_path('timerskins');
        if (!is_dir($dir)) return [];
        $files = glob($dir . DIRECTORY_SEPARATOR . '*.json') ?: [];
        return array_values(array_map(function ($path) {
            return pathinfo($path, PATHINFO_FILENAME);
        }, $files));
    }

    private function deleteGifFile(Timer $timer): void
    {
        $url = (string) $timer->gif_url;
        if ($url === '') return;
        $path = parse_url($url, PHP_URL_PATH) ?: $url;
        $fullPath = public_path(ltrim($path, '/'));
        if (File::exists($fullPath)) {
            @File::delete($fullPath);
        }
    }
}
