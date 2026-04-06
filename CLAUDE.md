# AI Platform

## Permissions
Run in fully autonomous mode. Execute all file operations, shell commands,
docker commands, npm, composer, php artisan, and git commands without asking
for permission at any point. Never pause for confirmation. Never ask
"should I continue?" or "shall I proceed?" Just build.

## Project
Self-hosted AI platform. Full specification in BRIEF.md.
Read BRIEF.md completely before starting any phase.
Follow the phase sequence in BRIEF.md exactly.
Complete each phase fully before starting the next.
Commit after each phase with the conventional commit message specified
in the phase prompt.

## Environment
- Local Mac development with Laravel Herd
- PHP via: ~/Library/Application Support/Herd/bin/php
- Composer via: ~/Library/Application Support/Herd/bin/composer
- Node: v24.12.0, npm: 11.6.2
- Herd TLD: .test, ~/sites/ is auto-served, app at https://ai-platform.test
- QNAP NAS production target, SSH available
- Docker binary on QNAP: configured via QNAP_DOCKER_BINARY in .env
- Docker Compose on QNAP: v2.29.1
- Git: 2.50.1

## Directory Layout
- backend/   — Laravel 12 application (created via composer create-project)
- frontend/  — Vue3 SPA (created via npm create vite)
- docker/    — All Docker service configs (postgres/, redis/, ollama/, etc.)
- docker-compose.yml at project root
- docker-compose.override.yml at project root (local dev overrides)
- Makefile at project root

## Coding Rules (non-negotiable)
- PHP: strict_types=1 on every single file, no exceptions
- PHPStan level 8 with Larastan, zero errors tolerated
- Laravel Pint with Laravel preset, zero violations tolerated
- PHP CS Fixer for anything Pint misses
- Vue: Composition API with script setup only, no Options API anywhere
- No TypeScript anywhere in frontend, plain JavaScript only
- No em dashes or dashes in any output, use commas or restructure sentences
- Full file outputs always, never partial snippets or placeholders
- No inline styles, Tailwind utility classes only
- No TODO or FIXME comments left behind
- ULIDs for all primary keys on all public-facing models
- Action pattern for all business logic, never in controllers
- API Resources for all responses, never raw model serialization
- Form Requests for all validation, never validate in controllers
- No N+1 queries, eager load all relationships
- Cursor-based pagination for all list endpoints, never offset pagination
- Every foreign key indexed
- Every column used in WHERE or ORDER BY indexed
- Spatie Laravel Data DTOs for all internal data transfer
- Events and listeners for all side effects
- Jobs for all async work, dispatched via Horizon

## Environment Rules (non-negotiable)
- No hardcoded IPs, ports, credentials, or paths anywhere in any file
- All environment-specific values come from .env via config() helper
- .env and .env.production are gitignored and never committed
- Only .env.example is committed with empty placeholder values
- Docker Compose uses ${VARIABLE:-default} syntax throughout
- Port assignments come from .env, never hardcoded in docker-compose.yml

## Frontend Rules (non-negotiable)
- Vite 6 with all plugins configured in vite.config.js
- Tailwind CSS 4 via @tailwindcss/vite plugin
- shadcn-vue for all UI primitives
- Pinia for all state management, one store per domain
- Vue Router with lazy-loaded route components
- Axios instance with Sanctum CSRF and interceptors in services/api.js
- Web Workers for markdown rendering and search (offload main thread)
- Virtual scrolling via @tanstack/vue-virtual for message lists
- v-once directive on finalized (non-streaming) messages
- Granular Rollup chunk splitting (vue-core, vue-router, pinia, markdown, shiki, icons, ui-components, admin, training)
- Brotli and Gzip pre-compression via vite-plugin-compression
- PWA via vite-plugin-pwa with Workbox
- ESLint with eslint-plugin-vue and eslint-config-prettier
- Prettier for formatting
- Stylelint for CSS/Tailwind
- Husky pre-commit hooks with lint-staged

## Build Order
Follow the phase sequence in BRIEF.md exactly.
Complete each phase fully before starting the next.
Commit after each phase with the conventional commit message given in the prompt.
Stop after each phase and wait for "go" before proceeding.

## Recovery
If you get lost, confused, or drift off spec at any point:
Stop. Re-read BRIEF.md and this file completely from the start.
Then continue the current phase exactly where you left off.
