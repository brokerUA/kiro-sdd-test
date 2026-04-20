<?php

declare(strict_types=1);

namespace Application\Auth\UseCases;

use Application\Auth\DTOs\CompletePasswordResetCommand;
use Application\Auth\Exceptions\TokenExpiredException;
use Application\Auth\Exceptions\TokenInvalidException;
use DateTimeImmutable;
use Domain\Auth\Events\PasswordResetCompleted;
use Domain\Auth\Repositories\ResetTokenRepositoryInterface;
use Domain\Auth\Repositories\SessionRepositoryInterface;
use Domain\Auth\Repositories\UserRepositoryInterface;
use Domain\Auth\Services\PasswordPolicy;
use Illuminate\Contracts\Events\Dispatcher as EventDispatcher;
use Illuminate\Validation\ValidationException;

final class CompletePasswordResetUseCase
{
    public function __construct(
        private readonly UserRepositoryInterface $userRepo,
        private readonly SessionRepositoryInterface $sessionRepo,
        private readonly ResetTokenRepositoryInterface $resetTokenRepo,
        private readonly PasswordPolicy $passwordPolicy,
        private readonly EventDispatcher $events,
    ) {}

    public function execute(CompletePasswordResetCommand $command): void
    {
        $tokenHash  = hash('sha256', $command->tokenRaw);
        $resetToken = $this->resetTokenRepo->findByTokenHash($tokenHash);

        if ($resetToken === null) {
            throw new TokenInvalidException();
        }

        if ($resetToken->isExpired(new DateTimeImmutable())) {
            throw new TokenExpiredException();
        }

        if ($resetToken->isUsed()) {
            throw new TokenInvalidException();
        }

        $policyResult = $this->passwordPolicy->validate($command->newPassword);

        if (!$policyResult->isValid()) {
            throw ValidationException::withMessages([
                'new_password' => $policyResult->getViolations(),
            ]);
        }

        $user = $this->userRepo->findById($resetToken->userId);

        if ($user === null) {
            throw new TokenInvalidException();
        }

        if ($this->passwordPolicy->verify($command->newPassword, $user->passwordHash)) {
            throw ValidationException::withMessages([
                'new_password' => ['New password must be different from the current password.'],
            ]);
        }

        $newHash = $this->passwordPolicy->hash($command->newPassword);
        $user->changePassword($newHash);
        $this->userRepo->save($user);

        $resetToken->markUsed(new DateTimeImmutable());
        $this->resetTokenRepo->save($resetToken);

        $this->sessionRepo->invalidateAllForUser($user->id);

        $this->events->dispatch(new PasswordResetCompleted($user->id, new DateTimeImmutable()));
    }
}
