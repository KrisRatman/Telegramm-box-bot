<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('bot_messages', function (Blueprint $table) {
            $table->id();
            $table->foreignId('telegram_user_id')->constrained()->cascadeOnDelete();
            // in — от пользователя боту, out — от админки/бота пользователю.
            $table->string('direction', 8);
            $table->text('text');
            $table->unsignedBigInteger('telegram_message_id')->nullable();
            // Кто из админов отправил (null — автоматическое сообщение бота).
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('broadcast_id')->nullable()->constrained()->cascadeOnDelete();
            $table->timestamps();

            $table->index(['telegram_user_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('bot_messages');
    }
};
