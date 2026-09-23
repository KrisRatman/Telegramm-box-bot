<?php

namespace Tests\Feature\Admin;

use App\Filament\Resources\Bots\Pages\CreateBot;
use App\Filament\Resources\Bots\Pages\EditBot;
use App\Filament\Resources\Bots\Pages\ListBots;
use App\Models\Bot;
use App\Models\User;
use Filament\Actions\Testing\TestAction;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\Concerns\InteractsWithBot;
use Tests\TestCase;

class BotResourceTest extends TestCase
{
    use InteractsWithBot, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->actingAs(User::factory()->create());
    }

    public function test_admin_creates_bot_with_encrypted_token(): void
    {
        Livewire::test(CreateBot::class)
            ->fillForm([
                'name' => 'Филиал',
                'token' => '123456:new-bot-token',
                'default_locale' => 'en',
            ])
            ->call('create')
            ->assertHasNoFormErrors();

        $bot = Bot::query()->sole();

        $this->assertSame('123456:new-bot-token', $bot->token);
        $this->assertSame('en', $bot->default_locale);
        $this->assertNotEmpty($bot->webhook_secret);
    }

    public function test_token_is_required_when_creating(): void
    {
        Livewire::test(CreateBot::class)
            ->fillForm(['name' => 'Без токена'])
            ->call('create')
            ->assertHasFormErrors(['token' => 'required']);
    }

    public function test_empty_token_field_on_edit_keeps_saved_token(): void
    {
        $bot = Bot::factory()->create(['token' => '111:keep-me']);

        Livewire::test(EditBot::class, ['record' => $bot->getRouteKey()])
            ->assertSchemaStateSet(['token' => null])
            ->fillForm(['name' => 'Переименован'])
            ->call('save')
            ->assertHasNoFormErrors();

        $bot->refresh();
        $this->assertSame('Переименован', $bot->name);
        $this->assertSame('111:keep-me', $bot->token);
    }

    public function test_bots_page_does_not_leak_tokens(): void
    {
        Bot::factory()->create(['token' => '999:very-secret']);

        $this->get('/admin/bots')
            ->assertOk()
            ->assertDontSee('very-secret');
    }

    public function test_check_action_fills_username_from_telegram(): void
    {
        $bot = Bot::factory()->create(['username' => null]);
        $this->fakeBot(bot: $bot)->willReceivePartial(['username' => 'real_bot', 'is_bot' => true, 'id' => 1, 'first_name' => 'Real']);

        Livewire::test(ListBots::class)
            ->callAction(TestAction::make('check')->table($bot))
            ->assertNotified('Токен рабочий: @real_bot');

        $this->assertSame('real_bot', $bot->fresh()->username);
    }
}
