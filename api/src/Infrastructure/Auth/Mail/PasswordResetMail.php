<?php

declare(strict_types=1);

namespace Infrastructure\Auth\Mail;

use Illuminate\Mail\Mailable;

class PasswordResetMail extends Mailable
{
    public function __construct(public readonly string $rawToken) {}

    public function build(): static
    {
        $resetUrl = config('app.frontend_url') . '/password-reset/complete?token=' . $this->rawToken;

        return $this->view('emails.password-reset')->with([
            'token'    => $this->rawToken,
            'resetUrl' => $resetUrl,
        ]);
    }
}
