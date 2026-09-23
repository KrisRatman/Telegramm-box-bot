<?php

use App\Http\Controllers\MiniApp\MiniAppController;
use App\Http\Controllers\MiniApp\OrderController;
use App\Http\Controllers\MiniApp\ProfileController;
use App\Http\Controllers\TelegramWebhookController;
use App\Http\Middleware\AuthenticateMiniApp;
use App\Http\Middleware\VerifyTelegramWebhook;
use Illuminate\Support\Facades\Route;

Route::get('/', fn () => redirect('/admin'));

Route::post('/telegram/webhook', TelegramWebhookController::class)
    ->middleware(VerifyTelegramWebhook::class)
    ->name('telegram.webhook');

// --- Mini App ----------------------------------------------------------------

Route::get('/app', MiniAppController::class)->name('mini-app');

Route::prefix('app/api')
    ->name('mini-app.')
    ->middleware([AuthenticateMiniApp::class, 'throttle:30,1'])
    ->group(function () {
        Route::get('/profile', ProfileController::class)->name('profile');
        Route::post('/orders', [OrderController::class, 'store'])->name('orders.store');
    });
