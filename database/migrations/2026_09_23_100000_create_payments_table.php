<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('payments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('order_id')->constrained()->cascadeOnDelete();
            $table->string('status', 32)->default('pending');
            $table->decimal('amount', 10, 2);
            $table->string('currency', 3)->default('RUB');
            // Уходит в счёт как payload и возвращается в pre_checkout_query
            // и successful_payment — по нему находим платёж.
            $table->string('invoice_payload')->unique();
            // Уникальность защищает от двойной обработки одного списания.
            $table->string('telegram_payment_charge_id')->nullable()->unique();
            // Идентификатор платежа у провайдера — по нему ищут в личном кабинете ЮKassa.
            $table->string('provider_payment_charge_id')->nullable();
            $table->timestamp('paid_at')->nullable();
            $table->timestamp('refunded_at')->nullable();
            $table->timestamps();

            $table->index(['order_id', 'status']);
            $table->index('paid_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('payments');
    }
};
