<?php

namespace App\Http\Controllers;

use App\Models\Bot;
use App\Telegram\BotManager;
use Illuminate\Http\Response;
use Nutgram\Laravel\RunningMode\LaravelWebhook;

class TelegramWebhookController extends Controller
{
    /**
     * У каждого бота свой адрес webhook: /telegram/webhook/{id}. По нему
     * понятно, чей это апдейт, и обработчики получают нужный экземпляр Nutgram.
     *
     * Telegram ждёт ответ 200 как можно быстрее, поэтому здесь только
     * запуск обработчиков — долгие операции уходят в очередь.
     */
    public function __invoke(Bot $bot, BotManager $bots): Response
    {
        $nutgram = $bots->for($bot);
        $nutgram->setRunningMode(LaravelWebhook::class);
        $nutgram->run();

        return response('', 200);
    }
}
