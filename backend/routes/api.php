<?php

use App\Http\Controllers\AuthController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\DriverController;
use App\Http\Controllers\FilesController;
use App\Http\Controllers\ImportController;
use App\Http\Controllers\MessagesController;
use App\Http\Controllers\OrganizationController;
use App\Http\Controllers\PlanController;
use App\Http\Controllers\ReportController;
use App\Http\Controllers\RouteTemplateController;
use App\Http\Controllers\ShopController;
use App\Http\Controllers\TeamController;
use App\Http\Controllers\TripController;
use App\Http\Controllers\WhatsAppWebhookController;
use Illuminate\Support\Facades\Route;

// Public
Route::get('/auth/csrf', [AuthController::class, 'csrf']);
Route::post('/auth/register', [AuthController::class, 'register'])->middleware('throttle:10,1');
Route::post('/auth/login', [AuthController::class, 'login'])->middleware('throttle:10,1');
Route::get('/auth/invitations/{token}', [AuthController::class, 'invitation']);
Route::post('/auth/join', [AuthController::class, 'join'])->middleware('throttle:10,1');
Route::get('/public/files/{file}', [FilesController::class, 'publicShow'])->name('files.public')->middleware('signed:relative');
Route::get('/webhooks/whatsapp', [WhatsAppWebhookController::class, 'verify']);
Route::post('/webhooks/whatsapp', [WhatsAppWebhookController::class, 'receive']);

// Signed in
Route::middleware('auth:sanctum')->group(function () {
    Route::post('/auth/logout', [AuthController::class, 'logout']);
    Route::get('/auth/me', [AuthController::class, 'show']);
    Route::put('/auth/me', [AuthController::class, 'update']);

    Route::get('/organization', [OrganizationController::class, 'show']);
    Route::put('/organization', [OrganizationController::class, 'update']);

    Route::get('/team', [TeamController::class, 'index']);
    Route::post('/team/invitations', [TeamController::class, 'invite']);
    Route::delete('/team/invitations/{id}', [TeamController::class, 'revoke'])->whereNumber('id');
    Route::delete('/team/members/{id}', [TeamController::class, 'remove'])->whereNumber('id');

    Route::get('/messages', [MessagesController::class, 'outbox']);
    Route::get('/messages/inbound', [MessagesController::class, 'inbox']);
    Route::post('/messages/{id}/retry', [MessagesController::class, 'retry'])->whereNumber('id');
    Route::post('/dev/inbound', [MessagesController::class, 'simulate']);

    Route::get('/files/{id}', [FilesController::class, 'show']);

    // The driver's phone: only their own, published trips.
    Route::prefix('driver')->controller(DriverController::class)->group(function () {
        Route::get('/today', 'today');
        Route::get('/trips', 'trips');
        Route::get('/trips/{id}', 'trip')->whereNumber('id');
        Route::post('/trips/{id}/start', 'start')->whereNumber('id');
        Route::post('/trips/{id}/finish', 'finish')->whereNumber('id');
        Route::post('/stops/{id}/outcome', 'outcome')->whereNumber('id');
        Route::post('/stops/{id}/proof', 'proof')->whereNumber('id');
    });

    // Owner and dispatcher: shops, routes, planning, reports.
    Route::middleware('role:OWNER,DISPATCHER')->group(function () {
        Route::get('/dashboard', [DashboardController::class, 'index']);
        Route::get('/map', [DashboardController::class, 'map']);
        Route::get('/team/drivers', [TeamController::class, 'drivers']);

        Route::get('/shops', [ShopController::class, 'index']);
        Route::post('/shops', [ShopController::class, 'store']);
        Route::get('/shops/{shop}', [ShopController::class, 'show'])->whereNumber('shop');
        Route::put('/shops/{shop}', [ShopController::class, 'update'])->whereNumber('shop');
        Route::get('/shops/{shop}/observations', [ShopController::class, 'observations'])->whereNumber('shop');
        Route::get('/shops/{shop}/visits', [ShopController::class, 'visits'])->whereNumber('shop');
        Route::post('/imports/shops', [ImportController::class, 'shops']);
        Route::post('/imports/observations', [ImportController::class, 'observations']);

        Route::apiResource('routes', RouteTemplateController::class)
            ->parameters(['routes' => 'template'])
            ->whereNumber('template');

        Route::get('/plan/{date}', [PlanController::class, 'day']);
        Route::post('/plan/generate', [PlanController::class, 'generate']);
        Route::controller(TripController::class)->prefix('trips/{trip}')->whereNumber('trip')->group(function () {
            Route::get('/', 'show');
            Route::put('/', 'update');
            Route::delete('/', 'destroy');
            Route::post('/optimise', 'optimise');
            Route::put('/order', 'order');
            Route::post('/publish', 'publish');
            Route::post('/unpublish', 'unpublish');
        });

        Route::get('/reports', [ReportController::class, 'index']);
    });
});
