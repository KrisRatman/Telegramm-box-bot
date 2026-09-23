<?php

namespace App\Enums;

use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasLabel;

/**
 * Канал, через который пришла заявка или событие воронки.
 */
enum OrderSource: string implements HasColor, HasLabel
{
    case Bot = 'bot';
    case MiniApp = 'mini_app';

    public function getLabel(): string
    {
        return match ($this) {
            self::Bot => 'Бот',
            self::MiniApp => 'Mini App',
        };
    }

    public function getColor(): string
    {
        return match ($this) {
            self::Bot => 'gray',
            self::MiniApp => 'info',
        };
    }
}
