<?php

namespace App\Policies;

use App\Models\User;
use App\Models\ContentItem;
use Illuminate\Auth\Access\HandlesAuthorization;

class ContentItemPolicy
{
    use HandlesAuthorization;

    public function viewAny(User $user): bool
    {
        return true;
    }

    public function view(User $user, ContentItem $item): bool
    {
        return true;
    }

    public function create(User $user): bool
    {
        return true;
    }

    public function update(User $user, ContentItem $item): bool
    {
        return true;
    }

    public function delete(User $user, ContentItem $item): bool
    {
        return true;
    }

    public function restore(User $user, ContentItem $item): bool
    {
        return true;
    }

    public function forceDelete(User $user, ContentItem $item): bool
    {
        return false;
    }
}
