<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\TimerController;
use App\Http\Controllers\GifController;

Route::get('/', function () {
    return view('welcome');
});

// Auth routes will be provided by Laravel Breeze once installed

// Dashboard & timers (authenticated)
Route::middleware(['auth'])->group(function () {
    Route::get('/dashboard', [TimerController::class, 'index'])->name('dashboard');
    Route::resource('timers', TimerController::class)->except(['index', 'show']);
    Route::get('timers/{timer}/preview', [TimerController::class, 'preview'])->name('timers.preview');
});

// Public GIF endpoint
Route::get('/timers/{user_id}/{filename}.gif', [GifController::class, 'show'])->where('filename', '.*');
