<?php

namespace App\Http\Middleware;

use App\Models\Bot;
use App\Models\TelegramUser;
use App\Services\Telegram\InitDataValidator;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Авторизация запросов из Mini App. Фронт шлёт Telegram.WebApp.initData
 * в заголовке X-Telegram-Init-Data. Подпись проверяется токеном бота из
 * адреса (/app/{bot}/api/...): данные, подписанные другим ботом, не пройдут.
 * Пользователь попадает в атрибуты запроса под ключом telegram_user.
 */
class AuthenticateMiniApp
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

        $validator = new InitDataValidator(
            botToken: (string) $bot->token,
            ttlSeconds: (int) config('telegram.mini_app.auth_ttl'),
        );

        $from = $validator->validate((string) $request->header('X-Telegram-Init-Data'));

        if ($from === null) {
            return response()->json([
                'message' => __('mini-app.errors.unauthorized'),
            ], Response::HTTP_UNAUTHORIZED);
        }

        $user = TelegramUser::updateOrCreate(
            ['bot_id' => $bot->id, 'chat_id' => $from['id']],
            [
                'username' => $from['username'] ?? null,
                'first_name' => $from['first_name'] ?? null,
                'last_name' => $from['last_name'] ?? null,
                'language_code' => ($from['language_code'] ?? null) ?: 'ru',
                'last_activity_at' => now(),
            ],
        );

        $request->attributes->set('telegram_user', $user);

        // Ошибки валидации и сообщение в чат — на языке клиента.
        app()->setLocale($user->preferredLocale());

        return $next($request);
    }
}
