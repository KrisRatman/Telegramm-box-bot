<?php

namespace App\Console\Commands\Concerns;

use App\Models\Bot;
use Illuminate\Support\Collection;

/**
 * Опция --bot=ID у команд Telegram: без неё команда проходит
 * по всем активным ботам.
 */
trait SelectsBots
{
    /**
     * @return Collection<int, Bot>
     */
    protected function selectedBots(): Collection
    {
        $query = Bot::query()->active()->orderBy('id');

        if ($id = $this->option('bot')) {
            $query->whereKey((int) $id);
        }

        $bots = $query->get();

        if ($bots->isEmpty()) {
            $this->error($this->option('bot')
                ? "Активный бот с id {$this->option('bot')} не найден."
                : 'Нет активных ботов с токеном. Добавьте бота в админке: Боты → Создать.');
        }

        return $bots;
    }
}
