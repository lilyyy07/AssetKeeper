<?php

return [
    // Server configuration only. Never enable an exception for unmarked accounts.
    'enabled' => env('UAT_MODE', false),
    // Exact dedicated account only; the protected UAT account flag is also required.
    'otp_bypass_email' => env('UAT_OTP_BYPASS_EMAIL'),
];
