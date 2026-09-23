<?php

namespace App\Filament\Resources\Bots\Tables;

use App\Models\Bot;
use App\Telegram\BotManager;
use Filament\Actions\Action;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Notifications\Notification;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Throwable;

class BotsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn ($query) => $query->withCount(['telegramUsers', 'orders']))
            ->columns([
                TextColumn::make('name')->label('Название')->weight('bold')->searchable(),
                TextColumn::make('username')
                    ->label('Username')
                    ->prefix('@')
                    ->url(fn (Bot $record) => $record->telegram_link)
                    ->openUrlInNewTab()
                    ->placeholder('—'),
                TextColumn::make('default_locale')->label('Язык')->badge()->color('gray'),
                IconColumn::make('is_active')->label('Активен')->boolean(),
                IconColumn::make('payments')
                    ->label('Оплата')
                    ->state(fn (Bot $record) => $record->paymentsEnabled())
                    ->boolean(),
                TextColumn::make('telegram_users_count')->label('Пользователей')->sortable(),
                TextColumn::make('orders_count')->label('Заявок')->sortable(),
            ])
            ->recordActions([
                Action::make('check')
                    ->label('Проверить токен')
                    ->icon(Heroicon::OutlinedCheckBadge)
                    ->action(fn (Bot $record) => self::check($record)),
                Action::make('webhook')
                    ->label('Установить webhook')
                    ->icon(Heroicon::OutlinedLink)
                    ->requiresConfirmation()
                    ->modalDescription(fn (Bot $record) => "Telegram будет присылать обновления на {$record->webhookUrl()}. Нужен https-адрес в APP_URL.")
                    ->action(fn (Bot $record) => self::setWebhook($record)),
                EditAction::make(),
                DeleteAction::make()
                    ->modalDescription('Вместе с ботом удалятся его пользователи, заявки, переписка и рассылки. Отменить нельзя.'),
            ]);
    }

    /**
     * getMe: токен рабочий — подставляем username, который выдал Telegram.
     */
    private static function check(Bot $bot): void
    {
        try {
            $me = app(BotManager::class)->for($bot)->getMe();
        } catch (Throwable $e) {
            Notification::make()->danger()->title('Токен не подошёл')->body($e->getMessage())->send();

            return;
        }

        $bot->forceFill(['username' => $me?->username])->save();

        Notification::make()->success()->title("Токен рабочий: @{$me?->username}")->send();
    }

    private static function setWebhook(Bot $bot): void
    {
        $url = $bot->webhookUrl();

        if (! str_starts_with($url, 'https://')) {
            Notification::make()->danger()->title('Нужен https')->body("Telegram не примет {$url}. Укажите https-адрес в APP_URL.")->send();

            return;
        }

        try {
            app(BotManager::class)->for($bot)->setWebhook(url: $url, secret_token: $bot->webhook_secret, drop_pending_updates: true);
        } catch (Throwable $e) {
            Notification::make()->danger()->title('Telegram отклонил запрос')->body($e->getMessage())->send();

            return;
        }

        Notification::make()->success()->title('Webhook установлен')->body($url)->send();
    }
}
