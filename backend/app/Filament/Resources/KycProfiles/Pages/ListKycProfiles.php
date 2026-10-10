<?php

namespace App\Filament\Resources\KycProfiles\Pages;

use App\Filament\Resources\KycProfiles\KycProfileResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListKycProfiles extends ListRecords
{
    protected static string $resource = KycProfileResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
