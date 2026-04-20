---
inclusion: always
---

# Product

**kiro-sdd-test** is a sandbox repository for evaluating and demonstrating Kiro's spec-driven development (SDD) workflow.

## Purpose

This repo exists to experiment with and validate Kiro's SDD methodology end-to-end:
- Requirements → Design → Tasks → Implementation
- Steering files, hooks, and AI-assisted development patterns
- Reference example for teams adopting Kiro

## Current Status

The project has one completed spec: **user-authentication** (login, logout, password reset).

**Implemented:**
- Laravel 13 backend API with DDD architecture (Domain, Application, Infrastructure, Presentation layers)
- PostgreSQL 18 database with migrations for users, sessions, and password reset tokens
- Redis for caching, queuing, and rate limiting
- Eloquent repositories, domain services, use cases, and controllers
- Email delivery via Laravel Mail + queued jobs (Mailpit for local dev)
- PHPUnit + eris property-based testing setup

**Defined in spec but not yet scaffolded:**
- Next.js 16 frontend (React, App Router, TypeScript)
- Docker Compose stack (postgres, redis, mailpit, php-fpm, nginx, nextjs)
- `mise.toml` task runner configuration
- Frontend property-based tests (fast-check)

## Development Conventions

- All features MUST be introduced via a Kiro spec (`.kiro/specs/{feature-name}/`)
- Each spec requires three files: `requirements.md`, `design.md`, and `tasks.md`
- Do not write application code outside of a spec-driven task
- Keep specs focused and incremental — one feature per spec directory
- Update `structure.md` and `tech.md` steering files as the stack evolves

## AI Assistant Guidance

- The tech stack is now established: Laravel 13 (PHP 8.5) + Next.js 16 (Node 26) + PostgreSQL + Redis
- Follow the DDD architecture defined in the user-authentication spec design
- Always check existing specs before creating new ones to avoid duplication
- Prefer minimal, idiomatic implementations that match the established stack
- When extending the stack, update steering files to reflect new conventions
