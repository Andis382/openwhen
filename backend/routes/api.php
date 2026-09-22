<?php

use App\Http\Controllers\AuthController;
use App\Http\Controllers\FilesController;
use App\Http\Controllers\MessagesController;
use App\Http\Controllers\OrganizationController;
use App\Http\Controllers\TeamController;
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
});
