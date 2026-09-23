<?php

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        // Запрос от Telegram приходит без сессии и CSRF-токена. API Mini App
        // авторизуется подписанным initData в заголовке, а не cookie, поэтому
        // подделать запрос с чужого сайта через CSRF там нельзя.
        $middleware->validateCsrfTokens(except: [
            'telegram/webhook/*',
            'app/*/api/*',
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->shouldRenderJsonWhen(
            fn (Request $request) => $request->is('api/*', 'app/*/api/*') || $request->expectsJson(),
        );
    })->create();
