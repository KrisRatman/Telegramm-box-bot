<?php

namespace App\Models;

use Database\Factories\TelegramUserFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class TelegramUser extends Model
{
    /** @use HasFactory<TelegramUserFactory> */
    use HasFactory;

    protected $fillable = [
        'chat_id',
        'username',
        'first_name',
        'last_name',
        'phone',
        'language_code',
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

    /** @return HasMany<Order, $this> */
    public function orders(): HasMany
    {
        return $this->hasMany(Order::class);
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
