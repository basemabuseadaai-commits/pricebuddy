<?php

namespace App\Filament\Pages\Auth;

use App\Services\Accounts\PasswordRules;
use Filament\Forms\Components\Component;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Form;
use Filament\Forms\Get;
use Filament\Pages\Auth\EditProfile as BaseEditProfile;

/**
 * "My profile": name, email and password. Changing the email or password needs
 * the current password.
 */
class EditProfile extends BaseEditProfile
{
    public function form(Form $form): Form
    {
        return $form->schema([
            $this->getNameFormComponent(),
            $this->getEmailFormComponent(),
            $this->getPasswordFormComponent(),
            $this->getPasswordConfirmationFormComponent(),
            TextInput::make('current_password')
                ->label(__('Current password'))
                ->password()
                ->revealable()
                ->currentPassword()
                ->required(fn (Get $get): bool => filled($get('password')) || $get('email') !== $this->getUser()->email)
                ->dehydrated(false)
                ->helperText(__('Needed to change your email or password.')),
        ]);
    }

    protected function getPasswordFormComponent(): Component
    {
        return parent::getPasswordFormComponent()
            ->rule(PasswordRules::rule())
            ->helperText(__('At least :n characters, with letters and numbers.', ['n' => config('accounts.password_min_length', 10)]));
    }
}
