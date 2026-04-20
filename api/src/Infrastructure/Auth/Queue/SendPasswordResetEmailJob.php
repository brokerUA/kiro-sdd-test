<?php

declare(strict_types=1);

namespace Infrastructure\Auth\Queue;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Mail\Mailer;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Infrastructure\Auth\Mail\PasswordResetMail;

class SendPasswordResetEmailJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function __construct(
        public readonly string $toEmail,
        public readonly string $rawToken,
    ) {}

    public function handle(Mailer $mailer): void
    {
        $mailer->to($this->toEmail)->send(new PasswordResetMail($this->rawToken));
    }
}
