<?php

namespace App\Services\Analytics;

use App\Enums\BotEventType;
use App\Enums\OrderSource;
use App\Enums\OrderStatus;
use App\Enums\PaymentStatus;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Payment;
use App\Models\TelegramUser;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;

/**
 * Все расчёты для страницы «Аналитика». Виджеты только рисуют,
 * поэтому цифры можно проверить тестами без Livewire.
 */
class AnalyticsService
{
    /**
     * @return array{new_users: int, active_users: int, orders: int, revenue: float, average_check: float, conversion: float}
     */
    public function summary(Period $period): array
    {
        $orders = $this->ordersIn($period)->where('status', '!=', OrderStatus::Cancelled);
        $funnel = $this->funnel($period);

        return [
            'new_users' => $funnel[0]['users'],
            'active_users' => TelegramUser::query()
                ->whereBetween('last_activity_at', [$period->from, $period->to])
                ->count(),
            'orders' => (clone $orders)->count(),
            'revenue' => (float) Payment::query()
                ->where('status', PaymentStatus::Paid)
                ->whereBetween('paid_at', [$period->from, $period->to])
                ->sum('amount'),
            'average_check' => (float) (clone $orders)->avg('price'),
            // Доля пришедших за период, кто дошёл до заявки.
            'conversion' => $funnel[0]['users'] > 0
                ? round($funnel[3]['users'] / $funnel[0]['users'] * 100, 1)
                : 0.0,
        ];
    }

    /**
     * Воронка по когорте: пользователи, которые пришли в бота за период.
     *
     * Шаг засчитывается, если пользователь дошёл до него или дальше.
     * События пишутся не с первого дня: заявка, оформленная до этого,
     * всё равно означает, что человек видел каталог и начинал оформление.
     *
     * @return list<array{step: string, users: int}>
     */
    public function funnel(Period $period): array
    {
        $cohort = fn (): Builder => TelegramUser::query()->whereBetween('created_at', [$period->from, $period->to]);

        $ordered = fn (Builder $query) => $query->whereHas('orders');
        $paid = fn (Builder $query) => $query->whereHas('orders', fn (Builder $orders) => $orders->whereNotNull('paid_at'));
        $started = fn (Builder $query) => $query->where(fn (Builder $q) => $q
            ->whereHas('events', fn (Builder $events) => $events->where('type', BotEventType::OrderStarted))
            ->orWhereHas('orders'));
        $viewed = fn (Builder $query) => $query->where(fn (Builder $q) => $q
            ->whereHas('events')
            ->orWhereHas('orders'));

        return [
            ['step' => 'Пришли в бота', 'users' => $cohort()->count()],
            ['step' => 'Открыли каталог', 'users' => $viewed($cohort())->count()],
            ['step' => 'Начали оформление', 'users' => $started($cohort())->count()],
            ['step' => 'Оставили заявку', 'users' => $ordered($cohort())->count()],
            ['step' => 'Оплатили', 'users' => $paid($cohort())->count()],
        ];
    }

    /**
     * @return array<string, int> Дата → новых пользователей.
     */
    public function newUsersByDay(Period $period): array
    {
        return $this->countByDay(
            TelegramUser::query()->whereBetween('created_at', [$period->from, $period->to]),
            $period,
        );
    }

    /**
     * @return array<string, array<string, int>> Источник → (дата → заявок).
     */
    public function ordersByDay(Period $period): array
    {
        $result = [];

        foreach (OrderSource::cases() as $source) {
            $result[$source->value] = $this->countByDay(
                $this->ordersIn($period)->where('source', $source),
                $period,
            );
        }

        return $result;
    }

    /**
     * Услуги с наибольшей суммой в неотменённых заявках за период.
     *
     * @return list<array{name: string, quantity: int, revenue: float}>
     */
    public function topServices(Period $period, int $limit = 5): array
    {
        return OrderItem::query()
            ->join('orders', 'orders.id', '=', 'order_items.order_id')
            ->whereBetween('orders.created_at', [$period->from, $period->to])
            ->where('orders.status', '!=', OrderStatus::Cancelled->value)
            ->groupBy('order_items.service_name')
            ->select('order_items.service_name')
            ->selectRaw('SUM(order_items.quantity) as quantity')
            ->selectRaw('SUM(order_items.price * order_items.quantity) as revenue')
            ->orderByDesc('revenue')
            ->limit($limit)
            ->get()
            ->map(fn (OrderItem $row) => [
                'name' => $row->service_name,
                'quantity' => (int) $row->getAttribute('quantity'),
                'revenue' => (float) $row->getAttribute('revenue'),
            ])
            ->all();
    }

    /**
     * @return Builder<Order>
     */
    private function ordersIn(Period $period): Builder
    {
        return Order::query()->whereBetween('created_at', [$period->from, $period->to]);
    }

    /**
     * Счётчик по дням с нулями для дней без записей — иначе график «проваливается».
     *
     * @param  Builder<covariant \Illuminate\Database\Eloquent\Model>  $query
     * @return array<string, int>
     */
    private function countByDay(Builder $query, Period $period): array
    {
        $counts = $query
            ->toBase()
            ->selectRaw('DATE(created_at) as day, COUNT(*) as total')
            ->groupBy(DB::raw('DATE(created_at)'))
            ->pluck('total', 'day');

        return collect($period->dates())
            ->mapWithKeys(fn (string $date) => [$date => (int) ($counts[$date] ?? 0)])
            ->all();
    }
}
