<?php

namespace App\Filament\Resources\EscrowTransactions\Schemas;

use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Schema;

class EscrowTransactionForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('uuid')
                    ->label('Escrow UUID')
                    ->disabled(),
                Select::make('job_id')
                    ->relationship('job', 'title')
                    ->disabled(),
                Select::make('buyer_id')
                    ->relationship('buyer', 'full_name')
                    ->disabled(),
                Select::make('delala_id')
                    ->relationship('delala', 'full_name')
                    ->disabled(),
                TextInput::make('commission_amount_santim')
                    ->label('Total (Santim)')
                    ->disabled()
                    ->numeric(),
                TextInput::make('platform_fee_santim')
                    ->label('Platform Fee (Santim)')
                    ->disabled()
                    ->numeric(),
                TextInput::make('delala_amount_santim')
                    ->label('Delala Payout (Santim)')
                    ->disabled()
                    ->numeric(),
                Select::make('status')
                    ->options([
                        'pending_payment' => 'Pending Payment',
                        'funded' => 'Funded',
                        'cancellation_requested' => 'Cancellation Requested',
                        'handover_declared' => 'Handover Declared',
                        'disputed' => 'Disputed',
                        'released' => 'Released',
                        'force_released' => 'Force Released (Admin)',
                        'force_refunded' => 'Force Refunded (Admin)',
                        'refund_failed' => 'Refund Failed',
                    ])
                    ->required(),
                TextInput::make('chapa_tx_ref')
                    ->disabled()
                    ->maxLength(255),
                DateTimePicker::make('chapa_paid_at')
                    ->disabled(),
                Textarea::make('admin_resolution_note')
                    ->columnSpanFull(),
            ]);
    }
}
