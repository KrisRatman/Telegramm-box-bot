<?php

namespace Database\Seeders;

use App\Models\Bot;
use App\Models\Service;
use App\Models\ServiceCategory;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

/**
 * Демо-каталог для портфолио: студия веб-разработки, с переводом на английский.
 */
class CatalogSeeder extends Seeder
{
    public function run(): void
    {
        $catalog = [
            [
                'name' => ['Сайты', 'Websites'],
                'description' => ['Разработка сайтов под ключ.', 'Turnkey website development.'],
                'services' => [
                    [['Лендинг', 'Landing page'], ['Одностраничный сайт с формой заявки и адаптивной вёрсткой.', 'One-page website with a request form and responsive layout.'], 35000, null],
                    [['Корпоративный сайт', 'Corporate website'], ['Многостраничный сайт с админкой и блогом.', 'Multi-page website with an admin panel and a blog.'], 90000, null],
                    [['Интернет-магазин', 'Online store'], ['Каталог, корзина, оплата, интеграция со складом.', 'Catalog, cart, payments, warehouse integration.'], 180000, null],
                ],
            ],
            [
                'name' => ['Telegram-боты', 'Telegram bots'],
                'description' => ['Боты для заявок, записи и продаж.', 'Bots for requests, bookings and sales.'],
                'services' => [
                    [['Бот для заявок', 'Request bot'], ['Каталог, оформление заявки, уведомления администратору.', 'Catalog, request form, admin notifications.'], 25000, null],
                    [['Бот с оплатой', 'Bot with payments'], ['Приём платежей через ЮKassa прямо в боте.', 'Accept payments via YooKassa right in the bot.'], 45000, null],
                    [['Telegram Web App', 'Telegram Web App'], ['Мини-приложение с каталогом и корзиной внутри Telegram.', 'Mini app with a catalog and cart inside Telegram.'], 70000, null],
                ],
            ],
            [
                'name' => ['Поддержка', 'Support'],
                'description' => ['Сопровождение готовых проектов.', 'Maintenance of existing projects.'],
                'services' => [
                    [['Консультация', 'Consultation'], ['Разбор проекта и план доработок.', 'Project review and improvement plan.'], 3000, 60],
                    [['Часовая доработка', 'Hourly work'], ['Правки по текущему проекту, оплата по факту.', 'Changes to your project, billed by the hour.'], 2500, 60],
                    [['Месячная поддержка', 'Monthly support'], ['Хостинг, обновления, мониторинг, 10 часов правок.', 'Hosting, updates, monitoring, 10 hours of changes.'], 20000, null],
                ],
            ],
        ];

        $botIds = Bot::query()->pluck('id');

        foreach ($catalog as $categoryIndex => $categoryData) {
            [$name, $nameEn] = $categoryData['name'];
            [$description, $descriptionEn] = $categoryData['description'];

            $category = ServiceCategory::updateOrCreate(
                ['slug' => Str::slug($name)],
                [
                    'name' => $name,
                    'description' => $description,
                    'translations' => ['en' => ['name' => $nameEn, 'description' => $descriptionEn]],
                    'sort_order' => $categoryIndex,
                    'is_active' => true,
                ],
            );

            foreach ($categoryData['services'] as $serviceIndex => [[$serviceName, $serviceNameEn], [$serviceDescription, $serviceDescriptionEn], $price, $duration]) {
                $service = Service::updateOrCreate(
                    ['slug' => Str::slug($serviceName)],
                    [
                        'service_category_id' => $category->id,
                        'name' => $serviceName,
                        'description' => $serviceDescription,
                        'translations' => ['en' => ['name' => $serviceNameEn, 'description' => $serviceDescriptionEn]],
                        'price' => $price,
                        'duration_minutes' => $duration,
                        'sort_order' => $serviceIndex,
                        'is_active' => true,
                    ],
                );

                // Демо-каталог продаётся во всех ботах.
                $service->bots()->syncWithoutDetaching($botIds);
            }
        }
    }
}
