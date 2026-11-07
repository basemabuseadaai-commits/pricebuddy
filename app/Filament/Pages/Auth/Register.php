<?php

namespace App\Filament\Pages\Auth;

use App\Enums\Role;
use App\Services\Accounts\NewAccount;
use App\Services\Accounts\PasswordRules;
use Filament\Forms\Components\Component;
use Filament\Pages\Auth\Register as BaseRegister;
use Illuminate\Database\Eloquent\Model;

/**
 * Sign-up page, shown only when ALLOW_REGISTRATION=true. New accounts are normal
 * users (never admins) and get their own copy of the shop search pages.
 */
class Register extends BaseRegister
{
    protected static string $layout = 'filament.layouts.auth';

    protected function getPasswordFormComponent(): Component
    {
        return parent::getPasswordFormComponent()
            ->rule(PasswordRules::rule())
            ->helperText(__('At least :n characters, with letters and numbers.', ['n' => config('accounts.password_min_length', 10)]));
    }

    protected function handleRegistration(array $data): Model
    {
        $data['role'] = Role::User;

        $user = $this->getUserModel()::create($data);

        NewAccount::setUp($user);

        return $user;
    }
}
