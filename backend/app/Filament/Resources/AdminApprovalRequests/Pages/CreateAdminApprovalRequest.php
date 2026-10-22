<?php

namespace App\Filament\Resources\AdminApprovalRequests\Pages;

use App\Filament\Resources\AdminApprovalRequests\AdminApprovalRequestResource;
use Filament\Resources\Pages\CreateRecord;

class CreateAdminApprovalRequest extends CreateRecord
{
    protected static string $resource = AdminApprovalRequestResource::class;
}
