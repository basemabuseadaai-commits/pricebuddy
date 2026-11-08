<?php

namespace App\Services\Accounts;

use App\Models\User;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Illuminate\Validation\Rules\Password;

class PasswordRules
{
    /**
     * At least 10 characters with letters and numbers, and not a known default.
     */
    public static function rule(): Password
    {
        return Password::min((int) config('accounts.password_min_length', 10))->letters()->numbers();
    }

    /**
     * Whether the account still uses a default password or placeholder email, so it
     * must be changed before anything else.
     */
    public static function needsChange(User $user): bool
    {
        if (in_array(Str::lower((string) $user->email), (array) config('accounts.placeholder_emails', []), true)) {
            return true;
        }

        foreach ((array) config('accounts.weak_passwords', []) as $weak) {
            if (Hash::check((string) $weak, (string) $user->password)) {
                return true;
            }
        }

        return false;
    }
}
