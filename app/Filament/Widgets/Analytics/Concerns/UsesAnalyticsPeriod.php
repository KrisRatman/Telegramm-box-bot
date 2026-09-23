<?php

namespace App\Filament\Widgets\Analytics\Concerns;

use App\Services\Analytics\AnalyticsService;
use App\Services\Analytics\Period;
use Filament\Widgets\Concerns\InteractsWithPageFilters;

/**
 * Период из фильтра страницы «Аналитика». Виджеты сами на дашборд
 * не попадают — их показывает только эта страница.
 */
trait UsesAnalyticsPeriod
{
    use InteractsWithPageFilters;

    public static function isDiscovered(): bool
    {
        return false;
    }

    protected function period(): Period
    {
        $days = (int) ($this->pageFilters['period'] ?? 30);

        return Period::lastDays(in_array($days, [7, 30, 90], true) ? $days : 30, $this->botId());
    }

    protected function botId(): ?int
    {
        $bot = $this->pageFilters['bot'] ?? null;

        return filled($bot) ? (int) $bot : null;
    }

    protected function analytics(): AnalyticsService
    {
        return app(AnalyticsService::class);
    }
}
