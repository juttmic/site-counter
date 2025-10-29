<?php

namespace App\Http\Controllers;

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
        $this->middleware('auth');
    }

    public function index()
    {
        $timers = Timer::where('user_id', Auth::id())->latest()->paginate(12);
        return view('dashboard.index', compact('timers'));
    }

    public function create()
    {
        $skins = $this->availableSkins();
        return view('timers.create', compact('skins'));
    }

    public function store(Request $request, TimerGifGenerator $generator): RedirectResponse
    {
        $data = $this->validateTimer($request);
        $data['user_id'] = Auth::id();

        $timer = new Timer($data);
        $timer->gif_url = '';
        $timer->save();

        $timer->cache_key = 'timer_' . $timer->id . '_' . bin2hex(random_bytes(3));
        $timer->gif_url = $generator->generateAndStore($timer);
        $timer->save();

        return redirect()->route('timers.edit', $timer)->with('status', 'Timer created');
    }

    public function edit(Timer $timer)
    {
        $this->authorizeOwner($timer);
        $skins = $this->availableSkins();
        return view('timers.edit', compact('timer', 'skins'));
    }

    public function update(Request $request, Timer $timer, TimerGifGenerator $generator): RedirectResponse
    {
        $this->authorizeOwner($timer);
        $data = $this->validateTimer($request);
        $timer->fill($data);
        $timer->save();

        // Regenerate GIF and clear cache entry if any
        $timer->gif_url = $generator->generateAndStore($timer);
        $timer->save();
        if ($timer->cache_key) {
            Cache::forget($timer->cache_key);
        }
        return redirect()->route('timers.edit', $timer)->with('status', 'Timer updated');
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
        ];

        $data = $request->validate($rules);

        if ($data['timer_type'] === 'fixed_date') {
            $data['duration_seconds'] = null;
        } else {
            $data['target_datetime'] = null;
        }

        $data['status'] = $data['status'] ?? 'active';
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


