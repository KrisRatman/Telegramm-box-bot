<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Каталог общий, но каждая услуга продаётся только в отмеченных ботах.
     */
    public function up(): void
    {
        Schema::create('bot_service', function (Blueprint $table) {
            $table->foreignId('bot_id')->constrained()->cascadeOnDelete();
            $table->foreignId('service_id')->constrained()->cascadeOnDelete();

            $table->primary(['bot_id', 'service_id']);
        });

        // До этой миграции бот был один и продавал весь каталог.
        DB::table('bot_service')->insertUsing(
            ['bot_id', 'service_id'],
            DB::table('bots')->crossJoin('services')->select(['bots.id', 'services.id']),
        );
    }

    public function down(): void
    {
        Schema::dropIfExists('bot_service');
    }
};
