<?php

namespace App\Filament\Resources\TelegramUsers\RelationManagers;

use App\Filament\Resources\Orders\OrderResource;
use Filament\Actions\Action;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class OrdersRelationManager extends RelationManager
{
    protected static string $relationship = 'orders';

    protected static ?string $title = 'Заявки';

    public function table(Table $table): Table
    {
        return $table
            ->defaultSort('created_at', 'desc')
            ->columns([
                TextColumn::make('number')->label('Номер')->weight('bold'),
                TextColumn::make('created_at')->label('Создана')->dateTime('d.m.Y H:i'),
                TextColumn::make('service_name')->label('Услуга')->wrap(),
                TextColumn::make('price')->label('Стоимость')->money('RUB'),
                TextColumn::make('status')->label('Статус')->badge(),
            ])
            ->headerActions([])
            ->recordActions([
                Action::make('open')
                    ->label('Открыть')
                    ->url(fn ($record) => OrderResource::getUrl('view', ['record' => $record])),
            ])
            ->toolbarActions([]);
    }
}
