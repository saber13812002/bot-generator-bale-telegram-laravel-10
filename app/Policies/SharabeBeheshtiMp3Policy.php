<?php

namespace App\Policies;

use Illuminate\Auth\Access\Response;
use App\Models\SharabeBeheshtiMp3;
use App\Models\User;

class SharabeBeheshtiMp3Policy
{
    /**
     * Determine whether the user can view any models.
     */
    public function viewAny(User $user): bool
    {
        return true;
    }

    /**
     * Determine whether the user can view the model.
     */
    public function view(User $user, SharabeBeheshtiMp3 $sharabeBeheshtiMp3): bool
    {
        return true;
    }

    /**
     * Determine whether the user can create models.
     */
    public function create(User $user): bool
    {
        return true;
    }

    /**
     * Determine whether the user can update the model.
     */
    public function update(User $user, SharabeBeheshtiMp3 $sharabeBeheshtiMp3): bool
    {
        return true;
    }

    /**
     * Determine whether the user can delete the model.
     */
    public function delete(User $user, SharabeBeheshtiMp3 $sharabeBeheshtiMp3): bool
    {
        return true;
    }

    /**
     * Determine whether the user can restore the model.
     */
    public function restore(User $user, SharabeBeheshtiMp3 $sharabeBeheshtiMp3): bool
    {
        return true;
    }

    /**
     * Determine whether the user can permanently delete the model.
     */
    public function forceDelete(User $user, SharabeBeheshtiMp3 $sharabeBeheshtiMp3): bool
    {
        return true;
    }
}
