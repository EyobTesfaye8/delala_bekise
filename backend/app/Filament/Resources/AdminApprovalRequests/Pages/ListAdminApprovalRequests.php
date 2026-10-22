<?php

namespace App\Filament\Resources\AdminApprovalRequests\Pages;

use App\Filament\Resources\AdminApprovalRequests\AdminApprovalRequestResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListAdminApprovalRequests extends ListRecords
{
    protected static string $resource = AdminApprovalRequestResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
