<?php

namespace App\Filament\Resources\TelegramUsers\Tables;

use App\Filament\Actions\ReplyToTelegramUserAction;
use Filament\Actions\ViewAction;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;

class TelegramUsersTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->defaultSort('last_activity_at', 'desc')
            ->columns([
                TextColumn::make('full_name')
                    ->label('Имя')
                    ->searchable(['first_name', 'last_name'])
                    ->weight('bold'),
                TextColumn::make('username')
                    ->label('Username')
                    ->prefix('@')
                    ->searchable()
                    ->url(fn ($record) => $record->telegram_link)
                    ->openUrlInNewTab()
                    ->placeholder('—'),
                TextColumn::make('bot.name')
                    ->label('Бот')
                    ->badge()
                    ->color('gray')
                    ->toggleable(),
                TextColumn::make('chat_id')
                    ->label('Chat ID')
                    ->copyable()
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('phone')
                    ->label('Телефон')
                    ->searchable()
                    ->placeholder('—'),
                TextColumn::make('orders_count')
                    ->label('Заявок')
                    ->counts('orders')
                    ->sortable(),
                IconColumn::make('is_blocked')
                    ->label('Заблокировал бота')
                    ->boolean()
                    ->trueIcon('heroicon-o-no-symbol')
                    ->falseIcon('heroicon-o-check-circle')
                    ->trueColor('danger')
                    ->falseColor('success'),
                TextColumn::make('last_activity_at')
                    ->label('Активность')
                    ->since()
                    ->sortable(),
            ])
            ->filters([
                SelectFilter::make('bot')
                    ->label('Бот')
                    ->relationship('bot', 'name')
                    ->preload(),
                TernaryFilter::make('is_blocked')
                    ->label('Заблокировал бота')
                    ->placeholder('Все')
                    ->trueLabel('Заблокировали')
                    ->falseLabel('Активные'),
            ])
            ->recordActions([
                ReplyToTelegramUserAction::make(),
                ViewAction::make()->label('Карточка'),
            ])
            ->toolbarActions([]);
    }
}
