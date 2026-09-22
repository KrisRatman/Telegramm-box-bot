<?php

namespace App\Filament\Resources\TelegramUsers\RelationManagers;

use App\Enums\MessageDirection;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;

class MessagesRelationManager extends RelationManager
{
    protected static string $relationship = 'messages';

    protected static ?string $title = 'Переписка';

    public function table(Table $table): Table
    {
        return $table
            ->defaultSort('created_at', 'desc')
            ->columns([
                TextColumn::make('created_at')->label('Когда')->dateTime('d.m.Y H:i'),
                TextColumn::make('direction')->label('Направление')->badge(),
                TextColumn::make('text')->label('Сообщение')->wrap()->limit(300),
                TextColumn::make('author.name')->label('Отправил')->placeholder('бот'),
            ])
            ->filters([
                SelectFilter::make('direction')
                    ->label('Направление')
                    ->options(MessageDirection::class),
            ])
            ->headerActions([])
            ->recordActions([])
            ->toolbarActions([]);
    }
}
