<?php

namespace App\Filament\Resources\Broadcasts\Tables;

use App\Enums\BroadcastStatus;
use App\Filament\Actions\SendBroadcastAction;
use App\Models\Broadcast;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Actions\ViewAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;

class BroadcastsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->defaultSort('created_at', 'desc')
            ->columns([
                TextColumn::make('title')->label('Название')->searchable()->weight('bold'),
                TextColumn::make('bot.name')
                    ->label('Бот')
                    ->badge()
                    ->color('gray')
                    ->toggleable(),
                TextColumn::make('status')->label('Статус')->badge(),
                TextColumn::make('recipients_count')->label('Получателей'),
                TextColumn::make('sent_count')->label('Доставлено')->color('success'),
                TextColumn::make('failed_count')
                    ->label('Ошибок')
                    ->color(fn (int $state) => $state > 0 ? 'danger' : 'gray'),
                TextColumn::make('created_at')->label('Создана')->dateTime('d.m.Y H:i')->sortable(),
            ])
            ->filters([
                SelectFilter::make('bot')
                    ->label('Бот')
                    ->relationship('bot', 'name')
                    ->preload(),
                SelectFilter::make('status')
                    ->label('Статус')
                    ->options(BroadcastStatus::class),
            ])
            ->recordActions([
                SendBroadcastAction::make(),
                ViewAction::make()->label('Открыть'),
                EditAction::make()
                    ->label('Изменить')
                    ->visible(fn (Broadcast $record) => $record->isEditable()),
                DeleteAction::make()
                    ->label('Удалить')
                    ->visible(fn (Broadcast $record) => $record->isEditable()),
            ])
            ->toolbarActions([]);
    }
}
