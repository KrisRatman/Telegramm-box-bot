<?php

namespace App\Filament\Resources\Orders\Tables;

use App\Enums\OrderSource;
use App\Enums\OrderStatus;
use App\Filament\Actions\ChangeOrderStatusAction;
use App\Filament\Actions\ReplyToTelegramUserAction;
use Filament\Actions\EditAction;
use Filament\Actions\ViewAction;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class OrdersTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->defaultSort('created_at', 'desc')
            ->columns([
                TextColumn::make('number')
                    ->label('Номер')
                    ->searchable()
                    ->sortable()
                    ->weight('bold'),
                TextColumn::make('created_at')
                    ->label('Создана')
                    ->dateTime('d.m.Y H:i')
                    ->sortable(),
                TextColumn::make('telegramUser.full_name')
                    ->label('Клиент')
                    ->description(fn ($record) => $record->telegramUser?->username
                        ? '@'.$record->telegramUser->username
                        : null)
                    ->searchable(query: fn (Builder $query, string $search) => $query->whereHas(
                        'telegramUser',
                        fn (Builder $q) => $q
                            ->where('first_name', 'like', "%{$search}%")
                            ->orWhere('last_name', 'like', "%{$search}%")
                            ->orWhere('username', 'like', "%{$search}%"),
                    )),
                TextColumn::make('service_name')
                    ->label('Услуга')
                    ->searchable()
                    ->wrap(),
                TextColumn::make('bot.name')
                    ->label('Бот')
                    ->badge()
                    ->color('gray')
                    ->toggleable(),
                TextColumn::make('source')
                    ->label('Канал')
                    ->badge()
                    ->toggleable(),
                TextColumn::make('price')
                    ->label('Стоимость')
                    ->money('RUB')
                    ->sortable(),
                TextColumn::make('contact_phone')
                    ->label('Телефон')
                    ->searchable()
                    ->toggleable(),
                TextColumn::make('status')
                    ->label('Статус')
                    ->badge()
                    ->sortable(),
                IconColumn::make('paid_at')
                    ->label('Оплата')
                    ->state(fn ($record) => $record->isPaid())
                    ->boolean()
                    ->tooltip(fn ($record) => $record->paid_at?->format('d.m.Y H:i'))
                    ->sortable(),
            ])
            ->filters([
                SelectFilter::make('bot')
                    ->label('Бот')
                    ->relationship('bot', 'name')
                    ->preload(),
                SelectFilter::make('status')
                    ->label('Статус')
                    ->options(OrderStatus::class)
                    ->multiple(),
                SelectFilter::make('source')
                    ->label('Канал')
                    ->options(OrderSource::class),
                Filter::make('new_only')
                    ->label('Только новые')
                    ->query(fn (Builder $query) => $query->where('status', OrderStatus::New))
                    ->toggle(),
                TernaryFilter::make('paid')
                    ->label('Оплата')
                    ->trueLabel('Оплаченные')
                    ->falseLabel('Не оплаченные')
                    ->queries(
                        true: fn (Builder $query) => $query->whereNotNull('paid_at'),
                        false: fn (Builder $query) => $query->whereNull('paid_at'),
                    ),
            ])
            ->recordActions([
                ChangeOrderStatusAction::make(),
                ReplyToTelegramUserAction::make(),
                ViewAction::make()->label('Открыть'),
                EditAction::make()->label('Изменить'),
            ])
            ->toolbarActions([]);
    }
}
