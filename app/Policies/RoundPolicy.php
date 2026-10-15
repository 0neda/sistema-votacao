<?php

namespace App\Policies;

use App\Models\Round;
use App\Models\User;

class RoundPolicy
{
    public function vote(User $user, Round $round): bool
    {
        return $user->is_active && in_array($user->role, ['eleitor', 'administrador', 'auditor'], true);
    }

    public function close(User $user, Round $round): bool
    {
        return $user->is_active && $user->role === 'administrador';
    }
}
