<?php

namespace App\Filament\Resources\JobSources\Schemas;

use App\Models\JobSource;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Html;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Illuminate\Support\HtmlString;
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
                            ->default(false)
                            ->helperText('Turn on only after field extraction is configured. Inactive sources are skipped by the scraper.'),
                    ])
                    ->columns(2),
                Section::make('Field extraction')
                    ->description('Map job title, URL, and other fields using the visual configurator.')
                    ->visibleOn('create')
                    ->schema([
                        Html::make(new HtmlString(
                            '<p class="text-sm text-gray-600 dark:text-gray-300">'
                            .'Save this source first — you will be taken to the <strong>visual configurator</strong> '
                            .'to load a page preview and click-map fields.</p>'
                            .'<p class="mt-2 text-sm text-gray-500 dark:text-gray-400">'
                            .'Keep <strong>Active</strong> off until extraction is set up.</p>'
                        )),
                    ]),
                Section::make('Field extraction')
                    ->description('Load a preview and click-map fields from the careers page.')
                    ->visibleOn('edit')
                    ->schema([
                        Html::make(function (?JobSource $record): HtmlString {
                            if (! $record) {
                                return new HtmlString('');
                            }

                            $url = route('job-sources.configure', $record);

                            return new HtmlString(
                                '<p class="text-sm text-gray-600 dark:text-gray-300">'
                                .'Open the <a href="'.e($url).'" class="font-medium text-primary-600 underline dark:text-primary-400">visual configurator</a> '
                                .'to load a preview, select list items, and map fields.</p>'
                            );
                        }),
                    ]),
                Section::make('Advanced extraction config (JSON)')
                    ->description('Power-user override. Prefer the visual configurator for normal setup.')
                    ->collapsed()
                    ->visibleOn('edit')
                    ->schema([
                        Textarea::make('extraction_config')
                            ->label('Configuration (JSON)')
                            ->rows(16)
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
                            ->helperText('Required for active sources: listing.item_selector'),
                    ]),
            ]);
    }
}
