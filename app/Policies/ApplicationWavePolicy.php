<?php

namespace App\Policies;

use App\Models\ApplicationWave;
use App\Models\User;

class ApplicationWavePolicy
{
    public function viewAny(User $user): bool
    {
        return true;
    }

    public function create(User $user): bool
    {
        return true;
    }

    public function update(User $user, ApplicationWave $wave): bool
    {
        return $wave->user_id === $user->id;
    }

    public function delete(User $user, ApplicationWave $wave): bool
    {
        return $wave->user_id === $user->id;
    }
}
