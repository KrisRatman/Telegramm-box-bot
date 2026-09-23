<?php

namespace Tests\Feature\Analytics;

use App\Enums\BotEventType;
use App\Enums\OrderSource;
use App\Enums\OrderStatus;
use App\Enums\PaymentStatus;
use App\Models\BotEvent;
use App\Models\Order;
use App\Models\Payment;
use App\Models\TelegramUser;
use App\Services\Analytics\AnalyticsService;
use App\Services\Analytics\Period;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AnalyticsServiceTest extends TestCase
{
    use RefreshDatabase;

    private AnalyticsService $analytics;

    protected function setUp(): void
    {
        parent::setUp();

        $this->travelTo('2026-09-23 15:00:00');
        $this->analytics = app(AnalyticsService::class);
    }

    public function test_funnel_counts_users_who_reached_step_or_further(): void
    {
        // Только пришёл.
        TelegramUser::factory()->create();
        // Открыл каталог.
        $this->event(TelegramUser::factory()->create(), BotEventType::CatalogViewed);
        // Начал оформление.
        $this->event(TelegramUser::factory()->create(), BotEventType::OrderStarted);
        // Заявка без событий: оформлена до того, как их начали писать.
        Order::factory()->for(TelegramUser::factory())->create();
        // Оплатил.
        Order::factory()->for(TelegramUser::factory())->create(['paid_at' => now()]);
        // Пришёл раньше периода — в когорту не попадает.
        $this->event(TelegramUser::factory()->create(['created_at' => now()->subDays(40)]), BotEventType::CatalogViewed);

        $this->assertSame([
            ['step' => 'Пришли в бота', 'users' => 5],
            ['step' => 'Открыли каталог', 'users' => 4],
            ['step' => 'Начали оформление', 'users' => 3],
            ['step' => 'Оставили заявку', 'users' => 2],
            ['step' => 'Оплатили', 'users' => 1],
        ], $this->analytics->funnel(Period::lastDays(30)));
    }

    public function test_summary_excludes_cancelled_orders_and_refunded_payments(): void
    {
        $user = TelegramUser::factory()->create(['last_activity_at' => now()]);
        TelegramUser::factory()->create(['last_activity_at' => now()->subDays(60), 'created_at' => now()->subDays(60)]);

        $paid = Order::factory()->for($user)->create(['price' => 10000]);
        Order::factory()->for($user)->create(['price' => 30000]);
        Order::factory()->for($user)->status(OrderStatus::Cancelled)->create(['price' => 99000]);

        Payment::factory()->for($paid)->create(['status' => PaymentStatus::Paid, 'amount' => 10000, 'paid_at' => now()]);
        Payment::factory()->for($paid)->create(['status' => PaymentStatus::Refunded, 'amount' => 5000, 'paid_at' => now()]);

        $summary = $this->analytics->summary(Period::lastDays(7));

        $this->assertSame(1, $summary['new_users']);
        $this->assertSame(1, $summary['active_users']);
        $this->assertSame(2, $summary['orders']);
        $this->assertSame(10000.0, $summary['revenue']);
        $this->assertSame(20000.0, $summary['average_check']);
        $this->assertSame(100.0, $summary['conversion']);
    }

    public function test_orders_by_day_split_by_source_with_empty_days(): void
    {
        Order::factory()->create(['created_at' => now()]);
        Order::factory()->create(['created_at' => now(), 'source' => OrderSource::MiniApp]);
        Order::factory()->create(['created_at' => now()->subDay(), 'source' => OrderSource::MiniApp]);

        $byDay = $this->analytics->ordersByDay(Period::lastDays(3));

        $this->assertSame(['2026-09-21' => 0, '2026-09-22' => 0, '2026-09-23' => 1], $byDay['bot']);
        $this->assertSame(['2026-09-21' => 0, '2026-09-22' => 1, '2026-09-23' => 1], $byDay['mini_app']);
    }

    public function test_top_services_rank_by_revenue_without_cancelled_orders(): void
    {
        $order = Order::factory()->create();
        $order->items()->createMany([
            ['service_name' => 'Лендинг', 'price' => 20000, 'quantity' => 1],
            ['service_name' => 'Поддержка', 'price' => 3000, 'quantity' => 3],
        ]);
        Order::factory()->create()->items()->create(['service_name' => 'Поддержка', 'price' => 3000, 'quantity' => 10]);
        Order::factory()->status(OrderStatus::Cancelled)->create()
            ->items()->create(['service_name' => 'Магазин', 'price' => 180000, 'quantity' => 1]);

        $this->assertSame([
            ['name' => 'Поддержка', 'quantity' => 13, 'revenue' => 39000.0],
            ['name' => 'Лендинг', 'quantity' => 1, 'revenue' => 20000.0],
        ], $this->analytics->topServices(Period::lastDays(30)));
    }

    public function test_previous_period_has_same_length_and_ends_before_current(): void
    {
        $period = Period::lastDays(7);
        $previous = $period->previous();

        $this->assertSame('2026-09-17 00:00:00', $period->from->toDateTimeString());
        $this->assertSame('2026-09-10 00:00:00', $previous->from->toDateTimeString());
        $this->assertSame('2026-09-16 23:59:59', $previous->to->toDateTimeString());
        $this->assertSame(7, $previous->days());
    }

    private function event(TelegramUser $user, BotEventType $type): void
    {
        BotEvent::record($user, $type, OrderSource::Bot);
    }
}
