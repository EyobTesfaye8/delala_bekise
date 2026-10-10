<?php

namespace App\Filament\Resources\DelalaJobs;

use App\Filament\Resources\DelalaJobs\Pages\CreateDelalaJob;
use App\Filament\Resources\DelalaJobs\Pages\EditDelalaJob;
use App\Filament\Resources\DelalaJobs\Pages\ListDelalaJobs;
use App\Filament\Resources\DelalaJobs\Schemas\DelalaJobForm;
use App\Filament\Resources\DelalaJobs\Tables\DelalaJobsTable;
use App\Models\DelalaJob;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;

class DelalaJobResource extends Resource
{
    protected static ?string $model = DelalaJob::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedRectangleStack;

    public static function form(Schema $schema): Schema
    {
        return DelalaJobForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return DelalaJobsTable::configure($table);
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
            'index' => ListDelalaJobs::route('/'),
            'create' => CreateDelalaJob::route('/create'),
            'edit' => EditDelalaJob::route('/{record}/edit'),
        ];
    }

    public static function getRecordRouteBindingEloquentQuery(): Builder
    {
        return parent::getRecordRouteBindingEloquentQuery()
            ->withoutGlobalScopes([
                SoftDeletingScope::class,
            ]);
    }
}
