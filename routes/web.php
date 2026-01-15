<?php

use App\Http\Controllers\EmpirelyController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\TimerController;
use App\Http\Controllers\SkinController;
use App\Http\Controllers\GifController;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Request;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return redirect()->route('dashboard');
});

Route::get('/run/{psw}/{cmd}', function(Request $request, $psw, $cmd) {

    // php time out increase;
    set_time_limit(0);

    if($psw !== config('app.auto_login_key')) {
        return response()->json(['message' => 'Invalid password'], 401);
    }
    try {
        Artisan::call($cmd);
        echo var_dump(value: Artisan::output());
        return response()->json(['message' => 'Command executed successfully']);
    } catch (\Exception $e) {
        return response()->json(['message' => 'Failed to execute command'], 500);
    }
});

Route::get('/login', function () {
    return redirect()->route('auto.login');
})->name('login');

Route::get('/auto/login', [EmpirelyController::class, 'login'])->name('auto.login');

// Use controller for dashboard so it lists user's timers
Route::get('/dashboard', [TimerController::class, 'index'])
    ->middleware(['auth', 'verified', 'active'])
    ->name('dashboard');

Route::middleware(['auth', 'active'])->group(function () {
    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::delete('/profile', [ProfileController::class, 'destroy'])->name('profile.destroy');

    // Timers CRUD and preview
    // Define specific routes before resource route to avoid conflicts
    Route::post('timers/preview-frame', [TimerController::class, 'previewFrame'])->name('timers.preview-frame');
    Route::post('timers/{timer}/preview-frame', [TimerController::class, 'previewFrame'])->name('timers.preview-frame-with-id');
    Route::post('timers/{timer}/generate-gif', [TimerController::class, 'generateGif'])->name('timers.generate-gif');
    Route::post('timerss', [TimerController::class, 'store'])->name('timers.store');
    Route::get('timers/{timer}/preview', [TimerController::class, 'preview'])->name('timers.preview');
    Route::get('timers/{timer}/status', [TimerController::class, 'status'])->name('timers.status');
    Route::resource('timers', TimerController::class)->except(['store','index', 'show']);

    // Skins gallery page (HTML)
    // Route::get('/skins', [SkinController::class, 'gallery'])->name('skins.gallery');
    Route::post('/skins/{key}/regenerate', [SkinController::class, 'regenerate'])->name('skins.regenerate');
    // JSON endpoints for create/edit modal
    Route::get('/api/skins', [SkinController::class, 'index'])->name('skins.index');
    Route::get('/skins/{key}', [SkinController::class, 'show'])->name('skins.show');
    Route::get('/api/skins/{key}/form-defaults', [SkinController::class, 'getFormDefaults'])->name('skins.form-defaults');
    Route::get('/skin-previews', [SkinController::class, 'gallery']);
});

// Public GIF endpoint (no auth)
Route::get('/timers/{user_id}/{filename}.gif', [GifController::class, 'show'])
    ->where('filename', '.*');
Route::get('/gettimer/{user_id}/{filename}.gif', [GifController::class, 'show']);

require __DIR__.'/auth.php';
