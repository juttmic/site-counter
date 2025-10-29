<?php

use App\Http\Controllers\ProfileController;
use App\Http\Controllers\TimerController;
use App\Http\Controllers\GifController;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return view('welcome');
});

// Use controller for dashboard so it lists user's timers
Route::get('/dashboard', [TimerController::class, 'index'])
    ->middleware(['auth', 'verified'])
    ->name('dashboard');

Route::middleware('auth')->group(function () {
    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::delete('/profile', [ProfileController::class, 'destroy'])->name('profile.destroy');

    // Timers CRUD and preview
    Route::resource('timers', TimerController::class)->except(['store','index', 'show']);
    Route::post('timerss', [TimerController::class, 'store'])->name('timers.store');
    Route::get('timers/{timer}/preview', [TimerController::class, 'preview'])->name('timers.preview');
});

// Public GIF endpoint (no auth)
Route::get('/timers/{user_id}/{filename}.gif', [GifController::class, 'show'])
    ->where('filename', '.*');

require __DIR__.'/auth.php';
