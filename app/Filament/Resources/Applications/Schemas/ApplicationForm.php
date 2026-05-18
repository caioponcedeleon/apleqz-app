<?php

namespace App\Filament\Resources\Applications\Schemas;

use App\Enums\ApplicationStatus;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Schema;

class ApplicationForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Select::make('user_id')
                    ->relationship('user', 'email')
                    ->searchable()
                    ->required(),
                Select::make('area_id')
                    ->relationship('area', 'name')
                    ->searchable()
                    ->required(),
                TextInput::make('position')->required()->maxLength(255),
                TextInput::make('company')->required()->maxLength(255),
                TextInput::make('location')->maxLength(255),
                DatePicker::make('applied_at')
                    ->required(fn ($get) => ApplicationStatus::tryFrom($get('status') ?? '')?->requiresAppliedDate() ?? true),
                Select::make('status')
                    ->options(collect(ApplicationStatus::cases())->mapWithKeys(
                        fn (ApplicationStatus $s) => [$s->value => $s->value]
                    ))
                    ->required(),
                TextInput::make('channel')->maxLength(255),
                TextInput::make('job_url')->url()->maxLength(2048),
                Textarea::make('notes')->columnSpanFull(),
            ]);
    }
}
