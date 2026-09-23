<?php

namespace App\Services\Telegram;

use App\Enums\MessageDirection;
use App\Models\BotMessage;
use App\Models\Broadcast;
use App\Models\TelegramUser;
use App\Models\User;
use Illuminate\Support\Facades\Log;
use SergiX44\Nutgram\Nutgram;
use SergiX44\Nutgram\Telegram\Exceptions\TelegramException;
use SergiX44\Nutgram\Telegram\Properties\ParseMode;
use Throwable;

/**
 * Единственная точка исходящих сообщений: и ответ админа, и рассылка,
 * и системные уведомления проходят здесь, поэтому вся переписка попадает
 * в bot_messages, а заблокировавшие бота отмечаются автоматически.
 */
class BotMessenger
{
    public function __construct(private readonly Nutgram $bot) {}

    /**
     * @return bool Доставлено ли сообщение.
     */
    public function sendToUser(
        TelegramUser $user,
        string $text,
        ?User $author = null,
        ?Broadcast $broadcast = null,
    ): bool {
        $message = $this->deliver($user, fn () => $this->bot->sendMessage(
            text: $text,
            chat_id: $user->chat_id,
            parse_mode: ParseMode::HTML,
        ));

        if ($message === false) {
            return false;
        }

        $this->log($user, MessageDirection::Out, $text, $message?->message_id, $author, $broadcast);

        return true;
    }

    /**
     * Счёт на оплату. Сам счёт — не текст, поэтому в историю переписки
     * пишем короткую отметку $logText, чтобы админ видел, когда его выставили.
     *
     * @param  array<string, mixed>  $invoice  Аргументы sendInvoice без chat_id.
     * @return bool Доставлен ли счёт.
     */
    public function sendInvoice(TelegramUser $user, array $invoice, string $logText, ?User $author = null): bool
    {
        $message = $this->deliver($user, fn () => $this->bot->sendInvoice(
            ...$invoice,
            chat_id: $user->chat_id,
        ));

        if ($message === false) {
            return false;
        }

        $this->log($user, MessageDirection::Out, $logText, $message?->message_id, $author);

        return true;
    }

    /**
     * Уведомление администраторам из TELEGRAM_ADMIN_CHAT_IDS.
     */
    public function notifyAdmins(string $text): void
    {
        foreach (config('telegram.admin_chat_ids', []) as $chatId) {
            try {
                $this->bot->sendMessage(
                    text: $text,
                    chat_id: (int) $chatId,
                    parse_mode: ParseMode::HTML,
                );
            } catch (Throwable $e) {
                Log::warning('Не удалось уведомить администратора', [
                    'chat_id' => $chatId,
                    'error' => $e->getMessage(),
                ]);
            }
        }
    }

    public function log(
        TelegramUser $user,
        MessageDirection $direction,
        string $text,
        ?int $telegramMessageId = null,
        ?User $author = null,
        ?Broadcast $broadcast = null,
    ): BotMessage {
        return BotMessage::create([
            'telegram_user_id' => $user->id,
            'direction' => $direction,
            'text' => $text,
            'telegram_message_id' => $telegramMessageId,
            'user_id' => $author?->id,
            'broadcast_id' => $broadcast?->id,
        ]);
    }

    /**
     * Общая обработка ошибок доставки: 403 помечает пользователя
     * заблокировавшим бота, успешная доставка снимает отметку.
     *
     * @template T
     *
     * @param  callable(): T  $send
     * @return T|false
     */
    private function deliver(TelegramUser $user, callable $send): mixed
    {
        try {
            $result = $send();
        } catch (TelegramException $e) {
            if ($this->isBlockedError($e)) {
                $user->forceFill(['is_blocked' => true])->save();
            } else {
                Log::warning('Не удалось отправить сообщение в Telegram', [
                    'chat_id' => $user->chat_id,
                    'error' => $e->getMessage(),
                ]);
            }

            return false;
        } catch (Throwable $e) {
            Log::error('Сбой при отправке сообщения в Telegram', [
                'chat_id' => $user->chat_id,
                'error' => $e->getMessage(),
            ]);

            return false;
        }

        if ($user->is_blocked) {
            $user->forceFill(['is_blocked' => false])->save();
        }

        return $result;
    }

    private function isBlockedError(TelegramException $e): bool
    {
        return str_contains(mb_strtolower($e->getMessage()), 'bot was blocked')
            || str_contains(mb_strtolower($e->getMessage()), 'user is deactivated')
            || str_contains(mb_strtolower($e->getMessage()), 'chat not found');
    }
}
