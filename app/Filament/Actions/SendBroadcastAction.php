<?php

namespace App\Filament\Actions;

use App\Models\Broadcast;
use App\Services\BroadcastService;
use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Filament\Support\Icons\Heroicon;

/**
 * Запуск рассылки. Сообщения уходят воркером очереди, поэтому действие
 * возвращает управление сразу, а прогресс виден в карточке рассылки.
 */
class SendBroadcastAction
{
    public static function make(string $name = 'send'): Action
    {
        return Action::make($name)
            ->label('Отправить')
            ->icon(Heroicon::OutlinedPaperAirplane)
            ->color('success')
            ->visible(fn (Broadcast $record) => $record->isEditable())
            ->requiresConfirmation()
            ->modalHeading('Запустить рассылку?')
            ->modalDescription(fn (Broadcast $record) => 'Сообщение получат '
                .app(BroadcastService::class)->recipientsCount($record->bot_id)
                ." пользователей бота «{$record->bot?->name}». Отменить отправку после запуска нельзя.")
            ->modalSubmitActionLabel('Запустить')
            ->action(function (Broadcast $record) {
                app(BroadcastService::class)->queue($record);

                Notification::make()
                    ->title('Рассылка поставлена в очередь')
                    ->body('Убедитесь, что запущен воркер: php artisan queue:work')
                    ->success()
                    ->send();
            });
    }
}
