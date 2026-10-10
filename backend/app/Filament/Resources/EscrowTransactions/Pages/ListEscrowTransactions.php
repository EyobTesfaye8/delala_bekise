<?php

namespace App\Filament\Resources\EscrowTransactions\Pages;

use App\Filament\Resources\EscrowTransactions\EscrowTransactionResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListEscrowTransactions extends ListRecords
{
    protected static string $resource = EscrowTransactionResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
