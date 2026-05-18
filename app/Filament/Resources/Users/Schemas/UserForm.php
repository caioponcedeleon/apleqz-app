<?php

namespace App\Filament\Resources\Users\Schemas;

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
                    ->options(array_combine(config('app.available_locales'), config('app.available_locales')))
                    ->default('en')
                    ->required(),
                Toggle::make('is_admin')->label('Administrator'),
                TextInput::make('password')
                    ->password()
                    ->dehydrateStateUsing(fn (?string $state) => filled($state) ? Hash::make($state) : null)
                    ->dehydrated(fn (?string $state) => filled($state))
                    ->required(fn (string $operation): bool => $operation === 'create'),
            ]);
    }
}
