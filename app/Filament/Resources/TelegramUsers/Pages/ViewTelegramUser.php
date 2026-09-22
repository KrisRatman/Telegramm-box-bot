<?php

namespace App\Filament\Resources\TelegramUsers\Pages;

use App\Filament\Actions\ReplyToTelegramUserAction;
use App\Filament\Resources\TelegramUsers\TelegramUserResource;
use Filament\Resources\Pages\ViewRecord;

class ViewTelegramUser extends ViewRecord
{
    protected static string $resource = TelegramUserResource::class;

    public function getTitle(): string
    {
        return $this->getRecord()->full_name;
    }

    protected function getHeaderActions(): array
    {
        return [
            ReplyToTelegramUserAction::make(),
        ];
    }
}
