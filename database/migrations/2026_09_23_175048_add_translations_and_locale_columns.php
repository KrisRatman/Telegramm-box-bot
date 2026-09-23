<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Русский остаётся в name и description — это основной язык каталога,
     * по нему работают поиск и админка. Переводы лежат в translations:
     * {"en": {"name": "...", "description": "..."}}. Нет перевода — показываем русский.
     */
    public function up(): void
    {
        foreach (['services', 'service_categories'] as $tableName) {
            Schema::table($tableName, function (Blueprint $table) {
                $table->json('translations')->nullable()->after('description');
            });
        }

        Schema::table('telegram_users', function (Blueprint $table) {
            // Язык, выбранный командой /language. Пусто — берём язык Telegram.
            $table->string('locale', 5)->nullable()->after('language_code');
        });
    }

    public function down(): void
    {
        Schema::table('telegram_users', function (Blueprint $table) {
            $table->dropColumn('locale');
        });

        foreach (['services', 'service_categories'] as $tableName) {
            Schema::table($tableName, function (Blueprint $table) {
                $table->dropColumn('translations');
            });
        }
    }
};
