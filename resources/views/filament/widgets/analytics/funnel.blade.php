{{--
    Воронка. Стили inline: собранный CSS Filament не знает произвольных
    классов Tailwind. Полупрозрачная подложка читается в обеих темах.
--}}
<x-filament-widgets::widget>
    <x-filament::section heading="Воронка новых пользователей" description="Кто пришёл за период и докуда дошёл">
        @if ($isEmpty)
            <p style="color: var(--gray-500)">За период новых пользователей не было.</p>
        @else
            <div style="display: flex; flex-direction: column; gap: 14px">
                @foreach ($steps as $step)
                    <div>
                        <div style="display: flex; justify-content: space-between; gap: 12px; margin-bottom: 6px; font-size: 0.875rem">
                            <span style="font-weight: 500">{{ $step['step'] }}</span>
                            <span>
                                <strong>{{ number_format($step['users'], 0, ',', ' ') }}</strong>
                                <span style="color: var(--gray-500)">
                                    · {{ $step['share'] }} %
                                    @if ($step['from_previous'] !== null)
                                        · {{ $step['from_previous'] }} % из предыдущего
                                    @endif
                                </span>
                            </span>
                        </div>
                        <div style="height: 10px; border-radius: 4px; background: rgba(128, 128, 128, 0.15)">
                            <div style="height: 100%; width: {{ max($step['share'], $step['users'] > 0 ? 1 : 0) }}%; border-radius: 4px; background: #2a78d6"></div>
                        </div>
                    </div>
                @endforeach
            </div>
        @endif
    </x-filament::section>
</x-filament-widgets::widget>
