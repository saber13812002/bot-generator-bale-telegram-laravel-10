<?php

namespace App\Policies;

use App\Models\User;
use App\Models\ContentAsset;
use Illuminate\Auth\Access\HandlesAuthorization;

class ContentAssetPolicy
{
    use HandlesAuthorization;

    public function viewAny(User $user): bool
    {
        return true;
    }

    public function view(User $user, ContentAsset $asset): bool
    {
        return true;
    }

    public function create(User $user): bool
    {
        return true;
    }

    public function update(User $user, ContentAsset $asset): bool
    {
        return true;
    }

    public function delete(User $user, ContentAsset $asset): bool
    {
        return true;
    }

    public function restore(User $user, ContentAsset $asset): bool
    {
        return true;
    }

    public function forceDelete(User $user, ContentAsset $asset): bool
    {
        return false;
    }
}
