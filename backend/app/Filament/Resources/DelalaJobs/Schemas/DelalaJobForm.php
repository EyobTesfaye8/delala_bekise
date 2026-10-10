<?php

namespace App\Filament\Resources\DelalaJobs\Schemas;

use Filament\Forms\Components\KeyValue;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Schema;

class DelalaJobForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Select::make('buyer_id')
                    ->relationship('buyer', 'full_name')
                    ->disabled(),
                Select::make('category_id')
                    ->relationship('category', 'name')
                    ->disabled(),
                TextInput::make('title')
                    ->disabled(),
                Textarea::make('description')
                    ->disabled()
                    ->columnSpanFull(),
                Select::make('status')
                    ->options([
                        'draft' => 'Draft',
                        'open' => 'Open',
                        'negotiating' => 'Negotiating',
                        'contracted' => 'Contracted',
                        'completed' => 'Completed',
                        'canceled' => 'Canceled',
                    ])
                    ->disabled(),
                TextInput::make('location')
                    ->disabled(),
                TextInput::make('budget_min')
                    ->disabled()
                    ->numeric(),
                TextInput::make('budget_max')
                    ->disabled()
                    ->numeric(),
                KeyValue::make('attributes')
                    ->disabled()
                    ->columnSpanFull(),
                TextInput::make('proposal_count')
                    ->disabled()
                    ->numeric(),
            ]);
    }
}
