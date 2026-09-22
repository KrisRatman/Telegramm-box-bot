<?php

namespace App\Filament\Widgets;

use App\Enums\OrderStatus;
use App\Models\Order;
use App\Models\TelegramUser;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

class StatsOverview extends StatsOverviewWidget
{
    protected static ?int $sort = 1;

    protected function getStats(): array
    {
        $newOrders = Order::query()->where('status', OrderStatus::New)->count();
        $ordersToday = Order::query()->whereDate('created_at', today())->count();
        $revenue = Order::query()
            ->where('status', OrderStatus::Completed)
            ->sum('price');

        return [
            Stat::make('Новые заявки', $newOrders)
                ->description('Ждут обработки')
                ->color($newOrders > 0 ? 'warning' : 'success'),
            Stat::make('Заявок сегодня', $ordersToday)
                ->description('С начала суток'),
            Stat::make('Пользователей бота', TelegramUser::query()->subscribed()->count())
                ->description('Без заблокировавших бота'),
            Stat::make('Выручка', number_format((float) $revenue, 0, ',', ' ').' ₽')
                ->description('По выполненным заявкам')
                ->color('success'),
        ];
    }
}
