<?php

namespace App\Filament\Resources\TelegramUsers;

use App\Filament\Resources\TelegramUsers\Pages\ListTelegramUsers;
use App\Filament\Resources\TelegramUsers\Pages\ViewTelegramUser;
use App\Filament\Resources\TelegramUsers\RelationManagers\MessagesRelationManager;
use App\Filament\Resources\TelegramUsers\RelationManagers\OrdersRelationManager;
use App\Filament\Resources\TelegramUsers\Schemas\TelegramUserInfolist;
use App\Filament\Resources\TelegramUsers\Tables\TelegramUsersTable;
use App\Models\TelegramUser;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use UnitEnum;

class TelegramUserResource extends Resource
{
    protected static ?string $model = TelegramUser::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedUsers;

    protected static string|UnitEnum|null $navigationGroup = 'Работа с клиентами';

    protected static ?int $navigationSort = 2;

    protected static ?string $navigationLabel = 'Пользователи бота';

    protected static ?string $modelLabel = 'пользователь';

    protected static ?string $pluralModelLabel = 'Пользователи бота';

    protected static ?string $recordTitleAttribute = 'first_name';

    public static function infolist(Schema $schema): Schema
    {
        return TelegramUserInfolist::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return TelegramUsersTable::configure($table);
    }

    public static function getRelations(): array
    {
        return [
            OrdersRelationManager::class,
            MessagesRelationManager::class,
        ];
    }

    /**
     * Пользователи появляются только из бота.
     */
    public static function canCreate(): bool
    {
        return false;
    }

    public static function getPages(): array
    {
        return [
            'index' => ListTelegramUsers::route('/'),
            'view' => ViewTelegramUser::route('/{record}'),
        ];
    }
}
