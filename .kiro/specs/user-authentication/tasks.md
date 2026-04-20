# Implementation Plan: User Authentication

## Overview

Implement a stateless token-based authentication system across a Laravel 13 DDD API backend and a Next.js 16 frontend. The plan follows the layered architecture (Domain → Application → Infrastructure → Presentation → Frontend) so each step builds on a stable foundation before the next layer is wired in.

## Tasks

- [x] 1. Bootstrap project scaffolding and tooling
  - Create `api/` Laravel 13 project skeleton (no default `app/` auth scaffolding)
  - Create `frontend/` Next.js 16 project skeleton with TypeScript and App Router
  - Add `mise.toml` at the project root with pinned tool versions (PHP 8.5, Composer 2.8, Node 26, pnpm 9) and all task definitions (`api:install`, `api:migrate`, `api:test`, `api:test:pbt`, `frontend:install`, `frontend:test`, `docker:up`, `docker:down`, `docker:build`)
  - Create `docker-compose.yml` with all 8 services: `postgres`, `redis`, `mailpit`, `php-fpm-laravel-api`, `nginx-api`, `php-cli-laravel-queue-worker-default`, `php-cli-laravel-background`, `nextjs`, `nginx-frontend`
  - Create `api/Dockerfile.fpm` and `api/Dockerfile.cli`
  - Create `frontend/Dockerfile`
  - Create `docker/nginx/api.conf` and `docker/nginx/frontend.conf`
  - Configure `api/composer.json` to autoload `src/` under the root namespace and add `giorgiosironi/eris` as a dev dependency
  - Configure `frontend/package.json` with Jest, React Testing Library, and `fast-check` as dev dependencies
  - _Requirements: all (infrastructure prerequisite)_

- [x] 2. Domain layer — value objects, entities, and repository interfaces
  - [x] 2.1 Implement value objects
    - Create `src/Domain/Auth/ValueObjects/Email.php` — validates format via `filter_var`; throws `InvalidArgumentException` on invalid input
    - Create `src/Domain/Auth/ValueObjects/HashedPassword.php` — wraps bcrypt hash string
    - Create `src/Domain/Auth/ValueObjects/SessionToken.php` — holds `raw` (64-char hex) and `hash` (SHA-256) pair
    - Create `src/Domain/Auth/ValueObjects/ResetToken.php` — same raw/hash pair pattern as `SessionToken`
    - _Requirements: 1.1, 1.6, 5.3_

  - [ ]* 2.2 Write property test for Email value object (P10)
    - **Property 10: Invalid email format is rejected before credential lookup**
    - Use `eris` `Generator\string()` filtered to non-RFC-5322 strings; assert `new Email($value)` always throws; assert valid email strings never throw
    - **Validates: Requirements 1.6, 3.3**

  - [x] 2.3 Implement domain entities
    - Create `src/Domain/Auth/Entities/User.php` with `changePassword(HashedPassword)` method
    - Create `src/Domain/Auth/Entities/Session.php` with `isExpired(DateTimeImmutable, int): bool` and `touch(DateTimeImmutable): void`
    - Create `src/Domain/Auth/Entities/PasswordResetToken.php` with `isExpired`, `isUsed`, and `markUsed` methods
    - _Requirements: 1.2, 2.4, 2.5, 3.4, 4.1, 4.3_

  - [x] 2.4 Implement repository interfaces
    - Create `src/Domain/Auth/Repositories/UserRepositoryInterface.php` (`findById`, `findByEmail`, `save`)
    - Create `src/Domain/Auth/Repositories/SessionRepositoryInterface.php` (`create`, `findByTokenHash`, `invalidate`, `invalidateAllForUser`, `purgeExpired`)
    - Create `src/Domain/Auth/Repositories/ResetTokenRepositoryInterface.php` (`create`, `findByTokenHash`, `invalidateForUser`, `save`)
    - _Requirements: 1.2, 2.1, 3.1, 4.1_

  - [x] 2.5 Implement domain events
    - Create `src/Domain/Auth/Events/UserLoggedIn.php`, `UserLoggedOut.php`, `PasswordResetRequested.php`, `PasswordResetCompleted.php` as `final readonly` classes
    - _Requirements: 1.2, 2.1, 3.1, 4.1_

