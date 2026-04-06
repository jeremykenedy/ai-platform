# AI Platform

Self-hosted AI chat platform running entirely on Docker Compose. Laravel 12 API backend, compiled Vue 3 SPA frontend, and local LLM inference via Ollama. The UI is modeled after Claude.ai in look, feel, and interaction patterns.

## Table of Contents

- [Tech Stack](#tech-stack)
- [Screenshots](#screenshots)
- [Prerequisites](#prerequisites)
- [Quick Start: Local Development](#quick-start-local-development)
- [Quick Start: QNAP Deployment](#quick-start-qnap-deployment)
- [Make Targets](#make-targets)
- [Architecture](#architecture)
- [Services](#services)
- [Environment Variables](#environment-variables)
- [Artisan Commands](#artisan-commands)
- [API Endpoints](#api-endpoints)
- [Contributing](#contributing)
- [License](#license)

## Tech Stack

| Layer | Technology | Version |
|---|---|---|
| Backend Framework | Laravel | 13 |
| PHP Runtime | FrankenPHP | latest |
| Frontend Framework | Vue 3 (Composition API) | 3.x |
| Build Tool | Vite | 6.x |
| CSS Framework | Tailwind CSS | 4.x |
| UI Components | shadcn-vue + Radix Vue | latest |
| Database | PostgreSQL + pgvector | 16 |
| Connection Pool | PgBouncer | latest |
| Cache, Queue | Redis | 7 |
| Queue Dashboard | Laravel Horizon | 5.x |
| WebSockets | Laravel Reverb | 1.x |
| LLM Inference | Ollama | latest |
| LLM UI | Open WebUI | latest |
| Image Generation | ComfyUI | latest |
| Object Storage | MinIO | latest |
| Search Engine | SearXNG | latest |
| Error Tracking | GlitchTip | latest |
| Container Updates | Watchtower | latest |
| Static Analysis | PHPStan + Larastan | Level 8 |
| PHP Formatting | Laravel Pint | latest |
| JS Linting | ESLint + Prettier | latest |

## Screenshots

Screenshots will be added after the UI is finalized.

## Prerequisites

- Docker and Docker Compose v2
- Git

For local development without Docker:
- PHP 8.3+ with pdo_pgsql, redis, pcntl, intl, zip, bcmath, gd extensions
- Composer 2.x
- Node.js 20+
- npm 10+

## Quick Start: Local Development

```bash
# Clone the repository
git clone <repo-url> ai-platform
cd ai-platform

# Create environment file
cp .env.example .env
# Edit .env with your local values (DB password, API keys, etc.)

# Build and start all containers
make build
make up

# Run database migrations and seed initial data
make migrate
make seed

# Access the application
# Frontend: http://localhost (or configured port)
# Open WebUI: http://localhost:3000
# Horizon: accessible via /horizon route
```

For development without Docker (using Laravel Herd):

```bash
# Backend
cd backend
composer install
cp ../.env .env
php artisan key:generate
php artisan migrate --seed

# Frontend
cd ../frontend
npm install
npm run dev
```

## Quick Start: QNAP Deployment

```bash
# SSH to QNAP
ssh jeremy@<QNAP_HOST>

# Navigate to project
cd /share/CE_CACHEDEV1_DATA/homes/jeremy/sites/ai-platform

# Create environment file
cp .env.example .env
# Edit .env with production values

# Build and deploy
make build
make up
make migrate
make seed
```

The Docker binary on QNAP is auto-detected by the Makefile at:
`/share/CE_CACHEDEV1_DATA/.qpkg/container-station/usr/bin/.libs/docker`

Subsequent deployments:

```bash
make deploy-qnap
```

## Make Targets

| Target | Description |
|---|---|
| `make up` | Start all containers in detached mode |
| `make down` | Stop all containers |
| `make build` | Build all container images |
| `make fresh` | Drop all tables, re-migrate, and seed |
| `make migrate` | Run database migrations |
| `make seed` | Run database seeders |
| `make shell` | Open a shell in the FrankenPHP container |
| `make tinker` | Open Laravel Tinker REPL |
| `make logs` | Tail all container logs |
| `make lint` | Run all linters (Pint, PHPStan, ESLint, Prettier, Stylelint) |
| `make test` | Run backend test suite |
| `make deploy-local` | Deploy locally via deploy script |
| `make deploy-qnap` | Deploy to QNAP via deploy script |
| `make ssh-qnap` | SSH into the QNAP NAS |

## Architecture

```
ai-platform/
  backend/                 Laravel 13 API (FrankenPHP)
    app/
      Actions/             Single-responsibility business logic
      Console/Commands/    Artisan commands (13 commands)
      Enums/               PHP 8.4 backed enums (15 enums)
      Events/              Broadcast events (9 events)
      Http/
        Controllers/Api/V1/  Versioned API controllers (12)
        Requests/           Form request validation (21)
        Resources/          API resource transformers (17)
      Jobs/                Queue jobs (9 jobs)
      Listeners/           Event listeners (5)
      Models/              Eloquent models (19 models)
      Policies/            Authorization policies (9)
      Services/
        AI/                Provider abstraction, streaming, context, embeddings
          Providers/       13 AI provider implementations
        Integrations/      30 third-party integration services
        Media/             File extraction, image gen, audio
        Memory/            Memory extraction, retrieval, decay, summarization
    database/
      migrations/          26 migrations
      seeders/             5 seeders
    routes/
      api/                 9 route files under /api/v1/
      channels.php         WebSocket channel authorization
      console.php          Scheduled tasks

  frontend/                Vue 3 SPA (Nginx)
    src/
      components/          38 Vue components
        chat/              ConversationView, ChatInput, MessageBubble, etc.
        sidebar/           AppSidebar, ConversationItem, ProjectFilter, UserMenu
        layout/            AppLayout, AuthLayout, AdminLayout, SettingsLayout
        selectors/         ModelSelector, PersonaSelector
        files/             FileUpload, FilePreview
        voice/             AudioPlayer, WaveformVisualizer, VoiceRecordButton
        markdown/          MarkdownRenderer, CodeBlock (lazy Shiki)
        feedback/          ToastContainer, ErrorBoundary, CommandPalette
        offline/           OfflineIndicator, PwaInstallPrompt, ServiceWorkerUpdate
        ui/                Skeletons (conversation, message, dashboard)
      composables/         11 composables
      stores/              10 Pinia stores
      services/            api.js, echo.js, streaming.js
      workers/             markdown.worker.js, search.worker.js
      pages/               15 page components
      router/              Vue Router with lazy routes and auth guards

  docker/                  Service configurations
    frankenphp/            Caddyfile
    postgres/              postgresql.conf, init scripts
    redis/                 redis.conf
    ollama/                entrypoint.sh with model auto-pull
    nginx/                 Frontend static serving
    searxng/               Search engine config
    minio/                 Object storage

  docker-compose.yml       Production (15 services)
  docker-compose.override.yml  Development overrides
  Makefile                 All operational targets
  deploy.sh               Deployment script (local and QNAP)
```

## Services

| Service | Purpose | Internal Port |
|---|---|---|
| frankenphp | PHP application server | 80, 443 |
| horizon | Queue worker dashboard | (via frankenphp) |
| reverb | WebSocket server | 8080 |
| frontend | Vue SPA static files | 80 |
| postgres | Primary database (pgvector) | 5432 |
| pgbouncer | Connection pooling | 6432 |
| redis | Cache, queue, sessions | 6379 |
| minio | S3-compatible object storage | 9000, 9001 |
| ollama | Local LLM inference | 11434 |
| open-webui | LLM management UI | 8080 |
| comfyui | Local image generation | 8188 |
| axolotl | Model fine-tuning | (batch) |
| glitchtip | Error tracking | 8000 |
| searxng | Self-hosted search | 8080 |
| watchtower | Container auto-updates | (daemon) |

PostgreSQL, PgBouncer, and Redis are internal-only (not exposed to host in production).

## Environment Variables

All configuration is driven by environment variables. See `.env.example` for the complete list with descriptions.

Key variable groups: Core Application, Ports, Database, Redis, Session/Cache/Queue, Sanctum, Broadcasting (Reverb), MinIO (S3), AI Providers (13 providers), Ollama, ComfyUI, Intel GPU, Docker Resource Limits, Search, Integrations (OAuth + API Keys), Monitoring, Cloudflare, Model Routing Defaults, QNAP Deployment.

## Artisan Commands

| Command | Description |
|---|---|
| `models:sync` | Sync available models from all configured providers |
| `models:pull {model}` | Pull a model via Ollama with progress output |
| `models:delete {model}` | Delete a model from Ollama |
| `models:list` | List all registered models with capabilities |
| `models:running` | Show currently loaded Ollama models |
| `integrations:list` | List all integration definitions |
| `integrations:seed` | Seed integration definitions table |
| `integrations:test {user} {integration}` | Test a user's integration connection |
| `integrations:clear-expired-tokens` | Clear expired OAuth tokens |
| `app:seed-super-admin` | Create super admin from env variables |
| `memory:decay` | Decay importance of unaccessed memories |
| `activity:prune` | Remove old activity log entries |
| `files:cleanup` | Remove orphaned files from storage |

## API Endpoints

All endpoints are under `/api/v1/`. Authentication via Sanctum (cookie-based SPA auth).

| Group | Endpoints |
|---|---|
| Auth | login, logout, register, user, password reset, email verification |
| Conversations | CRUD, export, nested messages (send, delete, regenerate) |
| Models | list, show, pull, delete, running |
| Personas | full CRUD |
| Projects | full CRUD |
| Training | dataset CRUD, job management (start, cancel, status) |
| Integrations | list, connect, disconnect, OAuth callback, execute tools |
| Settings | show, update, memory management (CRUD, bulk delete, conflict resolution) |
| Admin | users (list, update, invite), dashboard stats |
| Health | service status check (no auth required) |

## Contributing

Contributions are welcome. Please ensure all linters pass before submitting a PR:

```bash
# Backend
cd backend
vendor/bin/pint
vendor/bin/phpstan analyse --level=8

# Frontend
cd frontend
npm run lint
npm run format
npm run lint:style
```

## License

This project is private software. All rights reserved.
