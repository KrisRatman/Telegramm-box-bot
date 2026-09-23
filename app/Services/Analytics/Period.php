<?php

namespace App\Services\Analytics;

use Carbon\CarbonImmutable;
use Carbon\CarbonPeriod;

/**
 * Отчётный срез: последние N дней, включая сегодня, и при необходимости
 * один бот. null в botId — все боты.
 */
final readonly class Period
{
    public function __construct(
        public CarbonImmutable $from,
        public CarbonImmutable $to,
        public ?int $botId = null,
    ) {}

    public static function lastDays(int $days, ?int $botId = null): self
    {
        $today = CarbonImmutable::today();

        return new self($today->subDays($days - 1), $today->endOfDay(), $botId);
    }

    /**
     * Такой же по длине период перед этим — для сравнения «было / стало».
     */
    public function previous(): self
    {
        $length = $this->days();

        return new self($this->from->subDays($length), $this->from->subSecond(), $this->botId);
    }

    public function days(): int
    {
        return (int) $this->from->diffInDays($this->to->startOfDay()) + 1;
    }

    /**
     * @return list<string> Даты периода в формате Y-m-d.
     */
    public function dates(): array
    {
        return collect(CarbonPeriod::create($this->from, $this->to->startOfDay()))
            ->map(fn ($date) => $date->format('Y-m-d'))
            ->all();
    }
}
