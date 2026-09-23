<?php

namespace App\Filament\Resources\Orders\Pages;

use App\Filament\Actions\ChangeOrderStatusAction;
use App\Filament\Actions\ReplyToTelegramUserAction;
use App\Filament\Actions\SendInvoiceAction;
use App\Filament\Resources\Orders\OrderResource;
use Filament\Actions\EditAction;
use Filament\Resources\Pages\ViewRecord;

class ViewOrder extends ViewRecord
{
    protected static string $resource = OrderResource::class;

    protected function getHeaderActions(): array
    {
        return [
            ChangeOrderStatusAction::make(),
            ReplyToTelegramUserAction::make(),
            SendInvoiceAction::make(),
            EditAction::make()->label('Изменить'),
        ];
    }
}
