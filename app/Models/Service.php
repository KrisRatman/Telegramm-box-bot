<?php

namespace App\Models;

use App\Models\Concerns\HasTranslations;
use Database\Factories\ServiceFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Service extends Model
{
    /** @use HasFactory<ServiceFactory> */
    use HasFactory, HasTranslations;

    protected $fillable = [
        'service_category_id',
        'name',
        'slug',
        'description',
        'translations',
        'price',
        'duration_minutes',
        'sort_order',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'price' => 'decimal:2',
            'duration_minutes' => 'integer',
            'sort_order' => 'integer',
            'is_active' => 'boolean',
        ];
    }

    /** @return BelongsTo<ServiceCategory, $this> */
    public function category(): BelongsTo
    {
        return $this->belongsTo(ServiceCategory::class, 'service_category_id');
    }

    /** @return BelongsToMany<Bot, $this> */
    public function bots(): BelongsToMany
    {
        return $this->belongsToMany(Bot::class);
    }

    /** @return HasMany<Order, $this> */
    public function orders(): HasMany
    {
        return $this->hasMany(Order::class);
    }

    /** @param  Builder<Service>  $query */
    public function scopeActive(Builder $query): void
    {
        $query->where('is_active', true);
    }

    /**
     * Услуга видна клиенту бота: активна, лежит в активной категории
     * и отмечена для продажи в этом боте.
     *
     * @param  Builder<Service>  $query
     */
    public function scopeOrderable(Builder $query, Bot|int $bot): void
    {
        $query->active()
            ->whereHas('category', fn (Builder $category) => $category->where('is_active', true))
            ->whereHas('bots', fn (Builder $bots) => $bots->whereKey($bot instanceof Bot ? $bot->id : $bot));
    }

    public function getFormattedPriceAttribute(): string
    {
        return number_format((float) $this->price, 0, ',', ' ').' ₽';
    }
}
