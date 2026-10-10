<?php

namespace App\Filament\Resources\LedgerEntries\Schemas;

use Filament\Forms\Components\KeyValue;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Schema;

class LedgerEntryForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('transaction_ref')
                    ->disabled(),
                Select::make('ledger_account_id')
                    ->relationship('account', 'name')
                    ->disabled(),
                Select::make('escrow_transaction_id')
                    ->relationship('escrowTransaction', 'uuid')
                    ->disabled(),
                TextInput::make('direction')
                    ->disabled(),
                TextInput::make('amount_santim')
                    ->label('Amount (Santim)')
                    ->disabled()
                    ->numeric(),
                TextInput::make('description')
                    ->disabled(),
                KeyValue::make('metadata')
                    ->disabled()
                    ->columnSpanFull(),
            ]);
    }
}
