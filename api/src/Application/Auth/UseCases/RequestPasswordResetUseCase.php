<?php

declare(strict_types=1);

namespace Application\Auth\UseCases;

use Application\Auth\DTOs\RequestPasswordResetCommand;
use DateTimeImmutable;
use Domain\Auth\Events\PasswordResetRequested;
use Domain\Auth\Repositories\ResetTokenRepositoryInterface;
use Domain\Auth\Repositories\UserRepositoryInterface;
use Domain\Auth\ValueObjects\Email;
use Domain\Auth\ValueObjects\ResetToken;
use Illuminate\Contracts\Events\Dispatcher as EventDispatcher;
use Infrastructure\Auth\Mail\LaravelMailAdapter;

final class RequestPasswordResetUseCase
{
    public function __construct(
        private readonly UserRepositoryInterface $userRepo,
        private readonly ResetTokenRepositoryInterface $resetTokenRepo,
        private readonly LaravelMailAdapter $mailAdapter,
        private readonly EventDispatcher $events,
    ) {}

    public function execute(RequestPasswordResetCommand $command): void
    {
        $email = new Email($command->email);
        $user  = $this->userRepo->findByEmail($email);

        // Silently return for unregistered emails — requirement 3.2
        if ($user === null) {
            return;
        }

        // Invalidate any existing token — requirement 3.5
        $this->resetTokenRepo->invalidateForUser($user->id);

        // Generate new reset token
        $raw       = bin2hex(random_bytes(32));
        $hash      = hash('sha256', $raw);
        $token     = new ResetToken($raw, $hash);
        $expiresAt = new DateTimeImmutable('+60 minutes');

        $this->resetTokenRepo->create($user->id, $token, $expiresAt);

        // Dispatch email
        $this->mailAdapter->sendPasswordResetEmail($email, $token);

        // Dispatch domain event
        $this->events->dispatch(new PasswordResetRequested($user->id, new DateTimeImmutable()));
    }
}
