<?php

namespace App\Models;

use App\Enums\OrderSource;
use App\Enums\OrderStatus;
use App\Observers\OrderObserver;
use Database\Factories\OrderFactory;
use Illuminate\Database\Eloquent\Attributes\ObservedBy;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[ObservedBy(OrderObserver::class)]
class Order extends Model
{
    /** @use HasFactory<OrderFactory> */
    use HasFactory;

    protected $fillable = [
        'number',
        'bot_id',
        'telegram_user_id',
        'service_id',
        'service_name',
        'price',
        'status',
        'source',
        'contact_name',
        'contact_phone',
        'comment',
        'admin_note',
        'completed_at',
        'paid_at',
    ];

    /** Совпадает с default в миграции: так source есть и у ещё не сохранённой заявки. */
    protected $attributes = [
        'source' => 'bot',
    ];

    protected function casts(): array
    {
        return [
            'status' => OrderStatus::class,
            'source' => OrderSource::class,
            'price' => 'decimal:2',
            'completed_at' => 'datetime',
            'paid_at' => 'datetime',
        ];
    }

    protected static function booted(): void
    {
        static::creating(function (Order $order) {
            $order->number ??= static::generateNumber();
            // Бот заявки — бот клиента. Хранится в заявке, чтобы фильтр
            // и аналитика по боту не ходили через join с пользователями.
            $order->bot_id ??= $order->telegramUser?->bot_id;
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

    /** @return BelongsTo<Bot, $this> */
    public function bot(): BelongsTo
    {
        return $this->belongsTo(Bot::class);
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

    /** @return HasMany<OrderItem, $this> */
    public function items(): HasMany
    {
        return $this->hasMany(OrderItem::class);
    }

    /** @return HasMany<Payment, $this> */
    public function payments(): HasMany
    {
        return $this->hasMany(Payment::class);
    }

    public function isPaid(): bool
    {
        return $this->paid_at !== null;
    }

    /**
     * Счёт выставляем только на неоплаченную заявку с ценой. Выполненную
     * тоже можно оплатить — бывает, что клиент платит после работы.
     */
    public function canBePaid(): bool
    {
        return ! $this->isPaid()
            && (float) $this->price > 0
            && $this->status !== OrderStatus::Cancelled;
    }

    public function getFormattedPriceAttribute(): string
    {
        return number_format((float) $this->price, 0, ',', ' ').' ₽';
    }
}
