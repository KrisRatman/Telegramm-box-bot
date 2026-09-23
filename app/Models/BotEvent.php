<?php

namespace App\Models;

use App\Enums\BotEventType;
use App\Enums\OrderSource;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Событие воронки. Запись только добавляется, поэтому без updated_at.
 */
class BotEvent extends Model
{
    public const UPDATED_AT = null;

    protected $fillable = [
        'telegram_user_id',
        'type',
        'source',
        'created_at',
    ];

    protected function casts(): array
    {
        return [
            'type' => BotEventType::class,
            'source' => OrderSource::class,
            'created_at' => 'datetime',
        ];
    }

    /** @return BelongsTo<TelegramUser, $this> */
    public function telegramUser(): BelongsTo
    {
        return $this->belongsTo(TelegramUser::class);
    }

    public static function record(TelegramUser $user, BotEventType $type, OrderSource $source): self
    {
        return static::create([
            'telegram_user_id' => $user->id,
            'type' => $type,
            'source' => $source,
        ]);
    }
}
