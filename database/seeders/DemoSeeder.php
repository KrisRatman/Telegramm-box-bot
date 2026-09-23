<?php

namespace Database\Seeders;

use App\Enums\BotEventType;
use App\Enums\MessageDirection;
use App\Enums\OrderSource;
use App\Enums\OrderStatus;
use App\Enums\PaymentStatus;
use App\Models\Bot;
use App\Models\BotEvent;
use App\Models\BotMessage;
use App\Models\Order;
use App\Models\Payment;
use App\Models\Service;
use App\Models\TelegramUser;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Seeder;
use Illuminate\Support\Carbon;
use Random\Engine\Mt19937;
use Random\Randomizer;

/**
 * Демо-данные для скриншотов и показа админки заказчику.
 * В продакшене не запускается: php artisan db:seed --class=DemoSeeder.
 */
class DemoSeeder extends Seeder
{
    private int $payableOrders = 0;

    public function run(): void
    {
        [$mainBot, $branchBot] = $this->seedBots();

        $services = Service::query()->active()->get();

        if ($services->isEmpty()) {
            $this->call(CatalogSeeder::class);
            $services = Service::query()->active()->get();
        }

        $people = [
            ['Анна', 'Смирнова', 'anna_s', '+79001112233'],
            ['Дмитрий', 'Ковалёв', 'dkovalev', '+79262223344'],
            ['Ольга', 'Иванова', null, '+79153334455'],
            ['Сергей', 'Петров', 'spetrov', '+79094445566'],
            ['Мария', 'Новикова', 'mnovikova', '+79165556677'],
        ];

        $statuses = OrderStatus::cases();

        foreach ($people as $index => [$first, $last, $username, $phone]) {
            $user = TelegramUser::updateOrCreate(
                ['bot_id' => $mainBot->id, 'chat_id' => 700000000 + $index],
                [
                    'first_name' => $first,
                    'last_name' => $last,
                    'username' => $username,
                    'phone' => $phone,
                    'language_code' => 'ru',
                    'is_blocked' => false,
                    'last_activity_at' => now()->subHours($index * 5),
                ],
            );

            BotMessage::create([
                'telegram_user_id' => $user->id,
                'direction' => MessageDirection::In,
                'text' => '/start',
                'created_at' => now()->subDays($index + 1),
                'updated_at' => now()->subDays($index + 1),
            ]);

            foreach (range(0, $index % 3) as $orderIndex) {
                $service = $services->random();
                $status = $statuses[($index + $orderIndex) % count($statuses)];

                $order = Order::create([
                    'telegram_user_id' => $user->id,
                    'service_id' => $service->id,
                    'service_name' => $service->name,
                    'price' => $service->price,
                    'status' => $status,
                    'contact_name' => $first,
                    'contact_phone' => $phone,
                    'comment' => $orderIndex === 0 ? 'Удобнее созвониться после 18:00.' : null,
                    'completed_at' => $status === OrderStatus::Completed ? now()->subDays($index) : null,
                    'created_at' => now()->subDays($index)->subHours($orderIndex),
                    'updated_at' => now()->subDays($index),
                ]);

                $order->items()->create([
                    'service_id' => $service->id,
                    'service_name' => $service->name,
                    'price' => $service->price,
                    'quantity' => 1,
                ]);

                $this->seedPayment($order);
            }

            if ($index === 0) {
                $this->seedCartOrder($user, $services->take(3), $phone);
            }
        }

        $this->seedFunnelHistory($services, $mainBot, $branchBot);
    }

    /**
     * Основной бот — настоящий из .env, если он уже заведён, иначе демо.
     * Второй бот нужен, чтобы в админке было что фильтровать. У демо-ботов
     * ненастоящие токены, поэтому они выключены: команды и webhook их не трогают.
     *
     * @return array{0: Bot, 1: Bot}
     */
    private function seedBots(): array
    {
        $main = Bot::query()->orderBy('id')->first() ?? Bot::create([
            'name' => 'Демо: студия',
            'username' => 'demo_studio_bot',
            'token' => '000000001:demo-token-not-real',
            'default_locale' => 'ru',
            'is_active' => false,
        ]);

        $branch = Bot::query()->firstOrCreate(
            ['name' => 'Демо: Dubai branch'],
            [
                'username' => 'demo_dubai_bot',
                'token' => '000000002:demo-token-not-real',
                'default_locale' => 'en',
                'is_active' => false,
            ],
        );

        return [$main, $branch];
    }

