<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

return new class extends Migration
{
    /**
     * Пользователи, заявки и рассылки теперь принадлежат конкретному боту.
     * Всё, что накопилось до этой миграции, отдаём первому боту, собранному
     * из прежних настроек .env: TELEGRAM_TOKEN, секрет webhook и токен оплаты.
     */
    public function up(): void
    {
        $botId = $this->createBotForExistingData();

        foreach (['telegram_users', 'orders', 'broadcasts'] as $tableName) {
            Schema::table($tableName, function (Blueprint $table) {
                $table->foreignId('bot_id')->nullable()->after('id')->constrained()->cascadeOnDelete();
            });

            if ($botId !== null) {
                DB::table($tableName)->update(['bot_id' => $botId]);
            }

            Schema::table($tableName, function (Blueprint $table) {
                $table->foreignId('bot_id')->nullable(false)->change();
            });
        }

        // Один и тот же человек в двух ботах — два разных пользователя:
        // у каждого своя переписка, язык и статус блокировки.
        Schema::table('telegram_users', function (Blueprint $table) {
            $table->dropUnique(['chat_id']);
            $table->unique(['bot_id', 'chat_id']);
        });
    }

    public function down(): void
    {
        Schema::table('telegram_users', function (Blueprint $table) {
            $table->dropUnique(['bot_id', 'chat_id']);
            $table->unique('chat_id');
        });

        foreach (['broadcasts', 'orders', 'telegram_users'] as $tableName) {
            Schema::table($tableName, function (Blueprint $table) {
                $table->dropConstrainedForeignId('bot_id');
            });
        }
    }

    private function createBotForExistingData(): ?int
    {
        $hasData = DB::table('telegram_users')->exists() || DB::table('broadcasts')->exists();

        if (! $hasData) {
            return null;
        }

        $token = config('nutgram.token');
        $paymentToken = config('telegram.payments.provider_token');

        return DB::table('bots')->insertGetId([
            'name' => config('telegram.bot_username') ?: config('app.name'),
            'username' => config('telegram.bot_username'),
            'token' => filled($token) ? Crypt::encryptString($token) : null,
            'webhook_secret' => Crypt::encryptString(config('telegram.webhook_secret') ?: Str::random(48)),
            'payment_provider_token' => filled($paymentToken) ? Crypt::encryptString($paymentToken) : null,
            'default_locale' => 'ru',
            'is_active' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }
};
