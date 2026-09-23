<?php

namespace App\Http\Middleware;

use App\Models\Bot;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * URL webhook публичный, поэтому подлинность запроса подтверждает секрет
 * бота, который Telegram присылает в заголовке X-Telegram-Bot-Api-Secret-Token.
 * Секрет у каждого бота свой: запрос с секретом одного бота к другому не пройдёт.
 */
class VerifyTelegramWebhook
{
    public function handle(Request $request, Closure $next): Response
    {
        // Middleware может отработать раньше привязки моделей к маршруту
        // (Laravel сортирует middleware по приоритету) — тогда здесь ещё id.
        $bot = $request->route('bot');
        $bot = $bot instanceof Bot ? $bot : Bot::query()->find((int) $bot);

        if (! $bot instanceof Bot || ! $bot->is_active) {
            abort(404);
        }

        $provided = (string) $request->header('X-Telegram-Bot-Api-Secret-Token');

        if (! hash_equals((string) $bot->webhook_secret, $provided)) {
            abort(403, 'Invalid webhook secret.');
        }

        return $next($request);
    }
}
