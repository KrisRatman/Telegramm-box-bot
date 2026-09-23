<?php

namespace App\Models;

use Database\Factories\BotFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

/**
 * Telegram-бот в системе. Ботов может быть несколько: у каждого свои
 * пользователи, заявки и рассылки, каталог общий — с выбором услуг.
 */
class Bot extends Model
{
    /** @use HasFactory<BotFactory> */
    use HasFactory;

    protected $fillable = [
        'name',
        'username',
        'token',
        'payment_provider_token',
        'default_locale',
        'is_active',
    ];

    /** Токены не должны утечь ни в JSON, ни в логи Livewire. */
    protected $hidden = [
        'token',
        'webhook_secret',
        'payment_provider_token',
    ];

    protected function casts(): array
    {
        return [
            'token' => 'encrypted',
            'webhook_secret' => 'encrypted',
            'payment_provider_token' => 'encrypted',
            'is_active' => 'boolean',
        ];
    }

    protected static function booted(): void
    {
        static::creating(function (Bot $bot) {
            $bot->webhook_secret ??= Str::random(48);
        });

        // Новый бот сразу продаёт весь активный каталог — лишнее снимают в карточке услуги.
        static::created(function (Bot $bot) {
            $bot->services()->syncWithoutDetaching(Service::query()->active()->pluck('id'));
        });
    }

    /** @return HasMany<TelegramUser, $this> */
    public function telegramUsers(): HasMany
    {
        return $this->hasMany(TelegramUser::class);
    }

    /** @return HasMany<Order, $this> */
    public function orders(): HasMany
    {
        return $this->hasMany(Order::class);
    }

    /** @return BelongsToMany<Service, $this> */
    public function services(): BelongsToMany
    {
        return $this->belongsToMany(Service::class);
    }

    /** @param  Builder<Bot>  $query */
    public function scopeActive(Builder $query): void
    {
        $query->where('is_active', true)->whereNotNull('token');
    }

    public function paymentsEnabled(): bool
    {
        return filled($this->payment_provider_token);
    }

    /**
     * Адрес Mini App этого бота или null, если https-адрес сайта не задан:
     * Telegram открывает Mini App только по https.
     */
    public function miniAppUrl(): ?string
    {
        $base = rtrim((string) config('telegram.mini_app.url'), '/');

        return str_starts_with($base, 'https://') ? "{$base}/app/{$this->id}" : null;
    }

    public function webhookUrl(): string
    {
        return rtrim((string) config('app.url'), '/')."/telegram/webhook/{$this->id}";
    }

    public function getTelegramLinkAttribute(): ?string
    {
        return $this->username ? 'https://t.me/'.$this->username : null;
    }
}
