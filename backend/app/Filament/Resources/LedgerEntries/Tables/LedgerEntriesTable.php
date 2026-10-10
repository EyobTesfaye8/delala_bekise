<?php

namespace App\Filament\Resources\LedgerEntries\Tables;

use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;

class LedgerEntriesTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('transaction_ref')
                    ->searchable(),
                TextColumn::make('account.name')
                    ->sortable()
                    ->searchable(),
                TextColumn::make('escrowTransaction.uuid')
                    ->label('Escrow')
                    ->sortable()
                    ->searchable()
                    ->limit(8),
                TextColumn::make('direction')
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        'debit' => 'danger',
                        'credit' => 'success',
                        default => 'gray',
                    }),
                TextColumn::make('amount_santim')
                    ->label('Amount (ETB)')
                    ->formatStateUsing(fn ($state) => number_format($state / 100, 2))
                    ->sortable(),
                TextColumn::make('description')
                    ->searchable()
                    ->limit(20),
                TextColumn::make('created_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: false),
            ])
            ->filters([
                SelectFilter::make('direction')
                    ->options([
                        'debit' => 'Debit',
                        'credit' => 'Credit',
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
