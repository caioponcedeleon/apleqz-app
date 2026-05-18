<?php

namespace App\Policies;

use App\Models\ApplicationFile;
use App\Models\User;

class ApplicationFilePolicy
{
    public function delete(User $user, ApplicationFile $file): bool
    {
        return $user->application_files_enabled
            && $file->user_id === $user->id;
    }

    public function download(User $user, ApplicationFile $file): bool
    {
        return $file->user_id === $user->id;
    }
}
