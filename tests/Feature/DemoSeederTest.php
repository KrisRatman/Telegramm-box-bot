<?php

namespace Tests\Feature;

use App\Enums\OrderSource;
use App\Enums\PaymentStatus;
use App\Models\Order;
use App\Models\Payment;
use App\Services\Analytics\AnalyticsService;
use App\Services\Analytics\Period;
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

        // История для аналитики: воронка сужается, заявки есть в обоих каналах.
        $funnel = array_column(app(AnalyticsService::class)->funnel(Period::lastDays(30)), 'users');
        $this->assertGreaterThan(50, $funnel[0]);
        $this->assertSame($funnel, collect($funnel)->sortDesc()->values()->all());
        $this->assertGreaterThan(0, $funnel[4]);
        $this->assertTrue(Order::query()->where('source', OrderSource::MiniApp)->exists());
        $this->assertTrue(Order::query()->where('source', OrderSource::Bot)->exists());

        // Возвращённый платёж не должен оставлять заявку оплаченной.
        $refunded = Payment::query()->where('status', PaymentStatus::Refunded)->first();
        $this->assertFalse($refunded->order->isPaid());
    }
}
