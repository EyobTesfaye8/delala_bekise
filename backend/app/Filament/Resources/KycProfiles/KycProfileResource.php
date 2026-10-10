<?php

namespace App\Filament\Resources\KycProfiles;

use App\Filament\Resources\KycProfiles\Pages\CreateKycProfile;
use App\Filament\Resources\KycProfiles\Pages\EditKycProfile;
use App\Filament\Resources\KycProfiles\Pages\ListKycProfiles;
use App\Filament\Resources\KycProfiles\Schemas\KycProfileForm;
use App\Filament\Resources\KycProfiles\Tables\KycProfilesTable;
use App\Models\KycProfile;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;

class KycProfileResource extends Resource
{
    protected static ?string $model = KycProfile::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedRectangleStack;

    public static function form(Schema $schema): Schema
    {
        return KycProfileForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return KycProfilesTable::configure($table);
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
            'index' => ListKycProfiles::route('/'),
            'create' => CreateKycProfile::route('/create'),
            'edit' => EditKycProfile::route('/{record}/edit'),
        ];
    }
}
