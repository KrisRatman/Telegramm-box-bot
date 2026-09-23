<?php

namespace App\Filament\Actions;

use App\Models\Order;
use App\Services\PaymentService;
use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Filament\Support\Icons\Heroicon;

/**
 * Счёт клиенту из админки — например, после того как цену уточнили
 * по телефону и поправили в заявке.
 */
class SendInvoiceAction
{
    public static function make(string $name = 'sendInvoice'): Action
    {
        return Action::make($name)
            ->label('Отправить счёт')
            ->icon(Heroicon::OutlinedCreditCard)
            ->color('success')
            ->visible(fn (Order $record) => PaymentService::enabled() && $record->canBePaid())
            ->requiresConfirmation()
            ->modalHeading('Отправить счёт клиенту')
            ->modalDescription(fn (Order $record) => "Клиент получит в боте счёт на {$record->formatted_price} по заявке №{$record->number}.")
            ->modalSubmitActionLabel('Отправить')
            ->action(function (Order $record) {
                $sent = app(PaymentService::class)->sendInvoice($record, auth()->user());

                $sent
                    ? Notification::make()->title('Счёт отправлен')->success()->send()
                    : Notification::make()
                        ->title('Telegram не принял счёт')
                        ->body('Проверьте токен провайдера и сумму: у Telegram есть минимальная сумма платежа.')
                        ->danger()
                        ->send();
            });
    }
}
