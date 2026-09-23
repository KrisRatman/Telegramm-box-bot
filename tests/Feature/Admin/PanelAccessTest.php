<?php

namespace Tests\Feature\Admin;

use App\Models\Broadcast;
use App\Models\Order;
use App\Models\Service;
use App\Models\ServiceCategory;
use App\Models\TelegramUser;
use App\Models\User;
use Database\Seeders\CatalogSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class PanelAccessTest extends TestCase
{
    use RefreshDatabase;

    public function test_guest_is_redirected_to_login(): void
    {
        $this->get('/admin')->assertRedirect('/admin/login');
    }

    public function test_root_redirects_to_panel(): void
    {
        $this->get('/')->assertRedirect('/admin');
    }

    public function test_login_page_is_available(): void
    {
        $this->get('/admin/login')->assertOk();
    }

    #[DataProvider('panelPages')]
    public function test_panel_pages_render(string $uri): void
    {
        $this->actingAs(User::factory()->create())
            ->get($uri)
            ->assertOk();
    }

    public static function panelPages(): array
    {
        return [
            'дашборд' => ['/admin'],
            'заявки' => ['/admin/orders'],
            'пользователи' => ['/admin/telegram-users'],
            'услуги' => ['/admin/services'],
            'категории' => ['/admin/service-categories'],
            'рассылки' => ['/admin/broadcasts'],
            'аналитика' => ['/admin/analytics'],
        ];
    }

    public function test_order_card_renders(): void
    {
        $order = Order::factory()->create();

        $this->actingAs(User::factory()->create())
            ->get("/admin/orders/{$order->id}")
            ->assertOk()
            ->assertSee($order->number);
    }

    public function test_telegram_user_card_renders(): void
    {
        $user = TelegramUser::factory()->create(['first_name' => 'Мария']);

        $this->actingAs(User::factory()->create())
            ->get("/admin/telegram-users/{$user->id}")
            ->assertOk()
            ->assertSee('Мария');
    }

    public function test_broadcast_card_renders(): void
    {
        $broadcast = Broadcast::factory()->create(['title' => 'Акция недели']);

        $this->actingAs(User::factory()->create())
            ->get("/admin/broadcasts/{$broadcast->id}")
            ->assertOk()
            ->assertSee('Акция недели');
    }

    public function test_catalog_seeder_fills_bot_catalog(): void
    {
        $this->seed(CatalogSeeder::class);

        $this->assertTrue(ServiceCategory::query()->active()->exists());
        $this->assertTrue(Service::query()->active()->exists());
    }
}
