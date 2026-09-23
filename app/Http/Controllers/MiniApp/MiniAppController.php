<?php

namespace App\Http\Controllers\MiniApp;

use App\Http\Controllers\Controller;
use App\Models\Service;
use App\Models\ServiceCategory;
use Illuminate\Contracts\View\View;

/**
 * Страница Mini App. Каталог отдаём сразу в HTML: он публичный,
 * а лишний запрос при открытии внутри Telegram заметен на мобильной сети.
 */
class MiniAppController extends Controller
{
    public function __invoke(): View
    {
        $categories = ServiceCategory::query()
            ->active()
            ->whereHas('services', fn ($query) => $query->active())
            ->with(['services' => fn ($query) => $query->active()->orderBy('sort_order')->orderBy('name')])
            ->orderBy('sort_order')
            ->orderBy('name')
            ->get();

        return view('mini-app', [
            'catalog' => $categories->map(fn (ServiceCategory $category) => [
                'id' => $category->id,
                'name' => $category->name,
                'services' => $category->services->map(fn (Service $service) => [
                    'id' => $service->id,
                    'name' => $service->name,
                    'description' => $service->description,
                    'price' => (float) $service->price,
                    'duration' => $service->duration_minutes,
                ])->values(),
            ])->values(),
        ]);
    }
}
