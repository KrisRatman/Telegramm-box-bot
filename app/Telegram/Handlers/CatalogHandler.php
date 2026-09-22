<?php

namespace App\Telegram\Handlers;

use App\Models\Service;
use App\Models\ServiceCategory;
use App\Telegram\Support\Keyboards;
use App\Telegram\Support\Screen;
use App\Telegram\Support\Texts;
use SergiX44\Nutgram\Nutgram;

class CatalogHandler
{
    public function categories(Nutgram $bot): void
    {
        $categories = ServiceCategory::query()
            ->active()
            ->whereHas('services', fn ($query) => $query->active())
            ->orderBy('sort_order')
            ->orderBy('name')
            ->get();

        if ($categories->isEmpty()) {
            Screen::show($bot, Texts::emptyCatalog(), Keyboards::backToMenu());

            return;
        }

        Screen::show($bot, Texts::categories(), Keyboards::categories($categories));
    }

    public function services(Nutgram $bot, string $categoryId): void
    {
        $category = ServiceCategory::query()->active()->find((int) $categoryId);

        if ($category === null) {
            $this->categories($bot);

            return;
        }

        $services = $category->services()
            ->active()
            ->orderBy('sort_order')
            ->orderBy('name')
            ->get();

        if ($services->isEmpty()) {
            Screen::show($bot, Texts::emptyCategory(), Keyboards::backToMenu());

            return;
        }

        $text = "<b>{$category->name}</b>\n\n".($category->description ?: 'Выберите услугу:');

        Screen::show($bot, $text, Keyboards::services($services));
    }

    public function card(Nutgram $bot, string $serviceId): void
    {
        $service = Service::query()->active()->find((int) $serviceId);

        if ($service === null) {
            $this->categories($bot);

            return;
        }

        Screen::show($bot, Texts::serviceCard($service), Keyboards::serviceCard($service));
    }
}
