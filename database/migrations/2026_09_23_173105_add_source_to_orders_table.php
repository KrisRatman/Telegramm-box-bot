<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            // Откуда пришла заявка: диалог в боте или корзина Mini App.
            // Все заявки до этой миграции оформлены в боте.
            $table->string('source', 16)->default('bot')->after('status');
        });
    }

    public function down(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->dropColumn('source');
        });
    }
};
