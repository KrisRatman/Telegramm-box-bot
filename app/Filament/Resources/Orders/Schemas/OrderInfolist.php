<?php

namespace App\Filament\Resources\Orders\Schemas;

use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class OrderInfolist
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Заявка')
                    ->columns(3)
                    ->schema([
                        TextEntry::make('number')->label('Номер')->weight('bold'),
                        TextEntry::make('status')->label('Статус')->badge(),
                        TextEntry::make('created_at')->label('Создана')->dateTime('d.m.Y H:i'),
                        TextEntry::make('service_name')->label('Услуга'),
                        TextEntry::make('price')->label('Стоимость')->money('RUB'),
                        TextEntry::make('completed_at')
                            ->label('Выполнена')
                            ->dateTime('d.m.Y H:i')
                            ->placeholder('—'),
                    ]),
                Section::make('Клиент')
                    ->columns(3)
                    ->schema([
                        TextEntry::make('telegramUser.full_name')->label('Имя в Telegram'),
                        TextEntry::make('telegramUser.username')
                            ->label('Username')
                            ->prefix('@')
                            ->url(fn ($record) => $record->telegramUser?->telegram_link)
                            ->openUrlInNewTab()
                            ->placeholder('—'),
                        TextEntry::make('telegramUser.chat_id')->label('Chat ID')->copyable(),
                        TextEntry::make('contact_name')->label('Имя из заявки')->placeholder('—'),
                        TextEntry::make('contact_phone')->label('Телефон')->copyable()->placeholder('—'),
                    ]),
                Section::make('Комментарии')
                    ->schema([
                        TextEntry::make('comment')
                            ->label('Комментарий клиента')
                            ->placeholder('—')
                            ->columnSpanFull(),
                        TextEntry::make('admin_note')
                            ->label('Заметка администратора')
                            ->placeholder('—')
                            ->columnSpanFull(),
                    ]),
            ]);
    }
}
