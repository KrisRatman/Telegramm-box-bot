<?php

namespace Tests\Feature\MiniApp;

use App\Models\Order;
use App\Models\Service;
use App\Models\ServiceCategory;
use App\Models\TelegramUser;
use App\Services\PaymentService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use SergiX44\Nutgram\Nutgram;
use Tests\Concerns\InteractsWithBot;
use Tests\TestCase;

class MiniAppTest extends TestCase
{
    use InteractsWithBot, RefreshDatabase;

    private const BOT_TOKEN = '123456:test-bot-token';

    private const CHAT_ID = 424242;

    private const ADMIN_CHAT_ID = 111;

    protected function setUp(): void
    {
        parent::setUp();

        config([
            'nutgram.token' => self::BOT_TOKEN,
            'telegram.admin_chat_ids' => [(string) self::ADMIN_CHAT_ID],
            'telegram.payments.provider_token' => null,
        ]);
    }

    // --- Страница ------------------------------------------------------------

    public function test_page_shows_only_orderable_services(): void
    {
        $this->withoutVite();

        Service::factory()->create(['name' => 'Лендинг под ключ']);
        Service::factory()->create(['name' => 'Снятая услуга', 'is_active' => false]);
        Service::factory()
            ->for(ServiceCategory::factory()->state(['is_active' => false]), 'category')
            ->create(['name' => 'Услуга из скрытой категории']);

        $this->get('/app')
            ->assertOk()
            ->assertSee('Лендинг под ключ')
            ->assertDontSee('Снятая услуга')
            ->assertDontSee('Услуга из скрытой категории');
    }

    // --- Проверка initData ---------------------------------------------------

    public function test_api_rejects_request_without_init_data(): void
    {
        $this->getJson('/app/api/profile')
            ->assertUnauthorized()
            ->assertJson(['message' => 'Откройте каталог из Telegram-бота.']);
    }

    public function test_api_rejects_init_data_with_forged_user(): void
    {
        // Подписано для одного пользователя, а в строку подставлен другой id.
        $initData = str_replace(
            urlencode('"id":'.self::CHAT_ID),
            urlencode('"id":999'),
            $signed = $this->initData(),
        );
        $this->assertNotSame($signed, $initData);

        $this->getJson('/app/api/profile', ['X-Telegram-Init-Data' => $initData])->assertUnauthorized();

        $this->assertDatabaseMissing('telegram_users', ['chat_id' => 999]);
    }

    public function test_api_rejects_init_data_signed_with_another_token(): void
    {
        $this->getJson('/app/api/profile', [
            'X-Telegram-Init-Data' => $this->initData(token: '654321:other-bot'),
        ])->assertUnauthorized();
    }

    public function test_api_rejects_expired_init_data(): void
    {
        config(['telegram.mini_app.auth_ttl' => 3600]);

        $this->getJson('/app/api/profile', [
            'X-Telegram-Init-Data' => $this->initData(authDate: now()->subHours(2)->timestamp),
        ])->assertUnauthorized();
    }

    public function test_profile_registers_new_user_and_returns_saved_phone(): void
    {
        $this->getJson('/app/api/profile', $this->headers())
            ->assertOk()
            ->assertExactJson(['name' => 'Иван', 'phone' => null]);

        TelegramUser::query()->where('chat_id', self::CHAT_ID)->update(['phone' => '+79001234567']);

        $this->getJson('/app/api/profile', $this->headers())
            ->assertOk()
            ->assertJsonPath('phone', '+79001234567');

        $this->assertSame(1, TelegramUser::query()->where('chat_id', self::CHAT_ID)->count());
    }

    // --- Оформление заявки ---------------------------------------------------

