<?php

namespace App\Filament\Resources\Orders\Schemas;

use App\Enums\OrderStatus;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class OrderForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Заявка')
                    ->columns(2)
                    ->schema([
                        TextInput::make('number')
                            ->label('Номер')
                            ->disabled(),
                        Select::make('status')
                            ->label('Статус')
                            ->options(OrderStatus::class)
                            ->required()
                            ->native(false),
                        TextInput::make('service_name')
                            ->label('Услуга')
                            ->disabled(),
                        TextInput::make('price')
                            ->label('Стоимость, ₽')
                            ->numeric()
                            ->required(),
                    ]),
                Section::make('Контакты клиента')
                    ->columns(2)
                    ->schema([
                        TextInput::make('contact_name')
                            ->label('Имя')
                            ->maxLength(255),
                        TextInput::make('contact_phone')
                            ->label('Телефон')
                            ->tel()
                            ->maxLength(255),
                        Textarea::make('comment')
                            ->label('Комментарий клиента')
                            ->rows(3)
                            ->columnSpanFull(),
                    ]),
                Section::make('Для своих')
                    ->schema([
                        Textarea::make('admin_note')
                            ->label('Заметка администратора')
                            ->helperText('Клиент её не видит.')
                            ->rows(3)
                            ->columnSpanFull(),
                    ]),
            ]);
    }
}
