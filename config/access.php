<?php

return [

    /*
    |--------------------------------------------------------------------------
    | VAWC / BPO access by explicit assignment
    |--------------------------------------------------------------------------
    |
    | Off (the default) keeps the current behaviour: the Super Admin and an
    | active Barangay Captain can read and manage every VAWC case and BPO.
    |
    | On: every account, administrators included, needs the vawc.* keys in its
    | own assignments. Turn it on only after the barangay has designated who
    | handles these cases, the Super Admin has assigned them, and
    | `php artisan personnel:access-report` confirms an active designated
    | account can view and manage cases.
    |
    */

    'vawc_explicit_assignment' => (bool) env('VAWC_EXPLICIT_ASSIGNMENT', false),

    /*
    |--------------------------------------------------------------------------
    | Two-factor authentication for privileged personnel
    |--------------------------------------------------------------------------
    |
    | Accounts with privileged access (App\Models\User::requiresTwoFactorAuthentication) must finish setting up
    | two-factor authentication before they can use the workspace. Always on in production; it can be turned
    | off only outside production (the automated test suite does, and tests the rule on its own).
    |
    */
    'privileged_two_factor' => env('APP_ENV') === 'production' || (bool) env('PRIVILEGED_TWO_FACTOR', true),

];
