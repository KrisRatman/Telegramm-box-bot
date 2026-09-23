<?php

namespace App\Filament\Widgets\Analytics;

use App\Filament\Widgets\Analytics\Concerns\UsesAnalyticsPeriod;
use Carbon\CarbonImmutable;
use Filament\Widgets\ChartWidget;

class NewUsersChart extends ChartWidget
{
    use UsesAnalyticsPeriod;

    protected static ?int $sort = 2;

    protected ?string $heading = 'Новые пользователи по дням';

    protected ?string $maxHeight = '260px';

    protected function getType(): string
    {
        return 'line';
    }

    protected function getData(): array
    {
        $byDay = $this->analytics()->newUsersByDay($this->period());

        return [
            'datasets' => [[
                'label' => 'Новые пользователи',
                'data' => array_values($byDay),
                // Цвет проверен валидатором палитры для светлой и тёмной темы.
                'borderColor' => '#2a78d6',
                'backgroundColor' => '#2a78d6',
                'borderWidth' => 2,
                'pointRadius' => 0,
                'pointHoverRadius' => 5,
                // Монотонная кривая не уходит ниже нуля между точками, в отличие от tension.
                'cubicInterpolationMode' => 'monotone',
            ]],
            'labels' => array_map(fn (string $date) => CarbonImmutable::parse($date)->format('d.m'), array_keys($byDay)),
        ];
    }

    protected function getOptions(): array
    {
        return [
            // Одна серия — заголовок её называет, легенда не нужна.
            'plugins' => ['legend' => ['display' => false]],
            'interaction' => ['mode' => 'index', 'intersect' => false],
            'scales' => [
                'y' => ['beginAtZero' => true, 'ticks' => ['precision' => 0]],
                'x' => ['grid' => ['display' => false]],
            ],
        ];
    }
}