- [x] 3. Domain layer — domain services
  - [x] 3.1 Implement `CredentialVerifier`
    - Create `src/Domain/Auth/Services/CredentialVerifier.php`
    - `verify(Email, string, UserRepositoryInterface): VerifyResult` — returns `VerifyResult::success(User)` or `VerifyResult::failure()` with no distinction between unknown email and wrong password
    - _Requirements: 1.2, 1.3, 1.4_

  - [ ]* 3.2 Write property test for `CredentialVerifier` (P2)
    - **Property 2: Authentication failure response is identical regardless of failure reason**
    - Generate unknown-email cases and wrong-password cases; assert both return structurally identical `VerifyResult::failure()` with no distinguishing field
    - **Validates: Requirements 1.3, 1.4**

  - [x] 3.3 Implement `PasswordPolicy`
    - Create `src/Domain/Auth/Services/PasswordPolicy.php`
    - `validate(string): PolicyResult` — enforces min 8 chars, uppercase, lowercase, digit, special character
    - `hash(string): HashedPassword` — bcrypt via Laravel `Hash` facade (cost ≥ 12)
    - `verify(string, HashedPassword): bool`
    - _Requirements: 5.1, 5.2, 5.3_

  - [ ]* 3.4 Write property test for `PasswordPolicy` — determinism (P4)
    - **Property 4: Password policy validation is consistent**
    - Use `eris` `Generator\string()` for arbitrary passwords; call `validate()` twice on the same input; assert results are identical
    - **Validates: Requirements 5.1, 5.2**

  - [ ]* 3.5 Write property test for `PasswordPolicy` — hash round-trip (P5)
    - **Property 5: Password hashing round-trip never exposes plaintext**
    - Generate policy-compliant passwords; hash; assert `verify(original, hash)` is true; assert `verify(mutated, hash)` is false; assert `hash !== plaintext`
    - **Validates: Requirements 5.3**

  - [ ]* 3.6 Write unit tests for domain services
    - `CredentialVerifierTest`: valid credentials succeed; unknown email fails; wrong password fails
    - `PasswordPolicyTest`: 7-char password fails; no uppercase fails; no digit fails; no special char fails; valid password passes
    - _Requirements: 1.2, 1.3, 1.4, 5.1, 5.2_

- [x] 4. Checkpoint — domain layer complete
  - Ensure all domain layer unit and property tests pass, ask the user if questions arise.

- [x] 5. Database migrations and `DatabaseServiceProvider`
  - Create `src/Domain/Database/Migrations/0001_create_users_table.php` — `users` table with UUID PK, `email` UNIQUE, `password_hash`, timestamps
  - Create `src/Domain/Database/Migrations/0002_create_sessions_table.php` — `sessions` table with UUID PK, `user_id` FK, `token_hash` UNIQUE, `last_activity_at`, `expires_at`
  - Create `src/Domain/Database/Migrations/0003_create_password_reset_tokens_table.php` — `password_reset_tokens` table with UUID PK, `user_id` UNIQUE FK, `token_hash` UNIQUE, `expires_at`, `used_at`
  - Create `src/Domain/Database/Providers/DatabaseServiceProvider.php` — calls `$this->loadMigrationsFrom(__DIR__ . '/../Migrations')`
  - Register `DatabaseServiceProvider` in `bootstrap/app.php`
  - _Requirements: 1.2, 2.1, 3.1, 3.5, 4.1_

- [x] 6. Infrastructure layer — Eloquent repositories
  - [x] 6.1 Implement `EloquentUserRepository`
    - Create `src/Infrastructure/Auth/Repositories/EloquentUserRepository.php` implementing `UserRepositoryInterface`
    - Map Eloquent model ↔ domain `User` entity in both directions
    - _Requirements: 1.2, 1.3, 4.1_

  - [x] 6.2 Implement `EloquentSessionRepository`
    - Create `src/Infrastructure/Auth/Repositories/EloquentSessionRepository.php` implementing `SessionRepositoryInterface`
    - Implement `invalidateAllForUser` (used on password reset) and `purgeExpired`
    - _Requirements: 2.1, 2.2, 4.6_

  - [x] 6.3 Implement `EloquentResetTokenRepository`
    - Create `src/Infrastructure/Auth/Repositories/EloquentResetTokenRepository.php` implementing `ResetTokenRepositoryInterface`
    - `invalidateForUser` deletes existing row; DB `UNIQUE` on `user_id` enforces one-token-per-user
    - _Requirements: 3.1, 3.5, 4.1, 4.3_

