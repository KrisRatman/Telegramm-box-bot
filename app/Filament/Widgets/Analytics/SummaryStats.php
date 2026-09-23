<?php

namespace App\Filament\Widgets\Analytics;

use App\Filament\Widgets\Analytics\Concerns\UsesAnalyticsPeriod;
use Filament\Support\Icons\Heroicon;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

/**
 * Ключевые цифры периода и изменение к такому же периоду до него.
 */
class SummaryStats extends StatsOverviewWidget
{
    use UsesAnalyticsPeriod;

    protected static ?int $sort = 1;

    protected function getColumns(): int
    {
        return 3;
    }

    protected function getStats(): array
    {
        $period = $this->period();
        $current = $this->analytics()->summary($period);
        $previous = $this->analytics()->summary($period->previous());

        return [
            $this->stat('Новые пользователи', $current['new_users'], $previous['new_users']),
            $this->stat('Активные пользователи', $current['active_users'], $previous['active_users'], 'Писали боту или открывали Mini App'),
            $this->stat('Заявки', $current['orders'], $previous['orders'], 'Без отменённых'),
            $this->stat('Конверсия в заявку', $current['conversion'], $previous['conversion'], 'Из новых пользователей', suffix: ' %', inPoints: true),
            $this->stat('Средний чек', $current['average_check'], $previous['average_check'], suffix: ' ₽'),
            $this->stat('Оплачено', $current['revenue'], $previous['revenue'], 'Платежи в Telegram, без возвратов', suffix: ' ₽'),
        ];
    }

    /**
     * Карточка с дельтой. Для процентов дельта в процентных пунктах —
     * «рост конверсии на 50 %» с 2 % до 3 % только запутал бы.
     */
    private function stat(
        string $label,
        int|float $value,
        int|float $before,
        ?string $hint = null,
        string $suffix = '',
        bool $inPoints = false,
    ): Stat {
        $stat = Stat::make($label, $this->format($value).$suffix);

        if ($before == 0 && $value == 0) {
            return $stat->description($hint ?? 'Нет данных за оба периода');
        }

        $delta = $inPoints ? $value - $before : ($before == 0 ? null : ($value - $before) / $before * 100);

        if ($delta === null) {
            return $stat->description(trim(($hint ? $hint.' · ' : '').'в прошлом периоде 0'));
        }

        $sign = $delta > 0 ? '+' : ($delta < 0 ? '−' : '');
        $text = $sign.$this->format(abs($delta)).($inPoints ? ' п.п.' : ' %').' к прошлому периоду';

        return $stat
            ->description($hint ? "{$text} · {$hint}" : $text)
            ->descriptionIcon(match (true) {
                $delta > 0 => Heroicon::ArrowTrendingUp,
                $delta < 0 => Heroicon::ArrowTrendingDown,
                default => Heroicon::Minus,
            })
            ->color(match (true) {
                $delta > 0 => 'success',
                $delta < 0 => 'danger',
                default => 'gray',
            });
    }

    private function format(int|float $number): string
    {
        $decimals = is_float($number) && floor($number) != $number && abs($number) < 100 ? 1 : 0;

        return number_format($number, $decimals, ',', ' ');
    }
}
