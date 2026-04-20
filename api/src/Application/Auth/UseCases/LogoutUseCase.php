<?php

declare(strict_types=1);

namespace Application\Auth\UseCases;

use Application\Auth\DTOs\LogoutCommand;
use DateTimeImmutable;
use Domain\Auth\Events\UserLoggedOut;
use Domain\Auth\Repositories\SessionRepositoryInterface;
use Illuminate\Contracts\Events\Dispatcher as EventDispatcher;

final class LogoutUseCase
{
    public function __construct(
        private readonly SessionRepositoryInterface $sessionRepo,
        private readonly EventDispatcher $events,
    ) {}

    public function execute(LogoutCommand $command): void
    {
        $session = $this->sessionRepo->findByTokenHash($command->tokenHash);

        if ($session === null) {
            return;
        }

        $this->sessionRepo->invalidate($command->tokenHash);

        $this->events->dispatch(new UserLoggedOut($session->userId, new DateTimeImmutable()));
    }
}