- [x] 7. Infrastructure layer — rate limiter, mail adapter, and queue job
  - [x] 7.1 Implement `RedisRateLimiter`
    - Create `src/Infrastructure/Auth/RateLimiting/RedisRateLimiter.php`
    - `recordFailure(Email)`, `isLocked(Email): LockStatus`, `clearAttempts(Email)`
    - Key: `auth.failed:{sha256(email)}`; sliding window of 5 attempts in 15 minutes
    - _Requirements: 1.8_

  - [x] 7.2 Implement mail adapter and queued job
    - Create `src/Infrastructure/Auth/Mail/LaravelMailAdapter.php` — dispatches `SendPasswordResetEmailJob`
    - Create `src/Infrastructure/Auth/Queue/SendPasswordResetEmailJob.php` implementing `ShouldQueue`
    - Create `src/Infrastructure/Auth/Mail/PasswordResetMail.php` extending `Mailable`
    - Create `resources/views/emails/password-reset.blade.php` — reset link email template
    - _Requirements: 3.1_

  - [x] 7.3 Implement `AuthServiceProvider`
    - Create `src/Infrastructure/Auth/Providers/AuthServiceProvider.php`
    - Bind `UserRepositoryInterface → EloquentUserRepository`, `SessionRepositoryInterface → EloquentSessionRepository`, `ResetTokenRepositoryInterface → EloquentResetTokenRepository`
    - Register `AuthServiceProvider` in `bootstrap/app.php`
    - _Requirements: all (DI wiring)_

- [x] 8. Application layer — use cases and DTOs
  - [x] 8.1 Implement DTOs and application exceptions
    - Create `src/Application/Auth/DTOs/LoginCommand.php`, `LogoutCommand.php`, `RequestPasswordResetCommand.php`, `CompletePasswordResetCommand.php`
    - Create `src/Application/Auth/Exceptions/AuthenticationFailedException.php`, `AccountLockedException.php`, `TokenExpiredException.php`, `TokenInvalidException.php`
    - _Requirements: 1.1, 2.1, 3.1, 4.1_

  - [x] 8.2 Implement `LoginUseCase`
    - Create `src/Application/Auth/UseCases/LoginUseCase.php`
    - Check rate limiter → verify credentials → create session → dispatch `UserLoggedIn` event → return `SessionToken`
    - Throw `AccountLockedException` if locked; throw `AuthenticationFailedException` if credentials invalid; clear attempts on success
    - _Requirements: 1.2, 1.3, 1.4, 1.8_

  - [ ]* 8.3 Write property test for `LoginUseCase` (P1)
    - **Property 1: Valid credentials always produce a usable session token**
    - Generate registered users with valid credentials; call `LoginUseCase::execute`; assert returned token is accepted by `SessionRepositoryInterface::findByTokenHash`
    - **Validates: Requirements 1.2, 1.7**

  - [ ]* 8.4 Write property test for `LoginUseCase` (P8)
    - **Property 8: Account lockout triggers after threshold**
    - Generate email addresses; simulate exactly 5 consecutive failed attempts; assert the 6th attempt returns `AccountLockedException` (not `AuthenticationFailedException`)
    - **Validates: Requirements 1.8**

  - [x] 8.5 Implement `LogoutUseCase`
    - Create `src/Application/Auth/UseCases/LogoutUseCase.php`
    - Find session by token hash → invalidate → dispatch `UserLoggedOut` event
    - _Requirements: 2.1, 2.2_

  - [ ]* 8.6 Write property test for `LogoutUseCase` (P3)
    - **Property 3: Invalidated session token is always rejected**
    - Generate sessions; call `LogoutUseCase::execute`; assert `SessionRepositoryInterface::findByTokenHash` returns `null` for the invalidated token on all subsequent calls
    - **Validates: Requirements 2.1, 2.2**

  - [x] 8.7 Implement `RequestPasswordResetUseCase`
    - Create `src/Application/Auth/UseCases/RequestPasswordResetUseCase.php`
    - Look up user by email; if not found, return silently (no error); invalidate existing token; create new token; dispatch mail; dispatch `PasswordResetRequested` event
    - _Requirements: 3.1, 3.2, 3.5_

  - [ ]* 8.8 Write property test for `RequestPasswordResetUseCase` (P7)
    - **Property 7: Only one active reset token per user**
    - Generate N sequential reset requests for the same user; assert only the token from the last request is findable; assert all prior tokens return `null` from `findByTokenHash`
    - **Validates: Requirements 3.5**

  - [x] 8.9 Implement `CompletePasswordResetUseCase`
    - Create `src/Application/Auth/UseCases/CompletePasswordResetUseCase.php`
    - Find token by hash → check expiry (throw `TokenExpiredException`) → check used/invalid (throw `TokenInvalidException`) → validate password policy → check not same as current password → hash new password → update user → mark token used → invalidate all sessions for user → dispatch `PasswordResetCompleted` event
    - _Requirements: 4.1, 4.2, 4.3, 4.4, 4.5, 4.6, 5.1, 5.2, 5.4_

  - [ ]* 8.10 Write property test for `CompletePasswordResetUseCase` (P6)
    - **Property 6: Reset token is single-use**
    - Generate valid reset flows; consume token via `CompletePasswordResetUseCase::execute`; assert a second call with the same token throws `TokenInvalidException`
    - **Validates: Requirements 4.3**

  - [ ]* 8.11 Write property test for `CompletePasswordResetUseCase` (P9)
    - **Property 9: Password reset invalidates all existing sessions**
    - Generate users with N active sessions; complete password reset; assert all N session token hashes return `null` from `findByTokenHash`
    - **Validates: Requirements 4.6**

  - [ ]* 8.12 Write unit tests for use cases
    - `LoginUseCaseTest`: valid login returns token; unknown email returns `AuthenticationFailedException`; wrong password returns `AuthenticationFailedException`; locked account returns `AccountLockedException`
    - `LogoutUseCaseTest`: valid token invalidated; already-invalidated token returns `UNAUTHENTICATED`
    - `RequestPasswordResetUseCaseTest`: unregistered email returns 200 (no exception); registered email dispatches job
    - `CompletePasswordResetUseCaseTest`: expired token throws `TokenExpiredException`; used token throws `TokenInvalidException`; non-existent token throws `TokenInvalidException`; same-as-current password throws `ValidationException`
    - _Requirements: 1.2, 1.3, 1.4, 1.8, 2.1, 2.3, 3.2, 4.2, 4.3, 4.4, 5.4_

