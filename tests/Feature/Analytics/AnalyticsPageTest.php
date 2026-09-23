<?php

namespace Tests\Feature\Analytics;

use App\Enums\OrderSource;
use App\Filament\Widgets\Analytics\FunnelWidget;
use App\Filament\Widgets\Analytics\NewUsersChart;
use App\Filament\Widgets\Analytics\OrdersBySourceChart;
use App\Filament\Widgets\Analytics\SummaryStats;
use App\Filament\Widgets\Analytics\TopServicesWidget;
use App\Models\Order;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class AnalyticsPageTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->actingAs(User::factory()->create());

        Order::factory()->create(['price' => 20000, 'source' => OrderSource::MiniApp])
            ->items()->create(['service_name' => 'Лендинг под ключ', 'price' => 20000, 'quantity' => 1]);
    }

    public function test_summary_shows_period_figures(): void
    {
        Livewire::test(SummaryStats::class, ['pageFilters' => ['period' => 7]])
            ->assertSee('Новые пользователи')
            ->assertSee('Конверсия в заявку')
            ->assertSee('20 000 ₽');
    }

    public function test_funnel_shows_steps(): void
    {
        Livewire::test(FunnelWidget::class, ['pageFilters' => ['period' => 30]])
            ->assertSee('Пришли в бота')
            ->assertSee('Оставили заявку')
            ->assertSee('100 % из предыдущего');
    }

    public function test_top_services_lists_ordered_service(): void
    {
        Livewire::test(TopServicesWidget::class, ['pageFilters' => ['period' => 30]])
            ->assertSee('Лендинг под ключ')
            ->assertSee('20 000 ₽');
    }

    public function test_charts_render(): void
    {
        Livewire::test(NewUsersChart::class, ['pageFilters' => ['period' => 90]])->assertOk();
        Livewire::test(OrdersBySourceChart::class, ['pageFilters' => ['period' => 7]])
            ->assertOk()
            ->assertSee('Mini App');
    }

    public function test_analytics_widgets_stay_off_main_dashboard(): void
    {
        $this->get('/admin')
            ->assertOk()
            ->assertDontSee('Воронка новых пользователей');
    }
}
