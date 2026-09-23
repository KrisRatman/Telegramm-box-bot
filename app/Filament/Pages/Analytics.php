<?php

namespace App\Filament\Pages;

use App\Filament\Widgets\Analytics\FunnelWidget;
use App\Filament\Widgets\Analytics\NewUsersChart;
use App\Filament\Widgets\Analytics\OrdersBySourceChart;
use App\Filament\Widgets\Analytics\SummaryStats;
use App\Filament\Widgets\Analytics\TopServicesWidget;
use BackedEnum;
use Filament\Forms\Components\Select;
use Filament\Pages\Dashboard;
use Filament\Pages\Dashboard\Concerns\HasFiltersForm;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;

/**
 * Аналитика: пользователи, заявки по каналам, воронка и популярные услуги.
 * Период выбирается один раз над всеми виджетами.
 */
class Analytics extends Dashboard
{
    use HasFiltersForm;

    protected static string $routePath = '/analytics';

    protected static ?string $title = 'Аналитика';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedChartBar;

    protected static ?int $navigationSort = -1;

    public function filtersForm(Schema $schema): Schema
    {
        return $schema->components([
            Select::make('period')
                ->label('Период')
                ->options([
                    7 => 'Последние 7 дней',
                    30 => 'Последние 30 дней',
                    90 => 'Последние 90 дней',
                ])
                ->default(30)
                ->selectablePlaceholder(false)
                ->native(false),
        ]);
    }

    public function getWidgets(): array
    {
        return [
            SummaryStats::class,
            NewUsersChart::class,
            OrdersBySourceChart::class,
            FunnelWidget::class,
            TopServicesWidget::class,
        ];
    }
}
