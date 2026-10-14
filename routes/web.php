<?php

use App\Http\Controllers\Auth\SessionController;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return view('welcome');
});

// ###> api-platform/admin ###
Route::get('/admin/{path?}', fn () => view('admin'))->where('path', '.*');
// ###< api-platform/admin ###

Route::prefix('artisan')->middleware('guest:web')->group(function (): void {
    Route::get('/login', [SessionController::class, 'create'])->name('login');
    Route::post('/login', [SessionController::class, 'store'])->middleware('throttle:login');
});

Route::prefix('artisan')->middleware('auth:web')->group(function (): void {
    Route::view('/account', 'auth.dashboard')->name('runner.dashboard');
    Route::post('/logout', [SessionController::class, 'destroy'])->name('logout');
});
