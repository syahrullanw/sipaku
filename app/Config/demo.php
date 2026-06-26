<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Demo Mode
    |--------------------------------------------------------------------------
    |
    | When enabled, sensitive data (teacher personal information and contact
    | details) will be masked across the application. Toggling the mode
    | requires the password below.
    |
    */
    'enabled' => false,

    // Set a strong password for enabling/disabling demo mode.
    'password' => '11223344',

    // Placeholder text that replaces sensitive values while demo mode is active.
    'mask_label' => 'Disembunyikan (Mode Demo)',
];
