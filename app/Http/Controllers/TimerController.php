<?php

namespace App\Http\Controllers;

use App\Jobs\GenerateTimerGif;
use App\Models\Timer;
use App\Services\TimerGifGenerator;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;

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

        $timer = new Timer($data);
        $timer->gif_url = '';
        $timer->cache_key = 'timer_' . $timer->id . '_' . bin2hex(random_bytes(3));
        $timer->save();

        // Dispatch job to generate GIF in background
        $gen = new GenerateTimerGif($timer);
        $gen->handle();
        // GenerateTimerGif::dispatch($timer);

        if ($request->expectsJson()) {
            return response()->json([
                'id' => $timer->id,
                'gif_url' => $timer->gif_url,
                'message' => 'Timer created. GIF is being generated in the background.'
            ]);
        }

        return redirect()->route('timers.edit', $timer)->with('status', 'Timer created! GIF is being generated in the background.');
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

        // Clear cache entry if any
        if ($timer->cache_key) {
            Cache::forget($timer->cache_key);
        }

        // Dispatch job to regenerate GIF in background
        $gen = new GenerateTimerGif($timer);
        $gen->handle();
        // GenerateTimerGif::dispatch($timer);

        if ($request->expectsJson()) {
            return response()->json([
                'id' => $timer->id,
                'gif_url' => $timer->gif_url,
                'message' => 'Timer updated. GIF is being regenerated in the background.'
            ]);
        }

        return redirect()->route('timers.edit', $timer)->with('status', 'Timer updated! GIF is being regenerated in the background.');
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
        ]);
    }

    private function validateTimer(Request $request): array
    {
        $rules = [
            'title' => ['required', 'string', 'max:255'],
            'timer_type' => ['required', 'in:fixed_date,reload_based'],
            'target_datetime' => ['nullable', 'date'],
            'duration_seconds' => ['nullable', 'integer', 'min:1', 'max:604800'],
            'skin' => ['required', 'string'],
            'text_position' => ['required', 'in:top,bottom,none'],
            'custom_text' => ['nullable', 'string', 'max:255'],
            'status' => ['nullable', 'in:active,inactive'],
            'skin_overrides' => ['nullable', 'string'],
            'animation_frame_limit' => ['nullable', 'integer', 'min:10', 'max:60'],
        ];

        $data = $request->validate($rules);

        if ($data['timer_type'] === 'fixed_date') {
            $data['duration_seconds'] = null;
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


