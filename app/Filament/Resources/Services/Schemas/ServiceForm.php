<?php

namespace App\Filament\Resources\Services\Schemas;

use App\Filament\Support\TranslationFields;
use App\Models\Bot;
use Filament\Forms\Components\CheckboxList;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Illuminate\Support\Str;

class ServiceForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Услуга')
                    ->columns(2)
                    ->schema([
                        Select::make('service_category_id')
                            ->label('Категория')
                            ->relationship('category', 'name')
                            ->required()
                            ->native(false)
                            ->preload(),
                        TextInput::make('name')
                            ->label('Название')
                            ->required()
                            ->maxLength(255)
                            ->live(onBlur: true)
                            ->afterStateUpdated(fn (string $state, callable $set) => $set('slug', Str::slug($state))),
                        TextInput::make('slug')
                            ->label('Слаг')
                            ->required()
                            ->maxLength(255)
                            ->unique(ignoreRecord: true),
                        TextInput::make('price')
                            ->label('Цена, ₽')
                            ->numeric()
                            ->required()
                            ->default(0),
                        Textarea::make('description')
                            ->label('Описание')
                            ->rows(4)
                            ->columnSpanFull()
                            ->helperText('Текст карточки услуги в боте.'),
                    ]),
                Section::make('Отображение')
                    ->columns(3)
                    ->schema([
                        TextInput::make('duration_minutes')
                            ->label('Длительность, мин')
                            ->numeric()
                            ->helperText('Оставьте пустым, если неприменимо.'),
                        TextInput::make('sort_order')
                            ->label('Порядок')
                            ->numeric()
                            ->default(0),
                        Toggle::make('is_active')
                            ->label('Показывать в боте')
                            ->default(true),
                    ]),
                Section::make('Боты')
                    ->description('В каких ботах продаётся услуга. Каталог общий, но у каждого бота может быть свой набор.')
                    ->schema([
                        CheckboxList::make('bots')
                            ->hiddenLabel()
                            ->relationship('bots', 'name')
                            // Новая услуга по умолчанию продаётся везде.
                            ->default(fn () => Bot::query()->pluck('id')->all())
                            ->columns(2)
                            ->bulkToggleable(),
                    ]),
                ...TranslationFields::sections('Текст карточки услуги в боте и Mini App.'),
            ]);
    }
}
