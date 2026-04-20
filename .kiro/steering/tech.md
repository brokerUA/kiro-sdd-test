# Tech Stack

## Backend API (`api/`)

- **Language / Runtime**: PHP 8.5
- **Framework**: Laravel 13
- **Architecture**: Domain-Driven Design (DDD) — Presentation → Application → Domain → Infrastructure layers
- **Database**: PostgreSQL 18 (via Laravel Eloquent ORM)
- **Cache / Queue / Rate Limiting**: Redis
- **Password Hashing**: bcrypt via Laravel `Hash` facade (cost factor ≥ 12)
- **Session Tokens**: SHA-256-hashed, 256-bit random tokens stored in PostgreSQL
- **Email Delivery**: Laravel Mail + queued Mailable jobs (Mailpit for local dev)
- **Package Manager**: Composer 2.8

## Frontend (`frontend/`)

- **Language / Runtime**: Node 26 + TypeScript
- **Framework**: Next.js 16 (React, App Router)
- **Package Manager**: pnpm 9
- **Architecture**: Feature-based DDD — `src/features/auth/` module

## Testing

- **Backend unit/integration**: PHPUnit 11
- **Backend property-based testing (PBT)**: [eris](https://github.com/giorgiosironi/eris) ^0.12
- **Frontend unit**: Jest
- **Frontend property-based testing (PBT)**: [fast-check](https://fast-check.io/)

## Tooling

- **Version management + task runner**: [mise](https://mise.jdx.dev/) (`mise.toml` at project root)
- **Containerisation**: Docker + Docker Compose (php-fpm, nginx, PostgreSQL, Redis, Mailpit, Next.js)
- **IDE**: Kiro (AI-assisted development)
- **Repository**: Git

## Common Commands

```bash
# One-time setup
mise run docker:build       # build all Docker images

# Daily workflow
mise run docker:up          # start full stack (postgres, redis, mailpit, php-fpm, nginx, nextjs)
mise run api:migrate        # run database migrations
mise run docker:down        # tear down containers

# Testing
mise run api:test           # run all backend tests (unit + property + integration)
mise run api:test:pbt       # run property-based tests only
mise run frontend:test      # run all frontend tests (unit + property)

# Dependencies
mise run api:install        # composer install for Laravel API
mise run frontend:install   # pnpm install for Next.js frontend
```
