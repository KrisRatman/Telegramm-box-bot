<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('order_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('order_id')->constrained()->cascadeOnDelete();
            // Как и в orders: услугу могут удалить, позиция должна остаться.
            $table->foreignId('service_id')->nullable()->constrained()->nullOnDelete();
            $table->string('service_name');
            // Цена за единицу на момент заявки.
            $table->decimal('price', 10, 2)->default(0);
            $table->unsignedSmallInteger('quantity')->default(1);
            $table->timestamps();
        });

        // Заявки, оформленные до корзины, получают одну позицию —
        // так у каждой заявки состав читается одинаково.
        DB::table('order_items')->insertUsing(
            ['order_id', 'service_id', 'service_name', 'price', 'quantity', 'created_at', 'updated_at'],
            DB::table('orders')->select(['id', 'service_id', 'service_name', 'price', DB::raw('1'), 'created_at', 'updated_at']),
        );
    }

    public function down(): void
    {
        Schema::dropIfExists('order_items');
    }
};
