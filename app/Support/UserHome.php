<?php

namespace App\Support;

use App\Models\User;

class UserHome
{
    public static function route(User $user): string
    {
        return $user->applicationWaves()->exists()
            ? route('dashboard', absolute: false)
            : route('waves.index', absolute: false);
    }
}
