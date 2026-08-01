<?php

namespace App\Policies;

use App\Models\Fueling;
use App\Models\User;

class FuelingPolicy
{
    public function view(User $user, Fueling $fueling): bool
    {
        return $fueling->car?->user_id === $user->id;
    }

    public function delete(User $user, Fueling $fueling): bool
    {
        return $fueling->car?->user_id === $user->id;
    }
}
