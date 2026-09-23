<?php

namespace App\Services\Telegram;

/**
 * Проверка данных запуска Mini App (Telegram.WebApp.initData).
 *
 * Mini App — обычная веб-страница, и запрос к API может прислать кто угодно.
 * Доверять можно только строке initData: Telegram подписывает её токеном бота.
 * Алгоритм из документации (core.telegram.org/bots/webapps):
 *
 * 1. все поля, кроме hash, сортируются по ключу и склеиваются в "key=value\n...";
 * 2. secret = HMAC-SHA256(токен бота, ключ "WebAppData");
 * 3. hash должен совпасть с hex(HMAC-SHA256(строка из п.1, secret)).
 *
 * У Nutgram есть validateWebAppData(), но он сравнивает хеши через strcmp,
 * выбрасывает поля со значением "0" и не смотрит на auth_date — поэтому своя.
 */
class InitDataValidator
{
    public function __construct(
        private readonly string $botToken,
        private readonly int $ttlSeconds,
    ) {}

    /**
     * Пользователь Telegram или null, если подпись не сошлась или данные устарели.
     *
     * @return array{id: int, first_name?: string, last_name?: string, username?: string, language_code?: string}|null
     */
    public function validate(string $initData): ?array
    {
        parse_str($initData, $fields);

        $hash = $fields['hash'] ?? null;

        if (! is_string($hash) || $this->botToken === '') {
            return null;
        }

        unset($fields['hash']);
        ksort($fields);

        $checkString = implode("\n", array_map(
            fn (string $key, mixed $value) => $key.'='.(is_string($value) ? $value : ''),
            array_keys($fields),
            $fields,
        ));

        $secret = hash_hmac('sha256', $this->botToken, 'WebAppData', true);

        // hash_equals сравнивает за постоянное время: подбирать подпись по таймингу нельзя.
        if (! hash_equals(hash_hmac('sha256', $checkString, $secret), $hash)) {
            return null;
        }

        $authDate = (int) ($fields['auth_date'] ?? 0);

        if ($authDate <= 0 || now()->timestamp - $authDate > $this->ttlSeconds) {
            return null;
        }

        $user = json_decode((string) ($fields['user'] ?? ''), true);

        return is_array($user) && is_int($user['id'] ?? null) ? $user : null;
    }
}
