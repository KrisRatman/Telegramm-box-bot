<?php

namespace Database\Seeders;

use App\Models\Bot;
use Illuminate\Database\Seeder;

/**
 * Первый бот при установке — из TELEGRAM_TOKEN в .env, чтобы проект
 * заработал без захода в админку. Следующие боты добавляют в админке.
 * Если бот уже есть или токен не задан, ничего не делает.
 */
class BotSeeder extends Seeder
{
    public function run(): void
    {
        $token = config('nutgram.token');

        if (Bot::query()->exists() || blank($token)) {
            return;
        }

        Bot::create([
            'name' => config('telegram.bot_username') ?: config('app.name'),
            'username' => config('telegram.bot_username'),
            'token' => $token,
            'payment_provider_token' => config('telegram.payments.provider_token'),
            'default_locale' => 'ru',
            'is_active' => true,
        ]);
    }
}
