<?php

namespace App\Policies;

use App\Models\User;
use App\Models\ContentCategory;
use Illuminate\Auth\Access\HandlesAuthorization;

class ContentCategoryPolicy
{
    use HandlesAuthorization;

    public function viewAny(User $user): bool
    {
        return true;
    }

    public function view(User $user, ContentCategory $category): bool
    {
        return true;
    }

    public function create(User $user): bool
    {
        return true;
    }

    public function update(User $user, ContentCategory $category): bool
    {
        return true;
    }

    public function delete(User $user, ContentCategory $category): bool
    {
        return true;
    }

    public function restore(User $user, ContentCategory $category): bool
    {
        return true;
    }

    public function forceDelete(User $user, ContentCategory $category): bool
    {
        return false;
    }
}
