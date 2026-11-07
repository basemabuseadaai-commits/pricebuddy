<?php

namespace App\Services\Accounts;

use App\Models\ProductSource;
use App\Models\User;

/**
 * Gives a new account its own copy of the shop search pages, so its saved
 * searches and web search work straight away. Stores are shared by everyone.
 */
class NewAccount
{
    public static function setUp(User $user): int
    {
        if (ProductSource::query()->where('user_id', $user->getKey())->exists()) {
            return 0;
        }

        $template = ProductSource::query()
            ->where('user_id', '!=', $user->getKey())
            ->orderBy('user_id')
            ->get()
            ->groupBy('user_id')
            ->first();

        $copied = 0;

        foreach ($template ?? [] as $source) {
            /** @var ProductSource $source */
            $copy = $source->replicate(['slug']);
            $copy->user_id = $user->getKey();
            $copy->save();
            $copied++;
        }

        return $copied;
    }
}
