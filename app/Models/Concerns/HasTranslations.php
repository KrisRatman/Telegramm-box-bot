<?php

namespace App\Models\Concerns;

/**
 * Переводы полей каталога. Русский — в самих колонках (name, description),
 * остальные языки — в JSON-колонке translations: {"en": {"name": "..."}}.
 * Пустой перевод означает «показать русский».
 */
trait HasTranslations
{
    public function initializeHasTranslations(): void
    {
        $this->mergeCasts(['translations' => 'array']);
    }

    public function translated(string $field, ?string $locale = null): ?string
    {
        $locale ??= app()->getLocale();
        $translation = $this->translations[$locale][$field] ?? null;

        return filled($translation) ? $translation : $this->getAttribute($field);
    }
}
