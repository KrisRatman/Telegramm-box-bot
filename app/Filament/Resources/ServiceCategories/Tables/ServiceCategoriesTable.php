<?php

namespace App\Filament\Resources\ServiceCategories\Tables;

use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class ServiceCategoriesTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->defaultSort('sort_order')
            ->reorderable('sort_order')
            ->columns([
                TextColumn::make('name')->label('Название')->searchable()->weight('bold'),
                TextColumn::make('services_count')->label('Услуг')->counts('services'),
                IconColumn::make('is_active')->label('В боте')->boolean(),
                TextColumn::make('sort_order')->label('Порядок')->sortable(),
            ])
            ->recordActions([
                EditAction::make()->label('Изменить'),
                DeleteAction::make()->label('Удалить'),
            ])
            ->toolbarActions([]);
    }
}
