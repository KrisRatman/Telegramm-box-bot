<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('bots', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('username')->nullable();
            // Токены хранятся зашифрованными (cast encrypted), поэтому text:
            // длина шифротекста заранее неизвестна.
            $table->text('token')->nullable();
            $table->text('webhook_secret');
            $table->text('payment_provider_token')->nullable();
            $table->string('default_locale', 5)->default('ru');
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('bots');
    }
};
