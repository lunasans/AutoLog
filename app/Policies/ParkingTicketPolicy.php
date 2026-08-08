<?php

namespace App\Policies;

use App\Models\ParkingTicket;
use App\Models\User;

class ParkingTicketPolicy
{
    public function view(User $user, ParkingTicket $ticket): bool
    {
        return $ticket->car?->user_id === $user->id;
    }

    public function delete(User $user, ParkingTicket $ticket): bool
    {
        return $ticket->car?->user_id === $user->id;
    }
}
