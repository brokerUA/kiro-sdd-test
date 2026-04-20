# Design Document: User Authentication

## Overview

This document describes the technical design for a user authentication system supporting login, logout, and password reset. The system is responsible for verifying user identity, issuing and managing session tokens, and enabling secure password recovery.

The design follows a stateless token-based authentication model using short-lived session tokens stored server-side (to support explicit invalidation). Password reset uses single-use, time-limited tokens delivered via email.

**Technology Stack:**
- **Backend API**: Laravel 13 (PHP 8.5) — all authentication logic, session management, password hashing, and email dispatch
- **Frontend**: Next.js 16 (React) — UI for login, logout, and password reset flows
- **Password hashing**: bcrypt via Laravel's `Hash` facade (cost factor ≥ 12)
- **Session tokens**: cryptographically random 256-bit tokens via `Str::random(64)` / `random_bytes(32)`
- **Database**: PostgreSQL 18 — users, sessions, and reset tokens (via Laravel Eloquent ORM)
- **Cache / Sessions / Queues**: Redis — session cache, rate-limit counters, queue backend
- **Email delivery**: Laravel Mail + queued Mailable jobs; Mailpit for local testing
- **Property-based testing (backend)**: [eris](https://github.com/giorgiosironi/eris) (PHP PBT library) with PHPUnit
- **Property-based testing (frontend)**: [fast-check](https://fast-check.io/) with Jest

---

## Architecture

The authentication system is split across two services: a Laravel API backend and a Next.js frontend, both served behind nginx reverse proxies. All auth logic lives in the Laravel API; the Next.js frontend communicates with it over HTTP.

Both services follow a **Domain-Driven Design (DDD)** layered architecture. The Laravel API is organised around the `Authentication` bounded context with four explicit layers: Presentation → Application → Domain → Infrastructure. The Next.js frontend mirrors this with a `src/domains/auth/` structure.

```mermaid
graph TD
    Browser -->|HTTPS| NginxFE[nginx — frontend]
    NginxFE --> NextJS[Next.js App\nsrc/app/]
    NextJS -->|HTTP API calls| NginxAPI[nginx — API]
    NginxAPI -->|FastCGI| PHPFPM[php-fpm — Laravel API]

    subgraph Laravel API — Authentication Bounded Context
        subgraph Presentation Layer
            LoginController
            LogoutController
            RequestPasswordResetController
            CompletePasswordResetController
        end

        subgraph Application Layer
            LoginUseCase
            LogoutUseCase
            RequestPasswordResetUseCase
            CompletePasswordResetUseCase
        end

        subgraph Domain Layer
            CredentialVerifier[CredentialVerifier\nDomain Service]
            PasswordPolicy[PasswordPolicy\nDomain Service]
            UserEntity[User\nEntity]
            SessionEntity[Session\nEntity]
            PRT[PasswordResetToken\nEntity]
            DomainEvents[Domain Events\nUserLoggedIn / UserLoggedOut\nPasswordResetRequested / PasswordResetCompleted]
            RepoInterfaces[Repository Interfaces\nUserRepository / SessionRepository\nPasswordResetTokenRepository]
        end

        subgraph Infrastructure Layer
            EloquentUserRepo[EloquentUserRepository]
            EloquentSessionRepo[EloquentSessionRepository]
            EloquentPRTRepo[EloquentPasswordResetTokenRepository]
            RedisRateLimiter[RedisRateLimiter]
            MailAdapter[LaravelMailAdapter]
            QueueAdapter[SendPasswordResetEmailJob]
        end
    end

    PHPFPM --> LoginController
    PHPFPM --> LogoutController
    PHPFPM --> RequestPasswordResetController
    PHPFPM --> CompletePasswordResetController
    LoginController --> LoginUseCase
    LogoutController --> LogoutUseCase
    RequestPasswordResetController --> RequestPasswordResetUseCase
    CompletePasswordResetController --> CompletePasswordResetUseCase

    LoginUseCase --> CredentialVerifier
    LoginUseCase --> RepoInterfaces
    LoginUseCase --> RedisRateLimiter
    LoginUseCase --> DomainEvents

    LogoutUseCase --> RepoInterfaces
    LogoutUseCase --> DomainEvents

    RequestPasswordResetUseCase --> RepoInterfaces
    RequestPasswordResetUseCase --> MailAdapter
    RequestPasswordResetUseCase --> DomainEvents

    CompletePasswordResetUseCase --> PasswordPolicy
    CompletePasswordResetUseCase --> RepoInterfaces
    CompletePasswordResetUseCase --> DomainEvents

    CredentialVerifier --> UserEntity
    PasswordPolicy --> UserEntity

    RepoInterfaces --> EloquentUserRepo
    RepoInterfaces --> EloquentSessionRepo
    RepoInterfaces --> EloquentPRTRepo

    EloquentUserRepo --> PG[(PostgreSQL)]
    EloquentSessionRepo --> PG
    EloquentPRTRepo --> PG
    RedisRateLimiter --> Redis[(Redis)]

    MailAdapter --> QueueAdapter
    QueueAdapter -->|queued via Redis| QueueWorker[php-cli — queue worker]
    QueueWorker --> Mailpit[Mailpit SMTP]

    Scheduler[php-cli — scheduler] -->|purge expired sessions/tokens| PG
```

### Key Design Decisions

1. **DDD bounded context**: All authentication code lives under a single `Authentication/` bounded context, divided into `Domain/`, `Application/`, `Infrastructure/`, and `Presentation/` layers. The domain layer has zero framework dependencies — it contains only PHP classes and interfaces.

2. **Dependency inversion via repository interfaces**: The domain and application layers depend on repository interfaces (`UserRepository`, `SessionRepository`, `PasswordResetTokenRepository`) defined in the domain. Eloquent implementations live in the infrastructure layer and are injected via Laravel's service container.

3. **Use cases as the application boundary**: Each user-facing operation (`LoginUseCase`, `LogoutUseCase`, `RequestPasswordResetUseCase`, `CompletePasswordResetUseCase`) is an explicit application service class. Controllers are thin — they validate HTTP input and delegate entirely to a use case.

4. **Generic auth failure messages**: Login failures never reveal whether the email or password was wrong (requirements 1.3, 1.4). A single `AUTHENTICATION_FAILED` error is returned for both cases, enforced inside `CredentialVerifier`.

5. **Server-side session storage**: Tokens are stored in PostgreSQL so they can be explicitly invalidated on logout, password reset, and expiry — rather than relying solely on JWT expiry.

6. **One active reset token per user**: Generating a new reset token atomically invalidates the previous one (requirement 3.5), enforced by a `UNIQUE` constraint on `user_id` in the `password_reset_tokens` table.

7. **Rate limiting at the account level via Redis**: Failed attempts are tracked per email address in Redis using a sliding window counter, not per IP, to prevent lockout bypass via IP rotation while avoiding locking legitimate users due to shared IPs.

8. **Queued email dispatch**: Password reset emails are dispatched as queued Laravel Jobs so the API response is not blocked by SMTP latency.

9. **Token hashing**: Both session tokens and reset tokens are stored as SHA-256 hashes in the database. The raw token is only ever held in memory and returned to the client once — a database breach does not expose live tokens.

---

## Project Structure

### Laravel API (`api/`)

The backend follows a strict DDD layered architecture under `src/`, organised around the `Auth` bounded context. Framework-specific code (routes, config, migrations) lives in the standard Laravel locations outside `src/`.

```
api/
├── src/
│   ├── Domain/
│   │   ├── Auth/
│   │   │   ├── Entities/
│   │   │   │   ├── User.php                          # User aggregate root
│   │   │   │   ├── Session.php                       # Session entity
│   │   │   │   └── PasswordResetToken.php            # Reset token entity
│   │   │   ├── ValueObjects/
│   │   │   │   ├── Email.php                         # Validated email VO
│   │   │   │   ├── HashedPassword.php                # Bcrypt hash VO
│   │   │   │   ├── SessionToken.php                  # Raw/hashed token pair VO
│   │   │   │   └── ResetToken.php                    # Raw/hashed token pair VO
│   │   │   ├── Events/
│   │   │   │   ├── UserLoggedIn.php
│   │   │   │   ├── UserLoggedOut.php
│   │   │   │   ├── PasswordResetRequested.php
│   │   │   │   └── PasswordResetCompleted.php
│   │   │   ├── Repositories/
│   │   │   │   ├── UserRepositoryInterface.php
│   │   │   │   ├── SessionRepositoryInterface.php
│   │   │   │   └── ResetTokenRepositoryInterface.php
│   │   │   └── Services/
│   │   │       ├── CredentialVerifier.php            # Verifies email+password pair
│   │   │       └── PasswordPolicy.php                # Enforces password rules
│   │   │
│   │   └── Database/
│   │       ├── Migrations/
│   │       │   ├── 0001_create_users_table.php
│   │       │   ├── 0002_create_sessions_table.php
│   │       │   └── 0003_create_password_reset_tokens_table.php
│   │       └── Providers/
│   │           └── DatabaseServiceProvider.php       # Registers migrations via loadMigrationsFrom
│   │
│   ├── Application/
│   │   └── Auth/
│   │       ├── UseCases/
│   │       │   ├── LoginUseCase.php
│   │       │   ├── LogoutUseCase.php
│   │       │   ├── RequestPasswordResetUseCase.php
│   │       │   └── CompletePasswordResetUseCase.php
│   │       ├── DTOs/
│   │       │   ├── LoginCommand.php
│   │       │   ├── LogoutCommand.php
│   │       │   ├── RequestPasswordResetCommand.php
│   │       │   └── CompletePasswordResetCommand.php
│   │       └── Exceptions/
│   │           ├── AuthenticationFailedException.php
│   │           ├── AccountLockedException.php
│   │           ├── TokenExpiredException.php
│   │           └── TokenInvalidException.php
│   │
│   ├── Infrastructure/
│   │   └── Auth/
│   │       ├── Providers/
│   │       │   └── AuthServiceProvider.php           # Binds interfaces → implementations
│   │       ├── Repositories/
│   │       │   ├── EloquentUserRepository.php
│   │       │   ├── EloquentSessionRepository.php
│   │       │   └── EloquentResetTokenRepository.php
│   │       ├── RateLimiting/
│   │       │   └── RedisRateLimiter.php
│   │       ├── Mail/
│   │       │   ├── LaravelMailAdapter.php
│   │       │   └── PasswordResetMail.php             # Laravel Mailable
│   │       └── Queue/
│   │           └── SendPasswordResetEmailJob.php     # ShouldQueue job
│   │
│   └── Presentation/
│       └── Http/
│           └── Auth/
│               ├── Controllers/
│               │   ├── LoginController.php           # POST /api/auth/login
│               │   ├── LogoutController.php          # POST /api/auth/logout
│               │   ├── RequestPasswordResetController.php  # POST /api/auth/password-reset/request
│               │   └── CompletePasswordResetController.php # POST /api/auth/password-reset/complete
│               ├── Requests/
│               │   ├── LoginRequest.php
│               │   ├── PasswordResetRequestForm.php
│               │   └── PasswordResetCompleteForm.php
│               ├── Resources/
│               │   └── SessionTokenResource.php      # API Resource transformer
│               ├── Providers/
│               │   └── AuthRoutesServiceProvider.php # Loads routes.php via Route::prefix
│               └── routes.php                        # /api/auth/* route definitions
│
├── bootstrap/                                        # bootstrap/app.php registers providers
│                                                     # from Infrastructure and Presentation layers
├── config/
├── database/                                         # seeders and factories only
├── resources/
│   └── views/
│       └── emails/
│           └── password-reset.blade.php
├── tests/
│   ├── Unit/
│   │   └── Auth/
│   │       ├── Domain/
│   │       │   ├── CredentialVerifierTest.php
│   │       │   └── PasswordPolicyTest.php
│   │       └── Application/
│   │           ├── LoginUseCaseTest.php
│   │           ├── LogoutUseCaseTest.php
│   │           ├── RequestPasswordResetUseCaseTest.php
│   │           └── CompletePasswordResetUseCaseTest.php
│   ├── Property/
│   │   └── Auth/
│   │       └── AuthPropertiesTest.php                # eris PBT — all 10 properties
│   └── Integration/
│       └── Auth/
│           └── AuthFlowTest.php                      # Full-stack Docker Compose tests
├── composer.json
└── Dockerfile.fpm / Dockerfile.cli
```

### Next.js Frontend (`frontend/`)

The frontend mirrors DDD concepts with a `features/auth/` module that encapsulates all authentication UI, state, and API communication.

```
frontend/
├── src/
│   ├── app/                                          # Next.js App Router pages
│   │   ├── login/
│   │   │   └── page.tsx
│   │   ├── logout/
│   │   │   └── page.tsx
│   │   └── password-reset/
│   │       ├── page.tsx                              # Request reset
│   │       └── complete/
│   │           └── page.tsx                          # Complete reset (?token=...)
│   │
│   ├── features/
│   │   └── auth/
│   │       ├── components/
│   │       │   ├── LoginForm.tsx
│   │       │   ├── LogoutButton.tsx
│   │       │   ├── PasswordResetRequestForm.tsx
│   │       │   └── PasswordResetCompleteForm.tsx
│   │       ├── hooks/
│   │       │   ├── useLogin.ts
│   │       │   ├── useLogout.ts
│   │       │   └── usePasswordReset.ts
│   │       ├── services/
│   │       │   └── authApiClient.ts                  # Typed fetch wrappers for /api/auth/*
│   │       ├── context/
│   │       │   └── AuthContext.tsx                   # Session token state + httpOnly cookie mgmt
│   │       └── types/
│   │           ├── AuthErrors.ts                     # AUTHENTICATION_FAILED, TOKEN_EXPIRED, etc.
│   │           └── AuthResponses.ts                  # API response shapes
│   │
│   └── shared/
│       ├── components/
│       │   └── ErrorMessage.tsx
│       └── utils/
│           └── validation.ts                         # Client-side email/password validators
│
├── tests/
│   ├── unit/
│   │   └── auth/
│   │       ├── LoginForm.test.tsx
│   │       ├── PasswordResetRequestForm.test.tsx
│   │       └── PasswordResetCompleteForm.test.tsx
│   └── property/
│       └── auth/
│           └── authProperties.test.ts                # fast-check PBT — P2, P4, P10
├── package.json
└── Dockerfile
```

---

## Tooling

### mise — Version Management and Task Runner

[mise](https://mise.jdx.dev/) is used for local tooling. It pins exact versions of PHP, Composer, Node, and pnpm so every developer and CI environment uses identical runtimes — no global version managers needed. It also serves as the single task runner, replacing ad-hoc shell scripts.

**Install mise:** `curl https://mise.run | sh`

### `mise.toml` (project root)

```toml
[tools]
php        = "8.5"
composer   = "2.8"
node       = "26"
pnpm       = "9"

[tasks."api:install"]
description = "Install Laravel API dependencies"
run         = "composer install --working-dir=api"

[tasks."api:migrate"]
description = "Run database migrations (requires Docker stack to be up)"
run         = "docker compose exec php-fpm-laravel-api php artisan migrate"

[tasks."api:test"]
description = "Run all backend tests (unit + property + integration)"
run         = "docker compose exec php-fpm-laravel-api php artisan test"

[tasks."api:test:pbt"]
description = "Run property-based tests only"
run         = "docker compose exec php-fpm-laravel-api php artisan test --filter=Property"

[tasks."frontend:install"]
description = "Install Next.js frontend dependencies"
run         = "pnpm install --dir frontend"

[tasks."frontend:test"]
description = "Run all frontend tests (unit + property)"
run         = "pnpm --dir frontend test --run"

[tasks."docker:up"]
description = "Start the full Docker Compose stack"
run         = "docker compose up -d"

[tasks."docker:down"]
description = "Stop and remove Docker Compose containers"
run         = "docker compose down"

[tasks."docker:build"]
description = "Build all Docker images"
run         = "docker compose build"
```

**Common workflow:**

```bash
mise run docker:build       # build images once
mise run docker:up          # start postgres, redis, mailpit, php-fpm, nginx, nextjs
mise run api:migrate        # run migrations
mise run api:test           # run all backend tests
mise run api:test:pbt       # run property-based tests only
mise run frontend:test      # run frontend tests
mise run docker:down        # tear down
```

---

## Components and Interfaces

This section describes the key components organised by DDD layer. The architecture follows strict dependency rules: Presentation → Application → Domain ← Infrastructure. The domain layer has zero framework dependencies.

---

### Presentation Layer (`src/Presentation/Http/Auth/`)

#### Auth API — Laravel Routes

All routes are prefixed `/api/auth` and return JSON.

```
POST /api/auth/login
  Body: { email: string, password: string }
  Response 200: { session_token: string }
  Response 400: { error: "VALIDATION_ERROR", fields: string[] }
  Response 401: { error: "AUTHENTICATION_FAILED" }
  Response 423: { error: "ACCOUNT_LOCKED", retry_after_seconds: int }

POST /api/auth/logout
  Header: Authorization: Bearer <token>
  Response 200: {}
  Response 401: { error: "UNAUTHENTICATED" }

POST /api/auth/password-reset/request
  Body: { email: string }
  Response 200: {}   (always, even for unregistered emails)
  Response 400: { error: "VALIDATION_ERROR", fields: string[] }

POST /api/auth/password-reset/complete
  Body: { token: string, new_password: string }
  Response 200: {}
  Response 400: { error: "VALIDATION_ERROR", message: string }
  Response 400: { error: "TOKEN_EXPIRED" }
  Response 400: { error: "TOKEN_INVALID" }
```

Route definitions live in `src/Presentation/Http/Auth/routes.php` and are loaded by `AuthRoutesServiceProvider`:

```php
// src/Presentation/Http/Auth/routes.php
use Presentation\Http\Auth\Controllers\LoginController;
use Presentation\Http\Auth\Controllers\LogoutController;
use Presentation\Http\Auth\Controllers\RequestPasswordResetController;
use Presentation\Http\Auth\Controllers\CompletePasswordResetController;

Route::post('/login',                    LoginController::class);
Route::post('/logout',                   LogoutController::class);
Route::post('/password-reset/request',   RequestPasswordResetController::class);
Route::post('/password-reset/complete',  CompletePasswordResetController::class);
```

```php
// src/Presentation/Http/Auth/Providers/AuthRoutesServiceProvider.php
namespace Presentation\Http\Auth\Providers;

class AuthRoutesServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        Route::prefix('api/auth')->group(__DIR__ . '/../routes.php');
    }
}
```

`bootstrap/app.php` registers both `AuthServiceProvider` (Infrastructure) and `AuthRoutesServiceProvider` (Presentation).

#### Invokable Controllers (Presentation Layer)

Each controller handles exactly one route. Controllers are thin — they validate HTTP input via a `FormRequest` and delegate entirely to the corresponding use case.

```php
namespace Presentation\Http\Auth\Controllers;

class LoginController extends Controller
{
    public function __construct(
        private readonly LoginUseCase $loginUseCase,
    ) {}

    public function __invoke(LoginRequest $request): JsonResponse
}
```

```php
namespace Presentation\Http\Auth\Controllers;

class LogoutController extends Controller
{
    public function __construct(
        private readonly LogoutUseCase $logoutUseCase,
    ) {}

    public function __invoke(Request $request): JsonResponse
}
```

```php
namespace Presentation\Http\Auth\Controllers;

class RequestPasswordResetController extends Controller
{
    public function __construct(
        private readonly RequestPasswordResetUseCase $requestResetUseCase,
    ) {}

    public function __invoke(PasswordResetRequestForm $request): JsonResponse
}
```

```php
namespace Presentation\Http\Auth\Controllers;

class CompletePasswordResetController extends Controller
{
    public function __construct(
        private readonly CompletePasswordResetUseCase $completeResetUseCase,
    ) {}

    public function __invoke(PasswordResetCompleteForm $request): JsonResponse
}
```

---

### Application Layer (`src/Application/Auth/`)

#### Use Cases

Each use case is a single-responsibility application service that orchestrates domain logic, repositories, and infrastructure adapters.

##### LoginUseCase

```php
namespace Application\Auth\UseCases;

class LoginUseCase
{
    public function __construct(
        private readonly UserRepositoryInterface $userRepo,
        private readonly SessionRepositoryInterface $sessionRepo,
        private readonly CredentialVerifier $credentialVerifier,
        private readonly RedisRateLimiter $rateLimiter,
        private readonly EventDispatcher $events,
    ) {}

    public function execute(LoginCommand $command): SessionToken
    // Throws: AccountLockedException, AuthenticationFailedException
}
```

##### LogoutUseCase

```php
namespace Application\Auth\UseCases;

class LogoutUseCase
{
    public function __construct(
        private readonly SessionRepositoryInterface $sessionRepo,
        private readonly EventDispatcher $events,
    ) {}

    public function execute(LogoutCommand $command): void
}
```

##### RequestPasswordResetUseCase

```php
namespace Application\Auth\UseCases;

class RequestPasswordResetUseCase
{
    public function __construct(
        private readonly UserRepositoryInterface $userRepo,
        private readonly ResetTokenRepositoryInterface $resetTokenRepo,
        private readonly LaravelMailAdapter $mailAdapter,
        private readonly EventDispatcher $events,
    ) {}

    public function execute(RequestPasswordResetCommand $command): void
}
```

##### CompletePasswordResetUseCase

```php
namespace Application\Auth\UseCases;

class CompletePasswordResetUseCase
{
    public function __construct(
        private readonly UserRepositoryInterface $userRepo,
        private readonly SessionRepositoryInterface $sessionRepo,
        private readonly ResetTokenRepositoryInterface $resetTokenRepo,
        private readonly PasswordPolicy $passwordPolicy,
        private readonly EventDispatcher $events,
    ) {}

    public function execute(CompletePasswordResetCommand $command): void
    // Throws: TokenExpiredException, TokenInvalidException, ValidationException
}
```

---

### Domain Layer (`src/Domain/Auth/`)

Pure PHP — no framework dependencies. Contains entities, value objects, domain services, repository interfaces, and domain events.

#### Domain Services

##### CredentialVerifier

Verifies a submitted (email, password) pair against stored credentials. Always returns the same failure shape regardless of whether the email was not found or the password was wrong (requirements 1.3, 1.4).

```php
namespace Domain\Auth\Services;

class CredentialVerifier
{
    public function verify(Email $email, string $plainPassword, UserRepositoryInterface $userRepo): VerifyResult
    // Returns VerifyResult::success(User) or VerifyResult::failure()
}
```

##### PasswordPolicy

Enforces password composition rules (requirements 5.1, 5.2).

```php
namespace Domain\Auth\Services;

class PasswordPolicy
{
    public function validate(string $password): PolicyResult
    // PolicyResult::valid() or PolicyResult::invalid(violations: string[])

    public function hash(string $plaintext): HashedPassword
    public function verify(string $plaintext, HashedPassword $hash): bool
}
```

#### Value Objects

##### Email

```php
namespace Domain\Auth\ValueObjects;

final readonly class Email
{
    public function __construct(public string $value)
    {
        if (!filter_var($value, FILTER_VALIDATE_EMAIL)) {
            throw new InvalidArgumentException("Invalid email format");
        }
    }
}
```

##### HashedPassword

```php
namespace Domain\Auth\ValueObjects;

final readonly class HashedPassword
{
    public function __construct(public string $hash) {}
}
```

##### SessionToken / ResetToken

```php
namespace Domain\Auth\ValueObjects;

final readonly class SessionToken
{
    public function __construct(
        public string $raw,        // 64-char hex string
        public string $hash,       // SHA-256 hash for DB storage
    ) {}
}
```

#### Entities

##### User

```php
namespace Domain\Auth\Entities;

class User
{
    public function __construct(
        public readonly string $id,
        public Email $email,
        public HashedPassword $passwordHash,
        public DateTimeImmutable $createdAt,
        public DateTimeImmutable $updatedAt,
    ) {}

    public function changePassword(HashedPassword $newHash): void
}
```

##### Session

```php
namespace Domain\Auth\Entities;

class Session
{
    public function __construct(
        public readonly string $id,
        public readonly string $userId,
        public readonly string $tokenHash,
        public DateTimeImmutable $createdAt,
        public DateTimeImmutable $lastActivityAt,
        public DateTimeImmutable $expiresAt,
    ) {}

    public function isExpired(DateTimeImmutable $now, int $inactivityTimeoutSeconds): bool
    public function touch(DateTimeImmutable $now): void
}
```

##### PasswordResetToken

```php
namespace Domain\Auth\Entities;

class PasswordResetToken
{
    public function __construct(
        public readonly string $id,
        public readonly string $userId,
        public readonly string $tokenHash,
        public DateTimeImmutable $createdAt,
        public DateTimeImmutable $expiresAt,
        public ?DateTimeImmutable $usedAt,
    ) {}

    public function isExpired(DateTimeImmutable $now): bool
    public function isUsed(): bool
    public function markUsed(DateTimeImmutable $now): void
}
```

#### Repository Interfaces

```php
namespace Domain\Auth\Repositories;

interface UserRepositoryInterface
{
    public function findById(string $id): ?User;
    public function findByEmail(Email $email): ?User;
    public function save(User $user): void;
}

interface SessionRepositoryInterface
{
    public function create(string $userId, SessionToken $token, DateTimeImmutable $expiresAt): Session;
    public function findByTokenHash(string $hash): ?Session;
    public function invalidate(string $tokenHash): void;
    public function invalidateAllForUser(string $userId): void;
    public function purgeExpired(DateTimeImmutable $now): void;
}

interface ResetTokenRepositoryInterface
{
    public function create(string $userId, ResetToken $token, DateTimeImmutable $expiresAt): PasswordResetToken;
    public function findByTokenHash(string $hash): ?PasswordResetToken;
    public function invalidateForUser(string $userId): void;
    public function save(PasswordResetToken $token): void;
}
```

#### Domain Events

```php
namespace Domain\Auth\Events;

final readonly class UserLoggedIn
{
    public function __construct(
        public string $userId,
        public DateTimeImmutable $occurredAt,
    ) {}
}

final readonly class UserLoggedOut { /* ... */ }
final readonly class PasswordResetRequested { /* ... */ }
final readonly class PasswordResetCompleted { /* ... */ }
```

---

### Infrastructure Layer (`src/Infrastructure/Auth/`)

Implements repository interfaces using Eloquent, provides Redis rate limiting, handles email dispatch via Laravel Mail, and registers service container bindings via `AuthServiceProvider`.

#### AuthServiceProvider

Registered in `bootstrap/app.php`. Binds domain repository interfaces to their Eloquent implementations in Laravel's service container.

```php
namespace Infrastructure\Auth\Providers;

class AuthServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->bind(UserRepositoryInterface::class, EloquentUserRepository::class);
        $this->app->bind(SessionRepositoryInterface::class, EloquentSessionRepository::class);
        $this->app->bind(ResetTokenRepositoryInterface::class, EloquentResetTokenRepository::class);
    }
}
```

#### DatabaseServiceProvider

Registered in `bootstrap/app.php`. Loads migrations from the Domain layer so `php artisan migrate` discovers them without relying on the default `database/migrations/` path.

```php
namespace Domain\Database\Providers;

class DatabaseServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        $this->loadMigrationsFrom(__DIR__ . '/../Migrations');
    }
}
```

#### Eloquent Repository Implementations

```php
namespace Infrastructure\Auth\Repositories;

class EloquentUserRepository implements UserRepositoryInterface
{
    public function findById(string $id): ?User { /* Eloquent query → domain User */ }
    public function findByEmail(Email $email): ?User { /* ... */ }
    public function save(User $user): void { /* domain User → Eloquent model */ }
}

class EloquentSessionRepository implements SessionRepositoryInterface { /* ... */ }
class EloquentResetTokenRepository implements ResetTokenRepositoryInterface { /* ... */ }
```

#### RedisRateLimiter

Tracks failed login attempts per email address in Redis using a sliding window.

```php
namespace Infrastructure\Auth\RateLimiting;

class RedisRateLimiter
{
    public function recordFailure(Email $email): void
    public function isLocked(Email $email): LockStatus
    // LockStatus::unlocked() or LockStatus::locked(retryAfterSeconds: int)
    public function clearAttempts(Email $email): void
}
```

Uses Laravel's `RateLimiter` facade backed by Redis. Key: `auth.failed:{sha256(email)}`.

#### LaravelMailAdapter

```php
namespace Infrastructure\Auth\Mail;

class LaravelMailAdapter
{
    public function sendPasswordResetEmail(Email $toAddress, ResetToken $token): void
    {
        // Dispatches SendPasswordResetEmailJob to queue
    }
}
```

#### SendPasswordResetEmailJob (Queued Laravel Job)

```php
namespace Infrastructure\Auth\Queue;

class SendPasswordResetEmailJob implements ShouldQueue
{
    public function __construct(
        public readonly string $toAddress,
        public readonly string $rawToken,
    ) {}

    public function handle(Mailer $mailer): void
    {
        $mailer->to($this->toAddress)->send(new PasswordResetMail($this->rawToken));
    }
}
```

#### PasswordResetMail (Laravel Mailable)

```php
namespace Infrastructure\Auth\Mail;

class PasswordResetMail extends Mailable
{
    public function __construct(public readonly string $resetUrl) {}
    public function content(): Content  // renders reset email template
}
```

---

### Frontend — Next.js Pages and Components

```
/login          — LoginPage (form: email + password → POST /api/auth/login)
/logout         — triggers POST /api/auth/logout, clears token, redirects to /login
/password-reset — ResetRequestPage (form: email → POST /api/auth/password-reset/request)
/password-reset/complete?token=... — ResetCompletePage (form: new_password → POST /api/auth/password-reset/complete)
```

Auth state is managed via a React context (`AuthContext` in `src/features/auth/context/`) that stores the session token in an `httpOnly` cookie (set by the Next.js API route layer acting as a BFF proxy, so the raw token is never exposed to client-side JS).

#### authApiClient (Service Layer)

```ts
// src/features/auth/services/authApiClient.ts
export async function login(email: string, password: string): Promise<SessionTokenResponse>
export async function logout(token: string): Promise<void>
export async function requestPasswordReset(email: string): Promise<void>
export async function completePasswordReset(token: string, newPassword: string): Promise<void>
```

All API calls are typed and throw structured errors matching the backend error codes (`AUTHENTICATION_FAILED`, `TOKEN_EXPIRED`, etc.).

---

## Data Models

### users (Eloquent Model: `User`)

```sql
CREATE TABLE users (
    id            UUID PRIMARY KEY DEFAULT gen_random_uuid(),
    email         VARCHAR(255) NOT NULL UNIQUE,
    password_hash VARCHAR(255) NOT NULL,
    created_at    TIMESTAMPTZ NOT NULL DEFAULT NOW(),
    updated_at    TIMESTAMPTZ NOT NULL DEFAULT NOW()
);
CREATE INDEX idx_users_email ON users (email);
```

### sessions (Eloquent Model: `Session`)

```sql
CREATE TABLE sessions (
    id               UUID PRIMARY KEY DEFAULT gen_random_uuid(),
    user_id          UUID NOT NULL REFERENCES users(id) ON DELETE CASCADE,
    token_hash       VARCHAR(64) NOT NULL UNIQUE,  -- SHA-256 hex
    created_at       TIMESTAMPTZ NOT NULL DEFAULT NOW(),
    last_activity_at TIMESTAMPTZ NOT NULL DEFAULT NOW(),
    expires_at       TIMESTAMPTZ NOT NULL
);
CREATE INDEX idx_sessions_token_hash ON sessions (token_hash);
CREATE INDEX idx_sessions_user_id    ON sessions (user_id);
```

A session is valid only when `NOW() < expires_at` AND `NOW() < last_activity_at + inactivity_timeout`. The `expires_at` column enforces the absolute maximum session duration (requirement 2.5). Inactivity expiry (requirement 2.4) is computed at validation time from `last_activity_at`.

### password_reset_tokens (Eloquent Model: `PasswordResetToken`)

```sql
CREATE TABLE password_reset_tokens (
    id         UUID PRIMARY KEY DEFAULT gen_random_uuid(),
    user_id    UUID NOT NULL UNIQUE REFERENCES users(id) ON DELETE CASCADE,
    token_hash VARCHAR(64) NOT NULL UNIQUE,  -- SHA-256 hex
    created_at TIMESTAMPTZ NOT NULL DEFAULT NOW(),
    expires_at TIMESTAMPTZ NOT NULL,         -- created_at + 60 minutes
    used_at    TIMESTAMPTZ
);
CREATE INDEX idx_prt_token_hash ON password_reset_tokens (token_hash);
```

The `UNIQUE` constraint on `user_id` enforces one-active-token-per-user at the database level (requirement 3.5).

### Redis Keys (rate limiting)

```
auth.failed:{sha256(email)}   — sorted set of attempt timestamps (TTL: 15 minutes)
```

---

## Correctness Properties

*A property is a characteristic or behavior that should hold true across all valid executions of a system — essentially, a formal statement about what the system should do. Properties serve as the bridge between human-readable specifications and machine-verifiable correctness guarantees.*

### Property 1: Valid credentials always produce a usable session token

*For any* registered user with valid credentials, submitting those credentials to login SHALL return a session token that is subsequently accepted by the session validator.

**Validates: Requirements 1.2, 1.7**

### Property 2: Authentication failure response is identical regardless of failure reason

*For any* login attempt that fails — whether because the email is unregistered or the password is wrong — the response returned SHALL be structurally identical (same error code, same HTTP status, no field distinguishing the failure reason).

**Validates: Requirements 1.3, 1.4**

### Property 3: Invalidated session token is always rejected

*For any* session token that has been invalidated (via logout, password reset, or expiry), all subsequent requests presenting that token SHALL be rejected.

**Validates: Requirements 2.1, 2.2, 4.6**

### Property 4: Password policy validation is consistent

*For any* password string, the result of `validatePolicy` SHALL be deterministic and consistent: a password that passes validation SHALL always pass, and one that fails SHALL always fail with the same violations listed.

**Validates: Requirements 5.1, 5.2**

### Property 5: Password hashing round-trip never exposes plaintext

*For any* password that satisfies the password policy, hashing it and then verifying the original plaintext against the hash SHALL return true; verifying any different string against the hash SHALL return false; and the stored hash SHALL never equal the plaintext.

**Validates: Requirements 5.3**

### Property 6: Reset token is single-use

*For any* valid password reset token that has been successfully consumed, any subsequent attempt to use that same token SHALL be rejected as invalid.

**Validates: Requirements 4.3**

### Property 7: Only one active reset token per user

*For any* user, generating a new password reset token SHALL cause all previously issued reset tokens for that user to be rejected as invalid.

**Validates: Requirements 3.5**

### Property 8: Account lockout triggers after threshold

*For any* email address, after exactly 5 consecutive failed login attempts within a 15-minute window, the next login attempt SHALL return a lockout response rather than an authentication failure response.

**Validates: Requirements 1.8**

### Property 9: Password reset invalidates all existing sessions

*For any* user with one or more active sessions, successfully completing a password reset SHALL cause all of those sessions to be rejected as invalid.

**Validates: Requirements 4.6**

### Property 10: Invalid email format is rejected before credential lookup

*For any* string that is not a valid email address format, submitting it as the email field in a login or reset request SHALL return a validation error without performing any credential lookup.

**Validates: Requirements 1.6, 3.3**

---

## Error Handling

| Scenario | HTTP Status | Error Code |
|---|---|---|
| Missing required field | 400 | `VALIDATION_ERROR` with field name(s) |
| Invalid email format | 400 | `VALIDATION_ERROR` |
| Wrong credentials (any reason) | 401 | `AUTHENTICATION_FAILED` |
| Account locked | 423 | `ACCOUNT_LOCKED` with `retry_after_seconds` |
| Unauthenticated logout | 401 | `UNAUTHENTICATED` |
| Expired reset token | 400 | `TOKEN_EXPIRED` |
| Invalid/used/missing reset token | 400 | `TOKEN_INVALID` |
| Password policy violation | 400 | `VALIDATION_ERROR` with violation list |
| Password same as current | 400 | `VALIDATION_ERROR` with specific message |
| Email service failure | — | Log error, return 200 to caller (do not reveal delivery failure) |
| Database error | 500 | `INTERNAL_ERROR` (no internal details exposed) |

**Security-sensitive error handling rules:**
- Login failures MUST NOT distinguish between unknown email and wrong password
- Password reset requests MUST return 200 for both registered and unregistered emails
- Internal error details (stack traces, SQL errors) MUST NOT be included in API responses
- Laravel's `Handler::render()` is overridden to sanitize all 5xx responses in production
- Reset token errors (expired vs. used vs. not found) MAY be distinguished to aid UX, since the token itself is already known to the caller

---

## Testing Strategy

### Backend — PHPUnit + eris (Property-Based Testing)

**Unit tests** (PHPUnit, example-based) cover specific behaviors and edge cases:

- Login with valid credentials returns a token
- Login with unknown email returns `AUTHENTICATION_FAILED`
- Login with wrong password returns `AUTHENTICATION_FAILED`
- Login with missing `email` field returns `VALIDATION_ERROR` naming the field
- Login with missing `password` field returns `VALIDATION_ERROR` naming the field
- Logout with valid token succeeds
- Logout with already-invalidated token returns `UNAUTHENTICATED`
- Reset request for unregistered email returns 200 (same as registered)
- Reset token expires after 60 minutes
- Completing reset with used token returns `TOKEN_INVALID`
- Completing reset with non-existent token returns `TOKEN_INVALID`
- Password with 7 characters fails policy
- Password with no uppercase fails policy
- Password with no digit fails policy
- Password with no special character fails policy
- Resetting to current password returns `VALIDATION_ERROR`

**Property-based tests** use [eris](https://github.com/giorgiosironi/eris) with PHPUnit. Each test runs a minimum of **100 iterations**.

Each test is tagged with a docblock comment:
```php
/** @feature user-authentication @property 1 Valid credentials always produce a usable session token */
```

| Property | eris Generator Strategy |
|---|---|
| P1: Valid credentials → usable token | `Generator\string()` for email/password; create user; login; assert token accepted |
| P2: Failure response is identical | Generate unknown-email cases and wrong-password cases; assert responses structurally equal |
| P3: Invalidated token always rejected | Generate sessions; invalidate via logout/reset; assert all subsequent validations return null |
| P4: Policy validation is deterministic | `Generator\string()` for arbitrary passwords; assert repeated `validatePolicy` calls return identical result |
| P5: Hash round-trip | Generate policy-compliant passwords; hash; verify original passes, any mutation fails, hash ≠ plaintext |
| P6: Reset token is single-use | Generate reset flows; consume token; assert re-use returns `TOKEN_INVALID` |
| P7: One active reset token per user | Generate N sequential reset requests for same user; assert only latest token is valid |
| P8: Lockout after threshold | Generate email addresses; simulate 5 failed attempts; assert 6th returns lockout |
| P9: Password reset invalidates sessions | Generate users with N active sessions; complete reset; assert all N sessions rejected |
| P10: Invalid email format rejected | `Generator\string()` filtered to non-RFC-5322 strings; assert validation error, no DB query |

### Frontend — Jest + fast-check

**Unit/component tests** (Jest + React Testing Library):

- `LoginPage` renders email and password fields
- `LoginPage` shows error message on `AUTHENTICATION_FAILED` response
- `LoginPage` shows field-level errors on `VALIDATION_ERROR` response
- `LoginPage` shows lockout message on `ACCOUNT_LOCKED` response
- `ResetRequestPage` shows success message regardless of email registration status
- `ResetCompletePage` shows `TOKEN_EXPIRED` error appropriately
- `ResetCompletePage` shows password policy violation errors

**Property-based tests** use [fast-check](https://fast-check.io/) with Jest. Each test runs a minimum of **100 iterations**.

Each test is tagged:
```ts
// Feature: user-authentication, Property N: <property text>
```

| Property | fast-check Arbitrary Strategy |
|---|---|
| P2: Failure response identical | `fc.string()` for emails/passwords; mock API; assert response shapes equal |
| P4: Policy validation consistent | `fc.string()` for passwords; assert client-side validation is deterministic |
| P10: Invalid email rejected client-side | `fc.string()` filtered to non-email strings; assert form validation fires before submit |

### Integration Tests

Run against the full Docker Compose stack (see below):

- Full login → access protected resource → logout flow
- Full password reset flow (request → Mailpit → complete → login with new password)
- Session expiry via inactivity timeout (configured short for tests)
- Session expiry via absolute max duration
- Account lockout and automatic unlock after 15 minutes
- Concurrent reset token generation (race condition: only one token survives per user)
- Queue worker processes `SendPasswordResetEmailJob` and delivers to Mailpit

---

## Docker Compose Setup

Local development environment. All services communicate on an internal `app-network` bridge network.

### Services

#### `postgres`
PostgreSQL 18 database.
```yaml
postgres:
  image: postgres:18-alpine
  environment:
    POSTGRES_DB: auth_db
    POSTGRES_USER: auth_user
    POSTGRES_PASSWORD: secret
  volumes:
    - postgres_data:/var/lib/postgresql/data
  networks: [app-network]
```

#### `redis`
Redis 7 — used for sessions cache, rate-limit counters, and queue backend.
```yaml
redis:
  image: redis:7-alpine
  networks: [app-network]
```

#### `mailpit`
Mailpit — local SMTP server with web UI for inspecting outbound emails during development and integration tests.
```yaml
mailpit:
  image: axllent/mailpit:latest
  ports:
    - "8025:8025"   # web UI
    - "1025:1025"   # SMTP
  networks: [app-network]
```

#### `php-fpm-laravel-api`
PHP-FPM process serving the Laravel API application.
```yaml
php-fpm-laravel-api:
  build:
    context: ./api
    dockerfile: Dockerfile.fpm
  volumes:
    - ./api:/var/www/api
  environment:
    APP_ENV: local
    DB_CONNECTION: pgsql
    DB_HOST: postgres
    DB_DATABASE: auth_db
    DB_USERNAME: auth_user
    DB_PASSWORD: secret
    REDIS_HOST: redis
    MAIL_HOST: mailpit
    MAIL_PORT: 1025
    QUEUE_CONNECTION: redis
  depends_on: [postgres, redis, mailpit]
  networks: [app-network]
```

#### `nginx-api`
nginx reverse proxy that forwards requests to `php-fpm-laravel-api` via FastCGI.
```yaml
nginx-api:
  image: nginx:1.25-alpine
  volumes:
    - ./api:/var/www/api
    - ./docker/nginx/api.conf:/etc/nginx/conf.d/default.conf
  ports:
    - "8080:80"
  depends_on: [php-fpm-laravel-api]
  networks: [app-network]
```

#### `php-cli-laravel-queue-worker-default`
Runs `php artisan queue:work --queue=default` to process queued jobs (e.g., `SendPasswordResetEmailJob`).
```yaml
php-cli-laravel-queue-worker-default:
  build:
    context: ./api
    dockerfile: Dockerfile.cli
  command: ["php", "artisan", "queue:work", "--queue=default", "--tries=3"]
  volumes:
    - ./api:/var/www/api
  environment:
    APP_ENV: local
    DB_CONNECTION: pgsql
    DB_HOST: postgres
    DB_DATABASE: auth_db
    DB_USERNAME: auth_user
    DB_PASSWORD: secret
    REDIS_HOST: redis
    MAIL_HOST: mailpit
    MAIL_PORT: 1025
    QUEUE_CONNECTION: redis
  depends_on: [postgres, redis, mailpit]
  networks: [app-network]
```

#### `php-cli-laravel-background`
Runs `php artisan schedule:work` for scheduled tasks — purging expired sessions, pruning stale failed-login records, etc.
```yaml
php-cli-laravel-background:
  build:
    context: ./api
    dockerfile: Dockerfile.cli
  command: ["php", "artisan", "schedule:work"]
  volumes:
    - ./api:/var/www/api
  environment:
    APP_ENV: local
    DB_CONNECTION: pgsql
    DB_HOST: postgres
    DB_DATABASE: auth_db
    DB_USERNAME: auth_user
    DB_PASSWORD: secret
    REDIS_HOST: redis
    QUEUE_CONNECTION: redis
  depends_on: [postgres, redis]
  networks: [app-network]
```

#### `nextjs`
Next.js development server (or production build served by Node).
```yaml
nextjs:
  build:
    context: ./frontend
    dockerfile: Dockerfile
  volumes:
    - ./frontend:/app
  environment:
    NEXT_PUBLIC_API_URL: http://nginx-api
    API_URL: http://nginx-api   # server-side fetch (BFF proxy routes)
  depends_on: [nginx-api]
  networks: [app-network]
```

#### `nginx-frontend`
nginx reverse proxy serving the Next.js frontend.
```yaml
nginx-frontend:
  image: nginx:1.25-alpine
  volumes:
    - ./docker/nginx/frontend.conf:/etc/nginx/conf.d/default.conf
  ports:
    - "3000:80"
  depends_on: [nextjs]
  networks: [app-network]
```

### Volumes and Networks

```yaml
volumes:
  postgres_data:

networks:
  app-network:
    driver: bridge
```

### Port Summary

| Port | Service |
|---|---|
| `3000` | nginx-frontend → Next.js UI |
| `8080` | nginx-api → Laravel API |
| `8025` | Mailpit web UI |
| `1025` | Mailpit SMTP (internal) |
