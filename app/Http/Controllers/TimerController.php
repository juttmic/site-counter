<?php

namespace App\Http\Controllers;

use App\Jobs\GenerateTimerGif;
use App\Models\Timer;
use App\Services\TimerGifGenerator;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Storage;
use Carbon\Carbon;

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
            $job = new GenerateTimerGif($timer);
            $job->handle();
        }

        if ($request->expectsJson()) {
            return response()->json([
                'id' => $timer->id,
                'gif_url' => $timer->gif_url,
                'gif_url_with_cache_bust' => $timer->gif_url_with_cache_bust,
                'share_available' => $timer->isActive() && !empty($timer->gif_url),
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
        $data = $this->validateTimer($request);
        $timer->fill($data);
        $timer->save();

        // Optionally generate GIF immediately when requested (e.g. from Generate GIF button)
        if ($request->boolean('generate_gif')) {
            $job = new GenerateTimerGif($timer);
            $job->handle();
        }

        if ($request->expectsJson()) {
            return response()->json([
                'id' => $timer->id,
                'gif_url' => $timer->gif_url,
                'gif_url_with_cache_bust' => $timer->gif_url_with_cache_bust,
                'share_available' => $timer->isActive() && !empty($timer->gif_url),
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
        return response()->json([
            'id' => $timer->id,
            'gif_url' => $timer->gif_url,
            'gif_url_with_cache_bust' => $timer->gif_url_with_cache_bust,
            'status' => $timer->status,
            'share_available' => $timer->isActive() && !empty($timer->gif_url),
            'needs_regen' => $timer->needsGifRegeneration(),
        ]);
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
        // customTextColor, customTextMarginTop, customTextMarginBottom are stored in skin_overrides
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

        // Generate or regenerate the GIF synchronously for now
        $job = new GenerateTimerGif($timer);
        $job->handle();

        if (request()->expectsJson()) {
            $freshTimer = $timer->fresh();
            $gifUrl = $freshTimer->gif_url_with_cache_bust ?? $freshTimer->gif_url;
            return response()->json([
                'ok' => true,
                'gif_url' => $gifUrl ? url($gifUrl) : null,
                'message' => 'GIF generated successfully.',
            ]);
        }

        return back()->with('status', 'GIF generated successfully.');
    }

    private function validateTimerPreview(Request $request, ?Timer $timer = null): array
    {
        $rules = [
            'title' => ['sometimes', 'nullable', 'string', 'max:255'],
            'timer_type' => ['sometimes', 'nullable', 'in:fixed_date,reload_based'],
            'target_datetime' => ['sometimes', 'nullable', 'date', 'after_or_equal:now'],
            'duration_seconds' => ['sometimes', 'nullable', 'integer', 'min:1', 'max:604800'],
            'skin' => ['sometimes', 'string'],
            'text_position' => ['sometimes', 'in:top,bottom,none'],
            'custom_text' => ['sometimes', 'nullable', 'string', 'max:255'],
            'status' => ['sometimes', 'in:active,inactive'],
            'skin_overrides' => ['sometimes', 'nullable', 'string'],
            'animation_frame_limit' => ['sometimes', 'nullable', 'integer', 'min:10', 'max:60'],
        ];

        $data = $request->validate($rules);

        $data['title'] = $data['title'] ?? ($timer->title ?? 'Preview Counter');
        $data['timer_type'] = $data['timer_type'] ?? ($timer->timer_type ?? 'fixed_date');

        if ($data['timer_type'] === 'fixed_date') {
            $data['duration_seconds'] = null;
            if (!empty($data['target_datetime'])) {
                $target = Carbon::parse($data['target_datetime']);
                $data['target_datetime'] = $target->lt(now()) ? now()->addDays(3) : $target;
            } elseif ($timer && $timer->target_datetime) {
                $data['target_datetime'] = $timer->target_datetime;
            } else {
                $data['target_datetime'] = now()->addDays(3);
            }
        } else {
            $duration = $data['duration_seconds'] ?? ($timer->duration_seconds ?? null);
            $data['duration_seconds'] = $duration ? max(1, min((int) $duration, 604800)) : 60;
            $data['target_datetime'] = null;
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
            'target_datetime' => ['nullable', 'date', 'after_or_equal:now'],
            'duration_seconds' => ['nullable', 'integer', 'min:1', 'max:604800'],
            'skin' => ['required', 'string'],
            'text_position' => ['required', 'in:top,bottom,none'],
            'custom_text' => ['nullable', 'string', 'max:255'],
            'status' => ['nullable', 'in:active,inactive'],
            'skin_overrides' => ['nullable', 'string'],
            'animation_frame_limit' => ['nullable', 'integer', 'min:10', 'max:60'],
        ];

        $data = $request->validate($rules);
        
        // Set default timer_type if not provided
        if (empty($data['timer_type'])) {
            $data['timer_type'] = 'fixed_date';
        }

        if ($data['timer_type'] === 'fixed_date') {
            $data['duration_seconds'] = null;
            if (!empty($data['target_datetime'])) {
                $target = Carbon::parse($data['target_datetime']);
                if ($target->lt(now())) {
                    throw \Illuminate\Validation\ValidationException::withMessages([
                        'target_datetime' => 'The target date and time must be in the future.',
                    ]);
                }
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
        return $data;
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
}
