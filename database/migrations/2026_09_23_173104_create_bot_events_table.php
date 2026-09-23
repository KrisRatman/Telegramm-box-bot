<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Шаги воронки, которых не видно по заявкам и оплатам:
     * открыл каталог, начал оформлять заявку.
     */
    public function up(): void
    {
        Schema::create('bot_events', function (Blueprint $table) {
            $table->id();
            $table->foreignId('telegram_user_id')->constrained()->cascadeOnDelete();
            $table->string('type', 32);
            $table->string('source', 16);
            $table->timestamp('created_at')->useCurrent();

            // Воронка выбирает события одного типа за период.
            $table->index(['type', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('bot_events');
    }
};
