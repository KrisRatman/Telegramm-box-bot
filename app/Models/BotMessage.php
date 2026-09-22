<?php

namespace App\Models;

use App\Enums\MessageDirection;
use Database\Factories\BotMessageFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class BotMessage extends Model
{
    /** @use HasFactory<BotMessageFactory> */
    use HasFactory;

    protected $fillable = [
        'telegram_user_id',
        'direction',
        'text',
        'telegram_message_id',
        'user_id',
        'broadcast_id',
    ];

    protected function casts(): array
    {
        return [
            'direction' => MessageDirection::class,
            'telegram_message_id' => 'integer',
        ];
    }

    /** @return BelongsTo<TelegramUser, $this> */
    public function telegramUser(): BelongsTo
    {
        return $this->belongsTo(TelegramUser::class);
    }

    /** @return BelongsTo<User, $this> */
    public function author(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    /** @return BelongsTo<Broadcast, $this> */
    public function broadcast(): BelongsTo
    {
        return $this->belongsTo(Broadcast::class);
    }
}
