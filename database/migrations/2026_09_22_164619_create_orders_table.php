<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('orders', function (Blueprint $table) {
            $table->id();
            // Человекочитаемый номер, который видит клиент в боте.
            $table->string('number')->unique();
            $table->foreignId('telegram_user_id')->constrained()->cascadeOnDelete();
            // Услугу могут удалить из каталога — заявка должна пережить это.
            $table->foreignId('service_id')->nullable()->constrained()->nullOnDelete();
            $table->string('service_name');
            $table->decimal('price', 10, 2)->default(0);
            $table->string('status', 32)->default('new');
            $table->string('contact_name')->nullable();
            $table->string('contact_phone')->nullable();
            $table->text('comment')->nullable();
            $table->text('admin_note')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->timestamps();

            $table->index('status');
            $table->index('created_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('orders');
    }
};
