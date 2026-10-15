<?php

namespace App\Policies;

use App\Models\User;

class UserPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->is_active && $user->role === 'administrador';
    }

    public function update(User $user, User $target): bool
    {
        return $this->viewAny($user);
    }

    public function delete(User $user, User $target): bool
    {
        return $this->viewAny($user);
    }
}
