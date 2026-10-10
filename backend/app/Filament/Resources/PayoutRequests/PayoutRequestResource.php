<?php

namespace App\Filament\Resources\PayoutRequests;

use App\Filament\Resources\PayoutRequests\Pages\CreatePayoutRequest;
use App\Filament\Resources\PayoutRequests\Pages\EditPayoutRequest;
use App\Filament\Resources\PayoutRequests\Pages\ListPayoutRequests;
use App\Filament\Resources\PayoutRequests\Schemas\PayoutRequestForm;
use App\Filament\Resources\PayoutRequests\Tables\PayoutRequestsTable;
use App\Models\PayoutRequest;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;

class PayoutRequestResource extends Resource
{
    protected static ?string $model = PayoutRequest::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedRectangleStack;

    public static function form(Schema $schema): Schema
    {
        return PayoutRequestForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return PayoutRequestsTable::configure($table);
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
            'index' => ListPayoutRequests::route('/'),
            'create' => CreatePayoutRequest::route('/create'),
            'edit' => EditPayoutRequest::route('/{record}/edit'),
        ];
    }
}
