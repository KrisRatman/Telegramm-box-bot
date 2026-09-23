<?php

namespace App\Services\Analytics;

use Carbon\CarbonImmutable;
use Carbon\CarbonPeriod;

/**
 * Отчётный период: последние N дней, включая сегодня.
 */
final readonly class Period
{
    public function __construct(
        public CarbonImmutable $from,
        public CarbonImmutable $to,
    ) {}

    public static function lastDays(int $days): self
    {
        $today = CarbonImmutable::today();

        return new self($today->subDays($days - 1), $today->endOfDay());
    }

    /**
     * Такой же по длине период перед этим — для сравнения «было / стало».
     */
    public function previous(): self
    {
        $length = $this->days();

        return new self($this->from->subDays($length), $this->from->subSecond());
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
