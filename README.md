# AI Platform

Self-hosted AI chat platform running entirely on Docker Compose. Laravel 12 API backend, compiled Vue 3 SPA frontend, and local LLM inference via Ollama. The UI is modeled after Claude.ai in look, feel, and interaction patterns.

## Tech Stack

- **Backend:** Laravel 12, FrankenPHP, Horizon, Reverb
- **Frontend:** Vue 3 (Composition API), Vite 6, Tailwind CSS 4, shadcn-vue
- **Database:** PostgreSQL 16 with pgvector, PgBouncer connection pooling
- **Cache/Queue:** Redis 7
- **LLM Inference:** Ollama (local), plus 10+ commercial API providers
- **Image Generation:** ComfyUI (local), Replicate, Stability AI
- **Object Storage:** MinIO (S3-compatible)
- **Search:** SearXNG (self-hosted)
- **Monitoring:** GlitchTip (self-hosted Sentry)
- **Container Updates:** Watchtower
- **LLM Management:** Open WebUI

## Prerequisites

- Docker and Docker Compose v2
- Git

For local development (optional, without Docker):
- PHP 8.3+ with required extensions
- Node.js 20+
- Composer

## Setup

### Local Development

```bash
git clone <repo-url> ai-platform
cd ai-platform
cp .env.example .env
# Edit .env with your local values (DB password, API keys, etc.)
make build
make up
make migrate
make seed
```

### QNAP NAS Production

```bash
# SSH to QNAP
ssh jeremy@<QNAP_HOST>

cd /share/CE_CACHEDEV1_DATA/homes/jeremy/sites/ai-platform
cp .env.example .env
# Edit .env with production values

make build
make up
make migrate
make seed
```

The Docker binary on QNAP is auto-detected by the Makefile at:
`/share/CE_CACHEDEV1_DATA/.qpkg/container-station/usr/bin/.libs/docker`

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
  backend/       Laravel 12 API application
  frontend/      Vue 3 SPA (compiled, served by Nginx)
  docker/        Docker service configurations
  docker-compose.yml          Production compose
  docker-compose.override.yml Development overrides
  Makefile                    All operational targets
```

All services run on a shared `ai-platform` bridge network. PostgreSQL, PgBouncer, and Redis are internal only (not exposed to host in production). Every port, credential, and resource limit is driven by `.env` variables.
