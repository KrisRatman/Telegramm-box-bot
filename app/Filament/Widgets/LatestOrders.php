<?php

namespace App\Filament\Widgets;

use App\Filament\Resources\Orders\OrderResource;
use Filament\Actions\Action;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget;

class LatestOrders extends TableWidget
{
    protected static ?int $sort = 2;

    protected int|string|array $columnSpan = 'full';

    public function table(Table $table): Table
    {
        return $table
            ->heading('Последние заявки')
            ->query(OrderResource::getEloquentQuery()->with('telegramUser')->latest()->limit(10))
            ->paginated(false)
            ->columns([
                TextColumn::make('number')->label('Номер')->weight('bold'),
                TextColumn::make('created_at')->label('Создана')->dateTime('d.m.Y H:i'),
                TextColumn::make('telegramUser.full_name')->label('Клиент'),
                TextColumn::make('service_name')->label('Услуга')->wrap(),
                TextColumn::make('price')->label('Стоимость')->money('RUB'),
                TextColumn::make('status')->label('Статус')->badge(),
            ])
            ->recordActions([
                Action::make('open')
                    ->label('Открыть')
                    ->url(fn ($record) => OrderResource::getUrl('view', ['record' => $record])),
            ]);
    }
}
