<?php

namespace App\Filament\Resources\DelalaJobs\Tables;

use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;

class DelalaJobsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('title')
                    ->searchable(),
                TextColumn::make('buyer.full_name')
                    ->sortable()
                    ->searchable(),
                TextColumn::make('category.name')
                    ->sortable()
                    ->searchable(),
                TextColumn::make('status')
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        'draft' => 'gray',
                        'open' => 'success',
                        'negotiating' => 'warning',
                        'contracted' => 'primary',
                        'completed' => 'success',
                        'canceled' => 'danger',
                        default => 'gray',
                    }),
                TextColumn::make('budget_min')
                    ->numeric()
                    ->sortable(),
                TextColumn::make('budget_max')
                    ->numeric()
                    ->sortable(),
                TextColumn::make('proposal_count')
                    ->numeric()
                    ->sortable(),
                TextColumn::make('created_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                SelectFilter::make('status')
                    ->options([
                        'draft' => 'Draft',
                        'open' => 'Open',
                        'negotiating' => 'Negotiating',
                        'contracted' => 'Contracted',
                        'completed' => 'Completed',
                        'canceled' => 'Canceled',
                    ]),
            ])
            ->recordActions([
                EditAction::make(),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ]);
    }
}
