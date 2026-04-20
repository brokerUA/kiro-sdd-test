<?php

declare(strict_types=1);

namespace Application\Auth\UseCases;

use Application\Auth\DTOs\LoginCommand;
use Application\Auth\Exceptions\AccountLockedException;
use Application\Auth\Exceptions\AuthenticationFailedException;
use DateTimeImmutable;
use Domain\Auth\Events\UserLoggedIn;
use Domain\Auth\Repositories\SessionRepositoryInterface;
use Domain\Auth\Repositories\UserRepositoryInterface;
use Domain\Auth\Services\CredentialVerifier;
use Domain\Auth\ValueObjects\Email;
use Domain\Auth\ValueObjects\SessionToken;
use Illuminate\Contracts\Events\Dispatcher as EventDispatcher;
use Infrastructure\Auth\RateLimiting\RedisRateLimiter;

final class LoginUseCase
{
    public function __construct(
        private readonly UserRepositoryInterface $userRepo,
        private readonly SessionRepositoryInterface $sessionRepo,
        private readonly CredentialVerifier $credentialVerifier,
        private readonly RedisRateLimiter $rateLimiter,
        private readonly EventDispatcher $events,
    ) {}

    /**
     * @throws AccountLockedException
     * @throws AuthenticationFailedException
     */
    public function execute(LoginCommand $command): SessionToken
    {
        $email = new Email($command->email);

        $lockStatus = $this->rateLimiter->isLocked($email);

        if ($lockStatus->isLocked()) {
            throw new AccountLockedException($lockStatus->getRetryAfterSeconds());
        }

        $result = $this->credentialVerifier->verify($email, $command->password, $this->userRepo);

        if (!$result->isSuccess()) {
            $this->rateLimiter->recordFailure($email);
            throw new AuthenticationFailedException();
        }

        $this->rateLimiter->clearAttempts($email);

        $raw   = bin2hex(random_bytes(32));
        $hash  = hash('sha256', $raw);
        $token = new SessionToken($raw, $hash);

        $lifetime  = (int) config('auth.session_lifetime', 86400);
        $expiresAt = new DateTimeImmutable('+' . $lifetime . ' seconds');
        $this->sessionRepo->create($result->getUser()->id, $token, $expiresAt);

        $this->events->dispatch(new UserLoggedIn($result->getUser()->id, new DateTimeImmutable()));

        return $token;
    }
}
