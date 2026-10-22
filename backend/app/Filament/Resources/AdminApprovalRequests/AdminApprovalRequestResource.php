<?php

namespace App\Filament\Resources\AdminApprovalRequests;

use App\Filament\Resources\AdminApprovalRequests\Pages\CreateAdminApprovalRequest;
use App\Filament\Resources\AdminApprovalRequests\Pages\EditAdminApprovalRequest;
use App\Filament\Resources\AdminApprovalRequests\Pages\ListAdminApprovalRequests;
use App\Filament\Resources\AdminApprovalRequests\Schemas\AdminApprovalRequestForm;
use App\Filament\Resources\AdminApprovalRequests\Tables\AdminApprovalRequestsTable;
use App\Models\AdminApprovalRequest;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Model;

class AdminApprovalRequestResource extends Resource
{
    protected static ?string $model = AdminApprovalRequest::class;

    protected static ?string $navigationLabel = 'Maker-Checker Approvals';

    protected static ?string $modelLabel = 'Maker-Checker Approval';

    protected static ?string $pluralModelLabel = 'Maker-Checker Approvals';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedShieldCheck;

    protected static ?int $navigationSort = 4;

    public static function form(Schema $schema): Schema
    {
        return AdminApprovalRequestForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return AdminApprovalRequestsTable::configure($table);
    }

    public static function canCreate(): bool
    {
        return false;
    }

    public static function canDelete(Model $record): bool
    {
        return false;
    }

    public static function canDeleteAny(): bool
    {
        return false;
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
            'index' => ListAdminApprovalRequests::route('/'),
            'create' => CreateAdminApprovalRequest::route('/create'),
            'edit' => EditAdminApprovalRequest::route('/{record}/edit'),
        ];
    }
}
