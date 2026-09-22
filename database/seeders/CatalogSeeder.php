<?php

namespace Database\Seeders;

use App\Models\Service;
use App\Models\ServiceCategory;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

/**
 * Демо-каталог для портфолио: студия веб-разработки.
 */
class CatalogSeeder extends Seeder
{
    public function run(): void
    {
        $catalog = [
            [
                'name' => 'Сайты',
                'description' => 'Разработка сайтов под ключ.',
                'services' => [
                    ['Лендинг', 'Одностраничный сайт с формой заявки и адаптивной вёрсткой.', 35000, null],
                    ['Корпоративный сайт', 'Многостраничный сайт с админкой и блогом.', 90000, null],
                    ['Интернет-магазин', 'Каталог, корзина, оплата, интеграция со складом.', 180000, null],
                ],
            ],
            [
                'name' => 'Telegram-боты',
                'description' => 'Боты для заявок, записи и продаж.',
                'services' => [
                    ['Бот для заявок', 'Каталог, оформление заявки, уведомления администратору.', 25000, null],
                    ['Бот с оплатой', 'Приём платежей через ЮKassa прямо в боте.', 45000, null],
                    ['Telegram Web App', 'Мини-приложение с каталогом и корзиной внутри Telegram.', 70000, null],
                ],
            ],
            [
                'name' => 'Поддержка',
                'description' => 'Сопровождение готовых проектов.',
                'services' => [
                    ['Консультация', 'Разбор проекта и план доработок.', 3000, 60],
                    ['Часовая доработка', 'Правки по текущему проекту, оплата по факту.', 2500, 60],
                    ['Месячная поддержка', 'Хостинг, обновления, мониторинг, 10 часов правок.', 20000, null],
                ],
            ],
        ];

        foreach ($catalog as $categoryIndex => $categoryData) {
            $category = ServiceCategory::updateOrCreate(
                ['slug' => Str::slug($categoryData['name'])],
                [
                    'name' => $categoryData['name'],
                    'description' => $categoryData['description'],
                    'sort_order' => $categoryIndex,
                    'is_active' => true,
                ],
            );

            foreach ($categoryData['services'] as $serviceIndex => [$name, $description, $price, $duration]) {
                Service::updateOrCreate(
                    ['slug' => Str::slug($name)],
                    [
                        'service_category_id' => $category->id,
                        'name' => $name,
                        'description' => $description,
                        'price' => $price,
                        'duration_minutes' => $duration,
                        'sort_order' => $serviceIndex,
                        'is_active' => true,
                    ],
                );
            }
        }
    }
}
