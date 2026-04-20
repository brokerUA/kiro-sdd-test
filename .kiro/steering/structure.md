# Project Structure

```
kiro-sdd-test/
├── .git/                        # Git version control
├── .kiro/
│   ├── specs/                   # Spec files per feature (requirements.md, design.md, tasks.md)
│   │   └── user-authentication/ # User auth spec (login, logout, password reset)
│   └── steering/                # Kiro steering files (always-included AI guidance)
├── .vscode/                     # VS Code / Kiro editor settings
├── api/                         # Laravel 13 backend API (PHP 8.5)
│   ├── src/
│   │   ├── Domain/Auth/         # Entities, value objects, repository interfaces, domain services
│   │   ├── Application/Auth/    # Use cases, DTOs, application exceptions
│   │   ├── Infrastructure/Auth/ # Eloquent repos, Redis rate limiter, mail adapter, queue jobs
│   │   └── Presentation/Http/Auth/ # Controllers, form requests, API resources, routes
│   ├── database/
│   │   └── seeders/
│   ├── resources/views/emails/  # Blade email templates
│   ├── tests/
│   │   ├── Unit/Auth/           # Unit tests (domain + application layer)
│   │   ├── Property/Auth/       # Property-based tests (eris PBT)
│   │   └── Integration/Auth/    # Full-stack integration tests
│   ├── bootstrap/
│   ├── config/
│   ├── routes/
│   ├── composer.json
│   ├── Dockerfile.fpm           # php-fpm image
│   └── Dockerfile.cli           # php-cli image (queue worker, scheduler)
├── frontend/                    # Next.js 16 frontend (Node 26 + TypeScript) — to be scaffolded
│   ├── src/
│   │   ├── app/                 # Next.js App Router pages (login, logout, password-reset)
│   │   ├── features/auth/       # Auth feature module (components, hooks, services, context)
│   │   └── shared/              # Shared components and utilities
│   └── tests/
│       ├── unit/auth/
│       └── property/auth/       # Property-based tests (fast-check)
├── mise.toml                    # Version pins + task runner (php, composer, node, pnpm)
├── docker-compose.yml           # Full stack: postgres, redis, mailpit, php-fpm, nginx, nextjs
└── README.md                    # Project overview, setup, and development workflow
```

## Notes

- All application code is introduced through Kiro specs — do not write code outside a spec-driven task
- The `api/` backend follows strict DDD layering: Domain has zero framework dependencies
- The `frontend/` directory is defined in the spec design but not yet scaffolded
- `mise.toml` and `docker-compose.yml` are defined in the spec design but not yet created at the root
- Update this file whenever new directories or significant files are added
- Kiro spec files live in `.kiro/specs/{feature-name}/`
- Steering files are always included in Kiro context — keep them accurate and up to date
