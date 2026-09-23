<?php

namespace App\Filament\Widgets\Analytics;

use App\Filament\Widgets\Analytics\Concerns\UsesAnalyticsPeriod;
use Filament\Widgets\Widget;

class TopServicesWidget extends Widget
{
    use UsesAnalyticsPeriod;

    protected static ?int $sort = 5;

    protected string $view = 'filament.widgets.analytics.top-services';

    /**
     * @return array<string, mixed>
     */
    protected function getViewData(): array
    {
        return [
            'services' => $this->analytics()->topServices($this->period()),
        ];
    }
}
