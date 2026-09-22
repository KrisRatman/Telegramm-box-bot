<?php

namespace App\Telegram\Conversations;

use App\Models\Service;
use App\Services\OrderService;
use App\Telegram\Support\BotContext;
use App\Telegram\Support\Keyboards;
use App\Telegram\Support\Texts;
use SergiX44\Nutgram\Conversations\Conversation;
use SergiX44\Nutgram\Nutgram;
use SergiX44\Nutgram\Telegram\Properties\ParseMode;

/**
 * Оформление заявки: имя → телефон → комментарий → подтверждение.
 * Состояние шага хранит Nutgram в кеше, поэтому в свойствах только скаляры.
 */
class OrderConversation extends Conversation
{
    protected ?int $serviceId = null;

    protected ?string $contactName = null;

    protected ?string $contactPhone = null;

    protected ?string $comment = null;

    public function start(Nutgram $bot, int $serviceId): void
    {
        $service = Service::query()->active()->find($serviceId);

        if ($service === null) {
            $bot->answerCallbackQuery(text: 'Услуга больше недоступна.', show_alert: true);
            $this->end();

            return;
        }

        $this->serviceId = $service->id;

        $bot->answerCallbackQuery();
        $bot->sendMessage(
            text: Texts::askName($service),
            parse_mode: ParseMode::HTML,
            reply_markup: Keyboards::cancelOrder(),
        );

        $this->next('askPhone');
    }

    public function askPhone(Nutgram $bot): void
    {
        if ($this->interrupted($bot)) {
            return;
        }

        $name = trim((string) $bot->message()?->text);

        if ($name === '' || mb_strlen($name) > 100) {
            $bot->sendMessage('Введите имя текстом — до 100 символов.');

            return;
        }

        $this->contactName = $name;

        $bot->sendMessage(
            text: Texts::askPhone(),
            parse_mode: ParseMode::HTML,
            reply_markup: Keyboards::requestPhone(),
        );

        $this->next('askComment');
    }

    public function askComment(Nutgram $bot): void
    {
        if ($this->interrupted($bot)) {
            return;
        }

        $phone = $bot->message()?->contact?->phone_number
            ?? trim((string) $bot->message()?->text);

        if (! $this->isValidPhone($phone)) {
            $bot->sendMessage('Не похоже на номер телефона. Пример: +7 900 123-45-67');

            return;
        }

        $this->contactPhone = $phone;

        $bot->sendMessage(
            text: Texts::askComment(),
            parse_mode: ParseMode::HTML,
            reply_markup: Keyboards::removeReplyKeyboard(),
        );

        $this->next('confirm');
    }

    public function confirm(Nutgram $bot): void
    {
        if ($this->interrupted($bot)) {
            return;
        }

        $comment = trim((string) $bot->message()?->text);
        $this->comment = ($comment === '' || $comment === '-') ? null : mb_substr($comment, 0, 1000);

        $service = Service::find($this->serviceId);

        if ($service === null) {
            $bot->sendMessage('Услуга больше недоступна. Начните заново: /start');
            $this->end();

            return;
        }

        $bot->sendMessage(
            text: Texts::confirmOrder($service, $this->contactName, $this->contactPhone, $this->comment),
            parse_mode: ParseMode::HTML,
            reply_markup: Keyboards::confirmOrder(),
        );

        $this->next('store');
    }

    public function store(Nutgram $bot): void
    {
        $data = $bot->callbackQuery()?->data;

        if ($data === 'order:cancel') {
            $this->cancel($bot);

            return;
        }

        if ($data !== 'order:confirm') {
            $bot->sendMessage('Нажмите «Подтвердить» или «Отменить».');

            return;
        }

        $service = Service::find($this->serviceId);

        if ($service === null) {
            $bot->answerCallbackQuery(text: 'Услуга больше недоступна.', show_alert: true);
            $this->end();

            return;
        }

        $order = app(OrderService::class)->createFromBot(
            user: BotContext::user($bot),
            service: $service,
            contactName: $this->contactName,
            contactPhone: $this->contactPhone,
            comment: $this->comment,
        );

        $bot->answerCallbackQuery();
        $bot->editMessageText(
            text: Texts::orderCreated($order),
            parse_mode: ParseMode::HTML,
            reply_markup: Keyboards::backToMenu(),
        );

        $this->end();
    }

    /**
     * Пока диалог активен, он перехватывает все апдейты. Отмену обрабатываем,
     * остальные кнопки вежливо игнорируем, чтобы шаг не потерялся.
     */
    private function interrupted(Nutgram $bot): bool
    {
        $callback = $bot->callbackQuery();

        if ($callback === null) {
            return false;
        }

        if ($callback->data === 'order:cancel') {
            $this->cancel($bot);

            return true;
        }

        $bot->answerCallbackQuery(
            text: 'Сначала завершите оформление заявки или нажмите «Отменить».',
            show_alert: true,
        );

        return true;
    }

    private function cancel(Nutgram $bot): void
    {
        $bot->answerCallbackQuery();
        $bot->sendMessage(
            text: Texts::orderCancelled(),
            parse_mode: ParseMode::HTML,
            reply_markup: Keyboards::removeReplyKeyboard(),
        );
        $bot->sendMessage(
            text: Texts::mainMenu(),
            parse_mode: ParseMode::HTML,
            reply_markup: Keyboards::mainMenu(),
        );

        $this->end();
    }

    private function isValidPhone(string $phone): bool
    {
        $digits = preg_replace('/\D+/', '', $phone) ?? '';

        return mb_strlen($digits) >= 10 && mb_strlen($digits) <= 15;
    }
}
