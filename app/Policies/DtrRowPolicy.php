<?php

namespace App\Policies;

use App\Models\DtrMonth;
use App\Models\DtrRow;
use App\Models\User;

class DtrRowPolicy
{
    /**
     * Determine if the user can create DTR rows.
     */
    public function create(User $user, DtrMonth $dtrMonth): bool
    {
        return $user->id === $dtrMonth->user_id;
    }

    /**
     * Determine if the user can update the DTR row.
     */
    public function update(User $user, DtrRow $dtrRow): bool
    {
        return $user->id === $dtrRow->dtrMonth->user_id;
    }

    /**
     * Determine if the user can delete the DTR row.
     */
    public function delete(User $user, DtrRow $dtrRow): bool
    {
        return $user->id === $dtrRow->dtrMonth->user_id;
    }
}
