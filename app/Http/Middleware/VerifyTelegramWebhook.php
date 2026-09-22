<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpFoundation\Response;

/**
 * URL webhook публичный, поэтому подлинность запроса подтверждает секрет,
 * который Telegram присылает в заголовке X-Telegram-Bot-Api-Secret-Token.
 */
class VerifyTelegramWebhook
{
    public function handle(Request $request, Closure $next): Response
    {
        $expected = (string) config('telegram.webhook_secret');

        if ($expected === '') {
            Log::error('TELEGRAM_WEBHOOK_SECRET не задан — webhook отклоняет все запросы.');

            abort(500, 'Webhook secret is not configured.');
        }

        $provided = (string) $request->header('X-Telegram-Bot-Api-Secret-Token');

        if (! hash_equals($expected, $provided)) {
            abort(403, 'Invalid webhook secret.');
        }

        return $next($request);
    }
}
