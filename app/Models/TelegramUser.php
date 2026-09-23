<?php

namespace App\Models;

use Database\Factories\TelegramUserFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class TelegramUser extends Model
{
    /** @use HasFactory<TelegramUserFactory> */
    use HasFactory;

    protected $fillable = [
        'bot_id',
        'chat_id',
        'username',
        'first_name',
        'last_name',
        'phone',
        'language_code',
        'locale',
        'is_blocked',
        'last_activity_at',
    ];

    protected function casts(): array
    {
        return [
            'chat_id' => 'integer',
            'is_blocked' => 'boolean',
            'last_activity_at' => 'datetime',
        ];
    }

    /** @return BelongsTo<Bot, $this> */
    public function bot(): BelongsTo
    {
        return $this->belongsTo(Bot::class);
    }

    /** @return HasMany<Order, $this> */
    public function orders(): HasMany
    {
        return $this->hasMany(Order::class);
    }

    /** @return HasMany<BotEvent, $this> */
    public function events(): HasMany
    {
        return $this->hasMany(BotEvent::class);
    }

    /** @return HasMany<BotMessage, $this> */
    public function messages(): HasMany
    {
        return $this->hasMany(BotMessage::class);
    }

    /**
     * Получатели рассылок: все, кто не заблокировал бота.
     *
     * @param  Builder<TelegramUser>  $query
     */
    public function scopeSubscribed(Builder $query): void
    {
        $query->where('is_blocked', false);
    }

    /**
     * Язык ответов: выбор через /language, иначе язык Telegram,
     * если он поддерживается, иначе язык бота по умолчанию.
     */
    public function preferredLocale(): string
    {
        $supported = config('telegram.locales', ['ru']);

        foreach ([$this->locale, $this->language_code] as $candidate) {
            $candidate = strtolower(substr((string) $candidate, 0, 2));

            if (in_array($candidate, $supported, true)) {
                return $candidate;
            }
        }

        return $this->bot?->default_locale ?? $supported[0];
    }

    public function getFullNameAttribute(): string
    {
        $name = trim(($this->first_name ?? '').' '.($this->last_name ?? ''));

        return $name !== '' ? $name : ($this->username ?? 'ID '.$this->chat_id);
    }

    public function getTelegramLinkAttribute(): ?string
    {
        return $this->username ? 'https://t.me/'.$this->username : null;
    }
}
