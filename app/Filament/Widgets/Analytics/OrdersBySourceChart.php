<?php

namespace App\Filament\Widgets\Analytics;

use App\Enums\OrderSource;
use App\Filament\Widgets\Analytics\Concerns\UsesAnalyticsPeriod;
use Carbon\CarbonImmutable;
use Filament\Widgets\ChartWidget;

class OrdersBySourceChart extends ChartWidget
{
    use UsesAnalyticsPeriod;

    protected static ?int $sort = 3;

    protected ?string $heading = 'Заявки по дням и каналам';

    protected ?string $maxHeight = '260px';

    /**
     * Цвет закреплён за каналом, а не за порядком серии. Пара проверена
     * валидатором палитры (дальтонизм, контраст) для обеих тем Filament.
     */
    private const COLORS = [
        'bot' => '#2a78d6',
        'mini_app' => '#d95926',
    ];

    protected function getType(): string
    {
        return 'bar';
    }

    protected function getData(): array
    {
        $bySource = $this->analytics()->ordersByDay($this->period());
        $dates = array_keys(reset($bySource) ?: []);

        return [
            'datasets' => array_map(fn (OrderSource $source) => [
                'label' => $source->getLabel(),
                'data' => array_values($bySource[$source->value]),
                'backgroundColor' => self::COLORS[$source->value],
                'borderRadius' => 4,
                'maxBarThickness' => 28,
            ], OrderSource::cases()),
            'labels' => array_map(fn (string $date) => CarbonImmutable::parse($date)->format('d.m'), $dates),
        ];
    }

    protected function getOptions(): array
    {
        return [
            'plugins' => ['legend' => ['display' => true, 'position' => 'bottom']],
            'interaction' => ['mode' => 'index', 'intersect' => false],
            'scales' => [
                'x' => ['stacked' => true, 'grid' => ['display' => false]],
                'y' => ['stacked' => true, 'beginAtZero' => true, 'ticks' => ['precision' => 0]],
            ],
        ];
    }
}
