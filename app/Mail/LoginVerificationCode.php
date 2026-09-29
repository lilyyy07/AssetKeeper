<?php

namespace App\Mail;

use Illuminate\Mail\Mailable;

class LoginVerificationCode extends Mailable
{
    public function __construct(public string $code) {}

    public function build(): static
    {
        return $this->subject('AssetKeeper Login Verification Code')->view('emails.login-code');
    }
}
