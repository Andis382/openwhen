<?php

use App\Http\Controllers\Auth\AuthController;
use App\Http\Controllers\RouteController;
use App\Http\Controllers\ShopController;
use App\Http\Controllers\TodayController;
use App\Http\Controllers\VisitController;
use Illuminate\Support\Facades\Route;

Route::get('/', [TodayController::class, 'landing'])->name('home');

Route::middleware('guest')->group(function () {
    Route::get('login', [AuthController::class, 'showLogin'])->name('login');
    Route::post('login', [AuthController::class, 'login']);
    Route::get('register', [AuthController::class, 'showRegister'])->name('register');
    Route::post('register', [AuthController::class, 'register']);
});

Route::post('logout', [AuthController::class, 'logout'])->name('logout');

Route::middleware('auth')->group(function () {
    Route::get('today', [TodayController::class, 'index'])->name('today');
    Route::get('settings', [AuthController::class, 'settings'])->name('settings');
    Route::put('settings', [AuthController::class, 'updateSettings'])->name('settings.update');

    Route::post('visits', [VisitController::class, 'store'])->name('visits.store');

    // The offline outbox replays here. Idempotent on the id the phone made
    // before the tap ever left it.
    Route::post('api/visits', [VisitController::class, 'sync'])->name('visits.sync');

    Route::get('shops', [ShopController::class, 'index'])->name('shops.index');
    Route::post('shops', [ShopController::class, 'store'])->name('shops.store');
    Route::get('shops/{shop}', [ShopController::class, 'show'])->name('shops.show');
    Route::put('shops/{shop}', [ShopController::class, 'update'])->name('shops.update');

    Route::get('routes', [RouteController::class, 'index'])->name('routes.index');
    Route::post('routes', [RouteController::class, 'store'])->name('routes.store');
    Route::get('routes/{route}', [RouteController::class, 'show'])->name('routes.show');
    Route::post('routes/{route}/sequence', [RouteController::class, 'sequence'])->name('routes.sequence');
    Route::post('routes/{route}/repeat', [RouteController::class, 'repeat'])->name('routes.repeat');
    Route::post('routes/{route}/stops', [RouteController::class, 'addStop'])->name('routes.stops.store');
    Route::delete('routes/{route}/stops/{stop}', [RouteController::class, 'removeStop'])->name('routes.stops.destroy');
});
