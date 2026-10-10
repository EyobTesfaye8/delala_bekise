<?php

namespace App\Filament\Resources\KycProfiles\Schemas;

use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Schema;

class KycProfileForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Select::make('user_id')
                    ->relationship('user', 'full_name')
                    ->required()
                    ->disabled(),
                Select::make('status')
                    ->options([
                        'pending' => 'Pending',
                        'under_review' => 'Under Review',
                        'approved' => 'Approved',
                        'rejected' => 'Rejected',
                    ])
                    ->required(),
                TextInput::make('full_legal_name')
                    ->required()
                    ->maxLength(255),
                DatePicker::make('date_of_birth')
                    ->required(),
                TextInput::make('id_type')
                    ->required()
                    ->maxLength(255),
                TextInput::make('id_number')
                    ->required()
                    ->maxLength(255),
                FileUpload::make('id_document_s3_key')
                    ->label('ID Document')
                    ->disk('s3')
                    ->directory('kyc-documents')
                    ->visibility('private')
                    ->required(),
                FileUpload::make('selfie_s3_key')
                    ->label('Selfie Verification')
                    ->disk('s3')
                    ->directory('kyc-selfies')
                    ->visibility('private')
                    ->required(),
                Textarea::make('admin_notes')
                    ->columnSpanFull(),
            ]);
    }
}
