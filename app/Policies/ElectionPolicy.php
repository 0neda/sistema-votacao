<?php

namespace App\Policies;

use App\Models\User;

class ElectionPolicy
{
    public function create(User $user): bool
    {
        return $user->is_active && $user->role === 'administrador';
    }

    public function audit(User $user): bool
    {
        return $user->is_active && in_array($user->role, ['administrador', 'auditor'], true);
    }
}
