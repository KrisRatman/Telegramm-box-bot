<?php

namespace App\Enums;

use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasIcon;
use Filament\Support\Contracts\HasLabel;

enum OrderStatus: string implements HasColor, HasIcon, HasLabel
{
    case New = 'new';
    case Confirmed = 'confirmed';
    case InProgress = 'in_progress';
    case Completed = 'completed';
    case Cancelled = 'cancelled';

    public function getLabel(): string
    {
        return match ($this) {
            self::New => 'Новая',
            self::Confirmed => 'Подтверждена',
            self::InProgress => 'В работе',
            self::Completed => 'Выполнена',
            self::Cancelled => 'Отменена',
        };
    }

    public function getColor(): string
    {
        return match ($this) {
            self::New => 'warning',
            self::Confirmed => 'info',
            self::InProgress => 'primary',
            self::Completed => 'success',
            self::Cancelled => 'danger',
        };
    }

    public function getIcon(): string
    {
        return match ($this) {
            self::New => 'heroicon-o-sparkles',
            self::Confirmed => 'heroicon-o-check-circle',
            self::InProgress => 'heroicon-o-play-circle',
            self::Completed => 'heroicon-o-flag',
            self::Cancelled => 'heroicon-o-x-circle',
        };
    }

    /**
     * Текст, который уходит клиенту в бот при смене статуса.
     */
    public function notificationText(): string
    {
        return match ($this) {
            self::New => 'Заявка создана и ждёт обработки.',
            self::Confirmed => 'Мы подтвердили вашу заявку и скоро свяжемся с вами.',
            self::InProgress => 'Мы взяли вашу заявку в работу.',
            self::Completed => 'Заявка выполнена. Спасибо, что выбрали нас!',
            self::Cancelled => 'Заявка отменена. Если это ошибка — напишите нам.',
        };
    }
}
