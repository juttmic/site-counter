<?php

use App\Http\Controllers\EmpirelyController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

Route::get('/user', function (Request $request) {
    return $request->user();
})->middleware('auth:sanctum');

Route::post('/auto/register', [EmpirelyController::class, 'register']);
Route::get('/status', [EmpirelyController::class, 'statusCheck'])->name('status');