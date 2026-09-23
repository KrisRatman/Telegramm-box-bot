<?php

namespace App\Filament\Resources\TelegramUsers\Schemas;

use Filament\Infolists\Components\IconEntry;
use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class TelegramUserInfolist
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Профиль')
                    ->columns(3)
                    ->schema([
                        TextEntry::make('full_name')->label('Имя')->weight('bold'),
                        TextEntry::make('username')
                            ->label('Username')
                            ->prefix('@')
                            ->url(fn ($record) => $record->telegram_link)
                            ->openUrlInNewTab()
                            ->placeholder('—'),
                        TextEntry::make('chat_id')->label('Chat ID')->copyable(),
                        TextEntry::make('phone')->label('Телефон')->copyable()->placeholder('—'),
                        TextEntry::make('bot.name')->label('Бот'),
                        TextEntry::make('language_code')->label('Язык Telegram'),
                        TextEntry::make('locale')->label('Выбранный язык')->placeholder('Не выбирал'),
                        IconEntry::make('is_blocked')->label('Заблокировал бота')->boolean(),
                        TextEntry::make('created_at')->label('Первый контакт')->dateTime('d.m.Y H:i'),
                        TextEntry::make('last_activity_at')
                            ->label('Последняя активность')
                            ->dateTime('d.m.Y H:i')
                            ->placeholder('—'),
                    ]),
            ]);
    }
}
