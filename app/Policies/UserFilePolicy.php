<?php

namespace App\Policies;

use App\Models\User;
use App\Models\UserFile;

class UserFilePolicy
{
    public function viewAny(User $user): bool
    {
        return $user->personal_files_enabled;
    }

    public function delete(User $user, UserFile $file): bool
    {
        return $user->personal_files_enabled
            && $file->user_id === $user->id;
    }

    public function download(User $user, UserFile $file): bool
    {
        return $user->personal_files_enabled
            && $file->user_id === $user->id;
    }
}
