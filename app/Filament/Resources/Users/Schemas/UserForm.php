<?php

namespace App\Filament\Resources\Users\Schemas;

use App\Enums\JobAlertsTier;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Schema;
use Illuminate\Support\Facades\Hash;

class UserForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('name')->required()->maxLength(255),
                TextInput::make('email')
                    ->label('Email address')
                    ->email()
                    ->required()
                    ->maxLength(255),
                DateTimePicker::make('email_verified_at'),
                Select::make('locale')
                    ->options(collect(config('app.available_locales'))
                        ->mapWithKeys(fn (string $locale) => [
                            $locale => config("app.locale_labels.{$locale}", strtoupper($locale)),
                        ])
                        ->all())
                    ->default('en')
                    ->required(),
                Toggle::make('is_admin')->label('Administrator'),
                Toggle::make('application_files_enabled')
                    ->label('Application file uploads')
                    ->helperText('Allow PDF/DOCX attachments on job applications.'),
                Toggle::make('personal_files_enabled')
                    ->label('Personal file storage')
                    ->helperText('Allow a private file library (PDF/DOCX) in the user menu.'),
                Toggle::make('excel_import_enabled')
                    ->label('Excel import')
                    ->helperText('Show the Import Excel button on the applications list.'),
                Select::make('job_alerts_tier')
                    ->label('Job alerts tier')
                    ->options(collect(JobAlertsTier::cases())
                        ->mapWithKeys(fn (JobAlertsTier $tier): array => [$tier->value => $tier->label()])
                        ->all())
                    ->default(JobAlertsTier::None->value)
                    ->required()
                    ->helperText('None: hidden. Regex: title keyword rules. AI: profile-based scoring.'),
                TextInput::make('password')
                    ->password()
                    ->dehydrateStateUsing(fn (?string $state) => filled($state) ? Hash::make($state) : null)
                    ->dehydrated(fn (?string $state) => filled($state))
                    ->required(fn (string $operation): bool => $operation === 'create'),
            ]);
    }
}
