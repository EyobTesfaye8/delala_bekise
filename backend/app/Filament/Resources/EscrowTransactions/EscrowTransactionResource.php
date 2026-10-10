<?php

namespace App\Filament\Resources\EscrowTransactions;

use App\Filament\Resources\EscrowTransactions\Pages\CreateEscrowTransaction;
use App\Filament\Resources\EscrowTransactions\Pages\EditEscrowTransaction;
use App\Filament\Resources\EscrowTransactions\Pages\ListEscrowTransactions;
use App\Filament\Resources\EscrowTransactions\Schemas\EscrowTransactionForm;
use App\Filament\Resources\EscrowTransactions\Tables\EscrowTransactionsTable;
use App\Models\EscrowTransaction;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;

class EscrowTransactionResource extends Resource
{
    protected static ?string $model = EscrowTransaction::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedRectangleStack;

    public static function form(Schema $schema): Schema
    {
        return EscrowTransactionForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return EscrowTransactionsTable::configure($table);
    }

    public static function getRelations(): array
    {
        return [
            //
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListEscrowTransactions::route('/'),
            'create' => CreateEscrowTransaction::route('/create'),
            'edit' => EditEscrowTransaction::route('/{record}/edit'),
        ];
    }
}
