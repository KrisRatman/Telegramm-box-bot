<?php

namespace App\Telegram\Support;

use GuzzleHttp\Exception\TransferException;
use Illuminate\Support\Facades\Log;
use SergiX44\Nutgram\Nutgram;
use SergiX44\Nutgram\RunningMode\Polling;

/**
 * Long polling, который переживает сбои сети.
 *
 * Nutgram ждёт ответ на getUpdates всего на секунду дольше, чем Telegram
 * держит соединение. На медленном канале (VPN, прокси, мобильный интернет)
 * этой секунды иногда не хватает, и штатный Polling завершает процесс
 * с NetworkTimeoutException. Здесь сетевая ошибка пишется в лог,
 * а запрос повторяется с нарастающей паузой.
 *
 * Ошибки самого Telegram (неверный токен, конфликт с webhook) не глушим:
 * от повтора они не пройдут, процесс должен упасть и показать причину.
 */
class ResilientPolling extends Polling
{
    private const MAX_BACKOFF_SECONDS = 30;

    public function processUpdates(Nutgram $bot): void
    {
        $this->listenForSignals();

        $config = $bot->getConfig();
        $offset = 1;
        $failures = 0;

        echo "Listening...\n";

        while (self::$FOREVER) {
            try {
                $updates = $bot->getUpdates(
                    offset: $offset,
                    limit: $config->pollingLimit,
                    timeout: $config->pollingTimeout,
                    allowed_updates: $config->pollingAllowedUpdates,
                ) ?? [];
            } catch (TransferException $e) {
                $failures++;
                $pause = min(self::MAX_BACKOFF_SECONDS, 2 ** min($failures - 1, 5));

                Log::warning('Telegram недоступен, повтор getUpdates', [
                    'attempt' => $failures,
                    'retry_in_seconds' => $pause,
                    // Guzzle кладёт в текст ошибки URL, а в нём токен бота.
                    'error' => preg_replace('/bot\d+:[\w-]+/', 'bot***', $e->getMessage()),
                ]);

                sleep($pause);

                continue;
            }

            $failures = 0;

            // Первый запрос только находит последний апдейт — так делает
            // штатный Polling, логику оставляем прежней.
            if ($offset === 1) {
                $last = end($updates);

                if ($last) {
                    $offset = $last->update_id;
                }

                continue;
            }

            $offset += count($updates);

            $this->fire($bot, $updates);
        }
    }
}
