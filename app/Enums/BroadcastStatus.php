<?php

namespace App\Enums;

use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasLabel;

enum BroadcastStatus: string implements HasColor, HasLabel
{
    case Draft = 'draft';
    case Queued = 'queued';
    case Sending = 'sending';
    case Sent = 'sent';
    case Failed = 'failed';

    public function getLabel(): string
    {
        return match ($this) {
            self::Draft => 'Черновик',
            self::Queued => 'В очереди',
            self::Sending => 'Отправляется',
            self::Sent => 'Отправлена',
            self::Failed => 'Ошибка',
        };
    }

    public function getColor(): string
    {
        return match ($this) {
            self::Draft => 'gray',
            self::Queued => 'info',
            self::Sending => 'warning',
            self::Sent => 'success',
            self::Failed => 'danger',
        };
    }
}
