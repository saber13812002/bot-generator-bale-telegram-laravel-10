<?php

namespace App\Policies;

use App\Models\User;
use App\Models\BotAdminKieRequest;
use Illuminate\Auth\Access\HandlesAuthorization;

class BotAdminKieRequestPolicy
{
    use HandlesAuthorization;

    public function viewAny(User $user): bool { return true; }
    public function view(User $user, BotAdminKieRequest $request): bool { return true; }
    public function create(User $user): bool { return true; }
    public function update(User $user, BotAdminKieRequest $request): bool { return true; }
    public function delete(User $user, BotAdminKieRequest $request): bool { return false; }
    public function restore(User $user, BotAdminKieRequest $request): bool { return false; }
    public function forceDelete(User $user, BotAdminKieRequest $request): bool { return false; }
}
