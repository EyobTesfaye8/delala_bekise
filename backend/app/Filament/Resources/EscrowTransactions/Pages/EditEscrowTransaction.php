<?php

namespace App\Filament\Resources\EscrowTransactions\Pages;

use App\Filament\Resources\EscrowTransactions\EscrowTransactionResource;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

class EditEscrowTransaction extends EditRecord
{
    protected static string $resource = EscrowTransactionResource::class;

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make(),
        ];
    }
}
