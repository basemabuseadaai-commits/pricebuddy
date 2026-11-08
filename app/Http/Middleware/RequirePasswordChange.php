<?php

namespace App\Http\Middleware;

use App\Services\Accounts\PasswordRules;
use Closure;
use Filament\Facades\Filament;
use Filament\Notifications\Notification;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Sends an account that still uses a default password or placeholder email to its
 * profile page until both are changed.
 */
class RequirePasswordChange
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if (! $user || $request->routeIs('filament.*.auth.profile', 'filament.*.auth.logout', 'livewire.*')) {
            return $next($request);
        }

        if (PasswordRules::needsChange($user)) {
            Notification::make('change-default-password')
                ->title(__('Please set your own email and a new password'))
                ->body(__('This account still uses the default sign-in details.'))
                ->warning()
                ->persistent()
                ->send();

            return redirect(Filament::getProfileUrl());
        }

        return $next($request);
    }
}
