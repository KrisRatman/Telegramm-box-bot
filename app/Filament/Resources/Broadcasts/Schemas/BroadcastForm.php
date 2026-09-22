<?php

namespace App\Filament\Resources\Broadcasts\Schemas;

use App\Services\BroadcastService;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class BroadcastForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Сообщение')
                    ->description(fn () => 'Получателей сейчас: '.app(BroadcastService::class)->recipientsCount())
                    ->schema([
                        TextInput::make('title')
                            ->label('Название')
                            ->required()
                            ->maxLength(255)
                            ->helperText('Видно только в админке.'),
                        Textarea::make('message')
                            ->label('Текст рассылки')
                            ->required()
                            ->rows(8)
                            ->maxLength(4000)
                            ->columnSpanFull()
                            ->helperText('Можно использовать HTML-теги b, i, a.'),
                    ]),
            ]);
    }
}
