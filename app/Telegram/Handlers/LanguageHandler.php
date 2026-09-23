<?php

namespace App\Telegram\Handlers;

use App\Telegram\Support\BotContext;
use App\Telegram\Support\Keyboards;
use App\Telegram\Support\Screen;
use App\Telegram\Support\Texts;
use SergiX44\Nutgram\Nutgram;

class LanguageHandler
{
    public function choose(Nutgram $bot): void
    {
        Screen::show($bot, Texts::chooseLanguage(), Keyboards::languages());
    }

    /**
     * Выбор сохраняется в locale и перекрывает язык из настроек Telegram.
     */
    public function set(Nutgram $bot, string $locale): void
    {
        if (! array_key_exists($locale, Keyboards::LANGUAGES)) {
            $this->choose($bot);

            return;
        }

        BotContext::user($bot)->forceFill(['locale' => $locale])->save();
        app()->setLocale($locale);

        Screen::show(
            $bot,
            Texts::languageChanged()."\n\n".Texts::mainMenu(),
            Keyboards::mainMenu(BotContext::bot($bot)),
        );
    }
}