- [x] 9. Checkpoint — application and infrastructure layers complete
  - Ensure all unit and property tests pass, ask the user if questions arise.

- [x] 10. Presentation layer — form requests, controllers, routes, and service provider
  - [x] 10.1 Implement form requests
    - Create `src/Presentation/Http/Auth/Requests/LoginRequest.php` — validates `email` (required, email format) and `password` (required)
    - Create `src/Presentation/Http/Auth/Requests/PasswordResetRequestForm.php` — validates `email` (required, email format)
    - Create `src/Presentation/Http/Auth/Requests/PasswordResetCompleteForm.php` — validates `token` (required) and `new_password` (required)
    - _Requirements: 1.1, 1.5, 1.6, 3.3, 4.5_

  - [x] 10.2 Implement invokable controllers
    - Create `src/Presentation/Http/Auth/Controllers/LoginController.php` — validates via `LoginRequest`; calls `LoginUseCase`; returns `SessionTokenResource` on success; maps exceptions to JSON error responses
    - Create `src/Presentation/Http/Auth/Controllers/LogoutController.php` — extracts Bearer token; calls `LogoutUseCase`; returns 200 `{}`
    - Create `src/Presentation/Http/Auth/Controllers/RequestPasswordResetController.php` — calls `RequestPasswordResetUseCase`; always returns 200 `{}`
    - Create `src/Presentation/Http/Auth/Controllers/CompletePasswordResetController.php` — calls `CompletePasswordResetUseCase`; maps `TokenExpiredException` → `TOKEN_EXPIRED`, `TokenInvalidException` → `TOKEN_INVALID`
    - Create `src/Presentation/Http/Auth/Resources/SessionTokenResource.php` — transforms `SessionToken` to `{ session_token: string }`
    - _Requirements: 1.1, 1.2, 1.3, 1.4, 1.5, 1.6, 1.8, 2.1, 2.3, 3.1, 3.2, 3.3, 4.1, 4.2, 4.3, 4.4, 4.5_

  - [x] 10.3 Implement routes and `AuthRoutesServiceProvider`
    - Create `src/Presentation/Http/Auth/routes.php` — four `Route::post` definitions under `/api/auth`
    - Create `src/Presentation/Http/Auth/Providers/AuthRoutesServiceProvider.php` — loads routes via `Route::prefix('api/auth')`
    - Register `AuthRoutesServiceProvider` in `bootstrap/app.php`
    - _Requirements: 1.1, 2.1, 3.1, 4.1_

  - [ ]* 10.4 Write unit tests for controllers
    - `LoginControllerTest`: missing email field returns 400 `VALIDATION_ERROR`; missing password field returns 400 `VALIDATION_ERROR`; invalid email format returns 400 `VALIDATION_ERROR`; `AuthenticationFailedException` maps to 401 `AUTHENTICATION_FAILED`; `AccountLockedException` maps to 423 `ACCOUNT_LOCKED` with `retry_after_seconds`
    - `LogoutControllerTest`: no Bearer token returns 401 `UNAUTHENTICATED`
    - `CompletePasswordResetControllerTest`: `TokenExpiredException` maps to 400 `TOKEN_EXPIRED`; `TokenInvalidException` maps to 400 `TOKEN_INVALID`
    - _Requirements: 1.5, 1.6, 1.8, 2.3, 4.2, 4.3, 4.4_

