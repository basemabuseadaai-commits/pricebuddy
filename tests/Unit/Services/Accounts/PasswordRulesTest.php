<?php

namespace Tests\Unit\Services\Accounts;

use App\Models\User;
use App\Services\Accounts\PasswordRules;
use Illuminate\Support\Facades\Validator;
use Tests\TestCase;

class PasswordRulesTest extends TestCase
{
    public function test_default_sign_in_details_need_changing()
    {
        $this->assertTrue(PasswordRules::needsChange(new User(['email' => 'admin@example.com', 'password' => 'Str0ngPassw0rd'])));
        $this->assertTrue(PasswordRules::needsChange(new User(['email' => 'me@bsbs.eg', 'password' => 'admin'])));
        $this->assertFalse(PasswordRules::needsChange(new User(['email' => 'me@bsbs.eg', 'password' => 'Str0ngPassw0rd'])));
    }

    public function test_passwords_need_ten_characters_with_letters_and_numbers()
    {
        $passes = fn (string $password) => Validator::make(['p' => $password], ['p' => [PasswordRules::rule()]])->passes();

        $this->assertFalse($passes('short1'));
        $this->assertFalse($passes('onlyletterslong'));
        $this->assertFalse($passes('1234567890'));
        $this->assertTrue($passes('pricecheck2026'));
    }
}
