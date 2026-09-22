<?php

namespace App\Models;

use App\Enums\OrderStatus;
use App\Observers\OrderObserver;
use Database\Factories\OrderFactory;
use Illuminate\Database\Eloquent\Attributes\ObservedBy;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[ObservedBy(OrderObserver::class)]
class Order extends Model
{
    /** @use HasFactory<OrderFactory> */
    use HasFactory;

    protected $fillable = [
        'number',
        'telegram_user_id',
        'service_id',
        'service_name',
        'price',
        'status',
        'contact_name',
        'contact_phone',
        'comment',
        'admin_note',
        'completed_at',
    ];

    protected function casts(): array
    {
        return [
            'status' => OrderStatus::class,
            'price' => 'decimal:2',
            'completed_at' => 'datetime',
        ];
    }

    protected static function booted(): void
    {
        static::creating(function (Order $order) {
            $order->number ??= static::generateNumber();
        });
    }

    /**
     * Номер вида 260922-0007: дата создания + сквозной счётчик.
     * Счётчик берём от максимального id, а занятые номера пропускаем —
     * два webhook-запроса могут прийти одновременно и получить один и тот же id.
     */
    public static function generateNumber(): string
    {
        $sequence = (int) static::query()->max('id') + 1;
        $prefix = now()->format('ymd');

        do {
            $number = $prefix.'-'.str_pad((string) $sequence, 4, '0', STR_PAD_LEFT);
            $sequence++;
        } while (static::query()->where('number', $number)->exists());

        return $number;
    }

    /** @return BelongsTo<TelegramUser, $this> */
    public function telegramUser(): BelongsTo
    {
        return $this->belongsTo(TelegramUser::class);
    }

    /** @return BelongsTo<Service, $this> */
    public function service(): BelongsTo
    {
        return $this->belongsTo(Service::class);
    }

    public function getFormattedPriceAttribute(): string
    {
        return number_format((float) $this->price, 0, ',', ' ').' ₽';
    }
}
