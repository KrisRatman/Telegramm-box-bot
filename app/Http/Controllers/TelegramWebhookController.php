<?php

namespace App\Http\Controllers;

use Illuminate\Http\Response;
use Nutgram\Laravel\RunningMode\LaravelWebhook;
use SergiX44\Nutgram\Nutgram;

class TelegramWebhookController extends Controller
{
    /**
     * Telegram ждёт ответ 200 как можно быстрее, поэтому здесь только
     * запуск обработчиков — долгие операции уходят в очередь.
     */
    public function __invoke(Nutgram $bot): Response
    {
        $bot->setRunningMode(LaravelWebhook::class);
        $bot->run();

        return response('', 200);
    }
}
