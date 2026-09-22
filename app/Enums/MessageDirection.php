<?php

namespace App\Enums;

use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasLabel;

enum MessageDirection: string implements HasColor, HasLabel
{
    case In = 'in';
    case Out = 'out';

    public function getLabel(): string
    {
        return match ($this) {
            self::In => 'От пользователя',
            self::Out => 'Пользователю',
        };
    }

    public function getColor(): string
    {
        return match ($this) {
            self::In => 'gray',
            self::Out => 'success',
        };
    }
}
