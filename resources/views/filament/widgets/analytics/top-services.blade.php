<x-filament-widgets::widget>
    <x-filament::section heading="Популярные услуги" description="По сумме в неотменённых заявках за период">
        @if ($services === [])
            <p style="color: var(--gray-500)">За период заявок не было.</p>
        @else
            <table style="width: 100%; border-collapse: collapse; font-size: 0.875rem">
                <thead>
                    <tr style="color: var(--gray-500); text-align: left">
                        <th style="padding: 0 8px 8px 0; font-weight: 500">Услуга</th>
                        <th style="padding: 0 8px 8px; font-weight: 500; text-align: right">Кол-во</th>
                        <th style="padding: 0 0 8px 8px; font-weight: 500; text-align: right">Сумма</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($services as $service)
                        <tr style="border-top: 1px solid rgba(128, 128, 128, 0.2)">
                            <td style="padding: 8px 8px 8px 0">{{ $service['name'] }}</td>
                            <td style="padding: 8px; text-align: right">{{ $service['quantity'] }}</td>
                            <td style="padding: 8px 0 8px 8px; text-align: right; white-space: nowrap">
                                {{ number_format($service['revenue'], 0, ',', ' ') }} ₽
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        @endif
    </x-filament::section>
</x-filament-widgets::widget>
