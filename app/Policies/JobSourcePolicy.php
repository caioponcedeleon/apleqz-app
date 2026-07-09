<?php

namespace App\Policies;

use App\Models\JobSource;
use App\Models\User;

class JobSourcePolicy
{
    public function viewAny(User $user): bool
    {
        return $user->is_admin;
    }

    public function view(User $user, JobSource $jobSource): bool
    {
        return $user->is_admin;
    }

    public function create(User $user): bool
    {
        return $user->is_admin;
    }

    public function update(User $user, JobSource $jobSource): bool
    {
        return $user->is_admin;
    }

    public function delete(User $user, JobSource $jobSource): bool
    {
        return $user->is_admin;
    }
}
