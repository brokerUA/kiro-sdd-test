<?php

declare(strict_types=1);

namespace Infrastructure\Auth\Mail;

use Domain\Auth\ValueObjects\Email;
use Domain\Auth\ValueObjects\ResetToken;
use Infrastructure\Auth\Queue\SendPasswordResetEmailJob;

class LaravelMailAdapter
{
    public function sendPasswordResetEmail(Email $toAddress, ResetToken $token): void
    {
        SendPasswordResetEmailJob::dispatch($toAddress->value, $token->raw);
    }
}
