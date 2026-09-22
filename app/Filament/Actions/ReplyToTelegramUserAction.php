<?php

namespace App\Filament\Actions;

use App\Models\Order;
use App\Models\TelegramUser;
use App\Services\Telegram\BotMessenger;
use Filament\Actions\Action;
use Filament\Forms\Components\Textarea;
use Filament\Notifications\Notification;
use Filament\Support\Icons\Heroicon;
use Illuminate\Database\Eloquent\Model;

/**
 * Ответ пользователю прямо из админки: сообщение уходит в бот
 * и попадает в историю переписки.
 */
class ReplyToTelegramUserAction
{
    public static function make(string $name = 'reply'): Action
    {
        return Action::make($name)
            ->label('Ответить')
            ->icon(Heroicon::OutlinedPaperAirplane)
            ->color('gray')
            ->modalHeading('Сообщение клиенту в Telegram')
            ->modalSubmitActionLabel('Отправить')
            ->schema([
                Textarea::make('text')
                    ->label('Текст сообщения')
                    ->required()
                    ->rows(5)
                    ->maxLength(4000)
                    ->helperText('Можно использовать HTML-теги b, i, a.'),
            ])
            ->action(function (Model $record, array $data) {
                $user = self::resolveUser($record);

                if ($user === null) {
                    Notification::make()
                        ->title('Не удалось определить получателя')
                        ->danger()
                        ->send();

                    return;
                }

                $sent = app(BotMessenger::class)->sendToUser($user, $data['text'], auth()->user());

                $sent
                    ? Notification::make()->title('Сообщение отправлено')->success()->send()
                    : Notification::make()
                        ->title('Telegram не принял сообщение')
                        ->body('Возможно, пользователь заблокировал бота.')
                        ->danger()
                        ->send();
            });
    }

    private static function resolveUser(Model $record): ?TelegramUser
    {
        return match (true) {
            $record instanceof TelegramUser => $record,
            $record instanceof Order => $record->telegramUser,
            default => null,
        };
    }
}
