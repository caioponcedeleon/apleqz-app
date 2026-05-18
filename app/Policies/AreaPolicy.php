<?php

namespace App\Policies;

use App\Models\Area;
use App\Models\User;

class AreaPolicy
{
    public function viewAny(User $user): bool
    {
        return true;
    }

    public function create(User $user): bool
    {
        return true;
    }

    public function update(User $user, Area $area): bool
    {
        return $area->user_id === $user->id;
    }

    public function delete(User $user, Area $area): bool
    {
        return $area->user_id === $user->id;
    }
}
