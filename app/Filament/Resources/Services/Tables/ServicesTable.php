<?php

namespace App\Filament\Resources\Services\Tables;

use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;

class ServicesTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->defaultGroup('category.name')
            ->defaultSort('sort_order')
            ->columns([
                TextColumn::make('name')->label('Название')->searchable()->weight('bold'),
                TextColumn::make('category.name')->label('Категория')->sortable(),
                TextColumn::make('price')->label('Цена')->money('RUB')->sortable(),
                TextColumn::make('orders_count')->label('Заявок')->counts('orders'),
                IconColumn::make('is_active')->label('В боте')->boolean(),
            ])
            ->filters([
                SelectFilter::make('service_category_id')
                    ->label('Категория')
                    ->relationship('category', 'name')
                    ->preload(),
                TernaryFilter::make('is_active')
                    ->label('Показывается в боте')
                    ->placeholder('Все'),
            ])
            ->recordActions([
                EditAction::make()->label('Изменить'),
                DeleteAction::make()->label('Удалить'),
            ])
            ->toolbarActions([]);
    }
}
