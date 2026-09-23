<?php

namespace App\Filament\Resources\Broadcasts\Schemas;

use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class BroadcastInfolist
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Рассылка')
                    ->columns(3)
                    ->schema([
                        TextEntry::make('title')->label('Название')->weight('bold'),
                        TextEntry::make('bot.name')->label('Бот'),
                        TextEntry::make('status')->label('Статус')->badge(),
                        TextEntry::make('author.name')->label('Автор')->placeholder('—'),
                        TextEntry::make('message')->label('Текст')->columnSpanFull(),
                    ]),
                Section::make('Доставка')
                    ->columns(4)
                    ->schema([
                        TextEntry::make('recipients_count')->label('Получателей'),
                        TextEntry::make('sent_count')->label('Доставлено'),
                        TextEntry::make('failed_count')->label('Не доставлено'),
                        TextEntry::make('finished_at')
                            ->label('Завершена')
                            ->dateTime('d.m.Y H:i')
                            ->placeholder('—'),
                    ]),
            ]);
    }
}