- [x] 11. Frontend — types, API client, and auth context
  - [x] 11.1 Implement shared types and error constants
    - Create `src/features/auth/types/AuthErrors.ts` — export string literal union `AUTHENTICATION_FAILED | TOKEN_EXPIRED | TOKEN_INVALID | ACCOUNT_LOCKED | UNAUTHENTICATED | VALIDATION_ERROR`
    - Create `src/features/auth/types/AuthResponses.ts` — `SessionTokenResponse`, `ValidationErrorResponse`, `AccountLockedResponse` interfaces
    - Create `src/shared/utils/validation.ts` — client-side `isValidEmail(s: string): boolean` and `validatePassword(s: string): string[]` (mirrors backend policy)
    - _Requirements: 1.5, 1.6, 4.5, 5.1, 5.2_

  - [ ]* 11.2 Write property test for client-side validation (P4, P10 — frontend)
    - **Property 4 (frontend): Password policy validation is consistent**
    - Use `fast-check` `fc.string()` for arbitrary passwords; assert `validatePassword` returns identical results on repeated calls
    - **Property 10 (frontend): Invalid email format is rejected before credential lookup**
    - Use `fc.string()` filtered to non-email strings; assert `isValidEmail` returns `false` and form submit is blocked
    - **Validates: Requirements 1.6, 3.3, 5.1, 5.2**

  - [x] 11.3 Implement `authApiClient`
    - Create `src/features/auth/services/authApiClient.ts` — typed `fetch` wrappers for `login`, `logout`, `requestPasswordReset`, `completePasswordReset`
    - Throw structured errors matching `AuthErrors` on non-2xx responses
    - _Requirements: 1.1, 2.1, 3.1, 4.1_

  - [x] 11.4 Implement `AuthContext`
    - Create `src/features/auth/context/AuthContext.tsx` — React context storing session token in `httpOnly` cookie via Next.js BFF proxy route; expose `login`, `logout`, `isAuthenticated`
    - _Requirements: 1.7, 2.1_

