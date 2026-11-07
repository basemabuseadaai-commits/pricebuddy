<?php

namespace App\Filament\Resources\UserResource\Pages;

use App\Filament\Resources\UserResource;
use App\Services\Accounts\NewAccount;
use Filament\Resources\Pages\CreateRecord;

class CreateUser extends CreateRecord
{
    protected static string $resource = UserResource::class;

    /**
     * Give the new account its own copy of the shop search pages.
     */
    protected function afterCreate(): void
    {
        NewAccount::setUp($this->record);
    }
}
