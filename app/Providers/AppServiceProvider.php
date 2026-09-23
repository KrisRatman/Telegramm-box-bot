<?php

namespace App\Providers;

use App\Services\Telegram\InitDataValidator;
use App\Telegram\Support\ResilientPolling;
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

        $this->app->bind(InitDataValidator::class, fn () => new InitDataValidator(
            botToken: (string) config('nutgram.token'),
            ttlSeconds: (int) config('telegram.mini_app.auth_ttl'),
        ));
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        //
    }
}
