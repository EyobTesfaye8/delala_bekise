<?php

namespace App\Filament\Resources\EscrowTransactions\Tables;

use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;

class EscrowTransactionsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('uuid')
                    ->label('UUID')
                    ->searchable()
                    ->limit(10),
                TextColumn::make('job.title')
                    ->sortable()
                    ->searchable(),
                TextColumn::make('buyer.full_name')
                    ->sortable()
                    ->searchable(),
                TextColumn::make('delala.full_name')
                    ->sortable()
                    ->searchable(),
                TextColumn::make('status')
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        'pending_payment' => 'gray',
                        'funded' => 'primary',
                        'cancellation_requested' => 'warning',
                        'handover_declared' => 'info',
                        'disputed' => 'danger',
                        'released' => 'success',
                        'force_released' => 'success',
                        'force_refunded' => 'danger',
                        'refund_failed' => 'danger',
                        default => 'gray',
                    }),
                TextColumn::make('commission_amount_santim')
                    ->label('Amount (ETB)')
                    ->formatStateUsing(fn ($state) => number_format($state / 100, 2))
                    ->sortable(),
                TextColumn::make('chapa_paid_at')
                    ->dateTime()
                    ->sortable(),
            ])
            ->filters([
                SelectFilter::make('status')
                    ->options([
                        'pending_payment' => 'Pending Payment',
                        'funded' => 'Funded',
                        'disputed' => 'Disputed',
                        'released' => 'Released',
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
