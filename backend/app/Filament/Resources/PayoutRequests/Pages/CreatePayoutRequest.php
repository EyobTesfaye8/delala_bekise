<?php

namespace App\Filament\Resources\PayoutRequests\Pages;

use App\Filament\Resources\PayoutRequests\PayoutRequestResource;
use Filament\Resources\Pages\CreateRecord;

class CreatePayoutRequest extends CreateRecord
{
    protected static string $resource = PayoutRequestResource::class;
}
