<?php

namespace App\Filament\Resources\PayoutRequests\Schemas;

use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Schema;

class PayoutRequestForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Select::make('delala_id')
                    ->relationship('delala', 'full_name')
                    ->disabled(),
                TextInput::make('amount_santim')
                    ->label('Amount (Santim)')
                    ->disabled()
                    ->numeric(),
                Select::make('status')
                    ->options([
                        'pending' => 'Pending',
                        'paid' => 'Paid',
                        'cancelled' => 'Cancelled',
                    ])
                    ->required(),
                TextInput::make('bank_name')
                    ->disabled(),
                TextInput::make('account_number')
                    ->disabled(),
                TextInput::make('telebirr_number')
                    ->disabled(),
                TextInput::make('payment_method')
                    ->disabled(),
                DateTimePicker::make('paid_at')
                    ->disabled(),
                Textarea::make('admin_note')
                    ->columnSpanFull(),
            ]);
    }
}
