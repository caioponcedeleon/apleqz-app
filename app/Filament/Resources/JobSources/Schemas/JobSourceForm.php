<?php

namespace App\Filament\Resources\JobSources\Schemas;

use App\Models\JobSource;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Illuminate\Validation\ValidationException;

class JobSourceForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Source')
                    ->schema([
                        TextInput::make('name')
                            ->required()
                            ->maxLength(255),
                        TextInput::make('url')
                            ->label('Listing page URL')
                            ->url()
                            ->required()
                            ->maxLength(2048)
                            ->columnSpanFull(),
                        TextInput::make('company_name')
                            ->label('Default company name')
                            ->maxLength(255)
                            ->helperText('Used when the scraper does not extract a company from the page.'),
                        Toggle::make('is_active')
                            ->label('Active')
                            ->default(true)
                            ->helperText('Inactive sources are skipped by the scraper.'),
                    ])
                    ->columns(2),
                Section::make('Extraction config')
                    ->description('JSON rules for listing extraction. A visual configurator will replace this textarea in a later phase.')
                    ->schema([
                        Textarea::make('extraction_config')
                            ->label('Configuration (JSON)')
                            ->rows(20)
                            ->columnSpanFull()
                            ->formatStateUsing(function (?array $state): string {
                                $config = $state ?? JobSource::defaultExtractionConfig();

                                return json_encode($config, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR);
                            })
                            ->dehydrateStateUsing(function (?string $state): array {
                                if (blank($state)) {
                                    return JobSource::defaultExtractionConfig();
                                }

                                $decoded = json_decode($state, true);

                                if (json_last_error() !== JSON_ERROR_NONE || ! is_array($decoded)) {
                                    throw ValidationException::withMessages([
                                        'extraction_config' => 'The extraction config must be valid JSON.',
                                    ]);
                                }

                                return $decoded;
                            })
                            ->required()
                            ->helperText('Required for active sources: listing.item_selector'),
                    ]),
            ]);
    }
}
