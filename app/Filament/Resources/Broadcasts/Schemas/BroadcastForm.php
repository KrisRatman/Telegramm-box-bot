<?php

namespace App\Filament\Resources\Broadcasts\Schemas;

use App\Models\Bot;
use App\Services\BroadcastService;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema;

class BroadcastForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Сообщение')
                    ->description(fn (Get $get) => $get('bot_id')
                        ? 'Получателей сейчас: '.app(BroadcastService::class)->recipientsCount((int) $get('bot_id'))
                        : 'Выберите бота — рассылка уходит только его подписчикам.')
                    ->schema([
                        Select::make('bot_id')
                            ->label('Бот')
                            ->relationship('bot', 'name')
                            ->default(fn () => Bot::query()->count() === 1 ? Bot::query()->value('id') : null)
                            ->required()
                            ->live()
                            ->native(false),
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
