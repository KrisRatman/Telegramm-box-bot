<?php

namespace App\Models;

use App\Enums\PaymentStatus;
use Database\Factories\PaymentFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

class Payment extends Model
{
    /** @use HasFactory<PaymentFactory> */
    use HasFactory;

    protected $fillable = [
        'order_id',
        'status',
        'amount',
        'currency',
        'invoice_payload',
        'telegram_payment_charge_id',
        'provider_payment_charge_id',
        'paid_at',
        'refunded_at',
    ];

    protected function casts(): array
    {
        return [
            'status' => PaymentStatus::class,
            'amount' => 'decimal:2',
            'paid_at' => 'datetime',
            'refunded_at' => 'datetime',
        ];
    }

    protected static function booted(): void
    {
        static::creating(function (Payment $payment) {
            // Непредсказуемый payload: по нему нельзя подобрать чужой платёж.
            $payment->invoice_payload ??= 'pay_'.Str::lower((string) Str::ulid());
        });
    }

    /** @return BelongsTo<Order, $this> */
    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    /**
     * Сумма в минимальных единицах валюты (копейках) — так её ждёт Bot API.
     */
    public function amountInMinorUnits(): int
    {
        return self::toMinorUnits($this->amount);
    }

    public static function toMinorUnits(string|float|int $amount): int
    {
        return (int) round((float) $amount * 100);
    }

    public function getFormattedAmountAttribute(): string
    {
        return number_format((float) $this->amount, 0, ',', ' ').' ₽';
    }
}
