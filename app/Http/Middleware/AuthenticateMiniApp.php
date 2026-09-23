<?php

namespace App\Http\Middleware;

use App\Models\TelegramUser;
use App\Services\Telegram\InitDataValidator;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Авторизация запросов из Mini App. Фронт шлёт Telegram.WebApp.initData
 * в заголовке X-Telegram-Init-Data; по нему находим или заводим пользователя
 * и кладём его в атрибуты запроса под ключом telegram_user.
 */
class AuthenticateMiniApp
{
    public function __construct(private readonly InitDataValidator $validator) {}

    public function handle(Request $request, Closure $next): Response
    {
        $from = $this->validator->validate((string) $request->header('X-Telegram-Init-Data'));

        if ($from === null) {
            return response()->json([
                'message' => 'Откройте каталог из Telegram-бота.',
            ], Response::HTTP_UNAUTHORIZED);
        }

        $user = TelegramUser::updateOrCreate(
            ['chat_id' => $from['id']],
            [
                'username' => $from['username'] ?? null,
                'first_name' => $from['first_name'] ?? null,
                'last_name' => $from['last_name'] ?? null,
                'language_code' => ($from['language_code'] ?? null) ?: 'ru',
                'last_activity_at' => now(),
            ],
        );

        $request->attributes->set('telegram_user', $user);

        return $next($request);
    }
}
