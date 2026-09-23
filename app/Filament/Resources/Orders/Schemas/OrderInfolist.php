<?php

namespace App\Filament\Resources\Orders\Schemas;

use Filament\Infolists\Components\RepeatableEntry;
use Filament\Infolists\Components\RepeatableEntry\TableColumn;
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
                        TextEntry::make('bot.name')->label('Бот'),
                        TextEntry::make('source')->label('Канал')->badge(),
                        TextEntry::make('status')->label('Статус')->badge(),
                        TextEntry::make('created_at')->label('Создана')->dateTime('d.m.Y H:i'),
                        TextEntry::make('service_name')->label('Услуга'),
                        TextEntry::make('price')->label('Стоимость')->money('RUB'),
                        TextEntry::make('completed_at')
                            ->label('Выполнена')
                            ->dateTime('d.m.Y H:i')
                            ->placeholder('—'),
                        TextEntry::make('paid_at')
                            ->label('Оплачена')
                            ->dateTime('d.m.Y H:i')
                            ->placeholder('Не оплачена'),
                    ]),
                Section::make('Состав заявки')
                    ->schema([
                        RepeatableEntry::make('items')
                            ->hiddenLabel()
                            ->table([
                                TableColumn::make('Услуга'),
                                TableColumn::make('Цена'),
                                TableColumn::make('Кол-во'),
                                TableColumn::make('Сумма'),
                            ])
                            ->schema([
                                TextEntry::make('service_name'),
                                TextEntry::make('price')->money('RUB'),
                                TextEntry::make('quantity'),
                                TextEntry::make('formatted_total'),
                            ])
                            ->placeholder('Позиций нет'),
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
