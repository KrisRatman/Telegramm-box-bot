<?php

namespace Tests\Feature;

use App\Enums\PaymentStatus;
use App\Models\Order;
use App\Models\Payment;
use Database\Seeders\DemoSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DemoSeederTest extends TestCase
{
    use RefreshDatabase;

    public function test_demo_data_covers_payment_states(): void
    {
        $this->seed(DemoSeeder::class);

        $this->assertTrue(Payment::query()->where('status', PaymentStatus::Paid)->exists());
        $this->assertTrue(Payment::query()->where('status', PaymentStatus::Refunded)->exists());
        $this->assertTrue(Order::query()->whereNotNull('paid_at')->exists());
        $this->assertTrue(Order::query()->whereNull('paid_at')->exists());

        // Возвращённый платёж не должен оставлять заявку оплаченной.
        $refunded = Payment::query()->where('status', PaymentStatus::Refunded)->first();
        $this->assertFalse($refunded->order->isPaid());
    }
}
