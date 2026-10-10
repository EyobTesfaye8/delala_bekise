<?php

namespace App\Filament\Resources\DelalaJobs\Pages;

use App\Filament\Resources\DelalaJobs\DelalaJobResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListDelalaJobs extends ListRecords
{
    protected static string $resource = DelalaJobResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
