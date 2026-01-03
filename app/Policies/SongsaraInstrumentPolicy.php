<?php

namespace App\Policies;

use Illuminate\Auth\Access\Response;
use App\Models\SongsaraInstrument;
use App\Models\User;

class SongsaraInstrumentPolicy
{
    /**
     * Determine whether the user can view any models.
     */
    public function viewAny(User $user): bool
    {
        //
    }

    /**
     * Determine whether the user can view the model.
     */
    public function view(User $user, SongsaraInstrument $songsaraInstrument): bool
    {
        //
    }

    /**
     * Determine whether the user can create models.
     */
    public function create(User $user): bool
    {
        //
    }

    /**
     * Determine whether the user can update the model.
     */
    public function update(User $user, SongsaraInstrument $songsaraInstrument): bool
    {
        //
    }

    /**
     * Determine whether the user can delete the model.
     */
    public function delete(User $user, SongsaraInstrument $songsaraInstrument): bool
    {
        //
    }

    /**
     * Determine whether the user can restore the model.
     */
    public function restore(User $user, SongsaraInstrument $songsaraInstrument): bool
    {
        //
    }

    /**
     * Determine whether the user can permanently delete the model.
     */
    public function forceDelete(User $user, SongsaraInstrument $songsaraInstrument): bool
    {
        //
    }
}
