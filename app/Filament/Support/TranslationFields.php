<?php

namespace App\Filament\Support;

use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Section;

/**
 * Поля переводов каталога. Русский вводится в основных полях формы,
 * остальные языки — здесь и сохраняются в JSON-колонку translations.
 */
class TranslationFields
{
    private const LANGUAGE_NAMES = [
        'en' => 'английский',
    ];

    /**
     * @return list<Section>
     */
    public static function sections(string $descriptionHint): array
    {
        return collect(config('telegram.locales'))
            ->skip(1)
            ->map(fn (string $locale) => Section::make('Перевод: '.(self::LANGUAGE_NAMES[$locale] ?? $locale))
                ->description('Необязательно. Пустое поле — клиент с этим языком увидит русский текст.')
                ->collapsible()
                ->schema([
                    TextInput::make("translations.{$locale}.name")
                        ->label('Название')
                        ->maxLength(255),
                    Textarea::make("translations.{$locale}.description")
                        ->label('Описание')
                        ->rows(3)
                        ->helperText($descriptionHint),
                ]))
            ->values()
            ->all();
    }
}
