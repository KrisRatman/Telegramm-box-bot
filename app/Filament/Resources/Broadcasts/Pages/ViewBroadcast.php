<?php

namespace App\Filament\Resources\Broadcasts\Pages;

use App\Filament\Actions\SendBroadcastAction;
use App\Filament\Resources\Broadcasts\BroadcastResource;
use App\Models\Broadcast;
use Filament\Actions\EditAction;
use Filament\Resources\Pages\ViewRecord;

class ViewBroadcast extends ViewRecord
{
    protected static string $resource = BroadcastResource::class;

    /**
     * Пока рассылка идёт, страница обновляет счётчики сама.
     */
    protected ?string $pollingInterval = '5s';

    protected function getHeaderActions(): array
    {
        return [
            SendBroadcastAction::make(),
            EditAction::make()
                ->label('Изменить')
                ->visible(fn (Broadcast $record) => $record->isEditable()),
        ];
    }
}
