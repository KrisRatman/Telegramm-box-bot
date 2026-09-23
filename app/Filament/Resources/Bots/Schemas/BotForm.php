<?php

namespace App\Filament\Resources\Bots\Schemas;

use App\Telegram\Support\Keyboards;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class BotForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Бот')
                    ->columns(2)
                    ->schema([
                        TextInput::make('name')
                            ->label('Название')
                            ->required()
                            ->maxLength(255)
                            ->helperText('Видно только в админке: в фильтрах, рассылках, уведомлениях.'),
                        TextInput::make('username')
                            ->label('Username')
                            ->prefix('@')
                            ->maxLength(255)
                            ->helperText('Заполнится сам по кнопке «Проверить токен» в списке ботов.'),
                        Select::make('default_locale')
                            ->label('Язык по умолчанию')
                            ->options(Keyboards::LANGUAGES)
                            ->default('ru')
                            ->required()
                            ->native(false)
                            ->helperText('Для клиентов, чей язык Telegram бот не поддерживает.'),
                        Toggle::make('is_active')
                            ->label('Активен')
                            ->default(true)
                            ->helperText('Выключенный бот не принимает апдейты и не открывает Mini App.'),
                    ]),
                Section::make('Токены')
                    ->description('Хранятся в базе зашифрованными и в форму не выводятся. Чтобы заменить — введите новое значение.')
                    ->columns(2)
                    ->schema([
                        self::secret('token', 'Токен бота')
                            ->required(fn (string $operation) => $operation === 'create')
                            ->helperText('Выдаёт @BotFather при создании бота.'),
                        self::secret('payment_provider_token', 'Токен платёжного провайдера')
                            ->helperText('@BotFather → бот → Payments. Пусто — оплата в этом боте выключена.'),
                    ]),
            ]);
    }

    /**
     * Секрет не подставляется в форму, а пустое поле при сохранении
     * не затирает сохранённое значение.
     */
    private static function secret(string $name, string $label): TextInput
    {
        return TextInput::make($name)
            ->label($label)
            ->password()
            ->revealable()
            ->maxLength(255)
            ->formatStateUsing(fn () => null)
            ->dehydrated(fn (?string $state) => filled($state))
            ->placeholder(fn (string $operation) => $operation === 'edit' ? 'Сохранён — оставьте пустым, чтобы не менять' : null);
    }
}
