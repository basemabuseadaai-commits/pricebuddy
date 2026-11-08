<?php

namespace App\Policies;

use App\Models\Store;
use App\Models\User;

/**
 * Stores are shared by all users, so their settings (selectors, cookies, AI and
 * scraper options) change how prices are read for everyone. Any user can see
 * the list; only an admin can create, edit or delete a store. Adding a product
 * from a new shop still creates its store automatically.
 */
class StorePolicy
{
    public function viewAny(User $user): bool
    {
        return true;
    }

    public function view(User $user, Store $store): bool
    {
        return true;
    }

    public function create(User $user): bool
    {
        return $user->isAdmin();
    }

    public function update(User $user, Store $store): bool
    {
        return $user->isAdmin();
    }

    public function delete(User $user, Store $store): bool
    {
        return $user->isAdmin();
    }

    public function deleteAny(User $user): bool
    {
        return $user->isAdmin();
    }
}
