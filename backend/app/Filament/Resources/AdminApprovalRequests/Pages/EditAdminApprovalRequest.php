<?php

namespace App\Filament\Resources\AdminApprovalRequests\Pages;

use App\Filament\Resources\AdminApprovalRequests\AdminApprovalRequestResource;
use Filament\Resources\Pages\EditRecord;

class EditAdminApprovalRequest extends EditRecord
{
    protected static string $resource = AdminApprovalRequestResource::class;

    protected function getHeaderActions(): array
    {
        return [
            // Maker-Checker approval requests are immutable regulatory audit records and cannot be deleted.
        ];
    }
}
