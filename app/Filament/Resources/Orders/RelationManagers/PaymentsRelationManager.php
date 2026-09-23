<?php

namespace App\Filament\Resources\Orders\RelationManagers;

use App\Enums\PaymentStatus;
use App\Models\Payment;
use App\Services\PaymentService;
use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class PaymentsRelationManager extends RelationManager
{
    protected static string $relationship = 'payments';

    protected static ?string $title = 'Платежи';

    public function isReadOnly(): bool
    {
        return false;
    }

    public function table(Table $table): Table
    {
        return $table
            ->defaultSort('created_at', 'desc')
            ->emptyStateHeading('Счетов ещё не было')
            ->columns([
                TextColumn::make('created_at')->label('Счёт выставлен')->dateTime('d.m.Y H:i'),
                TextColumn::make('amount')->label('Сумма')->money('RUB'),
                TextColumn::make('status')->label('Статус')->badge(),
                TextColumn::make('paid_at')->label('Оплачен')->dateTime('d.m.Y H:i')->placeholder('—'),
                TextColumn::make('provider_payment_charge_id')
                    ->label('ID в ЮKassa')
                    ->copyable()
                    ->placeholder('—')
                    ->toggleable(),
                TextColumn::make('refunded_at')
                    ->label('Возврат')
                    ->dateTime('d.m.Y H:i')
                    ->placeholder('—')
                    ->toggleable(),
            ])
            ->headerActions([])
            ->recordActions([
                Action::make('markRefunded')
                    ->label('Отметить возврат')
                    ->icon(Heroicon::OutlinedArrowUturnLeft)
                    ->color('danger')
                    ->visible(fn (Payment $record) => $record->status === PaymentStatus::Paid)
                    ->requiresConfirmation()
                    ->modalHeading('Возврат платежа')
                    ->modalDescription('Сначала сделайте возврат в личном кабинете ЮKassa — Telegram не умеет '
                        .'возвращать деньги за оплату картой. Здесь возврат только фиксируется, '
                        .'а клиент получает уведомление в боте.')
                    ->modalSubmitActionLabel('Возврат сделан')
                    ->action(function (Payment $record) {
                        app(PaymentService::class)->markRefunded($record);

                        Notification::make()
                            ->title('Возврат отмечен, клиент уведомлён')
                            ->success()
                            ->send();
                    }),
            ])
            ->toolbarActions([]);
    }
}
