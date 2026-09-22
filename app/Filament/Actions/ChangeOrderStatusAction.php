<?php

namespace App\Filament\Actions;

use App\Enums\OrderStatus;
use App\Models\Order;
use App\Services\OrderService;
use Filament\Actions\Action;
use Filament\Forms\Components\Select;
use Filament\Notifications\Notification;
use Filament\Support\Icons\Heroicon;

/**
 * Смена статуса заявки. Клиенту в бот сразу уходит уведомление,
 * поэтому действие идёт через OrderService, а не обновляет модель напрямую.
 */
class ChangeOrderStatusAction
{
    public static function make(string $name = 'changeStatus'): Action
    {
        return Action::make($name)
            ->label('Статус')
            ->icon(Heroicon::OutlinedArrowPath)
            ->color('primary')
            ->modalHeading('Смена статуса заявки')
            ->modalSubmitActionLabel('Сохранить и уведомить')
            ->fillForm(fn (Order $record) => ['status' => $record->status->value])
            ->schema([
                Select::make('status')
                    ->label('Новый статус')
                    ->options(OrderStatus::class)
                    ->required()
                    ->native(false)
                    ->helperText('Клиент получит уведомление в боте.'),
            ])
            ->action(function (Order $record, array $data) {
                app(OrderService::class)->changeStatus(
                    order: $record,
                    status: OrderStatus::from($data['status']),
                );

                Notification::make()
                    ->title('Статус обновлён, клиент уведомлён')
                    ->success()
                    ->send();
            });
    }
}
