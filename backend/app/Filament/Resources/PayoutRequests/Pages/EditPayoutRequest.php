<?php

namespace App\Filament\Resources\PayoutRequests\Pages;

use App\Filament\Resources\PayoutRequests\PayoutRequestResource;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

class EditPayoutRequest extends EditRecord
{
    protected static string $resource = PayoutRequestResource::class;

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make(),
        ];
    }
}
