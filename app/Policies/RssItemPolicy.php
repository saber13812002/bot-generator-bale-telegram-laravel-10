<?php

namespace App\Policies;

use App\Models\RssBusiness;
use App\Models\RssItem;
use App\Models\User;

class RssItemPolicy
{
    /**
     * Determine whether the user can view any models.
     */
    public function viewAny(User $user): bool
    {
        return $this->userHasRssItems($user);
    }

    /**
     * Determine whether the user can view the model.
     */
    public function view(User $user, RssItem $rssItem): bool
    {
        return $this->userOwnsRssItem($user, $rssItem);
    }

    /**
     * Determine whether the user can create models.
     */
    public function create(User $user): bool
    {
        return $this->userHasRssItems($user);
    }

    /**
     * Determine whether the user can update the model.
     */
    public function update(User $user, RssItem $rssItem): bool
    {
        return $this->userOwnsRssItem($user, $rssItem);
    }

    /**
     * Determine whether the user can delete the model.
     */
    public function delete(User $user, RssItem $rssItem): bool
    {
        return $this->userOwnsRssItem($user, $rssItem);
    }

    /**
     * Determine whether the user can restore the model.
     */
    public function restore(User $user, RssItem $rssItem): bool
    {
        return $this->userOwnsRssItem($user, $rssItem);
    }

    /**
     * Determine whether the user can permanently delete the model.
     */
    public function forceDelete(User $user, RssItem $rssItem): bool
    {
        return $this->userOwnsRssItem($user, $rssItem);
    }

    /**
     * Check if the user has any RssItems.
     */
    private function userHasRssItems(User $user): bool
    {
        return RssItem::whereHas('rssBusiness', function($query) use ($user) {
            $query->where('admin_user_id', $user->id);
        })->exists();
    }

    /**
     * Check if the user owns the given RssItem.
     */
    private function userOwnsRssItem(User $user, RssItem $rssItem): bool
    {
        return $rssItem->rssBusiness->admin_user_id === $user->id;
    }
}
