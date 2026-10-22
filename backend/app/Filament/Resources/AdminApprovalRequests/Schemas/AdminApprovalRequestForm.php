<?php

namespace App\Filament\Resources\AdminApprovalRequests\Schemas;

use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Schema;

class AdminApprovalRequestForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('action_type')
                    ->label('Action Type')
                    ->disabled(),
                TextInput::make('amount_santim')
                    ->label('Amount (Santim)')
                    ->numeric()
                    ->helperText(fn ($state) => $state ? number_format($state / 100, 2).' ETB' : null)
                    ->disabled(),
                Select::make('status')
                    ->options([
                        'PENDING' => 'Pending',
                        'APPROVED' => 'Approved',
                        'REJECTED' => 'Rejected',
                    ])
                    ->disabled(),
                Select::make('requested_by_id')
                    ->relationship('requestedBy', 'full_name')
                    ->label('Requested By (Maker)')
                    ->disabled(),
                Select::make('approved_by_id')
                    ->relationship('approvedBy', 'full_name')
                    ->label('Approved/Reviewed By (Checker)')
                    ->disabled(),
                DateTimePicker::make('approved_at')
                    ->label('Approved/Reviewed At')
                    ->disabled(),
                Textarea::make('justification_note')
                    ->label('Justification Note')
                    ->columnSpanFull()
                    ->disabled(),
                Textarea::make('rejection_reason')
                    ->label('Rejection Reason')
                    ->columnSpanFull()
                    ->disabled(),
            ]);
    }
}