    public function test_cart_order_uses_catalog_prices_and_stores_items(): void
    {
        $landing = Service::factory()->create(['name' => 'Лендинг', 'price' => 20000]);
        $support = Service::factory()->create(['name' => 'Поддержка', 'price' => 3000]);

        $response = $this->postJson('/app/api/orders', [
            'items' => [
                // Цена с фронта должна игнорироваться.
                ['service_id' => $landing->id, 'quantity' => 1, 'price' => 1],
                ['service_id' => $support->id, 'quantity' => 3],
            ],
            'contact_name' => ' Иван ',
            'contact_phone' => '+7 900 123-45-67',
            'comment' => 'После обеда',
        ], $this->headers());

        $order = Order::query()->with('items')->sole();

        $response->assertCreated()->assertExactJson([
            'number' => $order->number,
            'total' => '29 000 ₽',
            'invoice_link' => null,
        ]);

        $this->assertSame('29000.00', $order->price);
        $this->assertSame('Лендинг и ещё 1', $order->service_name);
        $this->assertNull($order->service_id);
        $this->assertSame('Иван', $order->contact_name);
        $this->assertSame(
            [['Лендинг', '20000.00', 1], ['Поддержка', '3000.00', 3]],
            $order->items->map(fn ($item) => [$item->service_name, $item->price, $item->quantity])->all(),
        );
        $this->assertSame('+7 900 123-45-67', $order->telegramUser->phone);
    }

    public function test_cart_order_notifies_client_and_admin(): void
    {
        $service = Service::factory()->create(['name' => 'Лендинг', 'price' => 20000]);

        $this->postJson('/app/api/orders', $this->orderPayload([$service->id => 2]), $this->headers())
            ->assertCreated();

        $messages = collect($this->requestsTo(app(Nutgram::class), 'sendMessage'));

        $toClient = $messages->firstWhere('chat_id', self::CHAT_ID);
        $toAdmin = $messages->firstWhere('chat_id', self::ADMIN_CHAT_ID);

        $this->assertStringContainsString('принята', $toClient['text']);
        $this->assertStringContainsString('Стоимость: 40 000 ₽', $toClient['text']);
        $this->assertStringContainsString('Новая заявка', $toAdmin['text']);
    }

    public function test_single_service_order_keeps_link_to_service(): void
    {
        $service = Service::factory()->create(['name' => 'Лендинг']);

        $this->postJson('/app/api/orders', $this->orderPayload([$service->id => 1]), $this->headers())
            ->assertCreated();

        $order = Order::query()->sole();

        $this->assertSame($service->id, $order->service_id);
        $this->assertSame('Лендинг', $order->service_name);
    }

    public function test_order_with_unavailable_service_is_rejected(): void
    {
        $available = Service::factory()->create();
        $hidden = Service::factory()
            ->for(ServiceCategory::factory()->state(['is_active' => false]), 'category')
            ->create();

        $this->postJson('/app/api/orders', $this->orderPayload([$available->id => 1, $hidden->id => 1]), $this->headers())
            ->assertUnprocessable()
            ->assertJsonValidationErrors([
                'items' => 'Часть услуг больше недоступна. Обновите каталог и проверьте корзину.',
            ]);

        $this->assertDatabaseCount('orders', 0);
    }

