<?php

namespace App\Policies;

use App\Models\Repair;
use App\Models\User;

class RepairPolicy
{
    public function view(User $user, Repair $repair): bool
    {
        return $repair->car?->user_id === $user->id;
    }

    public function delete(User $user, Repair $repair): bool
    {
        return $repair->car?->user_id === $user->id;
    }
}
