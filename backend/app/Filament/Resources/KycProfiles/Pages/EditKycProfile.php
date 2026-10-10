<?php

namespace App\Filament\Resources\KycProfiles\Pages;

use App\Filament\Resources\KycProfiles\KycProfileResource;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

class EditKycProfile extends EditRecord
{
    protected static string $resource = KycProfileResource::class;

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make(),
        ];
    }
}