- [x] 12. Frontend — hooks and UI components
  - [x] 12.1 Implement auth hooks
    - Create `src/features/auth/hooks/useLogin.ts` — calls `authApiClient.login`; manages loading/error state
    - Create `src/features/auth/hooks/useLogout.ts` — calls `authApiClient.logout`; clears auth context
    - Create `src/features/auth/hooks/usePasswordReset.ts` — handles both request and complete flows
    - _Requirements: 1.2, 2.1, 3.1, 4.1_

  - [x] 12.2 Implement UI components
    - Create `src/features/auth/components/LoginForm.tsx` — email + password fields; shows `AUTHENTICATION_FAILED`, `ACCOUNT_LOCKED`, and `VALIDATION_ERROR` messages
    - Create `src/features/auth/components/LogoutButton.tsx` — triggers logout on click
    - Create `src/features/auth/components/PasswordResetRequestForm.tsx` — email field; always shows success message
    - Create `src/features/auth/components/PasswordResetCompleteForm.tsx` — new password field; shows `TOKEN_EXPIRED`, `TOKEN_INVALID`, and policy violation errors
    - Create `src/shared/components/ErrorMessage.tsx` — reusable error display component
    - _Requirements: 1.3, 1.4, 1.5, 1.8, 3.2, 4.2, 4.3, 4.5_

  - [x] 12.3 Implement Next.js App Router pages
    - Create `src/app/login/page.tsx` — renders `LoginForm`; redirects to home on success
    - Create `src/app/logout/page.tsx` — triggers logout on mount; redirects to `/login`
    - Create `src/app/password-reset/page.tsx` — renders `PasswordResetRequestForm`
    - Create `src/app/password-reset/complete/page.tsx` — reads `?token=` from URL; renders `PasswordResetCompleteForm`
    - _Requirements: 1.2, 2.1, 3.1, 4.1_

  - [ ]* 12.4 Write unit tests for frontend components
    - `LoginForm.test.tsx`: renders email and password fields; shows error on `AUTHENTICATION_FAILED`; shows field errors on `VALIDATION_ERROR`; shows lockout message on `ACCOUNT_LOCKED`
    - `PasswordResetRequestForm.test.tsx`: shows success message regardless of email registration status
    - `PasswordResetCompleteForm.test.tsx`: shows `TOKEN_EXPIRED` error; shows password policy violation errors
    - _Requirements: 1.3, 1.4, 1.5, 1.8, 3.2, 4.2, 4.5_

  - [ ]* 12.5 Write property test for frontend (P2 — frontend)
    - **Property 2 (frontend): Failure response is identical regardless of failure reason**
    - Use `fast-check` `fc.string()` for emails/passwords; mock API to return `AUTHENTICATION_FAILED`; assert `LoginForm` renders the same error UI for unknown-email and wrong-password cases
    - **Validates: Requirements 1.3, 1.4**

- [x] 13. Checkpoint — full stack wired, all tests pass
  - Ensure all backend unit, property, and frontend tests pass, ask the user if questions arise.

- [x] 14. Integration tests against Docker Compose stack
  - [x] 14.1 Write integration test: full login → access → logout flow
    - Create `api/tests/Integration/Auth/AuthFlowTest.php`
    - POST `/api/auth/login` with valid credentials → assert 200 + `session_token`; use token on a protected route → assert 200; POST `/api/auth/logout` → assert 200; reuse token → assert 401
    - _Requirements: 1.2, 1.7, 2.1, 2.2_

  - [x] 14.2 Write integration test: full password reset flow
    - Request reset → inspect Mailpit API for email → extract token → complete reset → login with new password
    - Assert old password no longer works; assert all prior sessions are invalidated
    - _Requirements: 3.1, 3.4, 4.1, 4.6_

  - [x] 14.3 Write integration test: session expiry
    - Configure short inactivity timeout and absolute max duration for test environment
    - Assert session rejected after inactivity timeout elapses; assert session rejected after absolute max duration
    - _Requirements: 2.4, 2.5_

  - [x] 14.4 Write integration test: account lockout and unlock
    - Submit 5 consecutive failed login attempts; assert 6th returns 423 `ACCOUNT_LOCKED` with `retry_after_seconds`; wait for lockout window to expire; assert login succeeds again
    - _Requirements: 1.8_

  - [x] 14.5 Write integration test: concurrent reset token race condition
    - Fire two simultaneous reset requests for the same user; assert only one token survives (DB `UNIQUE` on `user_id` enforces this); assert the surviving token is valid
    - _Requirements: 3.5_

  - [x] 14.6 Write integration test: queue worker delivers reset email
    - Request password reset; assert `SendPasswordResetEmailJob` is processed by the queue worker; assert Mailpit received the email with a valid reset link
    - _Requirements: 3.1_

- [x] 15. Final checkpoint — all tests pass end-to-end
  - Run `mise run api:test` and `mise run frontend:test` to confirm all unit, property, and integration tests pass. Ask the user if questions arise.

## Notes

- Tasks marked with `*` are optional and can be skipped for a faster MVP
- Each task references specific requirements for traceability
- Checkpoints ensure incremental validation at each layer boundary
- Property tests (P1–P10) validate universal correctness properties using `eris` (backend) and `fast-check` (frontend)
- Unit tests validate specific examples and edge cases
- Integration tests run against the full Docker Compose stack and require `mise run docker:up` before execution