    public function test_order_requires_cart_and_contacts(): void
    {
        $this->postJson('/app/api/orders', [], $this->headers())
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['items', 'contact_name', 'contact_phone']);
    }

    public function test_order_rejects_incomplete_phone(): void
    {
        $service = Service::factory()->create();

        $this->postJson('/app/api/orders', $this->orderPayload([$service->id => 1], phone: '12-34'), $this->headers())
            ->assertUnprocessable()
            ->assertJsonValidationErrors([
                'contact_phone' => 'Укажите телефон полностью, например +7 900 123-45-67.',
            ]);
    }

    public function test_order_returns_itemized_invoice_link_when_payments_enabled(): void
    {
        config(['telegram.payments.provider_token' => 'test-provider-token']);

        $landing = Service::factory()->create(['name' => 'Лендинг', 'price' => 20000]);
        $support = Service::factory()->create(['name' => 'Поддержка', 'price' => 3000]);

        $response = $this->postJson(
            '/app/api/orders',
            $this->orderPayload([$landing->id => 1, $support->id => 3]),
            $this->headers(),
        )->assertCreated();

        $this->assertNotEmpty($response->json('invoice_link'));

        $invoice = $this->requestsTo(app(Nutgram::class), 'createInvoiceLink')[0];
        $prices = is_string($invoice['prices']) ? json_decode($invoice['prices'], true) : $invoice['prices'];

        $this->assertSame([
            ['label' => 'Лендинг', 'amount' => 2000000],
            ['label' => 'Поддержка × 3', 'amount' => 900000],
        ], $prices);

        // Ожидающий платёж заведён — pre-checkout найдёт его по payload.
        $this->assertSame($invoice['payload'], Order::query()->sole()->payments()->sole()->invoice_payload);
    }

    public function test_invoice_falls_back_to_single_line_after_manual_price_change(): void
    {
        config(['telegram.payments.provider_token' => 'test-provider-token']);

        $order = Order::factory()->create(['service_name' => 'Лендинг и ещё 1', 'price' => 23000]);
        $order->items()->createMany([
            ['service_name' => 'Лендинг', 'price' => 20000, 'quantity' => 1],
            ['service_name' => 'Поддержка', 'price' => 3000, 'quantity' => 1],
        ]);

        // Админ дал скидку — позиции больше не складываются в сумму заявки.
        $order->update(['price' => 21000]);

        app(PaymentService::class)->invoiceLink($order);

        $invoice = $this->requestsTo(app(Nutgram::class), 'createInvoiceLink')[0];
        $prices = is_string($invoice['prices']) ? json_decode($invoice['prices'], true) : $invoice['prices'];

        $this->assertSame([['label' => 'Лендинг и ещё 1', 'amount' => 2100000]], $prices);
    }

    // --- Кнопка в боте -------------------------------------------------------

    public function test_main_menu_opens_mini_app_when_https_url_is_set(): void
    {
        config(['telegram.mini_app.url' => 'https://example.test/app']);

        $bot = $this->fakeBot(self::CHAT_ID);
        $bot->hearText('/start')->reply();

        $keyboard = $this->requestsTo($bot, 'sendMessage')[0]['reply_markup']['inline_keyboard'];

        $this->assertSame(
            ['text' => '🛒 Каталог и корзина', 'web_app' => ['url' => 'https://example.test/app']],
            $keyboard[0][0],
        );
    }

    public function test_main_menu_hides_mini_app_for_http_url(): void
    {
        config(['telegram.mini_app.url' => 'http://localhost/app']);

        $bot = $this->fakeBot(self::CHAT_ID);
        $bot->hearText('/start')->reply();

        $keyboard = $this->requestsTo($bot, 'sendMessage')[0]['reply_markup']['inline_keyboard'];

        $this->assertSame('🛍 Каталог услуг', $keyboard[0][0]['text']);
        $this->assertStringNotContainsString('web_app', json_encode($keyboard));
    }

    // --- Помощники -----------------------------------------------------------

    /**
     * initData, подписанный по алгоритму из документации Telegram.
     */
    private function initData(?string $token = null, ?int $authDate = null): string
    {
        $fields = [
            'auth_date' => (string) ($authDate ?? now()->timestamp),
            'query_id' => 'AAH-test-query',
            'user' => json_encode([
                'id' => self::CHAT_ID,
                'first_name' => 'Иван',
                'last_name' => 'Петров',
                'username' => 'ivan',
                'language_code' => 'ru',
            ], JSON_UNESCAPED_UNICODE),
        ];

        ksort($fields);
        $checkString = collect($fields)->map(fn (string $value, string $key) => "{$key}={$value}")->implode("\n");
        $secret = hash_hmac('sha256', $token ?? self::BOT_TOKEN, 'WebAppData', true);
        $fields['hash'] = hash_hmac('sha256', $checkString, $secret);

        return http_build_query($fields);
    }

    /**
     * @return array<string, string>
     */
    private function headers(): array
    {
        return ['X-Telegram-Init-Data' => $this->initData()];
    }

    /**
     * @param  array<int, int>  $quantities  service_id => количество
     * @return array<string, mixed>
     */
    private function orderPayload(array $quantities, string $phone = '+79001234567'): array
    {
        return [
            'items' => collect($quantities)
                ->map(fn (int $quantity, int $serviceId) => ['service_id' => $serviceId, 'quantity' => $quantity])
                ->values()
                ->all(),
            'contact_name' => 'Иван',
            'contact_phone' => $phone,
        ];
    }
}
