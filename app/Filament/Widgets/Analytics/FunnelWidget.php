<?php

namespace App\Filament\Widgets\Analytics;

use App\Filament\Widgets\Analytics\Concerns\UsesAnalyticsPeriod;
use Filament\Widgets\Widget;

/**
 * Воронка: полосы с числом пользователей, долей от первого шага
 * и конверсией из предыдущего. Своя разметка, потому что в Chart.js
 * воронки нет, а горизонтальный bar прячет проценты в подсказки.
 */
class FunnelWidget extends Widget
{
    use UsesAnalyticsPeriod;

    protected static ?int $sort = 4;

    protected string $view = 'filament.widgets.analytics.funnel';

    /**
     * @return array<string, mixed>
     */
    protected function getViewData(): array
    {
        $steps = $this->analytics()->funnel($this->period());
        $first = max($steps[0]['users'], 1);

        return [
            'steps' => array_map(function (array $step, int $index) use ($steps, $first) {
                $previous = $index > 0 ? $steps[$index - 1]['users'] : null;

                return [
                    ...$step,
                    'share' => round($step['users'] / $first * 100),
                    'from_previous' => $previous ? round($step['users'] / $previous * 100) : null,
                ];
            }, $steps, array_keys($steps)),
            'isEmpty' => $steps[0]['users'] === 0,
        ];
    }
}
