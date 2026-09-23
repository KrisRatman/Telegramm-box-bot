<?php

namespace App\Http\Controllers\MiniApp;

use App\Http\Controllers\Controller;
use App\Models\Bot;
use App\Models\Service;
use App\Models\ServiceCategory;
use Illuminate\Contracts\View\View;

/**
 * Страница Mini App бота. Каталог отдаём сразу в HTML: он публичный,
 * а лишний запрос при открытии внутри Telegram заметен на мобильной сети.
 *
 * Язык пользователя сервер при открытии ещё не знает (initData живёт
 * во фрагменте адреса и на сервер не приходит), поэтому тексты и названия
 * уходят на всех языках сразу, а выбирает фронт.
 */
class MiniAppController extends Controller
{
    public function __invoke(Bot $bot): View
    {
        abort_unless($bot->is_active, 404);

        $locales = config('telegram.locales');

        $categories = ServiceCategory::query()
            ->active()
            ->whereHas('services', fn ($query) => $query->orderable($bot))
            ->with(['services' => fn ($query) => $query->orderable($bot)->orderBy('sort_order')->orderBy('name')])
            ->orderBy('sort_order')
            ->orderBy('name')
            ->get();

        return view('mini-app', [
            'bot' => $bot,
            'config' => [
                'apiBase' => "/app/{$bot->id}/api",
                'defaultLocale' => $bot->default_locale,
                'messages' => collect($locales)->mapWithKeys(fn (string $locale) => [$locale => trans('mini-app.ui', [], $locale)]),
                'catalog' => $categories->map(fn (ServiceCategory $category) => [
                    'id' => $category->id,
                    'name' => $this->allLocales($category, 'name', $locales),
                    'services' => $category->services->map(fn (Service $service) => [
                        'id' => $service->id,
                        'name' => $this->allLocales($service, 'name', $locales),
                        'description' => $this->allLocales($service, 'description', $locales),
                        'price' => (float) $service->price,
                        'duration' => $service->duration_minutes,
                    ])->values(),
                ])->values(),
            ],
        ]);
    }

    /**
     * @param  list<string>  $locales
     * @return array<string, ?string>
     */
    private function allLocales(Service|ServiceCategory $model, string $field, array $locales): array
    {
        return collect($locales)->mapWithKeys(fn (string $locale) => [$locale => $model->translated($field, $locale)])->all();
    }
}
