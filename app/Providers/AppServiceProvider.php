<?php

namespace App\Providers;

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
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        //
    }
}
