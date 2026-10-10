<?php

namespace App\Filament\Resources\EscrowTransactions\Pages;

use App\Filament\Resources\EscrowTransactions\EscrowTransactionResource;
use Filament\Resources\Pages\CreateRecord;

class CreateEscrowTransaction extends CreateRecord
{
    protected static string $resource = EscrowTransactionResource::class;
}
