<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

/*
|--------------------------------------------------------------------------
| Очередь на виртуальном хостинге
|--------------------------------------------------------------------------
| Постоянный воркер там держать нельзя, поэтому cron раз в минуту запускает
| schedule:run, а тот — воркер, который разбирает очередь и выходит.
| --max-time меньше минуты, чтобы запуски не наслаивались. В Docker очередь
| разбирает отдельный контейнер, и это расписание там просто не вызывается.
*/
Schedule::command('queue:work --stop-when-empty --max-time=50 --tries=3')
    ->everyMinute()
    ->withoutOverlapping(5);
