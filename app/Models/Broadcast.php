<?php

namespace App\Models;

use App\Enums\BroadcastStatus;
use Database\Factories\BroadcastFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Broadcast extends Model
{
    /** @use HasFactory<BroadcastFactory> */
    use HasFactory;

    protected $fillable = [
        'title',
        'message',
        'status',
        'recipients_count',
        'sent_count',
        'failed_count',
        'created_by',
        'started_at',
        'finished_at',
    ];

    protected function casts(): array
    {
        return [
            'status' => BroadcastStatus::class,
            'recipients_count' => 'integer',
            'sent_count' => 'integer',
            'failed_count' => 'integer',
            'started_at' => 'datetime',
            'finished_at' => 'datetime',
        ];
    }

    /** @return BelongsTo<User, $this> */
    public function author(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /** @return HasMany<BotMessage, $this> */
    public function messages(): HasMany
    {
        return $this->hasMany(BotMessage::class);
    }

    public function isEditable(): bool
    {
        return $this->status === BroadcastStatus::Draft || $this->status === BroadcastStatus::Failed;
    }
}