    /**
     * История за 30 дней для страницы «Аналитика»: пользователи приходят
     * каждый день и отсеиваются на шагах воронки. Генератор с фиксированным
     * зерном — графики на скриншотах не меняются от запуска к запуску.
     *
     * @param  Collection<int, Service>  $services
     */
    private function seedFunnelHistory(Collection $services, Bot $mainBot, Bot $branchBot): void
    {
        // Повторный запуск сидера историю не дублирует.
        if (TelegramUser::query()->where('chat_id', 710000000)->exists()) {
            return;
        }

        $random = new Randomizer(new Mt19937(2026));
        $chance = fn (int $percent): bool => $random->getInt(1, 100) <= $percent;
        $names = ['Алексей', 'Екатерина', 'Никита', 'Юлия', 'Артём', 'Полина', 'Игорь', 'Светлана', 'Максим', 'Дарья'];

        foreach (range(0, 59) as $i) {
            $cameAt = now()->subDays($random->getInt(0, 29))->setTime($random->getInt(9, 21), $random->getInt(0, 59));
            $source = $chance(45) ? OrderSource::MiniApp : OrderSource::Bot;
            // Треть истории — во втором боте, там клиенты в основном англоязычные.
            $inBranch = $i % 3 === 0;
            $name = $names[$i % count($names)];

            $user = TelegramUser::query()->forceCreate([
                'bot_id' => $inBranch ? $branchBot->id : $mainBot->id,
                'chat_id' => 710000000 + $i,
                'first_name' => $name,
                'language_code' => $inBranch ? 'en' : 'ru',
                'is_blocked' => false,
                'last_activity_at' => $cameAt->copy()->addMinutes(20),
                'created_at' => $cameAt,
                'updated_at' => $cameAt,
            ]);

            // Отсев на шагах: 80 % смотрят каталог, из них 55 % начинают
            // оформление, из них 65 % оставляют заявку, половина оплачивает.
            if (! $chance(80)) {
                continue;
            }

            $this->seedEvent($user, BotEventType::CatalogViewed, $source, $cameAt->copy()->addMinute());

            if (! $chance(55)) {
                continue;
            }

            $this->seedEvent($user, BotEventType::OrderStarted, $source, $cameAt->copy()->addMinutes(5));

            if (! $chance(65)) {
                continue;
            }

            $service = $services[$random->getInt(0, $services->count() - 1)];
            $orderedAt = $cameAt->copy()->addMinutes(10);

            $order = Order::query()->forceCreate([
                'number' => Order::generateNumber(),
                'telegram_user_id' => $user->id,
                'service_id' => $service->id,
                'service_name' => $service->name,
                'price' => $service->price,
                'status' => OrderStatus::Confirmed,
                'source' => $source,
                'contact_name' => $name,
                'contact_phone' => '+7900'.str_pad((string) (1000000 + $i), 7, '0', STR_PAD_LEFT),
                'created_at' => $orderedAt,
                'updated_at' => $orderedAt,
            ]);

            $order->items()->create([
                'service_id' => $service->id,
                'service_name' => $service->name,
                'price' => $service->price,
                'quantity' => 1,
            ]);

            if ($chance(50)) {
                $paidAt = $orderedAt->copy()->addMinutes(3);

                Payment::query()->forceCreate([
                    'order_id' => $order->id,
                    'status' => PaymentStatus::Paid,
                    'amount' => $order->price,
                    'currency' => 'RUB',
                    'telegram_payment_charge_id' => 'demo_tg_'.$order->id,
                    'provider_payment_charge_id' => 'demo-'.str_pad((string) $order->id, 8, '0', STR_PAD_LEFT),
                    'paid_at' => $paidAt,
                    'created_at' => $orderedAt,
                    'updated_at' => $paidAt,
                ]);

                $order->forceFill(['paid_at' => $paidAt])->saveQuietly();
            }
        }
    }

    private function seedEvent(TelegramUser $user, BotEventType $type, OrderSource $source, Carbon $at): void
    {
        BotEvent::query()->forceCreate([
            'telegram_user_id' => $user->id,
            'type' => $type,
            'source' => $source,
            'created_at' => $at,
        ]);
    }

    /**
     * Заявка из корзины Mini App: несколько услуг в одной заявке.
     *
     * @param  Collection<int, Service>  $services
     */
    private function seedCartOrder(TelegramUser $user, Collection $services, string $phone): void
    {
        $quantities = $services->values()->mapWithKeys(fn (Service $service, int $i) => [$service->id => $i === 0 ? 2 : 1]);

        $order = Order::create([
            'telegram_user_id' => $user->id,
            'service_id' => null,
            'service_name' => $services->first()->name.' и ещё '.($services->count() - 1),
            'price' => $services->sum(fn (Service $service) => (float) $service->price * $quantities[$service->id]),
            'status' => OrderStatus::New,
            'source' => OrderSource::MiniApp,
            'contact_name' => $user->first_name,
            'contact_phone' => $phone,
            'comment' => 'Оформлено через Mini App.',
        ]);

        foreach ($services as $service) {
            $order->items()->create([
                'service_id' => $service->id,
                'service_name' => $service->name,
                'price' => $service->price,
                'quantity' => $quantities[$service->id],
            ]);
        }
    }

    /**
     * Часть подтверждённых и выполненных заявок оплачена в боте,
     * одна — с возвратом, чтобы в админке были все состояния платежа.
     */
    private function seedPayment(Order $order): void
    {
        $payable = in_array($order->status, [OrderStatus::Confirmed, OrderStatus::InProgress, OrderStatus::Completed], true);

        if (! $payable) {
            return;
        }

        // Каждая четвёртая подходящая заявка остаётся неоплаченной, третья — с возвратом.
        $position = ++$this->payableOrders;

        if ($position % 4 === 0) {
            return;
        }

        $paidAt = $order->created_at->copy()->addHour();
        $refunded = $position === 3;

        // forceCreate — чтобы сохранились исторические created_at и updated_at.
        Payment::query()->forceCreate([
            'order_id' => $order->id,
            'status' => $refunded ? PaymentStatus::Refunded : PaymentStatus::Paid,
            'amount' => $order->price,
            'currency' => 'RUB',
            'telegram_payment_charge_id' => 'demo_tg_'.$order->id,
            'provider_payment_charge_id' => 'demo-'.str_pad((string) $order->id, 8, '0', STR_PAD_LEFT),
            'paid_at' => $paidAt,
            'refunded_at' => $refunded ? $paidAt->copy()->addDay() : null,
            'created_at' => $order->created_at,
            'updated_at' => $paidAt,
        ]);

        if (! $refunded) {
            $order->forceFill(['paid_at' => $paidAt])->saveQuietly();
        }
    }
}
