<?php

namespace App\Providers;

use App\Telegram\BotManager;
use App\Telegram\Support\ResilientPolling;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\ServiceProvider;
use SergiX44\Nutgram\Nutgram;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        // nutgram:run по умолчанию крутит штатный Polling, который падает
        // на первом таймауте сети. Webhook и тесты этим не затрагиваются.
        $this->app->extend(Nutgram::class, function (Nutgram $bot) {
            if ($this->app->runningInConsole() && ! $this->app->runningUnitTests()) {
                $bot->setRunningMode(ResilientPolling::class);
            }

            return $bot;
        });

        // В тестах все боты работают через Nutgram::fake() из контейнера —
        // так же, как пакет подменяет единственного бота.
        $this->app->singleton(BotManager::class, fn ($app) => new BotManager(
            $app,
            $app->runningUnitTests() ? fn () => $app->make(Nutgram::class) : null,
        ));
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        // На хостинге без своего SSL сайт открывают через https-прокси
        // (например, Cloudflare Worker), а до сервера запрос доходит по http.
        // Ссылки строим от APP_URL, а не от заголовков запроса: доверять
        // X-Forwarded-* от кого угодно небезопасно, адреса прокси меняются.
        $appUrl = (string) config('app.url');

        if (str_starts_with($appUrl, 'https://')) {
            URL::forceRootUrl($appUrl);
            URL::forceScheme('https');
        }
    }
}
