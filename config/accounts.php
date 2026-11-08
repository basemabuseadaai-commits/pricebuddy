<?php

/*
 * Sign-in and account settings (BSBS PRICE CHECKER).
 */
return [
    // Show a "Sign up" link on the login page. Off: only an admin creates accounts.
    'registration' => (bool) env('ALLOW_REGISTRATION', false),

    // Passwords that force a change at the next sign-in.
    'weak_passwords' => ['admin', 'password', 'pricebuddy', '12345678', '123456789'],

    // Login emails that force a change at the next sign-in.
    'placeholder_emails' => ['admin@example.com'],

    'password_min_length' => 10,
];
