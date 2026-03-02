<?php

namespace App\Policies;

use App\Models\DtrMonth;
use App\Models\User;

class DtrMonthPolicy
{
    /**
     * Determine if the user can view the DTR month.
     */
    public function view(User $user, DtrMonth $dtrMonth): bool
    {
        return $user->id === $dtrMonth->user_id;
    }

    /**
     * Determine if the user can create DTR months.
     */
    public function create(User $user): bool
    {
        return true;
    }

    /**
     * Determine if the user can update the DTR month.
     */
    public function update(User $user, DtrMonth $dtrMonth): bool
    {
        return $user->id === $dtrMonth->user_id;
    }

    /**
     * Determine if the user can delete the DTR month.
     */
    public function delete(User $user, DtrMonth $dtrMonth): bool
    {
        return $user->id === $dtrMonth->user_id;
    }
}
