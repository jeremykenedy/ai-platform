# AI Platform — Claude Code Project Brief
# Complete Architecture and Build Instructions

---

## PROJECT OVERVIEW

Build a self-hosted AI chat platform running entirely on a QNAP NAS (Intel i3-8100T,
4 cores, 32GB RAM, Intel UHD 630, 15.7TB free storage) via Docker Compose. The platform
consists of a Laravel 12 API backend, a compiled Vue3 SPA frontend, and a local LLM
inference stack powered by Ollama. The UI/UX is modeled after Claude.ai in look, feel,
and interaction patterns.

---

## HARDWARE CONSTRAINTS AND TUNING TARGETS

- CPU: Intel Core i3-8100T 3.10GHz, 4 cores, 4 threads
- RAM: 32GB
- GPU: Intel UHD 630 (integrated, accessible via /dev/dri/card0 and /dev/dri/renderD128)
- Storage: Large spinning/SSD QNAP array
- No dedicated GPU — all LLM inference is CPU with Intel GPU offload via OpenCL

Tuning targets derived from hardware:
- FrankenPHP worker count: 4
- Queue worker concurrency: 2
- PgBouncer max pool size: 15
- Postgres shared_buffers: 4GB
- Postgres work_mem: 64MB
- Redis maxmemory: 2gb
- Redis maxmemory-policy: allkeys-lru
- Ollama num_thread: 4
- Ollama Intel GPU: enabled via OLLAMA_INTEL_GPU=1 and /dev/dri device passthrough

---

## REPOSITORY STRUCTURE

Monorepo layout:

```
/
├── backend/                    Laravel 12 application
│   ├── app/
│   │   ├── Actions/            Single-responsibility action classes
│   │   ├── Console/
│   │   ├── Data/               Spatie Laravel Data DTOs
│   │   ├── Enums/
│   │   ├── Events/
│   │   ├── Exceptions/
│   │   ├── Http/
│   │   │   ├── Controllers/
│   │   │   ├── Middleware/
│   │   │   ├── Requests/       Form Requests for all endpoints
│   │   │   └── Resources/      API Resources and Collections
│   │   ├── Jobs/
│   │   ├── Listeners/
│   │   ├── Models/
│   │   ├── Notifications/
│   │   ├── Policies/
│   │   ├── Providers/
│   │   └── Services/
│   ├── database/
│   │   ├── migrations/
│   │   └── seeders/
│   ├── routes/
│   │   ├── api.php             Entry — loads sub-files
│   │   ├── api/
│   │   │   ├── auth.php
│   │   │   ├── conversations.php
│   │   │   ├── models.php
│   │   │   ├── personas.php
│   │   │   ├── projects.php
│   │   │   ├── training.php
│   │   │   └── admin.php
│   │   └── web.php             Single catch-all for SPA shell
│   ├── Dockerfile
│   ├── .env.example
│   ├── phpstan.neon
│   ├── pint.json
│   └── .php-cs-fixer.php
├── frontend/                   Vue3 SPA
│   ├── src/
│   │   ├── assets/
│   │   ├── components/
│   │   │   ├── ui/             shadcn-vue components
│   │   │   ├── chat/
│   │   │   ├── sidebar/
│   │   │   ├── models/
│   │   │   ├── personas/
│   │   │   ├── projects/
│   │   │   ├── training/
│   │   │   └── admin/
│   │   ├── composables/
│   │   ├── layouts/
│   │   ├── lib/                Utility functions
│   │   ├── pages/
│   │   ├── router/
│   │   ├── services/           API service layer
│   │   ├── stores/             Pinia stores
│   │   └── types/
│   ├── Dockerfile
│   ├── vite.config.js
│   ├── .eslintrc.cjs
│   ├── .prettierrc
│   └── stylelint.config.cjs
├── docker/
│   ├── frankenphp/
│   │   └── Caddyfile
│   ├── postgres/
│   │   └── postgresql.conf
│   ├── pgbouncer/
│   │   ├── pgbouncer.ini
│   │   └── userlist.txt
│   ├── redis/
│   │   └── redis.conf
│   ├── nginx/                  Frontend static serving
│   │   └── nginx.conf
│   └── ollama/
│       └── entrypoint.sh       Auto-pulls default models on start
├── docker-compose.yml          Production
├── docker-compose.override.yml Development overrides
├── Makefile
├── .editorconfig
├── .gitignore
└── README.md
```

---

## DOCKER COMPOSE — ALL SERVICES

### Services (docker-compose.yml):

```yaml
services:

  frankenphp:
    build:
      context: ./backend
      dockerfile: Dockerfile
      target: production
    restart: unless-stopped
    environment:
      SERVER_NAME: "https://ai.local"
      OCTANE_SERVER: frankenphp
      APP_ENV: production
    volumes:
      - ./backend:/app
      - caddy_data:/data
      - caddy_config:/config
    depends_on:
      pgbouncer:
        condition: service_healthy
      redis:
        condition: service_healthy
      reverb:
        condition: service_started
    healthcheck:
      test: ["CMD", "curl", "-f", "https://localhost/api/health"]
      interval: 30s
      timeout: 10s
      retries: 3
    deploy:
      resources:
        limits:
          cpus: '2.0'
          memory: 4G

  horizon:
    build:
      context: ./backend
      dockerfile: Dockerfile
      target: production
    command: php artisan horizon
    restart: unless-stopped
    depends_on:
      - frankenphp
      - redis
    deploy:
      resources:
        limits:
          cpus: '1.0'
          memory: 1G

  reverb:
    build:
      context: ./backend
      dockerfile: Dockerfile
      target: production
    command: php artisan reverb:start --host=0.0.0.0 --port=8080
    restart: unless-stopped
    depends_on:
      - redis
    deploy:
      resources:
        limits:
          cpus: '0.5'
          memory: 512M

  frontend:
    build:
      context: ./frontend
      dockerfile: Dockerfile
      target: production
    restart: unless-stopped
    healthcheck:
      test: ["CMD", "curl", "-f", "http://localhost:80"]
      interval: 30s
      timeout: 5s
      retries: 3
    deploy:
      resources:
        limits:
          cpus: '0.25'
          memory: 128M

  postgres:
    image: pgvector/pgvector:pg16
    restart: unless-stopped
    environment:
      POSTGRES_DB: ${DB_DATABASE}
      POSTGRES_USER: ${DB_USERNAME}
      POSTGRES_PASSWORD: ${DB_PASSWORD}
    volumes:
      - postgres_data:/var/lib/postgresql/data
      - ./docker/postgres/postgresql.conf:/etc/postgresql/postgresql.conf
    command: postgres -c config_file=/etc/postgresql/postgresql.conf
    healthcheck:
      test: ["CMD-SHELL", "pg_isready -U ${DB_USERNAME}"]
      interval: 10s
      timeout: 5s
      retries: 5
    deploy:
      resources:
        limits:
          cpus: '1.0'
          memory: 6G

  pgbouncer:
    image: edoburu/pgbouncer:latest
    restart: unless-stopped
    environment:
      DB_HOST: postgres
      DB_NAME: ${DB_DATABASE}
      DB_USER: ${DB_USERNAME}
      DB_PASSWORD: ${DB_PASSWORD}
      POOL_MODE: transaction
      MAX_CLIENT_CONN: 100
      DEFAULT_POOL_SIZE: 15
    depends_on:
      postgres:
        condition: service_healthy
    healthcheck:
      test: ["CMD", "pg_isready", "-h", "localhost"]
      interval: 10s
      timeout: 5s
      retries: 5
    deploy:
      resources:
        limits:
          cpus: '0.25'
          memory: 256M

  redis:
    image: redis:7-alpine
    restart: unless-stopped
    command: redis-server /usr/local/etc/redis/redis.conf
    volumes:
      - redis_data:/data
      - ./docker/redis/redis.conf:/usr/local/etc/redis/redis.conf
    healthcheck:
      test: ["CMD", "redis-cli", "ping"]
      interval: 10s
      timeout: 5s
      retries: 5
    deploy:
      resources:
        limits:
          cpus: '0.25'
          memory: 2G

  minio:
    image: minio/minio:latest
    restart: unless-stopped
    command: server /data --console-address ":9001"
    environment:
      MINIO_ROOT_USER: ${MINIO_ROOT_USER}
      MINIO_ROOT_PASSWORD: ${MINIO_ROOT_PASSWORD}
    volumes:
      - minio_data:/data
    healthcheck:
      test: ["CMD", "curl", "-f", "http://localhost:9000/minio/health/live"]
      interval: 30s
      timeout: 10s
      retries: 3
    deploy:
      resources:
        limits:
          cpus: '0.5'
          memory: 1G

  ollama:
    image: ollama/ollama:latest
    restart: unless-stopped
    environment:
      OLLAMA_NUM_THREAD: 4
      OLLAMA_INTEL_GPU: 1
      OLLAMA_HOST: 0.0.0.0
    devices:
      - /dev/dri/card0:/dev/dri/card0
      - /dev/dri/renderD128:/dev/dri/renderD128
    volumes:
      - ollama_models:/root/.ollama
      - ./docker/ollama/entrypoint.sh:/entrypoint.sh
    entrypoint: ["/bin/sh", "/entrypoint.sh"]
    healthcheck:
      test: ["CMD", "curl", "-f", "http://localhost:11434/api/tags"]
      interval: 30s
      timeout: 10s
      retries: 5
    deploy:
      resources:
        limits:
          cpus: '3.0'
          memory: 16G

  open-webui:
    image: ghcr.io/open-webui/open-webui:main
    restart: unless-stopped
    environment:
      OLLAMA_BASE_URL: http://ollama:11434
    volumes:
      - open_webui_data:/app/backend/data
    depends_on:
      ollama:
        condition: service_healthy
    deploy:
      resources:
        limits:
          cpus: '0.5'
          memory: 1G

  axolotl:
    image: winglian/axolotl:main-latest
    restart: unless-stopped
    volumes:
      - axolotl_data:/workspace
      - minio_data:/minio
    deploy:
      resources:
        limits:
          cpus: '4.0'
          memory: 24G

  glitchtip:
    image: glitchtip/glitchtip:latest
    restart: unless-stopped
    environment:
      DATABASE_URL: postgresql://${DB_USERNAME}:${DB_PASSWORD}@postgres/${GLITCHTIP_DB}
      SECRET_KEY: ${GLITCHTIP_SECRET_KEY}
      EMAIL_URL: ${GLITCHTIP_EMAIL_URL}
      GLITCHTIP_DOMAIN: ${GLITCHTIP_DOMAIN}
    depends_on:
      postgres:
        condition: service_healthy
      redis:
        condition: service_healthy
    deploy:
      resources:
        limits:
          cpus: '0.5'
          memory: 512M

  watchtower:
    image: containrrr/watchtower:latest
    restart: unless-stopped
    volumes:
      - /var/run/docker.sock:/var/run/docker.sock
    command: --schedule "0 0 3 * * *" --cleanup
    deploy:
      resources:
        limits:
          cpus: '0.1'
          memory: 64M

volumes:
  postgres_data:
  redis_data:
  minio_data:
  ollama_models:
  open_webui_data:
  axolotl_data:
  caddy_data:
  caddy_config:

networks:
  default:
    driver: bridge
    internal: false
```

### docker-compose.override.yml (development):

```yaml
services:
  frankenphp:
    environment:
      SERVER_NAME: "http://localhost"
      APP_ENV: local
      APP_DEBUG: true
    volumes:
      - ./backend:/app

  postgres:
    ports:
      - "5432:5432"

  redis:
    ports:
      - "6379:6379"

  ollama:
    ports:
      - "11434:11434"

  open-webui:
    ports:
      - "3000:8080"

  glitchtip:
    ports:
      - "8200:8000"

  minio:
    ports:
      - "9000:9000"
      - "9001:9001"
```

---

## BACKEND — LARAVEL 12

### Dockerfile (multi-stage):

```dockerfile
FROM dunglas/frankenphp:latest-php8.3-alpine AS base

RUN install-php-extensions \
    pdo_pgsql \
    pgsql \
    redis \
    pcntl \
    intl \
    zip \
    bcmath \
    opcache

WORKDIR /app

FROM base AS development
RUN install-php-extensions xdebug
COPY . .
RUN composer install

FROM base AS production
COPY . .
RUN composer install --no-dev --optimize-autoloader
RUN php artisan config:cache \
    && php artisan route:cache \
    && php artisan view:cache \
    && php artisan event:cache
```

### Composer packages to install:

```bash
composer require \
  laravel/sanctum \
  laravel/reverb \
  laravel/horizon \
  laravel/octane \
  laravel/pulse \
  laravel/telescope \
  laravel/scout \
  spatie/laravel-permission \
  spatie/laravel-activitylog \
  spatie/laravel-data \
  spatie/laravel-media-library \
  spatie/laravel-query-builder \
  knuckleswtf/scribe \
  league/flysystem-aws-s3-v3 \
  predis/predis \
  sentry/sentry-laravel

composer require --dev \
  larastan/larastan \
  laravel/pint \
  friendsofphp/php-cs-fixer \
  pestphp/pest \
  pestphp/pest-plugin-laravel
```

### phpstan.neon:

```neon
includes:
  - vendor/larastan/larastan/extension.neon

parameters:
  level: 8
  paths:
    - app
  ignoreErrors: []
  checkMissingIterableValueType: false
```

### pint.json:

```json
{
  "preset": "laravel",
  "rules": {
    "ordered_imports": true,
    "no_unused_imports": true,
    "strict_param": true,
    "declare_strict_types": true,
    "final_class": false,
    "php_unit_test_class_requires_covers": false
  }
}
```

### All PHP files must:

- Declare `strict_types=1`
- Use readonly properties where applicable
- Use named arguments for clarity
- Use PHP 8.3 features: typed class constants, readonly classes where appropriate
- Have full PHPDoc blocks on all public methods
- Pass PHPStan level 8 with Larastan

---

## DATABASE SCHEMA

### Migrations to create (in order):

```
users                       Standard + invite_token, invited_by, subscription_tier (nullable)
personal_access_tokens      Sanctum
roles                       Spatie
permissions                 Spatie
model_has_roles             Spatie
model_has_permissions       Spatie
role_has_permissions        Spatie
activity_log                Spatie
projects                    id (ULID), user_id, name, description, persona_id (nullable), timestamps, softDeletes
personas                    id (ULID), user_id, name, description, system_prompt, model_name (nullable), temperature, top_p, top_k, repeat_penalty, timestamps, softDeletes
conversations               id (ULID), user_id, project_id (nullable FK), persona_id (nullable FK), title, model_name, context_window_used, timestamps, softDeletes
messages                    id (ULID), conversation_id, role (enum: user|assistant|system), content (text), tokens_used, embedding (vector(1536) nullable), finish_reason, timestamps, softDeletes
message_attachments         id (ULID), message_id, disk, path, filename, mime_type, size, timestamps
ai_models                   id (ULID), name, ollama_model_id, description, context_window, capabilities (json), is_active, is_default, parameter_count, timestamps
training_datasets           id (ULID), user_id, name, description, disk, path, format (enum: sharegpt|alpaca), row_count, timestamps, softDeletes
training_jobs               id (ULID), user_id, dataset_id, base_model_id, output_model_name, config (json), status (enum: pending|running|completed|failed|cancelled), progress, log_output (text), started_at, completed_at, timestamps
user_settings               id (ULID), user_id, default_model_id (nullable FK), default_persona_id (nullable FK), theme (enum: system|light|dark), font_size, send_on_enter, show_token_counts, timestamps
```

### Index strategy:

Every foreign key indexed. Additionally:

- `conversations`: index on `user_id`, `project_id`, `created_at DESC`
- `messages`: index on `conversation_id`, `created_at ASC`
- `messages`: GIN index on `embedding` for pgvector (when populated)
- `activity_log`: index on `causer_id`, `subject_id`, `created_at`
- `training_jobs`: index on `user_id`, `status`

### All primary keys:

ULIDs via `$table->ulid('id')->primary()`. Never auto-increment integers on public-facing models.

### Soft deletes:

conversations, messages, personas, projects, training_datasets, training_jobs

---

## AUTHENTICATION AND AUTHORIZATION

### Sanctum SPA configuration:

Cookie-based auth. Configure `sanctum.stateful` domains. CSRF token handling in the
Vue Axios instance. Token-based auth also enabled via `createToken()` for future native
app support — both modes supported simultaneously.

### Spatie roles and permissions to seed:

Roles:
- `super-admin` (level 100)
- `admin` (level 50)
- `user` (level 10)

Permissions:
- `conversations.create`
- `conversations.delete`
- `conversations.view-all` (admin)
- `models.view`
- `models.manage` (admin)
- `personas.create`
- `personas.manage-own`
- `personas.manage-all` (admin)
- `projects.create`
- `projects.manage-own`
- `training.view`
- `training.manage` (admin)
- `users.manage` (admin)
- `admin.access` (admin)
- `settings.manage-own`

### Registration: invite-only

- `invite_token` column on users, nullable, unique
- `InviteUserAction` generates a signed invite URL
- Invite URLs expire after 72 hours
- Super admin seeded on first run via `php artisan app:seed-super-admin`
- Registration open flag in `.env`: `REGISTRATION_OPEN=false`

---

## API DESIGN

### Versioned routing: all endpoints under `/api/v1/`

### Route files:

**auth.php:**
```
POST   /api/v1/auth/login
POST   /api/v1/auth/logout
POST   /api/v1/auth/register (invite token required)
GET    /api/v1/auth/user
POST   /api/v1/auth/password/forgot
POST   /api/v1/auth/password/reset
GET    /api/v1/auth/email/verify/{id}/{hash}
POST   /api/v1/auth/email/verify/resend
```

**projects.php:**
```
GET    /api/v1/projects
POST   /api/v1/projects
GET    /api/v1/projects/{project}
PUT    /api/v1/projects/{project}
DELETE /api/v1/projects/{project}
```

**conversations.php:**
```
GET    /api/v1/conversations
POST   /api/v1/conversations
GET    /api/v1/conversations/{conversation}
PUT    /api/v1/conversations/{conversation}
DELETE /api/v1/conversations/{conversation}
GET    /api/v1/conversations/{conversation}/messages
POST   /api/v1/conversations/{conversation}/messages
DELETE /api/v1/conversations/{conversation}/messages/{message}
POST   /api/v1/conversations/{conversation}/messages/{message}/regenerate
GET    /api/v1/conversations/{conversation}/export (json or markdown, query param)
```

**models.php:**
```
GET    /api/v1/models
GET    /api/v1/models/{model}
POST   /api/v1/models/pull (admin)
DELETE /api/v1/models/{model} (admin)
GET    /api/v1/models/running (Ollama running models)
```

**personas.php:**
```
GET    /api/v1/personas
POST   /api/v1/personas
GET    /api/v1/personas/{persona}
PUT    /api/v1/personas/{persona}
DELETE /api/v1/personas/{persona}
```

**training.php:**
```
GET    /api/v1/training/datasets
POST   /api/v1/training/datasets
DELETE /api/v1/training/datasets/{dataset}
GET    /api/v1/training/jobs
POST   /api/v1/training/jobs
GET    /api/v1/training/jobs/{job}
POST   /api/v1/training/jobs/{job}/cancel
```

**admin.php:**
```
GET    /api/v1/admin/users
POST   /api/v1/admin/users/invite
PUT    /api/v1/admin/users/{user}
DELETE /api/v1/admin/users/{user}
GET    /api/v1/admin/activity
GET    /api/v1/pulse (Laravel Pulse dashboard proxy)
```

### Health check:
```
GET    /api/health
```

### Response envelope:

All collection responses:
```json
{
  "data": [],
  "meta": { "current_page": 1, "per_page": 20, "total": 100, "path": "..." },
  "links": { "first": "...", "last": "...", "prev": null, "next": "..." }
}
```

All single resource responses:
```json
{ "data": {} }
```

All error responses:
```json
{ "message": "Human readable message", "errors": {}, "code": "ERROR_CODE" }
```

### Pagination: cursor-based on all list endpoints for performance.

### Rate limiting:

- Auth endpoints: 10 requests per minute
- Chat messages: 60 per minute per user
- Model pull: 5 per hour per admin
- General API: 300 per minute per user

---

## SERVICES

### OllamaService:

```php
<?php

declare(strict_types=1);

namespace App\Services;

// Handles all communication with the Ollama API.
// Streaming via Guzzle with chunked transfer.
// Dispatches tokens to Reverb channel in real time.
// Methods:
//   streamChat(Conversation $conversation, string $userMessage): void
//   listModels(): array
//   pullModel(string $modelId): Generator
//   deleteModel(string $modelId): bool
//   showModel(string $modelId): array
//   getRunningModels(): array
//   cancelStream(string $conversationId): void
//   buildMessageHistory(Conversation $conversation): array (handles context window truncation)
```

Context window management: when conversation history approaches the model's context
window limit, truncate oldest messages keeping the system prompt always present.
Log tokens used per response to `messages.tokens_used`.

### TrainingService:

```php
<?php

declare(strict_types=1);

namespace App\Services;

// Manages Axolotl fine-tuning pipeline.
// Methods:
//   startTrainingJob(TrainingJob $job): void
//   cancelTrainingJob(TrainingJob $job): void
//   streamLogs(TrainingJob $job): Generator
//   registerCompletedModel(TrainingJob $job): AiModel
//   buildAxolotlConfig(TrainingJob $job): array
//   validateDataset(TrainingDataset $dataset): array
```

### ModelManagerService:

Wraps OllamaService for model CRUD operations. Syncs Ollama model list with
`ai_models` table. Called on app boot via a scheduled command to keep the DB in sync.

---

## STREAMING ARCHITECTURE

### Flow:

1. User POSTs to `/api/v1/conversations/{id}/messages`
2. Controller creates the user Message record
3. Controller dispatches `StreamInferenceJob` to the `inference` queue
4. Controller immediately returns `202 Accepted` with the message ID
5. `StreamInferenceJob` calls `OllamaService::streamChat()`
6. Ollama streams tokens back via chunked HTTP
7. Each token chunk is broadcast on private Reverb channel `conversation.{id}`
8. Event: `TokenReceived` with `token` and `conversation_id`
9. On stream complete: `StreamCompleted` event with final `message_id`, `tokens_used`, `finish_reason`
10. On error: `StreamFailed` event
11. Assistant Message record created/updated in DB on completion

### Reverb channels:

- `private-conversation.{id}` — token streaming
- `private-user.{id}` — notifications, model pull progress, training job updates

### Frontend Echo subscription:

```js
Echo.private(`conversation.${conversationId}`)
  .listen('TokenReceived', (e) => chatStore.appendToken(e.token))
  .listen('StreamCompleted', (e) => chatStore.finalizeMessage(e.message))
  .listen('StreamFailed', (e) => chatStore.handleStreamError(e.error))
```

---

## FRONTEND — VUE3 SPA

### Dockerfile (multi-stage):

```dockerfile
FROM node:20-alpine AS build
WORKDIR /app
COPY package*.json ./
RUN npm ci
COPY . .
RUN npm run build

FROM nginx:alpine AS production
COPY --from=build /app/dist /usr/share/nginx/html
COPY docker/nginx/nginx.conf /etc/nginx/conf.d/default.conf
```

### vite.config.js:

```js
import { defineConfig } from 'vite'
import vue from '@vitejs/plugin-vue'
import AutoImport from 'unplugin-auto-import/vite'
import Components from 'unplugin-vue-components/vite'
import { fileURLToPath, URL } from 'node:url'

export default defineConfig({
  plugins: [
    vue(),
    AutoImport({
      imports: ['vue', 'vue-router', 'pinia', '@vueuse/core'],
      dts: false,
    }),
    Components({
      dirs: ['src/components'],
      dts: false,
    }),
  ],
  resolve: {
    alias: {
      '@': fileURLToPath(new URL('./src', import.meta.url)),
    },
  },
  build: {
    target: 'esnext',
    minify: 'esbuild',
    rollupOptions: {
      output: {
        manualChunks: {
          vendor: ['vue', 'vue-router', 'pinia'],
          ui: ['@vueuse/core', 'axios'],
          markdown: ['markdown-it', 'shiki'],
          chat: ['laravel-echo', 'pusher-js'],
        },
      },
    },
  },
})
```

### NPM packages:

```bash
npm install \
  vue@latest \
  vue-router@4 \
  pinia \
  @vueuse/core \
  axios \
  laravel-echo \
  pusher-js \
  markdown-it \
  shiki \
  @radix-vue/radix-vue \
  class-variance-authority \
  clsx \
  tailwind-merge \
  lucide-vue-next \
  date-fns

npm install --save-dev \
  @vitejs/plugin-vue \
  vite \
  tailwindcss \
  unplugin-auto-import \
  unplugin-vue-components \
  eslint \
  eslint-plugin-vue \
  eslint-config-prettier \
  @vue/eslint-config-prettier \
  prettier \
  stylelint \
  stylelint-config-standard \
  stylelint-config-tailwindcss \
  lint-staged \
  husky
```

### .eslintrc.cjs:

```js
module.exports = {
  root: true,
  env: { browser: true, es2022: true, node: true },
  extends: [
    'eslint:recommended',
    'plugin:vue/vue3-recommended',
    '@vue/eslint-config-prettier',
  ],
  parserOptions: {
    ecmaVersion: 'latest',
    sourceType: 'module',
  },
  rules: {
    'vue/multi-word-component-names': 'error',
    'vue/component-api-style': ['error', ['script-setup']],
    'vue/define-macros-order': ['error', {
      order: ['defineProps', 'defineEmits', 'defineExpose'],
    }],
    'vue/no-unused-vars': 'error',
    'no-console': 'warn',
    'no-debugger': 'error',
    'prefer-const': 'error',
    'no-unused-vars': 'error',
    'eqeqeq': ['error', 'always'],
  },
}
```

### .prettierrc:

```json
{
  "semi": false,
  "singleQuote": true,
  "tabWidth": 2,
  "trailingComma": "es5",
  "printWidth": 100,
  "vueIndentScriptAndStyle": true
}
```

### stylelint.config.cjs:

```js
module.exports = {
  extends: [
    'stylelint-config-standard',
    'stylelint-config-tailwindcss',
  ],
  rules: {
    'no-descending-specificity': null,
  },
}
```

### package.json scripts:

```json
{
  "scripts": {
    "dev": "vite",
    "build": "vite build",
    "preview": "vite preview",
    "lint": "eslint src --ext .vue,.js --fix",
    "lint:style": "stylelint src/**/*.{vue,css} --fix",
    "format": "prettier --write src",
    "lint:all": "npm run lint && npm run lint:style && npm run format"
  },
  "lint-staged": {
    "*.{vue,js}": ["eslint --fix", "prettier --write"],
    "*.{vue,css}": ["stylelint --fix"]
  }
}
```

### husky pre-commit:

```bash
npx husky init
echo "cd frontend && npx lint-staged" >> .husky/pre-commit
echo "cd backend && ./vendor/bin/pint && ./vendor/bin/phpstan analyse --no-progress" >> .husky/pre-commit
```

---

## FRONTEND PAGES AND COMPONENTS

### Pages (lazy loaded via Vue Router):

```
/login                      LoginPage.vue
/register/:token            RegisterPage.vue (invite token in URL)
/                           Redirects to /chat
/chat                       ChatLayout.vue (persistent sidebar)
/chat/:conversationId       ConversationView.vue
/projects                   ProjectsPage.vue
/projects/:projectId        ProjectDetailPage.vue
/personas                   PersonasPage.vue
/models                     ModelsPage.vue
/training                   TrainingPage.vue
/training/:jobId            TrainingJobDetailPage.vue
/settings                   SettingsPage.vue
/admin                      AdminLayout.vue
/admin/users                AdminUsersPage.vue
/admin/activity             AdminActivityPage.vue
/admin/pulse                AdminPulsePage.vue
```

### Vue Router configuration:

- History mode
- Navigation guards checking auth state and permissions
- Redirect unauthenticated users to /login
- Redirect authenticated users away from /login
- Per-route meta: `requiresAuth`, `requiresAdmin`, `title`
- Scroll behavior: restore position on back, top on new navigation

### Pinia stores:

**useAuthStore:**
```js
// state: user, isAuthenticated, isLoading
// actions: login, logout, fetchUser, updateSettings
// getters: isAdmin, isSuperAdmin, userPermissions
```

**useConversationStore:**
```js
// state: conversations (Map by id), activeConversationId, isLoading, pagination
// actions: fetchConversations, createConversation, updateConversation,
//          deleteConversation, fetchMessages, loadMoreMessages
// getters: activeConversation, activeMessages, sortedConversations
```

**useChatStore:**
```js
// state: isStreaming, streamingMessageId, pendingTokens, error
// actions: sendMessage, cancelStream, regenerateMessage, editMessage
// getters: isGenerating
```

**useModelStore:**
```js
// state: models (array), activeModel, isLoading
// actions: fetchModels, setActiveModel, pullModel, deleteModel
// getters: availableModels, defaultModel
```

**useProjectStore:**
```js
// state: projects, activeProjectId
// actions: fetchProjects, createProject, updateProject, deleteProject
```

**usePersonaStore:**
```js
// state: personas, activePersonaId
// actions: fetchPersonas, createPersona, updatePersona, deletePersona
```

**useUIStore:**
```js
// state: sidebarOpen, theme, notifications (array)
// actions: toggleSidebar, setTheme, addNotification, removeNotification
```

### Key components:

**AppSidebar.vue:**
- Project list with conversation counts
- Conversation list grouped by project and date (Today, Yesterday, Last 7 days, Older)
- Search input with debounced API call
- New conversation button
- Collapse button
- Model selector at bottom
- User avatar and settings link at bottom

**ConversationView.vue:**
- Scrollable message list with virtual scrolling for performance
- Auto-scroll to bottom on new messages
- Scroll-to-bottom button when user has scrolled up
- Message input at bottom
- Persona and model selector in header

**MessageBubble.vue:**
- User messages: right aligned
- Assistant messages: left aligned with model avatar
- Markdown rendering via markdown-it
- Code blocks with shiki syntax highlighting, copy button, language label
- Image attachment rendering
- Token count badge (toggleable)
- Regenerate button on last assistant message
- Edit button on user messages
- Copy message button

**ChatInput.vue:**
- Auto-growing textarea
- Send on Enter (configurable), Shift+Enter for newline
- Attach image button (triggers file picker)
- Image preview before send
- Character/token count indicator
- Stop generation button (shown while streaming)
- Disabled state while streaming

**StreamingIndicator.vue:**
- Animated pulsing dots while waiting for first token
- Disappears when tokens start arriving

**ModelSelector.vue:**
- Dropdown matching Claude's model selector style
- Shows model name, parameter count
- Active model highlighted
- Only shows active models

**PersonaSelector.vue:**
- Dropdown for selecting active persona per conversation
- None option (no system prompt)

---

## OLLAMA ENTRYPOINT SCRIPT

```bash
#!/bin/sh

# Start Ollama in background
ollama serve &
OLLAMA_PID=$!

# Wait for Ollama to be ready
echo "Waiting for Ollama..."
until curl -sf http://localhost:11434/api/tags > /dev/null; do
  sleep 1
done
echo "Ollama ready."

# Pull default models if not present
for model in "llama3.2:latest" "qwen2.5:7b" "mistral:7b"; do
  if ! ollama list | grep -q "$model"; then
    echo "Pulling $model..."
    ollama pull "$model"
  fi
done

echo "All models ready."
wait $OLLAMA_PID
```

---

## POSTGRES CONFIGURATION

```conf
# docker/postgres/postgresql.conf

shared_buffers = 4GB
effective_cache_size = 12GB
work_mem = 64MB
maintenance_work_mem = 512MB
max_connections = 25
wal_buffers = 64MB
checkpoint_completion_target = 0.9
random_page_cost = 1.1
effective_io_concurrency = 200
default_statistics_target = 100
log_min_duration_statement = 1000
shared_preload_libraries = 'pg_stat_statements'
```

pgvector extension created in migration:
```php
DB::statement('CREATE EXTENSION IF NOT EXISTS vector');
```

---

## REDIS CONFIGURATION

```conf
# docker/redis/redis.conf

maxmemory 2gb
maxmemory-policy allkeys-lru
save 900 1
save 300 10
save 60 10000
appendonly yes
appendfsync everysec
```

---

## NGINX FRONTEND CONFIGURATION

```nginx
# docker/nginx/nginx.conf

server {
  listen 80;
  root /usr/share/nginx/html;
  index index.html;
  gzip on;
  gzip_types text/plain text/css application/javascript application/json;
  gzip_min_length 1000;

  location / {
    try_files $uri $uri/ /index.html;
  }

  location ~* \.(js|css|png|jpg|jpeg|gif|ico|svg|woff2)$ {
    expires 1y;
    add_header Cache-Control "public, immutable";
  }
}
```

---

## MAKEFILE

```makefile
.PHONY: up down build fresh migrate seed shell tinker logs lint test deploy

up:
	docker compose up -d

down:
	docker compose down

build:
	docker compose build --no-cache

fresh:
	docker compose exec frankenphp php artisan migrate:fresh --seed

migrate:
	docker compose exec frankenphp php artisan migrate

seed:
	docker compose exec frankenphp php artisan db:seed

shell:
	docker compose exec frankenphp sh

tinker:
	docker compose exec frankenphp php artisan tinker

logs:
	docker compose logs -f

lint:
	docker compose exec frankenphp ./vendor/bin/pint
	docker compose exec frankenphp ./vendor/bin/phpstan analyse --no-progress
	cd frontend && npm run lint:all

test:
	docker compose exec frankenphp ./vendor/bin/pest

deploy:
	git pull origin main
	docker compose build frankenphp frontend
	docker compose up -d --no-deps frankenphp frontend
	docker compose exec frankenphp php artisan migrate --force
	docker compose exec frankenphp php artisan config:cache
	docker compose exec frankenphp php artisan route:cache
	docker compose exec frankenphp php artisan event:cache
	docker compose exec frankenphp php artisan horizon:terminate
	curl -X POST "https://api.cloudflare.com/client/v4/zones/${CF_ZONE_ID}/purge_cache" \
		-H "Authorization: Bearer ${CF_API_TOKEN}" \
		-H "Content-Type: application/json" \
		--data '{"purge_everything":true}'
	@echo "Deploy complete."
```

---

## .ENV EXAMPLE

```env
APP_NAME="My AI"
APP_ENV=production
APP_KEY=
APP_DEBUG=false
APP_URL=https://ai.local
REGISTRATION_OPEN=false

DB_CONNECTION=pgsql
DB_HOST=pgbouncer
DB_PORT=5432
DB_DATABASE=ai_platform
DB_USERNAME=ai_user
DB_PASSWORD=

REDIS_HOST=redis
REDIS_PORT=6379

BROADCAST_CONNECTION=reverb
QUEUE_CONNECTION=redis
CACHE_STORE=redis
SESSION_DRIVER=redis
SESSION_LIFETIME=1440

REVERB_APP_ID=
REVERB_APP_KEY=
REVERB_APP_SECRET=
REVERB_HOST=reverb
REVERB_PORT=8080
REVERB_SCHEME=http

OLLAMA_BASE_URL=http://ollama:11434
OLLAMA_DEFAULT_MODEL=llama3.2:latest
OLLAMA_TIMEOUT=300

MINIO_ROOT_USER=
MINIO_ROOT_PASSWORD=
AWS_ACCESS_KEY_ID=
AWS_SECRET_ACCESS_KEY=
AWS_DEFAULT_REGION=us-east-1
AWS_BUCKET=ai-platform
AWS_ENDPOINT=http://minio:9000
AWS_USE_PATH_STYLE_ENDPOINT=true

SENTRY_LARAVEL_DSN=
GLITCHTIP_SECRET_KEY=
GLITCHTIP_DB=glitchtip
GLITCHTIP_DOMAIN=

CLOUDFLARE_ZONE_ID=
CLOUDFLARE_API_TOKEN=

SUPER_ADMIN_NAME=
SUPER_ADMIN_EMAIL=
SUPER_ADMIN_PASSWORD=

TELESCOPE_ENABLED=false
```

---

## EDITORCONFIG

```ini
root = true

[*]
indent_style = space
indent_size = 2
end_of_line = lf
charset = utf-8
trim_trailing_whitespace = true
insert_final_newline = true

[*.php]
indent_size = 4

[*.md]
trim_trailing_whitespace = false
```

---

## CODING STANDARDS ENFORCED EVERYWHERE

### PHP:
- strict_types=1 on every file
- PHPStan level 8 via Larastan — zero errors required
- Laravel Pint (Laravel preset) — zero violations required
- PHP CS Fixer — ordered imports, no unused imports
- Action pattern for all business logic (one public `handle()` method per action)
- Form Requests for all input validation — no validation in controllers
- API Resources for all responses — no raw model serialization
- Policies for all authorization checks
- No raw queries — Eloquent or Query Builder only
- No N+1 queries — eager load all relationships
- Spatie Laravel Data DTOs for all data transfer between layers

### Vue/JS:
- ESLint with vue/vue3-recommended — zero errors required
- Prettier — consistent formatting enforced
- Stylelint — CSS/Tailwind validation
- Composition API with script setup only — no Options API
- One component per file
- Multi-word component names always
- Props validated with defineProps
- Emits declared with defineEmits
- No direct store mutations outside actions
- Services layer for all API calls — no axios calls in components
- Composables for reusable stateful logic

### Both:
- Git commits must be conventional: feat:, fix:, chore:, docs:, refactor:
- Husky pre-commit runs all linters — commit blocked on lint failure
- No console.log in committed code
- No commented out code committed

---

## BUILD ORDER FOR CLAUDE CODE

Build in this exact sequence:

1. Repository structure and all config files (.editorconfig, .gitignore, Makefile, docker/)
2. docker-compose.yml and docker-compose.override.yml
3. All Dockerfiles (backend multi-stage, frontend multi-stage)
4. Laravel scaffold with all packages installed
5. All database migrations in order
6. Seeders (roles, permissions, super admin, default models, default personas)
7. All Models with relationships, casts, scopes
8. All Policies
9. All Form Requests
10. All API Resources
11. OllamaService and ModelManagerService
12. All Controllers and Routes
13. TrainingService
14. Jobs (StreamInferenceJob, ModelPullJob, TrainingJob)
15. Events and Listeners (TokenReceived, StreamCompleted, StreamFailed)
16. Horizon configuration
17. Reverb configuration
18. Scheduled commands (sync models, cleanup old sessions)
19. Health check endpoint
20. Scribe API documentation configuration
21. Vue3 SPA scaffold (vite.config.js, router, stores)
22. shadcn-vue setup and theme configuration
23. Tailwind 4 CSS configuration
24. All Pinia stores
25. Services layer (api.js, auth.js, conversations.js, models.js, etc.)
26. Layout components (AppLayout, AuthLayout, AdminLayout)
27. AppSidebar with full functionality
28. ChatInput with file attachment
29. MessageBubble with markdown and shiki
30. ConversationView with virtual scrolling
31. All remaining pages
32. Admin pages
33. Settings page
34. Dark mode implementation
35. Responsive design pass (xxs, xs, sm, md, lg, xl)
36. Keyboard shortcuts
37. Error boundaries and global error handling
38. Loading states and skeleton screens
39. Toast notification system
40. Final linting pass on entire codebase

---

## NOTES FOR CLAUDE CODE

- Never use auto-increment integers as primary keys on public-facing models — use ULIDs
- Never put business logic in controllers — use Action classes
- Never serialize models directly — always use API Resources
- Never call axios directly from components — use the services layer
- Every container must have a healthcheck
- Every PHP file must declare strict_types=1
- The frontend is a compiled SPA — no Blade templates except the single app.blade.php shell
- web.php has exactly one route: the catch-all serving app.blade.php
- All streaming goes through Reverb — never use SSE
- Ollama requests must be queued — never call Ollama synchronously in a web request
- Context window truncation must happen in OllamaService before every inference call
- pgvector column on messages is nullable — populated asynchronously, not blocking
- All file uploads go through MinIO via Spatie Media Library
- Dark mode uses CSS variables and respects system preference by default
- shadcn-vue components go in src/components/ui/ and are never modified directly
- Custom components that wrap shadcn-vue go in their respective domain directories

---

## SPA PERFORMANCE AND MEMORY MANAGEMENT

This section covers every dimension of frontend performance and memory management.
All of the following must be implemented. None of it is optional.

### VITE BUILD — GRANULAR CHUNK SPLITTING

Replace the basic manualChunks in vite.config.js with a function-based strategy
that gives Rollup full control over granular splitting:

```js
build: {
  target: 'esnext',
  minify: 'esbuild',
  cssMinify: true,
  cssCodeSplit: true,
  reportCompressedSize: false,
  chunkSizeWarningLimit: 500,
  rollupOptions: {
    output: {
      manualChunks(id) {
        if (id.includes('node_modules/vue/')) return 'vue-core'
        if (id.includes('node_modules/vue-router')) return 'vue-router'
        if (id.includes('node_modules/pinia')) return 'pinia'
        if (id.includes('node_modules/@vueuse')) return 'vueuse'
        if (id.includes('node_modules/axios')) return 'axios'
        if (id.includes('node_modules/markdown-it')) return 'markdown'
        if (id.includes('node_modules/shiki')) return 'shiki'
        if (id.includes('node_modules/laravel-echo')) return 'echo'
        if (id.includes('node_modules/pusher-js')) return 'echo'
        if (id.includes('node_modules/lucide-vue-next')) return 'icons'
        if (id.includes('node_modules/@radix-vue')) return 'radix'
        if (id.includes('src/components/ui')) return 'ui-components'
        if (id.includes('src/components/admin')) return 'admin'
        if (id.includes('src/components/training')) return 'training'
      },
      entryFileNames: 'assets/[name].[hash].js',
      chunkFileNames: 'assets/[name].[hash].js',
      assetFileNames: 'assets/[name].[hash].[ext]',
    },
  },
}
```

Shiki is loaded lazily — only when a code block is first encountered in a rendered
message. Never imported at app startup.

### ROUTE-LEVEL CODE SPLITTING

Every single route uses dynamic import. No exceptions:

```js
const routes = [
  { path: '/chat/:id', component: () => import('@/pages/ConversationView.vue') },
  { path: '/admin', component: () => import('@/pages/admin/AdminLayout.vue') },
  { path: '/training', component: () => import('@/pages/TrainingPage.vue') },
  { path: '/training/:jobId', component: () => import('@/pages/TrainingJobDetail.vue') },
  { path: '/models', component: () => import('@/pages/ModelsPage.vue') },
  { path: '/personas', component: () => import('@/pages/PersonasPage.vue') },
  { path: '/settings', component: () => import('@/pages/SettingsPage.vue') },
]
```

Admin and training chunks never download to non-admin users' browsers.

### COMPONENT LAZY LOADING

Heavy components use defineAsyncComponent with loading and error states:

```js
const CodeBlock = defineAsyncComponent({
  loader: () => import('@/components/chat/CodeBlock.vue'),
  loadingComponent: CodeBlockSkeleton,
  delay: 0,
})

const TrainingJobForm = defineAsyncComponent(() =>
  import('@/components/training/TrainingJobForm.vue')
)

const AdminCharts = defineAsyncComponent(() =>
  import('@/components/admin/AdminCharts.vue')
)
```

### VIRTUAL SCROLLING — MANDATORY FOR MESSAGE LIST

Install @tanstack/vue-virtual. The message list MUST use virtual scrolling.
A conversation with 1000 messages must perform identically to one with 10.

```js
import { useVirtualizer } from '@tanstack/vue-virtual'

const virtualizer = useVirtualizer({
  count: messages.value.length,
  getScrollElement: () => scrollRef.value,
  estimateSize: (index) => {
    // Estimate based on content length for better accuracy
    const msg = messages.value[index]
    return msg.content.length > 500 ? 240 : 120
  },
  overscan: 5,
  measureElement: (el) => el.getBoundingClientRect().height,
})
```

Use dynamic measurement (measureElement) so variable-height messages calculate correctly.

### PINIA STORE MEMORY CAPS

Conversation cache is capped. Messages are paginated in memory. Implement in
useConversationStore:

```js
const MAX_CACHED_CONVERSATIONS = 20
const MAX_MESSAGES_IN_MEMORY = 100
const MESSAGES_PER_PAGE = 50

// Conversations map capped at MAX_CACHED_CONVERSATIONS
// When adding new, evict the LRU (oldest accessed) conversation
// Never store full message arrays for non-active conversations in memory
// Only store conversation metadata in the sidebar list
// Full message list only loaded for the active conversation

// When loading older messages (scroll up):
// Append older messages to front of array
// Trim same count from the bottom to maintain MAX_MESSAGES_IN_MEMORY cap
// Track hasMoreAbove and hasMoreBelow flags per conversation
```

### REQUEST CANCELLATION AND CLEANUP

All axios requests are cancellable. Pending requests are cancelled on route change
to prevent memory leaks from orphaned responses:

```js
// services/api.js
const pendingRequests = new Map()

api.interceptors.request.use((config) => {
  const controller = new AbortController()
  config.signal = controller.signal
  const key = `${config.method}:${config.url}`
  if (pendingRequests.has(key)) pendingRequests.get(key).abort()
  pendingRequests.set(key, controller)
  return config
})

api.interceptors.response.use(
  (response) => {
    pendingRequests.delete(`${response.config.method}:${response.config.url}`)
    return response
  },
  (error) => {
    if (axios.isCancel(error)) return Promise.resolve(null)
    return Promise.reject(error)
  }
)

// In router/index.js
router.beforeEach(() => {
  pendingRequests.forEach((c) => c.abort())
  pendingRequests.clear()
})
```

### WEBSOCKET SUBSCRIPTION CLEANUP

Every Echo channel subscription must be torn down in onUnmounted.
Leaked subscriptions are one of the most common Vue memory leak patterns:

```js
// In ConversationView.vue
let channel = null

onMounted(() => {
  channel = Echo.private(`conversation.${route.params.id}`)
    .listen('TokenReceived', handleToken)
    .listen('StreamCompleted', handleComplete)
    .listen('StreamFailed', handleError)
})

onUnmounted(() => {
  if (channel) {
    Echo.leave(`conversation.${route.params.id}`)
    channel = null
  }
})
```

Never subscribe without a corresponding cleanup. Use VueUse composables for
any subscription that needs to survive route changes.

### EVENT LISTENER CLEANUP

All manual addEventListener calls must be in a composable with onUnmounted cleanup.
Prefer VueUse composables (useEventListener, useIntersectionObserver, etc.) which
handle cleanup automatically:

```js
// composables/useKeyboardShortcuts.js
import { useEventListener } from '@vueuse/core'

export function useKeyboardShortcuts() {
  // useEventListener auto-removes on component unmount
  useEventListener(window, 'keydown', (e) => {
    if ((e.metaKey || e.ctrlKey) && e.key === 'k') {
      // open search
    }
  })
}
```

### OBJECT URL CLEANUP

All blob/object URLs created for image previews must be revoked on unmount:

```js
// composables/useFilePreview.js
export function useFilePreview() {
  const previewUrl = ref(null)

  function setFile(file) {
    if (previewUrl.value) URL.revokeObjectURL(previewUrl.value)
    previewUrl.value = file ? URL.createObjectURL(file) : null
  }

  onUnmounted(() => {
    if (previewUrl.value) URL.revokeObjectURL(previewUrl.value)
  })

  return { previewUrl, setFile }
}
```

### v-once AND v-memo ON FINALIZED MESSAGES

Finalized messages that will never change must use v-once to completely remove
them from Vue's reactivity tracking. This is critical for long conversations:

```vue
<!-- Streaming — reactive, updates on every token -->
<MessageBubble
  v-if="message.isStreaming"
  :message="message"
/>

<!-- Finalized — removed from reactivity entirely -->
<MessageBubble
  v-else
  v-once
  :message="message"
/>
```

Use v-memo on the message list wrapper to prevent list re-renders during streaming
when only the last message is changing:

```vue
<div
  v-for="message in virtualMessages"
  :key="message.id"
  v-memo="[message.id, message.isStreaming, message.content]"
>
```

### WEB WORKERS FOR HEAVY PROCESSING

Move these operations off the main thread using Vite's native worker support:

1. Markdown rendering for messages over 1500 characters
2. Syntax highlighting via shiki (wrap existing async API in worker)
3. Conversation full-text search across cached messages
4. Token count estimation

```js
// workers/markdown.worker.js
import MarkdownIt from 'markdown-it'
const md = new MarkdownIt({ html: false, linkify: true, typographer: true })
self.onmessage = ({ data }) => {
  self.postMessage({ id: data.id, html: md.render(data.content) })
}

// composables/useMarkdown.js
const worker = new Worker(
  new URL('../workers/markdown.worker.js', import.meta.url),
  { type: 'module' }
)

const callbacks = new Map()

worker.onmessage = ({ data }) => {
  callbacks.get(data.id)?.(data.html)
  callbacks.delete(data.id)
}

export function useMarkdown() {
  function render(id, content) {
    return new Promise((resolve) => {
      callbacks.set(id, resolve)
      worker.postMessage({ id, content })
    })
  }
  return { render }
}
```

### COMPUTED PROPERTY DISCIPLINE

Computed properties that return new object or array references on every access
break memoization and cause unnecessary re-renders. Rules:

- Never use .map(), .filter(), or spread inside a computed that returns a new reference
  unless the underlying data actually changed
- Derive primitive values from computed when possible
- For transformed lists, ensure the transformation is truly memoized by depending
  only on stable reactive sources

```js
// BAD — new array reference every access
const formatted = computed(() => messages.value.map(m => ({ ...m })))

// GOOD — stable, only recomputes on message change
const messageCount = computed(() => messages.value.length)
const lastMessage = computed(() => messages.value.at(-1))
```

### TAILWIND PURGING AND CLASS DISCIPLINE

- Never construct Tailwind class names via string concatenation at runtime
- All conditional classes use the full class name in a ternary or object syntax
- Use the cn() utility (clsx + tailwind-merge) for all conditional class merging
- No unused shadcn-vue components registered globally — import individually

```js
// BAD — defeats Tailwind's static analysis purging
const cls = `text-${color}-500`

// GOOD — full class names always
const cls = color === 'red' ? 'text-red-500' : 'text-blue-500'
```

### BROWSER CACHING — NGINX FRONTEND

```nginx
server {
  listen 80;
  root /usr/share/nginx/html;
  gzip on;
  gzip_comp_level 6;
  gzip_types text/plain text/css application/javascript application/json
             application/x-javascript text/xml application/xml
             application/xml+rss text/javascript image/svg+xml;
  gzip_min_length 1000;
  gzip_vary on;

  # Hashed JS/CSS — cache forever
  location ~* \.(js|css)$ {
    expires 1y;
    add_header Cache-Control "public, immutable";
    add_header Vary "Accept-Encoding";
  }

  # Static assets — cache forever
  location ~* \.(png|jpg|jpeg|gif|ico|svg|woff2|woff|ttf)$ {
    expires 1y;
    add_header Cache-Control "public, immutable";
  }

  # HTML entry point — never cache, always fresh
  location = /index.html {
    add_header Cache-Control "no-cache, no-store, must-revalidate";
    add_header Pragma "no-cache";
    add_header Expires "0";
  }

  # SPA fallback
  location / {
    try_files $uri $uri/ /index.html;
  }
}
```

### ADDITIONAL NPM PACKAGES FOR PERFORMANCE

```bash
npm install @tanstack/vue-virtual
npm install --save-dev vite-plugin-modulepreload
```

### INSTALL @tanstack/vue-virtual IN BUILD ORDER

Add after step 21 (Vue3 SPA scaffold):
- Install and configure @tanstack/vue-virtual
- Create composables/useVirtualList.js
- Create composables/useFilePreview.js
- Create composables/useMarkdown.js with Web Worker
- Create workers/markdown.worker.js
- Create workers/search.worker.js
- Create services/api.js with full request cancellation

### SUMMARY: MEMORY MANAGEMENT RULES FOR CLAUDE CODE

Every component that subscribes to a WebSocket channel must unsubscribe in onUnmounted.
Every component that creates a blob URL must revoke it in onUnmounted.
Every component that adds a manual event listener must remove it in onUnmounted.
Prefer VueUse composables over manual addEventListener — they handle cleanup automatically.
Finalized messages use v-once — streaming messages do not.
The message list uses @tanstack/vue-virtual — no exceptions.
Pinia conversation store caps cached conversations at 20 and messages in memory at 100.
Axios requests are cancelled on route change via AbortController.
Heavy processing (markdown, syntax highlighting, search) runs in Web Workers.
Shiki is never imported at app startup — loaded lazily on first code block render.
Admin and training route chunks never download to non-admin users.
All Tailwind classes are static strings — never constructed dynamically at runtime.

---

## PWA — PROGRESSIVE WEB APP

Install vite-plugin-pwa. This is the canonical Vite PWA solution using Workbox under the hood.

```bash
npm install --save-dev vite-plugin-pwa workbox-window
```

### vite.config.js PWA configuration:

```js
import { VitePWA } from 'vite-plugin-pwa'

// Add to plugins array:
VitePWA({
  registerType: 'autoUpdate',
  includeAssets: ['favicon.ico', 'apple-touch-icon.png', 'masked-icon.svg'],
  workbox: {
    globPatterns: ['**/*.{js,css,html,ico,png,svg,woff2}'],
    runtimeCaching: [
      {
        // API responses: network first, fall back to cache
        urlPattern: /^https?:\/\/.*\/api\/v1\/.*/i,
        handler: 'NetworkFirst',
        options: {
          cacheName: 'api-cache',
          expiration: { maxEntries: 100, maxAgeSeconds: 300 },
          networkTimeoutSeconds: 10,
        },
      },
      {
        // Static assets: cache first, always fast
        urlPattern: /\.(?:png|jpg|jpeg|svg|gif|webp|ico)$/,
        handler: 'CacheFirst',
        options: {
          cacheName: 'images-cache',
          expiration: { maxEntries: 60, maxAgeSeconds: 2592000 },
        },
      },
      {
        // Fonts: stale while revalidate
        urlPattern: /\.(?:woff|woff2|ttf|eot)$/,
        handler: 'StaleWhileRevalidate',
        options: { cacheName: 'fonts-cache' },
      },
    ],
    // Background sync for queued messages sent while offline
    backgroundSync: {
      name: 'message-queue',
      options: { maxRetentionTime: 24 * 60 },
    },
  },
  manifest: {
    name: 'My AI',
    short_name: 'My AI',
    description: 'Personal AI powered by local models',
    theme_color: '#000000',
    background_color: '#000000',
    display: 'standalone',
    orientation: 'portrait',
    start_url: '/chat',
    scope: '/',
    icons: [
      { src: 'icons/icon-192.png', sizes: '192x192', type: 'image/png' },
      { src: 'icons/icon-256.png', sizes: '256x256', type: 'image/png' },
      { src: 'icons/icon-384.png', sizes: '384x384', type: 'image/png' },
      { src: 'icons/icon-512.png', sizes: '512x512', type: 'image/png' },
      {
        src: 'icons/icon-512-maskable.png',
        sizes: '512x512',
        type: 'image/png',
        purpose: 'maskable',
      },
    ],
    shortcuts: [
      {
        name: 'New Chat',
        short_name: 'New Chat',
        description: 'Start a new conversation',
        url: '/chat',
        icons: [{ src: 'icons/shortcut-chat.png', sizes: '96x96' }],
      },
      {
        name: 'Projects',
        short_name: 'Projects',
        description: 'View my projects',
        url: '/projects',
        icons: [{ src: 'icons/shortcut-projects.png', sizes: '96x96' }],
      },
    ],
    screenshots: [
      {
        src: 'screenshots/desktop.png',
        sizes: '1280x800',
        type: 'image/png',
        form_factor: 'wide',
      },
      {
        src: 'screenshots/mobile.png',
        sizes: '390x844',
        type: 'image/png',
        form_factor: 'narrow',
      },
    ],
  },
})
```

### PWA install prompt component (composables/usePWAInstall.js):

```js
export function usePWAInstall() {
  const canInstall = ref(false)
  const isInstalled = ref(false)
  let deferredPrompt = null

  useEventListener(window, 'beforeinstallprompt', (e) => {
    e.preventDefault()
    deferredPrompt = e
    canInstall.value = true
  })

  useEventListener(window, 'appinstalled', () => {
    isInstalled.value = true
    canInstall.value = false
    deferredPrompt = null
  })

  async function install() {
    if (!deferredPrompt) return
    deferredPrompt.prompt()
    const { outcome } = await deferredPrompt.userChoice
    if (outcome === 'accepted') isInstalled.value = true
    deferredPrompt = null
    canInstall.value = false
  }

  return { canInstall, isInstalled, install }
}
```

Show install button in SettingsPage.vue and as a dismissible banner on first visit.

### Offline indicator:

Global component in AppLayout.vue that monitors navigator.onLine via VueUse
useOnline() composable. Shows a banner when offline with message:
"You are offline. Cached conversations are available read-only.
New messages will be sent when you reconnect."

### Service worker update notification:

When a new version of the app is deployed, the service worker detects it.
Show a toast notification: "A new version is available." with a "Reload" button
that calls `updateSW()` from vite-plugin-pwa's virtual module.

```js
import { useRegisterSW } from 'virtual:pwa-register/vue'

const { needRefresh, updateSW } = useRegisterSW()
// Show toast when needRefresh is true
// Call updateSW() on user confirmation
```

---

## ADDITIONAL VITE PLUGINS AND CAPABILITIES

Install all of the following:

```bash
npm install --save-dev \
  vite-plugin-compression2 \
  vite-plugin-imagemin \
  vite-plugin-modulepreload \
  rollup-plugin-visualizer \
  vite-plugin-checker \
  vite-plugin-circular-dependency
```

### vite.config.js — complete plugin list:

```js
import { defineConfig } from 'vite'
import vue from '@vitejs/plugin-vue'
import { VitePWA } from 'vite-plugin-pwa'
import AutoImport from 'unplugin-auto-import/vite'
import Components from 'unplugin-vue-components/vite'
import compression from 'vite-plugin-compression2'
import { visualizer } from 'rollup-plugin-visualizer'
import checker from 'vite-plugin-checker'
import circleDependency from 'vite-plugin-circular-dependency'
import modulepreload from 'vite-plugin-modulepreload'

export default defineConfig(({ mode }) => ({
  plugins: [
    vue(),
    VitePWA({ /* config above */ }),
    AutoImport({
      imports: ['vue', 'vue-router', 'pinia', '@vueuse/core'],
      dts: false,
    }),
    Components({
      dirs: ['src/components'],
      dts: false,
    }),
    compression({
      algorithms: ['gzip', 'brotliCompress'],
      threshold: 1024,
    }),
    modulepreload(),
    checker({
      eslint: { lintCommand: 'eslint ./src --ext .vue,.js' },
    }),
    circleDependency({ outputFilePath: './circular-deps.json' }),
    // Only in analyze mode
    mode === 'analyze' && visualizer({
      open: true,
      filename: 'dist/stats.html',
      gzipSize: true,
      brotliSize: true,
      template: 'treemap',
    }),
  ].filter(Boolean),

  // Dependency pre-bundling optimization
  optimizeDeps: {
    include: [
      'vue',
      'vue-router',
      'pinia',
      'axios',
      '@vueuse/core',
      'laravel-echo',
      'pusher-js',
      'markdown-it',
      'date-fns',
      'clsx',
      'tailwind-merge',
      'class-variance-authority',
    ],
    exclude: ['shiki'], // Loaded lazily, exclude from pre-bundle
  },
}))
```

### package.json analyze script:

```json
{
  "scripts": {
    "analyze": "vite build --mode analyze"
  }
}
```

### Brotli and Gzip pre-compression:

vite-plugin-compression2 generates `.gz` and `.br` files for every asset over 1KB at
build time. Update the Nginx frontend config to serve pre-compressed files:

```nginx
# In docker/nginx/nginx.conf
# Serve pre-compressed brotli first, gzip fallback, then raw
location ~* \.(js|css|html|json|svg)$ {
  gzip_static on;
  brotli_static on;
  expires 1y;
  add_header Cache-Control "public, immutable";
}
```

Install nginx-module-brotli in the frontend Dockerfile:

```dockerfile
FROM nginx:alpine AS production
RUN apk add --no-cache nginx-mod-http-brotli
```

---

## PERSISTENT MEMORY SYSTEM

This is the most important architectural feature for making the AI genuinely useful
over time. It addresses the fundamental flaw that LLMs forget everything between
conversations.

### Database additions:

```sql
-- memories table
id          ULID primary key
user_id     FK users
content     text                    -- the memory content
source_conversation_id  ULID FK conversations nullable
source_message_id       ULID FK messages nullable
category    enum(preference, fact, instruction, context, personality)
embedding   vector(1536) nullable   -- for semantic retrieval
importance  smallint default 5      -- 1-10, decays over time
last_accessed_at  timestamp nullable
access_count      integer default 0
is_active   boolean default true
timestamps
softDeletes

-- memory_conflicts table
id              ULID primary key
user_id         FK users
memory_id       ULID FK memories    -- the newer memory
conflicts_with  ULID FK memories    -- the existing memory it contradicts
resolved        boolean default false
resolution      enum(keep_new, keep_old, merge, dismiss) nullable
timestamps
```

GIN index on embedding column for pgvector similarity search.
Index on user_id, is_active, category, importance.

### Memory extraction pipeline:

`ExtractMemoriesJob` dispatched after every conversation that has 5+ messages,
and whenever a conversation is explicitly ended by the user.

Job process:
1. Load the full conversation
2. Send to Ollama with `format: json` and extraction prompt
3. Extraction system prompt instructs the model to return structured JSON:
   `[{ "content": "...", "category": "...", "importance": 1-10 }]`
4. Validate JSON response, retry up to 3 times on parse failure
5. For each candidate memory:
   a. Generate embedding via Ollama embedding endpoint
   b. Search existing memories for cosine similarity > 0.92 (near-duplicate)
   c. If near-duplicate found: update existing rather than insert
   d. If contradiction detected (similarity 0.7-0.92 but semantic opposition):
      create memory_conflicts record for user review
   e. Otherwise: insert new memory
6. Log extraction results to activity_log

### Memory injection pipeline:

`RetrieveRelevantMemoriesService` called at the start of building every inference
context in OllamaService::buildMessageHistory():

1. Take the user's most recent message
2. Generate embedding for it
3. pgvector similarity search against user's active memories:
   `ORDER BY embedding <=> $query_embedding LIMIT 15`
   weighted by importance and recency
4. Format top memories as a system prompt prefix:
   ```
   Current date and time: {datetime}. Timezone: {tz}.

   Things I remember about you:
   - {memory 1}
   - {memory 2}
   ...

   Use this context naturally. Do not explicitly reference that you remember things
   unless asked.
   ```
5. Update last_accessed_at and increment access_count for retrieved memories
6. Importance decay: a scheduled command runs nightly, reducing importance by 1
   for memories not accessed in 30+ days (floor: 1)

### Memory management UI (/settings/memory):

- Full list of all memories, filterable by category and sortable by importance/date
- Inline edit for each memory content
- Delete individual memories with confirmation
- Bulk delete by category or date range
- Manually add a memory
- Importance score visible and editable
- Conflict resolution UI: show conflicting memories side by side,
  let user choose which to keep or merge
- "Clear all memories" with confirmation and double-confirmation
- Memory count and last updated in settings sidebar
- Toggle: "Enable memory for new conversations" (per-user setting in user_settings)

### Conversation summarization pipeline:

`SummarizeConversationJob` dispatched:
- After every 20 new messages in a conversation (via Horizon job batching)
- Proactively when conversation reaches 80% of the model's context window

Process:
1. Take all unsummarized messages since the last summary
2. Send to Ollama with summarization prompt, `format: json`
3. Store result in `conversation_summaries` table:
   ```
   id, conversation_id, content, covers_message_ids (json array),
   message_count, created_at
   ```
4. When building context in OllamaService::buildMessageHistory():
   - Always include: system prompt, injected memories, current date/time
   - Include the most recent summary as a system message: "Earlier in this conversation: {summary}"
   - Include the last 30 raw messages in full
   - Never exceed the model's context window limit

---

## AI FLAW MITIGATIONS — ARCHITECTURAL REQUIREMENTS

The following architectural decisions directly address known LLM failure modes.
Every one of these must be implemented.

### 1. Stream interruption recovery

Track finish_reason on every message. Values: stop, length, error, interrupted.
Show a visible warning badge on non-stop messages.
Implement a "Continue" button on truncated messages that resumes generation
by appending "Please continue from where you left off" as a hidden user message.
Automatic retry with exponential backoff on stream connection errors (3 retries,
1s, 2s, 4s delays).
Never discard partial tokens — store whatever was received before interruption.

### 2. Streaming token deduplication

Reverb may deliver duplicate events if the client reconnects mid-stream.
Each token event carries a sequence number. Frontend deduplicates by sequence
before appending to the display. Store sequence counter in the streaming message
state in useChatStore.

### 3. Context window transparency

Display a context window usage indicator per conversation (like a progress bar).
Calculated from sum of tokens_used on all messages in the current window.
Warn at 70%, alert at 85%, block and show summarization notice at 95%.
Never silently fail due to context overflow — always surface it to the user.

### 4. Model version pinning

ai_models.ollama_model_id stores the exact version tag (e.g. llama3.2:3.2.1)
not a floating tag (llama3.2:latest).
messages.model_version stores the exact model ID used for each message.
Admin UI shows model changelog and allows pinning/unpinning versions.
When a model is updated via pull, the previous version entry is soft-deleted
and a new entry is created. Old conversations retain their version reference.

### 5. Structured output validation

All calls to Ollama that expect structured output (memory extraction,
summarization, title generation, conversation labeling) must:
- Use Ollama's format: "json" parameter
- Validate the response against an expected schema
- Retry up to 3 times with an appended correction prompt on failure:
  "Your previous response was not valid JSON. Please respond with only
  valid JSON matching this schema: {schema}"
- Log all validation failures to activity_log
- Never write unvalidated model output to the database

### 6. Automatic conversation title generation

After the first user message and first assistant response in a new conversation,
dispatch a TitleGenerationJob.
Send both messages to Ollama with format: json and prompt:
"Generate a short, descriptive title (max 6 words) for this conversation.
Respond with only JSON: { title: string }"
Validate and update conversations.title.
Show a subtle animation in the sidebar when the title updates.

### 7. Time awareness injection

Every inference call MUST include in the system prompt:
"Current date: {date}. Current time: {time}. User timezone: {timezone}."
This is injected by OllamaService::buildMessageHistory() unconditionally.
It cannot be disabled. It is never shown to the user but always present.

### 8. Message edit and regeneration

User can edit any of their previous messages. Editing a message:
1. Updates the message content in the DB (stores original in message_edits table)
2. Soft-deletes all subsequent messages in the conversation
3. Re-triggers inference from the edited point
message_edits table: id, message_id, original_content, edited_at

Regenerate button on the last assistant message:
1. Soft-deletes the last assistant message
2. Re-triggers inference with the same conversation history
3. Temperature slightly randomized (+/- 0.1) to get a different response

### 9. Offline message queue

When a message is sent while offline, store it in IndexedDB via the service worker.
Background sync API retries delivery when connection restores.
Show "Queued" status on the message bubble while offline.
Update to "Sending..." and then normal on reconnect and delivery.

### 10. Error boundaries at every level

Vue error boundaries (onErrorCaptured) at:
- AppLayout level (catches everything)
- ConversationView level (catches chat-specific errors)
- MessageBubble level (a broken message doesn't break the conversation)

Each boundary renders a graceful fallback rather than crashing the whole app.
All caught errors reported to Glitchtip.

---

## ADDITIONAL DATABASE TABLES

Add these to the migrations list in order:

```
conversation_summaries    id, conversation_id, content, covers_message_ids (json), message_count, timestamps
memories                  (full schema above)
memory_conflicts          (full schema above)
message_edits             id, message_id, original_content, edited_at, timestamps
```

---

## ADDITIONAL BACKEND JOBS

Add these to the jobs list:

- ExtractMemoriesJob         — extract and store memories from a conversation
- SummarizeConversationJob   — summarize older messages to reclaim context window
- TitleGenerationJob         — auto-generate conversation title
- MemoryImportanceDecayJob   — nightly: decay importance of unaccessed memories
- SyncOllamaModelsJob        — nightly: sync Ollama model list with ai_models table
- PruneOldActivityJob        — monthly: prune activity_log older than 90 days

---

## ADDITIONAL LARAVEL SCHEDULED COMMANDS

In Console/Kernel.php or routes/console.php:

```php
Schedule::job(new MemoryImportanceDecayJob)->daily()->at('02:00');
Schedule::job(new SyncOllamaModelsJob)->hourly();
Schedule::job(new PruneOldActivityJob)->monthly();
```

---

## INDEXEDDB FOR OFFLINE SUPPORT

Use idb (the lightweight IndexedDB wrapper) for client-side persistence:

```bash
npm install idb
```

Stores:
- `pending-messages`: messages queued while offline, cleared on successful send
- `cached-conversations`: conversation metadata for offline sidebar
- `cached-messages`: last 50 messages per conversation for offline reading

composables/useOfflineStore.js wraps all IndexedDB operations.
Service worker background sync processes pending-messages on reconnect.

---

## UNIVERSAL MODEL SYSTEM — EVERY AVAILABLE OPEN SOURCE AND COMMERCIAL MODEL

The goal is a unified AI router that exposes every major model from every major
provider through a single consistent interface. Local models for privacy and speed,
commercial APIs for capabilities that exceed local hardware. The user can switch
between any model at any time on any conversation.

---

### ADDITIONAL COMPOSER PACKAGES

```bash
composer require \
  echolabs/prism \
  openai-php/laravel \
  google/generative-ai-php \
  anthropic-php/sdk
```

PrismPHP is the primary abstraction layer. It already supports Anthropic, OpenAI,
Mistral, Groq, and Ollama. For providers not yet in Prism, use their native SDKs
wrapped in a consistent internal interface.

---

### PROVIDER ARCHITECTURE

Create app/Services/AI/Providers/ directory with one provider class per service.
Each provider extends AbstractAIProvider and implements AIProviderInterface.

```php
interface AIProviderInterface
{
    public function chat(ChatRequest $dto): ChatResponse;
    public function stream(ChatRequest $dto): Generator;
    public function embed(string $text): array;
    public function listModels(): array;
    public function supportsCapability(Capability $capability): bool;
    public function isAvailable(): bool;
}
```

Capability enum:
```php
enum Capability: string
{
    case CHAT = 'chat';
    case STREAMING = 'streaming';
    case VISION = 'vision';
    case CODE = 'code';
    case REASONING = 'reasoning';
    case FUNCTION_CALLING = 'function_calling';
    case EMBEDDINGS = 'embeddings';
    case IMAGE_GENERATION = 'image_generation';
    case AUDIO_TRANSCRIPTION = 'audio_transcription';
    case AUDIO_GENERATION = 'audio_generation';
    case FILE_ANALYSIS = 'file_analysis';
    case LONG_CONTEXT = 'long_context';
    case WEB_SEARCH = 'web_search';
    case STRUCTURED_OUTPUT = 'structured_output';
}
```

---

### ALL PROVIDERS TO IMPLEMENT

#### LOCAL — via Ollama

Provider: OllamaProvider
Base URL: http://ollama:11434

All models pulled and managed via Ollama. Capabilities detected automatically
from model metadata returned by Ollama API.

Recommended model set to pre-pull (php artisan models:pull --recommended):

Text and reasoning:
- llama3.3:70b-instruct-q4_K_M     — best general purpose, 70B quantized
- llama3.2:3b                       — fastest local responses
- qwen2.5:72b-instruct-q4_K_M      — excellent reasoning and code
- qwen2.5-coder:32b                 — best local code model
- qwq:32b                           — chain-of-thought reasoning
- deepseek-r1:32b                   — best local reasoning model
- deepseek-coder-v2:16b             — strong code model
- mistral:7b-instruct-v0.3          — fast, reliable all-rounder
- mixtral:8x7b-instruct-v0.1        — MoE, strong at instruction following
- phi4:14b                          — Microsoft, surprisingly capable for size
- gemma3:27b                        — Google Gemma 3, strong multilingual

Vision (multimodal):
- llama3.2-vision:11b               — Meta's vision model
- llava:34b                         — strong visual understanding
- moondream2                        — lightweight, fast vision
- minicpm-v:8b                      — good document and image analysis

Embeddings (for memory system):
- nomic-embed-text:latest           — best local embedding model
- mxbai-embed-large:latest          — high quality embeddings
- all-minilm:l6-v2                  — fast, lightweight embeddings

Code specialized:
- codellama:70b-instruct            — Meta code model
- starcoder2:15b                    — BigCode, strong at code completion
- codegemma:7b                      — Google code model

#### REMOTE COMMERCIAL — via API keys

All remote providers are optional. App works fully with local models only.
Remote providers activate when their API key is present in .env.

**Anthropic:**
Provider: AnthropicProvider
Models:
- claude-opus-4-5           — most capable, best reasoning
- claude-sonnet-4-5         — balanced, fast, strong
- claude-haiku-4-5          — fastest, cheapest
Capabilities: chat, streaming, vision, code, reasoning, function_calling,
              file_analysis, long_context, structured_output

**OpenAI:**
Provider: OpenAIProvider
Models:
- gpt-4o                    — multimodal flagship
- gpt-4o-mini               — fast and cheap
- o1                        — extended thinking, best reasoning
- o3-mini                   — fast reasoning
- gpt-4-turbo               — long context
- text-embedding-3-large    — best embeddings
- whisper-1                 — audio transcription
- tts-1-hd                  — high quality TTS
- dall-e-3                  — image generation
Capabilities: all

**Google Gemini:**
Provider: GeminiProvider
Models:
- gemini-2.0-flash          — fastest, multimodal
- gemini-2.0-pro            — most capable Gemini
- gemini-1.5-pro            — 1M token context window
- gemini-1.5-flash          — fast multimodal
- text-embedding-004        — Google embeddings
Capabilities: chat, streaming, vision, code, reasoning, function_calling,
              file_analysis, long_context, audio_transcription, structured_output

**Mistral AI:**
Provider: MistralProvider
Models:
- mistral-large-latest      — flagship
- mistral-medium-latest     — balanced
- codestral-latest          — code specialist
- mistral-embed             — embeddings
Capabilities: chat, streaming, code, function_calling, embeddings, structured_output

**Groq:**
Provider: GroqProvider
Models:
- llama-3.3-70b-versatile   — fastest 70B inference on the planet
- llama-3.1-8b-instant      — ultra fast
- mixtral-8x7b-32768        — fast MoE
- gemma2-9b-it              — fast Gemma
- whisper-large-v3          — fast transcription
Capabilities: chat, streaming, code, audio_transcription
Note: Groq is for speed. Use when response latency is the priority.

**Together AI:**
Provider: TogetherProvider
Access to 100+ open source models at scale without local hardware limits.
Key models:
- meta-llama/Llama-3.3-70B-Instruct-Turbo
- Qwen/Qwen2.5-72B-Instruct-Turbo
- deepseek-ai/DeepSeek-R1
- mistralai/Mixtral-8x22B-Instruct-v0.1
Capabilities: chat, streaming, vision, code, reasoning, embeddings

**OpenRouter:**
Provider: OpenRouterProvider
Single API key routing to 200+ models including all of the above plus:
- xai/grok-2                — Grok 2
- xai/grok-vision-beta      — Grok vision
- perplexity/sonar-pro      — web-grounded responses
- cohere/command-r-plus     — enterprise RAG model
- ai21/jamba-1.5-large      — long context specialist
Use OpenRouter as the fallback provider when a specific model is requested
that isn't available through a direct integration.
Capabilities: everything, depends on routed model

**Replicate:**
Provider: ReplicateProvider
Used exclusively for image generation and video (future).
Models:
- black-forest-labs/flux-1.1-pro    — best image quality available
- black-forest-labs/flux-schnell    — fastest flux
- stability-ai/sdxl                 — Stable Diffusion XL
- ideogram-ai/ideogram-v2           — best text in images
- lucataco/remove-bg                — background removal
Capabilities: image_generation

**Stability AI:**
Provider: StabilityProvider
Models:
- stable-image-ultra        — highest quality
- stable-image-core         — fast generation
- stable-diffusion-3-5-large
Capabilities: image_generation

**ElevenLabs:**
Provider: ElevenLabsProvider
Models:
- eleven_multilingual_v2    — best TTS quality
- eleven_turbo_v2_5         — low latency TTS
- eleven_flash_v2_5         — fastest TTS
Capabilities: audio_generation

**Deepgram:**
Provider: DeepgramProvider
Models:
- nova-3                    — best transcription accuracy
- nova-2                    — fast and accurate
Capabilities: audio_transcription

#### LOCAL IMAGE GENERATION — ComfyUI

Add a comfyui service to docker-compose.yml:

```yaml
comfyui:
  image: ghcr.io/ai-dock/comfyui:latest-cpu
  restart: unless-stopped
  volumes:
    - comfyui_models:/opt/ComfyUI/models
    - comfyui_output:/opt/ComfyUI/output
    - comfyui_input:/opt/ComfyUI/input
  environment:
    COMFYUI_FLAGS: "--cpu"
  deploy:
    resources:
      limits:
        cpus: '4.0'
        memory: 20G
```

ComfyUIProvider in Laravel wraps the ComfyUI API for local image generation.
Models to download into ComfyUI:
- FLUX.1-schnell (fastest local image gen)
- FLUX.1-dev (highest local quality)
- DreamShaper XL (versatile artistic)
- RealVisXL (photorealistic)

---

### DATABASE ADDITIONS FOR MODEL SYSTEM

```sql
-- providers table
id              ULID primary key
name            string              -- 'ollama', 'anthropic', 'openai', etc.
display_name    string
type            enum(local, remote)
base_url        string nullable
is_active       boolean default false
is_configured   boolean default false  -- has valid API key
health_status   enum(healthy, degraded, unavailable) default unavailable
last_health_check_at  timestamp nullable
capabilities    json                -- array of Capability enum values
config          json nullable       -- provider-specific config
timestamps

-- Update ai_models table, add columns:
provider_id         ULID FK providers
version             string nullable     -- exact version string
capabilities        json                -- array of Capability enum values
context_window      integer nullable
max_output_tokens   integer nullable
supports_vision     boolean default false
supports_functions  boolean default false
supports_streaming  boolean default true
input_cost_per_1k   decimal nullable    -- for remote models, USD
output_cost_per_1k  decimal nullable
parameter_count     string nullable     -- '7B', '70B', '405B'
quantization        string nullable     -- 'Q4_K_M', 'Q5_K_S', etc.
ollama_digest       string nullable     -- exact version hash for pinning
last_updated_at     timestamp nullable
update_available    boolean default false
```

---

### FILE READING AND ANALYSIS PIPELINE

Users can attach any file to a message. The system extracts content and sends
it to the appropriate model.

Supported file types and extraction methods:

```php
enum FileType: string
{
    case PDF = 'pdf';
    case DOCX = 'docx';
    case XLSX = 'xlsx';
    case CSV = 'csv';
    case TXT = 'txt';
    case MD = 'md';
    case PHP = 'php';
    case JS = 'js';
    case VUE = 'vue';
    case PY = 'py';
    case JSON = 'json';
    case XML = 'xml';
    case HTML = 'html';
    case IMAGE_PNG = 'png';
    case IMAGE_JPG = 'jpg';
    case IMAGE_WEBP = 'webp';
    case IMAGE_GIF = 'gif';
    case AUDIO_MP3 = 'mp3';
    case AUDIO_WAV = 'wav';
    case AUDIO_M4A = 'm4a';
}
```

Install extraction packages:
```bash
composer require \
  smalot/pdfparser \
  phpoffice/phpword \
  phpoffice/phpspreadsheet \
  league/csv
```

FileExtractionService:
- PDF: smalot/pdfparser extracts text, page by page
- DOCX: phpoffice/phpword extracts text and tables
- XLSX/CSV: phpoffice/phpspreadsheet, converts to structured text
- Code files: sent as-is with syntax context
- Images: sent as base64 to vision-capable models
- Audio: sent to transcription provider (Whisper/Deepgram), transcript used as text

For large files (over 50k tokens), chunk the content and use a summarization
pipeline before sending to the model.

message_attachments additions:
- extracted_text (longtext nullable) — cached extraction result
- extraction_status enum(pending, complete, failed)
- token_estimate integer nullable

---

### IMAGE GENERATION PIPELINE

ImageGenerationService routes to the best available image provider:

Priority order (configurable per user):
1. ComfyUI (local FLUX.1) if running and model loaded
2. Replicate FLUX.1 Pro if API key configured
3. Stability AI if API key configured
4. OpenAI DALL-E 3 if API key configured

Request parameters exposed in UI:
- Prompt (required)
- Negative prompt
- Model selection
- Aspect ratio: 1:1, 16:9, 9:16, 4:3, 3:2
- Quality: standard, high, ultra
- Style: photorealistic, artistic, illustration, cinematic

Generated images stored in MinIO, referenced in message_attachments.
Displayed inline in the message bubble.

---

### AUDIO PIPELINE

AudioTranscriptionService:
Priority: OpenAI Whisper > Groq Whisper > Deepgram Nova
Triggered when user attaches an audio file to a message.
Transcript injected as the message text content.
Original audio file stored in MinIO.

TextToSpeechService:
Priority: ElevenLabs > OpenAI TTS
Optional: "Read aloud" button on any assistant message.
Streams audio directly to browser via Web Audio API.
No storage of generated audio.

---

### ARTISAN COMMANDS — FULL IMPLEMENTATION

Create app/Console/Commands/Models/ directory.

#### models:sync

```
php artisan models:sync [--provider=] [--force]
```

For each active provider:
1. Calls provider's listModels() method
2. For Ollama: parses ollama list response including digest for version pinning
3. For remote: fetches model list from provider API
4. Upserts ai_models records: new models inserted, existing updated
5. Detects capability changes and updates capabilities json
6. Marks models no longer returned by provider as inactive
7. Outputs a summary table: added, updated, removed counts per provider
8. Dispatches CapabilityDetectionJob for any new models

#### models:pull

```
php artisan models:pull {model?} [--recommended] [--all-vision] [--all-code]
```

With no arguments: interactive selection from available Ollama models not yet pulled.
With --recommended: pulls the full recommended set defined in the command class.
Streams pull progress to terminal using output->progressBar().
After pull: runs models:sync to register the new model.
Stores exact ollama digest in ai_models.ollama_digest for version pinning.

#### models:update

```
php artisan models:update {model?} [--all] [--dry-run]
```

For each model (or specified model):
1. Checks Ollama registry for newer version via API
2. Sets ai_models.update_available = true if newer exists
3. With --dry-run: only reports what would be updated
4. Without --dry-run: pulls new version, updates digest, soft-deletes old record
5. Outputs before/after digest comparison for confirmation

Scheduled: runs nightly at 03:00 in --dry-run mode, sets update_available flags.
Admin UI shows update badges on models with available updates.

#### models:list

```
php artisan models:list [--provider=] [--local] [--remote] [--capability=]
```

Renders a formatted console table:
| Provider | Model | Size | Capabilities | Status | Last Updated |
Capabilities displayed as emoji shorthand:
💬 chat  👁 vision  🖥 code  🧠 reasoning  🎨 image  🔊 audio  📄 files

#### models:benchmark

```
php artisan models:benchmark {model?} [--all] [--category=chat|code|reasoning|vision]
```

Runs standardized prompts against models and measures:
- Time to first token (TTFT)
- Tokens per second
- Response quality score (via a judge model prompt)
Stores results in model_benchmarks table.
Displays comparison table sorted by performance.

#### models:status

```
php artisan models:status
```

Calls Ollama API for currently loaded models.
Shows: model name, VRAM/RAM usage, last used, keep-alive TTL.

#### models:unload

```
php artisan models:unload {model}
```

Sends keepAlive: 0 to Ollama to unload a model from memory immediately.
Useful for freeing RAM before running a large model.

#### providers:test

```
php artisan providers:test {provider?}
```

For each provider (or specified):
1. Sends a minimal test message ("Say 'OK'")
2. Measures response time
3. Updates providers.health_status
4. Outputs: Provider | Status | Latency | Model Count

#### providers:list

```
php artisan providers:list
```

Table showing all providers: name, type, configured (API key present),
health status, model count, capabilities.

#### capabilities:sync

```
php artisan capabilities:sync {model?}
```

Re-detects capabilities for models where capabilities json is empty or stale.
For Ollama models: parses modelfile and template to detect vision, function calling.
For remote models: fetches capability data from provider API.
Updates ai_models.capabilities, supports_vision, supports_functions.

---

### MODEL ROUTING SERVICE

ModelRouterService determines the best model for a given task automatically
when the user selects "Auto" mode:

```php
class ModelRouterService
{
    // Given a ChatRequest, select the optimal model
    public function route(ChatRequest $request): AiModel
    {
        // Priority matrix:
        // Has image attachment + needs vision -> best vision model
        // Code-heavy request -> best code model
        // "think" or "reason" keywords -> reasoning model
        // Image generation request -> image model
        // Audio attachment -> transcription first, then chat
        // Long document -> long-context model
        // Default -> user's preferred default model

        // Within each category, prefer:
        // 1. Local Ollama models (privacy, free, fast)
        // 2. Groq (if speed is priority)
        // 3. Best configured remote API
    }
}
```

Auto-routing can be toggled per conversation. User can override at any time.

---

### FRONTEND — MODEL SELECTOR COMPONENT

ModelSelector.vue — expanded for the full model catalog:

Display groups:
- Local Models (Ollama — on device)
- Fast (Groq, optimized for speed)
- Most Capable (Claude Opus, GPT-4o, Gemini Pro)
- Code Specialists
- Vision Models
- Reasoning Models

Each model shows:
- Provider logo/badge
- Model name and version
- Parameter count (7B, 70B, etc.)
- Capability icons: 💬 👁 🖥 🧠 🎨 🔊
- Latency indicator (local/fast/standard)
- Cost indicator for remote models (free/$ /$$ /$$$)
- Update available badge

Model search/filter in the selector dropdown.

"Auto" option at the top that enables ModelRouterService.

---

### FRONTEND — CAPABILITY-AWARE UI

The chat interface adapts based on the selected model's capabilities:

- Image attachment button: only shown if model supports vision OR image generation
- Audio attachment: only shown if model supports audio_transcription
- "Generate image" mode toggle: only shown if image generation capable model selected
- Thinking/reasoning indicator: shown for models with reasoning capability
- File attachment: always shown, but shows warning if model cannot process file type
- Function calling indicator: shown in advanced settings when model supports it

---

### .ENV ADDITIONS FOR ALL PROVIDERS

```env
# Local
OLLAMA_BASE_URL=http://ollama:11434
COMFYUI_BASE_URL=http://comfyui:8188

# Anthropic
ANTHROPIC_API_KEY=

# OpenAI
OPENAI_API_KEY=
OPENAI_ORGANIZATION=

# Google
GOOGLE_GEMINI_API_KEY=

# Mistral
MISTRAL_API_KEY=

# Groq
GROQ_API_KEY=

# Together AI
TOGETHER_API_KEY=

# OpenRouter
OPENROUTER_API_KEY=
OPENROUTER_APP_NAME="My AI"
OPENROUTER_APP_URL=

# Replicate
REPLICATE_API_TOKEN=

# Stability AI
STABILITY_API_KEY=

# ElevenLabs
ELEVENLABS_API_KEY=

# Deepgram
DEEPGRAM_API_KEY=

# Model routing defaults
DEFAULT_LOCAL_MODEL=llama3.2:latest
DEFAULT_EMBEDDING_MODEL=nomic-embed-text:latest
DEFAULT_VISION_MODEL=llama3.2-vision:11b
DEFAULT_CODE_MODEL=qwen2.5-coder:32b
DEFAULT_IMAGE_MODEL=comfyui:flux-schnell
DEFAULT_REASONING_MODEL=deepseek-r1:32b
DEFAULT_TRANSCRIPTION_MODEL=openai:whisper-1
DEFAULT_TTS_MODEL=elevenlabs:eleven_turbo_v2_5

# Auto-routing
MODEL_AUTO_ROUTING=true
PREFER_LOCAL_MODELS=true
```

---

### ADDITIONAL DATABASE TABLE

```sql
model_benchmarks
  id              ULID primary key
  model_id        ULID FK ai_models
  category        enum(chat, code, reasoning, vision, speed)
  prompt_hash     string
  ttft_ms         integer         -- time to first token in milliseconds
  tokens_per_sec  decimal
  total_tokens    integer
  quality_score   decimal nullable -- 1-10, judged by a referee model
  ran_at          timestamp
  timestamps
```

---

### DOCKER COMPOSE ADDITIONS

```yaml
  comfyui:
    image: ghcr.io/ai-dock/comfyui:latest-cpu
    restart: unless-stopped
    volumes:
      - comfyui_models:/opt/ComfyUI/models
      - comfyui_output:/opt/ComfyUI/output
      - comfyui_input:/opt/ComfyUI/input
    environment:
      COMFYUI_FLAGS: "--cpu --lowvram"
    healthcheck:
      test: ["CMD", "curl", "-f", "http://localhost:8188/system_stats"]
      interval: 30s
      timeout: 10s
      retries: 5
    deploy:
      resources:
        limits:
          cpus: '4.0'
          memory: 20G
```

Add comfyui_models, comfyui_output, comfyui_input to volumes.

---

### BUILD ORDER ADDITIONS

Insert after step 15 (Events and Listeners):

15a. Create Providers directory and AIProviderInterface
15b. Implement all provider classes (OllamaProvider, AnthropicProvider,
     OpenAIProvider, GeminiProvider, MistralProvider, GroqProvider,
     TogetherProvider, OpenRouterProvider, ReplicateProvider,
     StabilityProvider, ElevenLabsProvider, DeepgramProvider, ComfyUIProvider)
15c. Create ModelRouterService
15d. Create FileExtractionService with all file type handlers
15e. Create ImageGenerationService with provider routing
15f. Create AudioTranscriptionService with provider routing
15g. Create TextToSpeechService with provider routing
15h. Create all Artisan commands in app/Console/Commands/Models/
15i. Register all commands in Console/Kernel.php

---

### NOTES FOR CLAUDE CODE — MODEL SYSTEM

Every provider class must implement isAvailable() which checks:
1. For local providers: is the service reachable via HTTP health check
2. For remote providers: is an API key configured in .env

ModelRouterService must never throw — always falls back to the user's
configured default model if routing fails.

The providers:test command must be safe to run at any time without side effects.

All Artisan commands must use Laravel's OutputStyle for beautiful terminal output:
tables for lists, progress bars for long operations, colored status indicators.

Capability detection for Ollama models must parse the modelfile template to
determine if the model supports vision (presence of image tokens in template)
and function calling (presence of tools template).

The recommended model pull list in models:pull --recommended must be defined
as a constant array in the command class, not hardcoded in the brief.
It should be easy to update as new models are released.

ComfyUI integration uses its workflow API. Store workflow templates as JSON
files in storage/comfyui/workflows/ — one per image generation style.
The ImageGenerationService selects the appropriate workflow template based
on the requested style and injects the prompt before dispatching.

OpenRouter is the universal fallback. If a user requests a model that is not
directly integrated but exists on OpenRouter, route through OpenRouter automatically.
This means any model on the OpenRouter catalog is available in the app.

---

## INTEGRATIONS AND CONNECTORS SYSTEM

The app supports optional integrations that extend the AI's capabilities. Every
integration is permission-gated per user. Users connect their own accounts with
their own credentials. The AI automatically gains access to enabled integration
tools when composing responses.

This mirrors and extends every connector available in Claude.ai, plus adds
integrations specific to your connected MCP tools.

---

### DATABASE SCHEMA FOR INTEGRATIONS

```sql
-- integration_definitions table (seeded, defines all available integrations)
id                  ULID primary key
name                string unique           -- 'google_calendar', 'github', etc.
display_name        string                  -- 'Google Calendar'
description         text
category            enum(productivity, developer, design, finance, search,
                         career, legal, entertainment, local, ai)
auth_type           enum(oauth2, api_key, pat, none)
oauth_scopes        json nullable           -- required OAuth scopes
icon_url            string nullable
is_active           boolean default true    -- can be disabled globally by admin
requires_permission string nullable         -- spatie permission required to use
documentation_url   string nullable
timestamps

-- user_integrations table (per-user connection state)
id                  ULID primary key
user_id             ULID FK users
integration_id      ULID FK integration_definitions
is_enabled          boolean default false
credentials         text nullable           -- AES-256 encrypted JSON
oauth_token         text nullable           -- encrypted access token
oauth_refresh_token text nullable           -- encrypted refresh token
oauth_expires_at    timestamp nullable
scopes_granted      json nullable
last_used_at        timestamp nullable
last_error          text nullable
metadata            json nullable           -- integration-specific metadata
timestamps
softDeletes

-- integration_tool_calls table (audit log of AI tool usage)
id                  ULID primary key
user_id             ULID FK users
conversation_id     ULID FK conversations
message_id          ULID FK messages
integration_id      ULID FK integration_definitions
tool_name           string
input               json
output              json nullable
status              enum(pending, success, error)
duration_ms         integer nullable
error_message       text nullable
timestamps
```

Credentials are encrypted at rest using Laravel's `encrypt()` / `decrypt()` helpers
which use AES-256-CBC via the APP_KEY. Never store credentials in plaintext.

---

### INTEGRATION SERVICE ARCHITECTURE

```
app/Services/Integrations/
├── IntegrationManager.php          — registry, routes to correct service
├── AbstractIntegrationService.php  — base class with common methods
├── Contracts/
│   └── IntegrationServiceInterface.php
├── Productivity/
│   ├── GoogleCalendarService.php
│   ├── GoogleDriveService.php
│   ├── GmailService.php
│   ├── SlackService.php
│   ├── AppleNotesService.php
│   ├── CalendlyService.php
│   ├── NotionService.php
│   └── MicrosoftCalendarService.php
├── Developer/
│   ├── GitHubService.php
│   ├── GitLabService.php
│   ├── PostmanService.php
│   ├── CloudflareService.php
│   ├── JiraService.php
│   ├── LinearService.php
│   └── VercelService.php
├── Design/
│   ├── MiroService.php
│   └── FigmaService.php
├── Finance/
│   ├── PayPalService.php
│   └── StripeService.php
├── Search/
│   ├── BraveSearchService.php
│   ├── SearXNGService.php
│   └── ApifyService.php
├── Career/
│   ├── IndeedService.php
│   └── DiceService.php
├── Legal/
│   └── HarveyService.php
├── Entertainment/
│   └── SpotifyService.php
└── Local/
    ├── FilesystemService.php
    └── MacOSService.php
```

IntegrationServiceInterface:
```php
interface IntegrationServiceInterface
{
    public function getTools(): array;              // tool definitions for AI
    public function executeTool(string $tool, array $params): mixed;
    public function isConnected(User $user): bool;
    public function getAuthUrl(User $user): ?string; // OAuth URL if applicable
    public function handleCallback(User $user, array $params): void;
    public function disconnect(User $user): void;
    public function testConnection(User $user): bool;
}
```

---

### ALL INTEGRATIONS — FULL DEFINITION

#### PRODUCTIVITY AND CALENDAR

**Google Calendar** (oauth2)
Tools: list_events, create_event, update_event, delete_event,
       find_free_time, list_calendars, respond_to_event
Scopes: calendar.readonly, calendar.events
Your connected MCP: Google Calendar

**Gmail** (oauth2)
Tools: search_messages, read_message, read_thread, create_draft,
       list_labels, get_profile
Scopes: gmail.readonly, gmail.compose, gmail.labels
Your connected MCP: Gmail

**Google Drive** (oauth2)
Tools: search_files, read_file, list_directory, get_file_info
Scopes: drive.readonly
Your connected MCP: Google Drive (via claude-rules)

**Slack** (oauth2)
Tools: list_channels, read_messages, send_message, search_messages,
       list_users, get_channel_info
Scopes: channels:read, chat:write, users:read, search:read
Your connected MCP: Slack

**Apple Notes** (local, mac only)
Tools: list_notes, get_note, add_note, update_note
Auth: none (via AppleScript)
Your connected MCP: Read and Write Apple Notes

**iMessages** (local, mac only)
Tools: get_unread, read_messages, send_message, search_contacts
Auth: none (via AppleScript)
Your connected MCP: Read and Send iMessages

**Calendly** (oauth2)
Tools: list_event_types, get_availability, create_scheduling_link,
       list_events, cancel_event, list_invitees
Your connected MCP: Calendly

**Notion** (oauth2 / api_key)
Tools: search, get_page, create_page, update_page,
       query_database, create_database_entry

**Microsoft Outlook/Calendar** (oauth2)
Tools: same as Google Calendar equivalent
Scopes: Calendars.ReadWrite, Mail.ReadWrite

---

#### DEVELOPER TOOLS

**GitHub** (oauth2 / pat)
Tools: list_repos, get_repo, search_code, list_issues, create_issue,
       get_issue, list_prs, get_pr, create_pr, list_commits,
       get_file_contents, create_or_update_file, list_branches,
       search_repos, get_user

**GitLab** (oauth2 / pat)
Tools: same as GitHub equivalent

**Postman** (api_key)
Tools: list_workspaces, list_collections, get_collection,
       create_collection, list_environments, get_environment,
       list_apis, create_api, generate_collection_from_spec
Your connected MCP: Postman

**Cloudflare** (api_key)
Tools: list_workers, get_worker_code, list_kv_namespaces,
       kv_get, kv_set, list_r2_buckets, list_d1_databases,
       d1_query, search_docs, list_accounts
Your connected MCP: Cloudflare Developer Platform

**Jira** (oauth2 / api_key)
Tools: search_issues, get_issue, create_issue, update_issue,
       list_projects, get_project, add_comment, list_sprints

**Linear** (oauth2 / api_key)
Tools: list_issues, get_issue, create_issue, update_issue,
       list_projects, list_teams, search_issues

**Vercel** (api_key)
Tools: list_deployments, get_deployment, list_projects,
       get_project, list_domains, get_deployment_logs

---

#### DESIGN

**Miro** (oauth2)
Tools: list_boards, get_board, create_board, list_items,
       create_sticky_note, create_shape, create_text
Your connected MCP: Miro

**Figma** (oauth2 / pat)
Tools: get_file, list_projects, get_project_files,
       get_comments, get_component, get_styles

---

#### FINANCE

**PayPal** (oauth2 / api_key)
Tools: list_transactions, create_invoice, send_invoice,
       create_bulk_invoices, get_profile
Your connected MCP: PayPal

**Stripe** (api_key)
Tools: list_customers, get_customer, list_charges,
       list_subscriptions, create_payment_link
Note: Used for v2 billing integration, available now as a read tool

---

#### SEARCH AND RESEARCH

**Brave Search** (api_key)
Tools: web_search, news_search, image_search
Used as the primary web grounding tool for the AI

**SearXNG** (local docker service)
Tools: web_search, news_search
Self-hosted, zero API cost, privacy-preserving
Add searxng service to docker-compose.yml:
```yaml
searxng:
  image: searxng/searxng:latest
  restart: unless-stopped
  volumes:
    - ./docker/searxng:/etc/searxng
  deploy:
    resources:
      limits:
        cpus: '0.5'
        memory: 512M
```

**Apify** (api_key)
Tools: run_actor, get_actor_output, rag_web_browser,
       scrape_url, search_and_scrape
Your connected MCP: Apify

**Microsoft Learn** (none — public)
Tools: search_docs, fetch_doc, search_code_samples
Your connected MCP: Microsoft Learn

---

#### CAREER

**Indeed** (api_key / mcp)
Tools: search_jobs, get_job_details, get_company_data, get_resume
Your connected MCP: Indeed

**Dice** (api_key / mcp)
Tools: search_jobs
Your connected MCP: Dice

**LinkedIn** (oauth2)
Tools: get_profile, search_jobs, get_connections,
       search_people, get_company

---

#### LEGAL

**Harvey** (api_key)
Tools: legal_research, document_analysis, contract_review
Your connected MCP: Harvey

---

#### ENTERTAINMENT

**Spotify** (oauth2)
Tools: get_current_track, get_player_state, play, pause,
       next_track, previous_track, set_volume, search_tracks,
       get_playlist, play_track, set_shuffle, set_repeat
Your connected MCP: Spotify (AppleScript)

---

#### LOCAL (personal tier, mac only)

**Desktop Commander / Filesystem** (local)
Tools: read_file, write_file, list_directory, search_files,
       start_process, execute_command, get_file_info
Your connected MCP: Desktop Commander + Filesystem

**Control Mac** (local)
Tools: run_applescript, get_app_list, get_clipboard,
       set_clipboard, open_app, run_shortcut
Your connected MCP: Control your Mac

**Claude in Chrome** (local)
Tools: navigate, read_page, find_elements, click, form_input,
       get_page_text, execute_javascript, screenshot
Your connected MCP: Claude in Chrome

**Word / Office** (local)
Tools: create_document, open_document, insert_text,
       replace_text, format_text, export_pdf, save_document
Your connected MCP: Word (by Anthropic)

---

### TOOL CALLING ARCHITECTURE

When the AI generates a response and determines it needs to use a tool:

1. OllamaService checks which integrations the user has enabled
2. Builds a tools array from enabled integration tool definitions
3. Passes tools to the model inference call (for models that support function calling)
4. If model returns a tool_use response: IntegrationManager routes to correct service
5. Service executes the tool with the user's stored credentials
6. Result injected back into conversation as a tool_result message
7. Model generates final response incorporating tool result
8. All tool calls logged to integration_tool_calls table

For models that don't support native function calling (most local Ollama models):
Use a ReAct-style prompt engineering approach where the system prompt describes
available tools and the model outputs structured JSON tool calls that the
InferenceJob parses and executes.

---

### PERMISSIONS FOR INTEGRATIONS

Each integration category maps to a Spatie permission.
Users must have the permission to enable integrations in that category.
Super admin has all permissions by default.
Admin can grant/revoke integration categories per user.

```php
// Permissions to seed (add to existing permissions list):
'integrations.productivity'    // Google, Slack, Apple, Calendly, Notion
'integrations.developer'       // GitHub, GitLab, Postman, Cloudflare, Jira
'integrations.design'          // Miro, Figma
'integrations.finance'         // PayPal, Stripe
'integrations.search'          // Brave, SearXNG, Apify
'integrations.career'          // Indeed, Dice, LinkedIn
'integrations.legal'           // Harvey
'integrations.entertainment'   // Spotify
'integrations.local'           // Filesystem, Mac, Chrome (personal only)

// Default role assignments:
// super-admin: all integration permissions
// admin: all except local (local is personal tier only)
// user: productivity, search, entertainment by default
//       others grantable by admin
```

---

### API ROUTES FOR INTEGRATIONS

Add to a new route file routes/api/integrations.php:

```
GET    /api/v1/integrations                     — list all available integrations
GET    /api/v1/integrations/{integration}       — get integration details and user status
POST   /api/v1/integrations/{integration}/connect    — initiate OAuth or save API key
GET    /api/v1/integrations/{integration}/callback   — OAuth callback handler
DELETE /api/v1/integrations/{integration}/disconnect — disconnect integration
POST   /api/v1/integrations/{integration}/test       — test connection
GET    /api/v1/integrations/{integration}/tools      — list available tools
POST   /api/v1/integrations/{integration}/tools/{tool} — execute a tool directly
GET    /api/v1/integrations/tool-calls           — audit log of tool calls
```

---

### FRONTEND — INTEGRATIONS SETTINGS PAGE

Route: /settings/integrations

Layout mirrors Claude.ai's connector settings page.

For each integration category, show a section with:
- Category header and description
- Grid of integration cards
- Each card shows: icon, name, description, connection status badge
- Connected: green badge, "Connected" + disconnect button + last used
- Not connected: "Connect" button
- Requires upgrade: lock icon (for future tier gating)

OAuth flow:
1. User clicks Connect
2. App redirects to OAuth provider
3. Provider redirects back to /settings/integrations?integration=google_calendar&code=...
4. Frontend passes code to /api/v1/integrations/{integration}/connect
5. Laravel exchanges code for token, encrypts and stores
6. Frontend shows success toast, card updates to Connected

API key flow:
1. User clicks Connect
2. Modal with API key input field and link to provider's API key page
3. Submits to /api/v1/integrations/{integration}/connect with api_key
4. Backend tests connection before saving
5. Success: stored encrypted, card updates
6. Failure: error shown inline

Per-conversation integration toggle:
In the conversation header, show an integrations button that opens a popover
listing all connected integrations with per-conversation toggles.
User can enable/disable specific integrations for a specific conversation.
Stored in conversations.enabled_integrations (json array of integration names).

---

### ARTISAN COMMANDS FOR INTEGRATIONS

```
php artisan integrations:list                    — table of all integrations and status
php artisan integrations:seed                    — seed integration_definitions table
php artisan integrations:test {user} {integration} — test a user's integration connection
php artisan integrations:clear-expired-tokens    — refresh or clear expired OAuth tokens
```

---

### SEARXNG DOCKER CONFIGURATION

```yaml
# docker/searxng/settings.yml
use_default_settings: true
server:
  secret_key: "${SEARXNG_SECRET_KEY}"
  limiter: false
  image_proxy: true
ui:
  static_use_hash: true
search:
  safe_search: 0
  autocomplete: ''
  default_lang: en
engines:
  - name: google
    engine: google
    use_mobile_ui: false
  - name: bing
    engine: bing
  - name: duckduckgo
    engine: duckduckgo
  - name: github
    engine: github
  - name: stackoverflow
    engine: stackoverflow
  - name: arxiv
    engine: arxiv
  - name: wikipedia
    engine: wikipedia
```

---

### ADDITIONAL .ENV VARIABLES FOR INTEGRATIONS

```env
# Search
BRAVE_SEARCH_API_KEY=
SEARXNG_BASE_URL=http://searxng:8080
SEARXNG_SECRET_KEY=

# Google OAuth
GOOGLE_CLIENT_ID=
GOOGLE_CLIENT_SECRET=
GOOGLE_REDIRECT_URI=${APP_URL}/api/v1/integrations/google/callback

# GitHub OAuth
GITHUB_CLIENT_ID=
GITHUB_CLIENT_SECRET=
GITHUB_REDIRECT_URI=${APP_URL}/api/v1/integrations/github/callback

# Slack OAuth
SLACK_CLIENT_ID=
SLACK_CLIENT_SECRET=
SLACK_REDIRECT_URI=${APP_URL}/api/v1/integrations/slack/callback

# Spotify OAuth
SPOTIFY_CLIENT_ID=
SPOTIFY_CLIENT_SECRET=
SPOTIFY_REDIRECT_URI=${APP_URL}/api/v1/integrations/spotify/callback

# Calendly OAuth
CALENDLY_CLIENT_ID=
CALENDLY_CLIENT_SECRET=
CALENDLY_REDIRECT_URI=${APP_URL}/api/v1/integrations/calendly/callback

# Microsoft OAuth
MICROSOFT_CLIENT_ID=
MICROSOFT_CLIENT_SECRET=
MICROSOFT_REDIRECT_URI=${APP_URL}/api/v1/integrations/microsoft/callback

# Direct API keys (no OAuth)
POSTMAN_API_KEY=
CLOUDFLARE_API_TOKEN=
BRAVE_SEARCH_API_KEY=
APIFY_API_TOKEN=
HARVEY_API_KEY=
NOTION_API_KEY=
FIGMA_ACCESS_TOKEN=
LINEAR_API_KEY=
JIRA_API_TOKEN=
VERCEL_TOKEN=
DEEPGRAM_API_KEY=
ELEVENLABS_API_KEY=
REPLICATE_API_TOKEN=
STABILITY_API_KEY=
TOGETHER_API_KEY=
GROQ_API_KEY=
OPENROUTER_API_KEY=
MISTRAL_API_KEY=
GOOGLE_GEMINI_API_KEY=
OPENAI_API_KEY=
ANTHROPIC_API_KEY=
PAYPAL_CLIENT_ID=
PAYPAL_CLIENT_SECRET=
STRIPE_SECRET_KEY=
INDEED_API_KEY=
DICE_API_KEY=
```

---

### BUILD ORDER ADDITIONS FOR INTEGRATIONS

Insert after step 15i (all Artisan model commands):

15j. Seed integration_definitions with all integrations defined above
15k. Create IntegrationServiceInterface and AbstractIntegrationService
15l. Implement all integration service classes
15m. Create IntegrationManager registry
15n. Create OAuth controller for all OAuth2 flows
15o. Create integration API routes and controllers
15p. Create integration_tool_calls audit logging
15q. Wire tool calling into OllamaService inference pipeline
15r. Implement ReAct fallback for non-function-calling models
15s. Create integration Artisan commands
15t. Add SearXNG to docker-compose.yml with config

Add to frontend build order after step 32 (All remaining pages):
32a. IntegrationsSettingsPage.vue with category sections and OAuth flow
32b. IntegrationCard.vue component
32c. ApiKeyModal.vue for API key integrations
32d. Per-conversation integrations toggle popover
32e. Tool call display in MessageBubble.vue (show tool calls collapsible)

---

### TOOL CALL DISPLAY IN UI

When the AI uses a tool, display it inline in the conversation similar to
how Claude.ai shows tool use. In MessageBubble.vue:

- Collapsible section above the response text
- Shows: tool icon, integration name, tool name, execution time
- Expandable to show full input and output JSON
- Status indicator: spinner while running, checkmark on success, X on error
- Different visual treatment: subtle background, monospace for JSON

Example display:
```
▶ Used Google Calendar — list_events (124ms)
  [expand to see details]
```

---

### NOTES FOR CLAUDE CODE — INTEGRATIONS

Never store credentials in plaintext. Always use Laravel encrypt()/decrypt().
Every tool execution is logged to integration_tool_calls regardless of success/failure.
OAuth token refresh must be handled transparently — if a token is expired,
attempt refresh before executing the tool, not after failure.
Local integrations (Mac, Filesystem, Chrome) are only available when
the app is accessed from localhost or the specific user has the local permission.
The ReAct fallback prompt must be carefully engineered — include in the
system prompt a structured description of available tools and output format.
Test every integration connection on the providers:test equivalent command.
Integration tool definitions must be cacheable — cache per user per session,
invalidated when user enables/disables an integration.
SearXNG is always available as the default search tool, no API key required.
Brave Search is the premium search option when an API key is configured.

---

## VOICE — TEXT TO SPEECH AND SPEECH TO TEXT

---

### TEXT TO SPEECH — READ ALOUD

Every assistant message has a play button. Audio streams immediately without
waiting for full generation. A global audio player bar appears at the bottom
of the screen while audio is playing.

#### TTS Provider priority (configurable per user in settings):

1. ElevenLabs eleven_turbo_v2_5 (best quality, low latency streaming)
2. OpenAI tts-1-hd (excellent quality, reliable)
3. OpenAI tts-1 (faster, slightly lower quality)
4. Browser Web Speech API (zero cost, always available, no API key required)

Provider auto-selected based on what is configured. Falls back down the list
until a working provider is found. Browser Web Speech API is always the
final fallback requiring no configuration.

#### Backend — TextToSpeechService

```php
// app/Services/Voice/TextToSpeechService.php

// Methods:
// streamAudio(string $text, User $user): StreamedResponse
//   — proxies TTS stream from provider to browser
//   — preprocesses text before sending to provider
//   — selects provider based on user preference and availability

// preprocessText(string $markdown): string
//   — strips markdown syntax for natural reading
//   — converts code blocks based on user preference (read/skip/summarize)
//   — converts URLs to "link"
//   — converts bullet points with natural pauses
//   — wraps output in SSML where provider supports it
//   — runs via a dedicated ProcessTextForSpeechJob
```

Text preprocessing rules before TTS:
- Strip: **, *, __, ~~ (bold, italic, strikethrough markdown)
- Strip: # ## ### (headers — optionally replace with pause)
- Code blocks: replace with "Code example in {language}:" then either
  read the code or say "code omitted" based on user setting
- Inline code: wrap in natural speech ("the {value} variable")
- URLs: replace with "link"
- Bullet points: replace hyphen/asterisk with pause + natural flow
- Numbered lists: read numbers naturally ("First... Second... Third...")
- Tables: describe structure, read cell values with separators
- Math: read as-is (model output should already be natural language)
- Paragraph breaks: insert SSML <break time="600ms"/> where supported
- Bolded text: insert SSML <emphasis> where supported
- Headers: insert SSML <break time="800ms"/> before

#### API route for TTS:

```
POST /api/v1/voice/tts
Body: { message_id: ULID, voice: string, speed: float }
Response: audio/mpeg stream (chunked)
```

The endpoint:
1. Loads the message content
2. Preprocesses text via ProcessTextForSpeechJob (sync, fast)
3. Opens a streaming connection to the TTS provider
4. Proxies the audio stream directly to the browser response
5. Logs the TTS request to integration_tool_calls

#### Frontend — TTS components

**MessageBubble.vue additions:**
- Play button on every assistant message (speaker icon, Lucide)
- Shows spinner while fetching first audio chunk
- Switches to pause icon while playing
- Highlights the message being read in the conversation list
- Stop button appears on the active message

**AudioPlayer.vue — global persistent bar:**
Position: fixed bottom of screen above ChatInput, slides up when active.
Dismissable: clicking X stops playback and hides the bar.
Contents:
- Model avatar / voice provider icon
- Message snippet (first 60 chars of the message being read)
- Waveform visualization (animated bars, not full waveform)
- Current time / total time (or progress bar if duration unknown)
- Rewind 10s button
- Play/pause button (large, centered)
- Skip 10s button
- Speed selector: 0.75x 1x 1.25x 1.5x 2x
- Voice selector: shows available voices for current provider
- Auto-advance toggle: continues reading next message when current ends

**useAudioPlayer.js composable:**
```js
// State:
// isPlaying, isPaused, isStopped
// currentMessageId
// currentTime, duration
// speed (default 1.0)
// selectedVoice
// queue (array of message IDs for auto-advance)

// Actions:
// play(messageId)     — fetch audio stream, start playback
// pause()             — pause at current position
// resume()            — resume from paused position
// stop()              — stop and clear state
// seekTo(seconds)
// setSpeed(rate)
// setVoice(voiceId)
// enqueueMessage(messageId) — add to auto-advance queue

// Implementation:
// Uses fetch() with ReadableStream to receive chunked audio
// Feeds chunks into Web Audio API AudioContext via AudioBufferSourceNode
// Tracks current position via AudioContext.currentTime
// Handles stream errors with automatic retry (once)
```

**Web Audio API implementation:**
```js
const audioContext = new AudioContext()
const sourceNode = audioContext.createBufferSource()

// Stream audio chunks from fetch response
// Decode each chunk with audioContext.decodeAudioData()
// Schedule playback with sourceNode.start() at correct offset
// Chain chunks seamlessly for continuous playback
```

**Keyboard shortcuts for audio:**
- Space: play/pause current audio (when not focused on input)
- Shift+Space: stop audio
- Left arrow: rewind 10s
- Right arrow: skip 10s
- Shift+Up/Down: increase/decrease speed

#### Voice settings in /settings/voice:

- Preferred TTS provider (auto / elevenlabs / openai / browser)
- Voice selection (dropdown of available voices for selected provider)
- Default playback speed
- Code block handling: read code / skip code / say "code omitted"
- Auto-advance: read all messages sequentially (on/off)
- Keyboard shortcut to start reading (default: none, configurable)

#### User settings additions (user_settings table):

```sql
tts_provider        string nullable         -- preferred TTS provider
tts_voice           string nullable         -- preferred voice ID
tts_speed           decimal default 1.0
tts_code_handling   enum(read, skip, summarize) default skip
tts_auto_advance    boolean default false
stt_provider        string nullable         -- preferred STT provider
stt_auto_send       boolean default false
stt_silence_threshold integer default 2000  -- ms of silence before stopping
```

---

### SPEECH TO TEXT — VOICE DICTATION

Microphone button in ChatInput.vue. Click to start recording, click to stop.
Transcript appears in the input for review before sending.

#### STT Provider priority:

1. Groq Whisper large-v3 (fastest cloud Whisper, near real-time)
2. OpenAI Whisper-1 (accurate, reliable)
3. Local Whisper via Ollama (private, no API cost, slightly slower)
4. Browser Web Speech API (always available, real-time, no API key)

Provider auto-selected based on configuration. Browser Web Speech API
always available as final fallback.

#### Two transcription modes:

**Streaming mode (Browser Web Speech API):**
- Real-time transcription as user speaks
- Interim results shown in input as user speaks (grayed out)
- Final result replaces interim on pause/completion
- Zero latency, immediate feedback
- Lower accuracy on technical terms and code

**Batch mode (Whisper):**
- Records entire utterance
- Sends audio file to Whisper API after silence detected
- Returns final transcript
- Higher accuracy especially for code terms, model names, technical jargon
- ~300-500ms processing latency after speaking stops

User can switch between modes in settings. Default: Browser Web Speech if
available, Whisper batch if API key configured and user prefers accuracy.

#### Backend — SpeechToTextService

```php
// app/Services/Voice/SpeechToTextService.php

// Methods:
// transcribe(UploadedFile $audioFile, User $user): string
//   — routes to correct provider based on user preference
//   — returns transcript string
//   — logs to integration_tool_calls

// detectLanguage(UploadedFile $audioFile): string
//   — uses Whisper's language detection
//   — returns ISO language code
```

API route for STT:
```
POST /api/v1/voice/stt
Body: multipart/form-data with audio file (webm/mp4/wav)
Response: { transcript: string, language: string, duration_ms: int }
```

Audio format: Record as WebM/Opus in the browser (best compression, wide support).
Convert to MP3 server-side if required by the provider.

#### Frontend — Voice Input components

**ChatInput.vue additions:**

Microphone button (MicrophoneIcon from Lucide):
- Default state: gray mic icon
- Permission not granted: mic icon with slash
- Ready to record: mic icon, normal color
- Recording: pulsing red mic icon + recording timer
- Processing (Whisper mode): spinner
- Error: red exclamation + tooltip

**VoiceRecorder.vue composable:**
```js
// composables/useVoiceRecorder.js

// State:
// isRecording, isProcessing
// transcript (final)
// interimTranscript (streaming mode only)
// audioLevel (0-1, for waveform)
// duration (recording length in ms)
// error
// hasPermission

// Actions:
// requestPermission()
// startRecording()
// stopRecording()          — triggers transcription
// cancelRecording()        — discards audio
// toggleRecording()        — start if stopped, stop if recording

// Implementation:
// Uses MediaRecorder API for audio capture
// Stores audio chunks in array, concatenates on stop
// Creates Blob with audio/webm;codecs=opus MIME type
// Sends to /api/v1/voice/stt via multipart FormData
// For browser Web Speech: uses SpeechRecognition API with
//   continuous: false, interimResults: true

// Silence detection:
// Uses Web Audio API AnalyserNode to monitor audio level
// If level < threshold for stt_silence_threshold ms: auto-stop
// Threshold and duration configurable in user settings
```

**WaveformVisualizer.vue:**
Real-time waveform while recording using OffscreenCanvas:
```js
// Uses AnalyserNode.getByteTimeDomainData() at 60fps
// Renders to OffscreenCanvas transferred to Web Worker
// Displays as animated bars in the mic button area
// Color: red while recording, green while processing
// Minimal CPU: requestAnimationFrame loop only while recording
```

**MicrophonePermission.vue:**
Shown when microphone access is denied:
- Clear explanation of why permission is needed
- Step-by-step browser-specific instructions (Chrome, Firefox, Safari)
- Link to browser settings
- "Try again" button that re-requests permission

#### Keyboard shortcuts for voice input:

- Configurable push-to-talk key (default: none, user sets in settings)
- When push-to-talk key is set: hold key to record, release to transcribe
- Spacebar push-to-talk mode: only when input is NOT focused

#### Continuous dictation mode:

Toggle button in ChatInput.vue (next to mic button):
- Microphone stays open continuously
- Each utterance (sentence/phrase) detected by silence threshold
- Appended to input field with a space separator
- User sees growing transcript in the input
- Sends when user clicks Send or presses Enter
- Cancel button clears the accumulated transcript

#### Language support:

Whisper supports 99 languages. Web Speech API supports major languages.
Language auto-detected by default. User can pin a language in voice settings
for better accuracy when speaking in a specific language.

---

### ADDITIONAL NPM PACKAGES FOR VOICE

No additional packages required. All voice features use native browser APIs:
- MediaRecorder API (audio capture)
- Web Speech API (browser STT)
- Web Audio API (audio playback + waveform)
- OffscreenCanvas (waveform rendering in worker)
- fetch() with ReadableStream (streaming TTS)

All are available in modern browsers with no library dependency.

---

### ADDITIONAL COMPOSER PACKAGES FOR VOICE

No additional packages required beyond what is already in the stack.
OpenAI PHP SDK already handles Whisper transcription.
ElevenLabs and Groq are called via Guzzle HTTP in their provider classes.

---

### PERMISSIONS FOR VOICE

```php
// Add to seeded permissions:
'voice.tts'     // text to speech — default: all users
'voice.stt'     // speech to text — default: all users
```

Both enabled by default for all user roles.
Admin can revoke if needed (e.g. for compliance reasons in multiuser).

---

### BUILD ORDER ADDITIONS FOR VOICE

Insert after step 15s (integration Artisan commands):

15t. Create TextToSpeechService with provider routing and text preprocessing
15u. Create SpeechToTextService with provider routing
15v. Create voice API routes (/api/v1/voice/tts and /api/v1/voice/stt)
15w. Create VoiceController

Add to frontend build order after step 32e (tool call display):

32f. Create useVoiceRecorder.js composable with MediaRecorder + Web Speech API
32g. Create useAudioPlayer.js composable with Web Audio API streaming
32h. Create WaveformVisualizer.vue with OffscreenCanvas worker
32i. Create AudioPlayer.vue global persistent player bar
32j. Create MicrophonePermission.vue
32k. Update ChatInput.vue with mic button and recording states
32l. Update MessageBubble.vue with play button and active reading highlight
32m. Create VoiceSettingsSection.vue for /settings/voice page

---

### NOTES FOR CLAUDE CODE — VOICE

The TTS endpoint must stream the audio response — never buffer the full file
before responding. Use Laravel's StreamedResponse with appropriate headers:
Content-Type: audio/mpeg
Transfer-Encoding: chunked
X-Content-Type-Options: nosniff

The STT endpoint accepts audio/webm, audio/mp4, audio/wav, and audio/ogg.
Validate MIME type server-side before sending to provider.
Maximum audio file size: 25MB (Whisper API limit).
Maximum recording duration: 5 minutes, enforced client-side.

Browser Web Speech API is not available in all browsers (no Firefox support
as of 2025). Always show the mic button regardless, but fall back to Whisper
batch mode in unsupported browsers. Never hide the feature due to browser
limitations — degrade gracefully.

The AudioContext must be created or resumed in response to a user gesture
(Web Audio API autoplay policy). Create it on first play button click, not
on app mount. Store the single AudioContext instance in useAudioPlayer.js
and reuse it for all TTS playback — never create multiple AudioContexts.

Waveform rendering via OffscreenCanvas requires transferring the canvas to
a worker. If OffscreenCanvas is not supported, fall back to a simple CSS
animation (pulsing bars) rather than the real-time waveform.

Text preprocessing for TTS must handle the case where the assistant message
is still streaming. Only enable the play button once the message
finish_reason is 'stop' — never attempt TTS on a partial message.


---

## SECTION A: FINAL CLAUDE.md (COMPLETE VERSION)

Replace the existing CLAUDE.md at ~/sites/ai-platform/CLAUDE.md with
the contents below. This is the authoritative operating manual for
Claude Code CLI. (NOTE: CLAUDE.md has already been written separately.)

---

## SECTION B: PHASE-BY-PHASE CLAUDE CODE CLI PROMPTS

Jeremy: paste each prompt below into Claude Code CLI verbatim.
Wait for Claude Code to finish and confirm completion before pasting
the next prompt. Each prompt ends with a git commit and a stop instruction.

---

### PHASE 1 PROMPT

```
Read BRIEF.md and CLAUDE.md completely before doing anything.

Build Phase 1: Repository Structure and Docker Infrastructure.

Create the following at the project root:
- .editorconfig (4 space indent for PHP/JS/Vue/JSON/YAML, 2 for YAML, LF line endings, final newline, trim trailing whitespace)
- .gitignore (complete version from BRIEF.md covering .env files, vendor, node_modules, docker data volumes, editor files, logs, build artifacts)
- README.md with project name, description, tech stack summary, prerequisites, setup instructions for local and QNAP, and make target reference
- Makefile with all targets specified in BRIEF.md (up, down, build, fresh, migrate, seed, shell, tinker, logs, lint, test, deploy-local, deploy-qnap, ssh-qnap). The DOCKER variable must auto-detect the binary: check for /share/CE_CACHEDEV1_DATA/.qpkg/container-station/usr/bin/.libs/docker first, fall back to docker. QNAP_HOST must come from .env via grep or dotenv parsing.

Create the docker/ directory with all service configuration files:
- docker/postgres/postgresql.conf (tuned: shared_buffers=4GB, work_mem=64MB, effective_cache_size=12GB, maintenance_work_mem=512MB, max_connections=100, wal_level=replica, max_wal_size=1GB, checkpoint_completion_target=0.9)
- docker/postgres/init/ directory with 00-extensions.sql that creates pgvector and uuid-ossp extensions
- docker/redis/redis.conf (maxmemory 2gb, allkeys-lru, appendonly yes, appendfsync everysec)
- docker/ollama/entrypoint.sh (start ollama serve in background, wait for health, pull default models from env, keep alive)
- docker/frankenphp/Caddyfile (PHP server config, gzip, static file serving, /api/* to PHP, SPA fallback)
- docker/searxng/settings.yml (engines: google, bing, duckduckgo, github, stackoverflow, arxiv, wikipedia, safe_search 0, no limiter)
- docker/minio/ directory (empty, data volume mount point)

Create docker-compose.yml at project root with all services defined in BRIEF.md:
frankenphp, horizon, reverb, frontend, postgres, pgbouncer, redis, minio, ollama, open-webui, comfyui, axolotl, glitchtip, searxng, watchtower.
Every port, credential, resource limit, and path must use ${VARIABLE:-default} syntax from .env. Never hardcode any value. Use the safe port assignments from BRIEF.md. Postgres, PgBouncer, and Redis are internal network only, never exposed. All services on a shared bridge network called ai-platform.

Create docker-compose.override.yml for local dev overrides (reduced resource limits, no GPU passthrough, local ports).

Create the three .env files as specified in BRIEF.md:
- .env with local development values
- .env.production with QNAP production values (do not commit this, just create it as reference)
- .env.example with all keys present and no values (this is the only one committed)

Do not create the backend/ or frontend/ directories yet.

When done, run: git add -A && git commit -m "feat: phase 1 — repository structure and docker infrastructure"

Then stop and wait. Do not start Phase 2 until I say go.
```

---

### PHASE 2 PROMPT

```
Read BRIEF.md and CLAUDE.md if you have not already.

Build Phase 2: Laravel 12 Backend Scaffold.

Inside the project root, create the Laravel 12 application in the backend/ directory:
cd to project root and run: composer create-project laravel/laravel backend
Then cd into backend/ for all subsequent work.

Install all Composer packages specified in BRIEF.md:
- laravel/sanctum
- laravel/horizon
- laravel/reverb
- laravel/pulse
- spatie/laravel-permission
- spatie/laravel-data
- spatie/laravel-activitylog
- spatie/laravel-medialibrary
- pgvector/pgvector (Laravel pgvector)
- prism-php/prism (PrismPHP for multi-provider AI)
- echolabsdev/prism (if separate from above, verify correct package)
- tightenco/ziggy
- predis/predis
- league/flysystem-aws-s3-v3

Install dev packages:
- larastan/larastan
- laravel/pint
- laravel/telescope
- friendsofphp/php-cs-fixer
- nunomaduro/collision

Create phpstan.neon at level 8 with Larastan extension, paths to app/, ignoreErrors empty.
Create pint.json with Laravel preset.
Create .php-cs-fixer.php with rules matching Pint defaults.

Create ALL migrations in the exact order specified in BRIEF.md. Every migration must use:
- $table->ulid('id')->primary() for all public-facing models
- Correct foreign key types matching parent (ulid for ulid parents)
- All indexes on foreign keys and frequently queried columns
- Soft deletes where specified (conversations, messages, personas, projects, training_datasets, training_jobs)

Migration order:
1. users (add avatar, locale, timezone, invite_token, last_active_at columns to default)
2. Spatie permission tables (use spatie migration publisher)
3. user_settings
4. projects
5. personas
6. conversations (with project_id nullable FK, persona_id nullable FK, enabled_integrations json, model override columns)
7. messages (with conversation_id FK, role enum, content text, token counts, finish_reason, model used, pgvector embedding column nullable)
8. message_attachments
9. ai_models (provider, model_id, display_name, capabilities json, context_window, pricing json, is_local boolean, is_active boolean)
10. ai_providers
11. training_datasets
12. training_jobs
13. integration_definitions
14. user_integrations
15. integration_tool_calls
16. memories (user_id FK, content text, category, importance integer, source_conversation_id nullable FK, embedding vector(1536), last_accessed_at, access_count, is_active boolean)
17. memory_conflicts
18. conversation_summaries
19. activity_log (Spatie, use publisher)

Create ALL Models with:
- Complete $fillable arrays
- Complete $casts arrays (json columns cast to array, datetime columns cast, enums cast)
- All relationships defined (belongsTo, hasMany, belongsToMany, morphMany as needed)
- ULIDs trait on all public-facing models
- SoftDeletes where specified
- Scope methods for common queries
- $hidden for sensitive attributes

Create ALL Seeders:
- RoleAndPermissionSeeder (all roles and permissions from BRIEF.md)
- DefaultAiModelsSeeder (seed known models for all 13 providers with capabilities)
- IntegrationDefinitionsSeeder (all 30 integrations with name, category, auth_type, icon, description)
- DatabaseSeeder that calls them in order

Create the SuperAdminSeeder or reference the artisan command (app:seed-super-admin) that creates the initial super admin user from .env variables SUPER_ADMIN_NAME, SUPER_ADMIN_EMAIL, SUPER_ADMIN_PASSWORD.

Configure Sanctum for SPA cookie auth in config/sanctum.php (stateful domains from env).
Configure Horizon in config/horizon.php (environments, queue workers, memory limits).
Configure Reverb in config/reverb.php (host, port from env).
Update config/database.php for pgbouncer connection pooling.
Update config/filesystems.php for MinIO S3 disk.
Update config/app.php timezone to America/Los_Angeles.

Do not create controllers, routes, services, or policies yet.

When done, run: git add -A && git commit -m "feat: phase 2 — laravel scaffold, migrations, models, and seeders"

Then stop and wait. Do not start Phase 3 until I say go.
```

---

### PHASE 3 PROMPT

```
Read BRIEF.md and CLAUDE.md if you have not already.

Build Phase 3: All Service Classes.

Working in backend/app/Services/, create every service class specified in BRIEF.md.

AI Provider Services (backend/app/Services/AI/Providers/):
Create the provider interface and all 13 provider classes:
- Contracts/AiProviderInterface.php (methods: chat, stream, embeddings, models, testConnection)
- AbstractAiProvider.php (base class with shared logic)
- OllamaProvider.php (HTTP client to Ollama API, streaming via SSE, model management, pull, delete, running models)
- AnthropicProvider.php
- OpenAiProvider.php
- GoogleProvider.php (Gemini)
- MistralProvider.php
- GroqProvider.php
- TogetherProvider.php
- OpenRouterProvider.php
- ReplicateProvider.php
- StabilityProvider.php (image generation)
- ElevenLabsProvider.php (TTS)
- DeepgramProvider.php (STT)
- ComfyUiProvider.php (image generation via ComfyUI API)

Core AI Services (backend/app/Services/AI/):
- ModelRouterService.php (routes requests to correct provider based on model, handles fallback, respects user preferences for local vs cloud, auto-routing logic)
- StreamingService.php (SSE streaming handler, chunk buffering, finish_reason tracking, stream interruption recovery)
- ContextWindowService.php (token counting, context window management, triggers summarization when at 80%)
- EmbeddingService.php (generates embeddings via configured provider, stores in pgvector)

Media Services (backend/app/Services/Media/):
- FileExtractionService.php (extract text from PDF, DOCX, XLSX, CSV, TXT, images via OCR)
- ImageGenerationService.php (routes to Stability, ComfyUI, or cloud providers)
- AudioTranscriptionService.php (STT via Groq Whisper, OpenAI Whisper, local Whisper)
- TextToSpeechService.php (TTS via ElevenLabs, OpenAI, routes based on config)
- SpeechToTextService.php (STT pipeline coordinator)

Memory Services (backend/app/Services/Memory/):
- MemoryExtractionService.php (extracts facts from conversations using LLM, deduplicates via cosine similarity > 0.92, detects conflicts at 0.7-0.92 similarity)
- MemoryRetrievalService.php (pgvector similarity search, retrieves top 15 memories weighted by importance and recency, formats as system prompt prefix, updates access counts)
- MemoryDecayService.php (nightly importance decay for memories not accessed in 30+ days, floor of 1)
- ConversationSummaryService.php (summarizes conversations after every 20 messages or at 80% context window, stores in conversation_summaries table)

Integration Services (backend/app/Services/Integrations/):
Create the full integration architecture from BRIEF.md:
- Contracts/IntegrationServiceInterface.php (getTools, executeTool, isConnected, getAuthUrl, handleCallback, disconnect, testConnection)
- AbstractIntegrationService.php
- IntegrationManager.php (registry, routes to correct service by name)

Create all 30 integration service classes organized by category:
- Productivity/: GoogleCalendarService, GoogleDriveService, GmailService, SlackService, AppleNotesService, CalendlyService, NotionService, MicrosoftCalendarService
- Developer/: GitHubService, GitLabService, PostmanService, CloudflareService, JiraService, LinearService, VercelService
- Design/: MiroService, FigmaService
- Finance/: PayPalService, StripeService
- Search/: BraveSearchService, SearXNGService, ApifyService
- Career/: IndeedService, DiceService
- Legal/: HarveyService
- Entertainment/: SpotifyService
- Local/: FilesystemService, MacOSService

Each integration service must implement the interface with:
- Tool definitions array (name, description, parameters as JSON Schema)
- executeTool method that dispatches to the correct API call
- isConnected check against user_integrations table
- OAuth URL generation or API key validation as appropriate
- testConnection that makes a lightweight API call to verify credentials

When done, run: git add -A && git commit -m "feat: phase 3 — all service classes, providers, and integrations"

Then stop and wait. Do not start Phase 4 until I say go.
```

---

### PHASE 4 PROMPT

```
Read BRIEF.md and CLAUDE.md if you have not already.

Build Phase 4: Policies, Form Requests, API Resources, Controllers, and Routes.

Policies (backend/app/Policies/):
Create policies for: Conversation, Message, Persona, Project, TrainingDataset, TrainingJob, User, AiModel, UserIntegration.
Each policy must check ownership (user_id matches auth user) and role-based permissions via Spatie. Admin roles bypass ownership checks where specified.

Form Requests (backend/app/Http/Requests/):
Create Form Requests for every endpoint that accepts input. Organize by domain:
- Auth/: LoginRequest, RegisterRequest, ForgotPasswordRequest, ResetPasswordRequest
- Conversation/: StoreConversationRequest, UpdateConversationRequest
- Message/: StoreMessageRequest (validates content, model, attachments, streaming flag)
- Persona/: StorePersonaRequest, UpdatePersonaRequest
- Project/: StoreProjectRequest, UpdateProjectRequest
- Model/: PullModelRequest, UpdateModelRequest
- Training/: StoreDatasetRequest, StartTrainingRequest
- Integration/: ConnectIntegrationRequest, ExecuteToolRequest
- Settings/: UpdateSettingsRequest, UpdateMemoryRequest
- Admin/: UpdateUserRequest, InviteUserRequest

Every request must have authorize() returning a boolean and rules() with complete validation. No validation logic in controllers.

API Resources (backend/app/Http/Resources/):
Create API Resources and Collections for every model returned by the API:
- UserResource, ConversationResource, ConversationCollection, MessageResource, MessageCollection, PersonaResource, PersonaCollection, ProjectResource, ProjectCollection, AiModelResource, AiModelCollection, TrainingDatasetResource, TrainingJobResource, IntegrationResource, MemoryResource, MemoryCollection, SettingsResource

Every resource must use whenLoaded() for relationships to prevent N+1.

Controllers (backend/app/Http/Controllers/Api/V1/):
Create all controllers under the Api/V1 namespace:
- AuthController (login, logout, register, user, forgotPassword, resetPassword, verifyEmail, resendVerification)
- ConversationController (index, store, show, update, destroy, export)
- MessageController (index, store, destroy, regenerate) — store must handle streaming via StreamingService
- PersonaController (CRUD)
- ProjectController (CRUD)
- ModelController (index, show, pull, destroy, running)
- TrainingController (datasets CRUD, jobs CRUD, start, cancel)
- IntegrationController (index, connect, disconnect, callback, executeTools, togglePerConversation)
- SettingsController (show, update)
- MemoryController (index, store, update, destroy, bulkDestroy, resolveConflict)
- AdminController (users index, update, invite, dashboard stats)
- HealthController (single endpoint returning service status for all Docker services)

Every controller method must:
- Use Form Requests for validation
- Use Actions for business logic
- Return API Resources
- Never contain business logic directly

Actions (backend/app/Actions/):
Create action classes for all business logic:
- Auth/: LoginAction, RegisterAction, InviteUserAction
- Conversation/: CreateConversationAction, SendMessageAction, RegenerateMessageAction, ExportConversationAction
- Model/: PullModelAction, SyncModelsAction
- Memory/: ExtractMemoriesAction, ResolveMemoryConflictAction
- Training/: StartTrainingAction, CancelTrainingAction
- Integration/: ConnectIntegrationAction, DisconnectIntegrationAction, ExecuteIntegrationToolAction

Route Files (backend/routes/):
Create routes/api.php as the entry point that loads sub-files from routes/api/:
- routes/api/auth.php (all auth endpoints)
- routes/api/conversations.php (all conversation and message endpoints)
- routes/api/models.php (all model endpoints)
- routes/api/personas.php (all persona endpoints)
- routes/api/projects.php (all project endpoints)
- routes/api/training.php (all training endpoints)
- routes/api/integrations.php (all integration endpoints)
- routes/api/settings.php (settings and memory endpoints)
- routes/api/admin.php (admin endpoints, middleware role check)

All routes under /api/v1/ prefix. All authenticated routes use sanctum middleware. Admin routes use additional role middleware.

Create routes/web.php with a single catch-all that returns the SPA shell view.

When done, run: git add -A && git commit -m "feat: phase 4 — policies, requests, resources, controllers, and routes"

Then stop and wait. Do not start Phase 5 until I say go.
```

---

### PHASE 5 PROMPT

```
Read BRIEF.md and CLAUDE.md if you have not already.

Build Phase 5: Artisan Commands.

Create all Artisan commands in backend/app/Console/Commands/:

Model Management:
- ModelsSync.php (php artisan models:sync — queries all configured providers for available models, updates ai_models table, marks inactive models)
- ModelsPull.php (php artisan models:pull {model} — pulls a model via Ollama API, shows progress)
- ModelsDelete.php (php artisan models:delete {model} — deletes a model from Ollama)
- ModelsList.php (php artisan models:list — table output of all registered models with provider, capabilities, context window, active status)
- ModelsRunning.php (php artisan models:running — shows currently loaded Ollama models with memory usage)

Integration Management:
- IntegrationsList.php (php artisan integrations:list — table of all integration definitions with connected user count)
- IntegrationsSeed.php (php artisan integrations:seed — seeds integration_definitions table from config)
- IntegrationsTest.php (php artisan integrations:test {user} {integration} — tests a specific user's integration connection)
- IntegrationsClearExpired.php (php artisan integrations:clear-expired-tokens — refreshes or clears expired OAuth tokens)

Admin:
- SeedSuperAdmin.php (php artisan app:seed-super-admin — creates super admin from .env SUPER_ADMIN_* variables, idempotent, skips if exists)

Maintenance:
- MemoryDecay.php (php artisan memory:decay — runs nightly importance decay on memories not accessed in 30+ days)
- PruneActivityLog.php (php artisan activity:prune — removes activity log entries older than 90 days)
- CleanupOrphanedFiles.php (php artisan files:cleanup — removes orphaned files from MinIO not referenced by any message_attachment)

Register all commands in the console kernel or via automatic discovery. Schedule memory:decay daily at 3am, activity:prune weekly on Sundays, integrations:clear-expired-tokens daily at 4am.

When done, run: git add -A && git commit -m "feat: phase 5 — all artisan commands"

Then stop and wait. Do not start Phase 6 until I say go.
```

---

### PHASE 6 PROMPT

```
Read BRIEF.md and CLAUDE.md if you have not already.

Build Phase 6: Jobs, Events, Listeners, Queue Config, and WebSocket Config.

Jobs (backend/app/Jobs/):
- ProcessMessageJob (handles AI inference for a message, dispatched by SendMessageAction when not streaming)
- ExtractMemoriesJob (dispatched after message completion, runs MemoryExtractionService)
- SummarizeConversationJob (dispatched after every 20 messages or at 80% context window)
- PullModelJob (long-running job for Ollama model pulls with progress tracking)
- StartTrainingJob (dispatches fine-tuning via Axolotl)
- RefreshIntegrationTokenJob (refreshes an expiring OAuth token)
- ProcessFileExtractionJob (extracts text from uploaded files for context)
- GenerateEmbeddingJob (generates pgvector embedding for a message or memory)

Events (backend/app/Events/):
- MessageCreated (broadcasts to conversation channel)
- MessageStreamChunk (broadcasts SSE chunk to conversation channel)
- MessageCompleted (broadcasts when streaming finishes, includes token counts and finish_reason)
- ConversationCreated
- ConversationUpdated
- ModelPullProgress (broadcasts pull percentage to admin channel)
- TrainingJobStatusChanged
- IntegrationConnected
- IntegrationDisconnected

Listeners (backend/app/Listeners/):
- DispatchMemoryExtraction (listens to MessageCompleted, dispatches ExtractMemoriesJob)
- CheckConversationSummary (listens to MessageCompleted, dispatches SummarizeConversationJob if threshold met)
- LogActivity (listens to all major events, logs to Spatie activity log)
- UpdateUserLastActive (listens to MessageCreated, updates user last_active_at)
- NotifyModelPullComplete (listens to ModelPullProgress when at 100%)

Register all event/listener mappings in EventServiceProvider.

Configure Horizon (config/horizon.php):
- Production environment with supervisor for default queue (processes: 2, tries: 3, timeout: 300)
- Separate supervisor for long-running queue (processes: 1, tries: 1, timeout: 3600) for model pulls and training
- Memory limit 128MB per worker
- Trim completed/failed jobs after 72 hours

Configure Reverb (config/reverb.php):
- Host 0.0.0.0, port from env
- Private channels for conversations (private-conversation.{id})
- Private channel for user notifications (private-user.{id})
- Presence channel for admin dashboard (presence-admin)

Broadcasting channels (backend/routes/channels.php):
- Define authorization for each channel type

Health check endpoint: create a /api/v1/health route (no auth required) that returns status for postgres, redis, ollama, minio, and reverb connectivity.

Schedule configuration in console kernel:
- memory:decay daily at 3:00 AM
- activity:prune weekly on Sundays at 2:00 AM
- integrations:clear-expired-tokens daily at 4:00 AM
- models:sync daily at 5:00 AM
- horizon:snapshot every 5 minutes

When done, run: git add -A && git commit -m "feat: phase 6 — jobs, events, listeners, horizon, reverb, and scheduling"

Then stop and wait. Do not start Phase 7 until I say go.
```

---

### PHASE 7 PROMPT

```
Read BRIEF.md and CLAUDE.md if you have not already.

Build Phase 7: Vue3 SPA Scaffold.

Create the Vue3 application in the frontend/ directory:
cd to project root and run: npm create vite@latest frontend -- --template vue
Then cd into frontend/ for all subsequent work.

Install all npm packages specified in BRIEF.md:
- vue-router@4
- pinia
- axios
- laravel-echo
- pusher-js
- @vueuse/core
- @tanstack/vue-virtual
- markdown-it
- shiki (lazy loaded, never in main bundle)
- lucide-vue-next
- @radix-vue (required by shadcn-vue)
- class-variance-authority
- clsx
- tailwind-merge
- @tailwindcss/vite
- vite-plugin-pwa
- vite-plugin-compression (for Brotli and Gzip)
- workbox-precaching
- workbox-routing
- workbox-strategies

Install dev packages:
- eslint
- eslint-plugin-vue
- @vue/eslint-config-prettier
- prettier
- stylelint
- stylelint-config-standard
- lint-staged
- husky

Configure vite.config.js with:
- Vue plugin
- @tailwindcss/vite plugin
- PWA plugin with Workbox config (precache manifest, runtime caching for API and static assets)
- Compression plugin (Brotli and Gzip)
- Proxy /api and /broadcasting to Laravel backend (https://ai-platform.test in dev)
- Build target: esnext
- Granular manualChunks: vue-core, vue-router, pinia, vueuse, axios, markdown, shiki, echo, icons, radix, ui-components, admin, training
- Content hash filenames for long-lived browser caching
- reportCompressedSize: false

Configure Tailwind CSS 4 (css file with @import "tailwindcss"):
- Custom breakpoints: xxs (360px), xs (480px), sm, md, lg, xl, 2xl
- Dark mode class strategy
- Container center and padding

Initialize shadcn-vue (npx shadcn-vue@latest init):
- Configure components.json
- Install base components: Button, Input, Textarea, Dialog, Dropdown, Popover, Select, Switch, Tabs, Toast, Tooltip, Avatar, Badge, Card, ScrollArea, Separator, Skeleton, Sheet, Command

Set up ESLint config (.eslintrc.cjs or eslint.config.js):
- Vue3 essential rules, prettier integration, no TypeScript parser

Set up Prettier config (.prettierrc):
- Single quotes, no semicolons, 2 space indent, trailing comma es5, 100 print width

Set up Stylelint config (.stylelintrc.json):
- Standard config

Set up Husky and lint-staged:
- npx husky init
- Pre-commit hook runs: lint-staged
- lint-staged config: *.vue and *.js run eslint --fix and prettier --write, *.css runs stylelint --fix

Create package.json scripts:
- dev, build, preview, lint, lint:fix, format, type-check (noop since no TS), test

Create Vue Router (src/router/index.js):
- Lazy loaded routes for all pages:
  / (redirect to /c/new)
  /c/new (new conversation)
  /c/:id (conversation view)
  /settings (settings layout with nested routes)
  /settings/general
  /settings/models
  /settings/personas
  /settings/memory
  /settings/integrations
  /settings/voice
  /settings/appearance
  /admin (admin layout with nested routes)
  /admin/dashboard
  /admin/users
  /admin/models
  /admin/training
  /login
  /register
- Navigation guards for auth check

Create all Pinia stores (src/stores/):
- auth.js (user, login, logout, register, fetchUser)
- conversations.js (list, active, create, update, delete, setActive, fetchMessages)
- messages.js (messages for active conversation, send, delete, regenerate, streaming state)
- models.js (available models, active model, fetch, pull, delete)
- personas.js (list, active, create, update, delete)
- projects.js (list, active, create, update, delete)
- settings.js (user settings, update)
- integrations.js (definitions, connected, connect, disconnect, toggle per conversation)
- memory.js (memories, create, update, delete, bulkDelete, conflicts, resolveConflict)
- ui.js (sidebar open/closed, dark mode, mobile breakpoint, toast queue)

Create services layer (src/services/):
- api.js (Axios instance with baseURL, CSRF cookie handling, auth interceptors, error handling)
- echo.js (Laravel Echo instance configured for Reverb, private channel auth via Sanctum cookie)
- streaming.js (EventSource or fetch with ReadableStream for SSE streaming from /api/v1/conversations/{id}/messages with streaming flag)

Create Web Workers (src/workers/):
- markdown.worker.js (renders markdown-it output off main thread, receives raw markdown string, returns HTML)
- search.worker.js (fuzzy search across conversations and messages off main thread)

Create composables (src/composables/):
- useAuth.js (wraps auth store, provides isAuthenticated, user, login, logout)
- useConversation.js (wraps conversation and message stores, provides send, streaming state, scroll management)
- useStreaming.js (manages SSE connection lifecycle, chunk buffering, abort controller)
- useModel.js (wraps models store, provides activeModel, switchModel)
- useTheme.js (dark mode toggle, system preference detection, persists to localStorage)
- useKeyboard.js (keyboard shortcut registration and dispatch)
- useVoice.js (TTS and STT composable, manages recording state, audio playback)
- useVirtualScroll.js (wraps @tanstack/vue-virtual for message list)
- useMobile.js (responsive breakpoint detection, sidebar auto-collapse)
- useToast.js (toast notification queue management)
- useWebWorker.js (generic worker instantiation and message passing)

Create the main app entry:
- src/main.js (create app, install router, pinia, global error handler)
- src/App.vue (router-view with global toast container and offline indicator)

When done, run: git add -A && git commit -m "feat: phase 7 — vue3 spa scaffold, stores, services, composables, and workers"

Then stop and wait. Do not start Phase 8 until I say go.
```

---

### PHASE 8 PROMPT

```
Read BRIEF.md and CLAUDE.md if you have not already.

Build Phase 8: All Vue Layout and Core Components.

Working in frontend/src/components/, create every component specified in BRIEF.md.

Layout Components (src/components/layout/):
- AppLayout.vue (main authenticated layout: sidebar + content area, responsive)
- AuthLayout.vue (centered card layout for login/register)
- AdminLayout.vue (admin sidebar variant)
- SettingsLayout.vue (settings sidebar with nested router-view)

Sidebar (src/components/sidebar/):
- AppSidebar.vue (conversation list, new conversation button, project filter, search, user menu at bottom, collapsible on mobile)
- SidebarConversationItem.vue (single conversation in list: title, model icon, last message preview, delete action)
- SidebarProjectFilter.vue (dropdown to filter conversations by project)
- SidebarUserMenu.vue (avatar, name, settings link, logout, dark mode toggle)

Chat Core (src/components/chat/):
- ConversationView.vue (main chat view: message list with virtual scrolling via @tanstack/vue-virtual, auto-scroll on new messages, scroll-to-bottom button when not at bottom, empty state for new conversations)
- ChatInput.vue (multi-line textarea with auto-resize, send button, file attachment button with drag-and-drop, voice record button, model selector trigger, persona selector trigger, Shift+Enter for newline, Enter to send, character count, file preview chips for attachments)
- MessageBubble.vue (single message: avatar, role label, rendered markdown content via Web Worker, copy button, edit button for user messages, regenerate button for assistant messages, timestamp, token count, model badge, integration tool call display collapsible, streaming indicator when in progress, v-once on finalized messages)
- StreamingIndicator.vue (animated dots or cursor while assistant is generating)
- MessageActions.vue (hover actions: copy, edit, regenerate, delete)
- ToolCallDisplay.vue (collapsible inline display showing integration name, tool name, execution time, expandable JSON input/output)

Model and Persona Selection (src/components/selectors/):
- ModelSelector.vue (dropdown or command palette showing all available models grouped by provider, with capability badges, search/filter)
- PersonaSelector.vue (dropdown showing user's personas with preview of system prompt, create new option)

File Handling (src/components/files/):
- FileUploadZone.vue (drag-and-drop overlay, click to browse, file type validation, size limit display)
- FilePreviewChip.vue (attached file chip with name, size, type icon, remove button)
- FilePreview.vue (inline preview for images, PDF first page, text file content)

Voice (src/components/voice/):
- AudioPlayer.vue (global audio player for TTS: play/pause, progress bar, speed control, voice selector, appears in message bubble and as a floating player)
- WaveformVisualizer.vue (real-time waveform during recording, uses OffscreenCanvas in a Web Worker, falls back to CSS pulse animation)
- VoiceRecordButton.vue (microphone button with recording state, push-to-talk support, silence detection auto-stop)
- MicrophonePermission.vue (permission request flow with explanation)

Markdown Rendering (src/components/markdown/):
- MarkdownRenderer.vue (receives raw markdown, dispatches to Web Worker for rendering, displays HTML result, handles code blocks with lazy-loaded Shiki syntax highlighting, copy button per code block, math rendering if needed)
- CodeBlock.vue (syntax highlighted code block with language label, copy button, line numbers optional)

When done, run: git add -A && git commit -m "feat: phase 8 — all vue layout and core components"

Then stop and wait. Do not start Phase 9 until I say go.
```

---

### PHASE 9 PROMPT

```
Read BRIEF.md and CLAUDE.md if you have not already.

Build Phase 9: All Page Components.

Create all page-level Vue components in frontend/src/pages/:

Auth Pages:
- LoginPage.vue (email, password, submit, link to register if open)
- RegisterPage.vue (invite token required, name, email, password, confirm password)

Conversation Pages:
- NewConversationPage.vue (empty state with model selector, persona selector, greeting, recent conversations)
- ConversationPage.vue (wraps ConversationView, loads conversation by route param, handles 404)

Settings Pages (nested under /settings):
- SettingsGeneralPage.vue (name, email, avatar upload, timezone, locale, password change)
- SettingsModelsPage.vue (default model selection, model preferences, local vs cloud preference toggle)
- SettingsPersonasPage.vue (list own personas, create, edit inline, delete, set default)
- SettingsMemoryPage.vue (full memory management UI from BRIEF.md: list all memories filterable by category, sortable by importance/date, inline edit, delete, bulk delete by category or date range, manually add memory, importance score visible and editable, conflict resolution UI showing conflicting memories side by side with choose/merge/dismiss, clear all with double confirmation, memory count, toggle "enable memory for new conversations")
- SettingsIntegrationsPage.vue (grid of all integration cards grouped by category, each card shows icon, name, description, connected/disconnected status, connect/disconnect button. Connect triggers OAuth redirect or API key modal. Per-integration settings where applicable.)
- SettingsVoicePage.vue (TTS voice selection with preview, TTS speed, TTS auto-play toggle, STT mode selection: Web Speech vs Whisper, silence detection threshold, continuous dictation toggle, push-to-talk key binding)
- SettingsAppearancePage.vue (dark mode toggle, font size, compact mode, sidebar width)

Admin Pages (nested under /admin, requires admin role):
- AdminDashboardPage.vue (stats: total users, conversations, messages, models, active users last 24h, queue health from Horizon, disk usage, memory usage)
- AdminUsersPage.vue (user list with search, role badges, last active, edit role, invite new user, disable/enable)
- AdminModelsPage.vue (all models across all providers, pull new Ollama models with progress bar, enable/disable, sync button, running models with memory usage)
- AdminTrainingPage.vue (dataset upload, dataset list, start training job with config, job status with real-time updates via Reverb, cancel job)

When done, run: git add -A && git commit -m "feat: phase 9 — all page components including settings and admin"

Then stop and wait. Do not start Phase 10 until I say go.
```

---

### PHASE 10 PROMPT

```
Read BRIEF.md and CLAUDE.md if you have not already.

Build Phase 10: Polish, Dark Mode, Responsive, Keyboard Shortcuts, and UX.

Dark Mode:
- Implement dark mode using Tailwind's class strategy
- useTheme composable reads system preference on first load, user override persists to localStorage and user_settings
- All components must have dark: variants for backgrounds, text, borders, shadows
- Toggle in sidebar user menu and settings/appearance

Responsive Design:
- All layouts must work at all breakpoints: xxs (360px), xs (480px), sm (640px), md (768px), lg (1024px), xl (1280px), 2xl (1536px)
- Sidebar collapses to sheet/drawer on mobile (below md)
- Chat input sticks to bottom on mobile
- Model and persona selectors become full-screen on mobile
- Settings and admin pages stack vertically on mobile
- Touch-friendly tap targets (minimum 44x44px)

Keyboard Shortcuts (registered via useKeyboard composable):
- Ctrl/Cmd+K: open command palette (search conversations, switch model, switch persona)
- Ctrl/Cmd+N: new conversation
- Ctrl/Cmd+Shift+S: toggle sidebar
- Ctrl/Cmd+/: show keyboard shortcut help modal
- Escape: close any open modal/sheet/popover
- Up arrow in empty input: edit last user message

Error Boundaries:
- Global Vue error handler in main.js that logs to Glitchtip/Sentry and shows toast
- Per-component error boundaries for chat, sidebar, settings sections
- Graceful fallback UI when a component errors (not a blank screen)

Toast System:
- useToast composable manages a queue of toast notifications
- Toast types: success, error, warning, info
- Auto-dismiss after configurable duration (default 5 seconds)
- Manual dismiss via close button
- Stack from bottom-right, max 3 visible at once
- Animated enter/exit

Loading States:
- Skeleton screens for: conversation list, message list, settings pages, admin dashboard
- Inline loading spinners for: model pull progress, integration connection, file upload
- Full-page loading for initial app boot (while checking auth)

Offline Support:
- Offline indicator bar at top of screen when navigator.onLine is false
- Queue messages when offline, send when back online (via service worker background sync)
- Show cached conversations from service worker cache when offline
- PWA install prompt component (shows install banner on supported browsers)
- Service worker update notification (toast with "New version available, click to update")

When done, run: git add -A && git commit -m "feat: phase 10 — dark mode, responsive, keyboard shortcuts, error handling, and offline support"

Then stop and wait. Do not start Phase 11 until I say go.
```

---

### PHASE 11 PROMPT

```
Read BRIEF.md and CLAUDE.md if you have not already.

Build Phase 11: Full Lint Pass.

Run the complete linting stack across the entire codebase and fix every single error, warning, and violation. Zero tolerance.

Backend:
1. cd backend/
2. Run: ./vendor/bin/pint --test
   Fix every violation until pint passes with zero issues.
3. Run: ./vendor/bin/phpstan analyse --level=8
   Fix every error until phpstan passes at level 8 with zero errors.
   If any errors require baseline ignoring, document why in phpstan-baseline.neon.
   Do not ignore errors lazily. Fix them properly.
4. Run: ./vendor/bin/php-cs-fixer fix --dry-run --diff
   Fix anything pint missed.
5. Verify strict_types=1 is on every single PHP file. Write a quick grep to check:
   find app/ -name "*.php" | xargs grep -L "declare(strict_types=1)" | head -20
   Fix any file missing it.

Frontend:
1. cd frontend/
2. Run: npx eslint src/ --ext .vue,.js
   Fix every error and warning until ESLint passes clean.
3. Run: npx prettier --check "src/**/*.{vue,js,css,json}"
   Fix every formatting issue.
4. Run: npx stylelint "src/**/*.{css,vue}"
   Fix every CSS violation.
5. Verify no TypeScript has crept in anywhere. No .ts files, no lang="ts" on script tags.

Cross-check:
- Verify no TODO or FIXME comments remain in any file
- Verify no console.log statements remain in production code (only in workers and dev-only blocks)
- Verify no hardcoded IPs, ports, or credentials in any file
- Verify all imports resolve correctly
- Verify no unused imports

When done, run: git add -A && git commit -m "chore: phase 11 — full lint pass, zero errors across entire codebase"

Then stop and wait. Do not start Phase 12 until I say go.
```

---

### PHASE 12 PROMPT

```
Read BRIEF.md and CLAUDE.md if you have not already.

Build Phase 12: Docker Build Test, Deploy Scripts, and Final README.

Docker:
1. Create backend/Dockerfile (multi-stage: composer install, PHP 8.3 with FrankenPHP, copy app, production target with opcache and no dev dependencies)
2. Create frontend/Dockerfile (multi-stage: npm ci, npm run build, nginx or caddy to serve dist/, production target)
3. Run: docker compose build
   Fix any build errors until all images build successfully.
4. Run: docker compose up -d
   Verify all services start and reach healthy status.
   Fix any startup errors.
5. Run: docker compose down

Deploy Scripts:
1. Create deploy.sh at project root:
   - Accepts environment argument (local or qnap)
   - For local: git pull, composer install, npm ci, npm run build, php artisan migrate, php artisan config:cache, php artisan route:cache, php artisan view:cache
   - For qnap: SSH to QNAP_HOST, git pull, docker compose build, docker compose up -d, docker compose exec frankenphp php artisan migrate --force, docker compose exec frankenphp php artisan config:cache, docker compose exec frankenphp php artisan route:cache
2. Make deploy.sh executable: chmod +x deploy.sh
3. Verify Makefile deploy-local and deploy-qnap targets call deploy.sh correctly

Final README.md:
Update README.md to be comprehensive:
- Project name and description
- Screenshots placeholder section
- Tech stack with versions
- Prerequisites (Docker, Node, PHP if local)
- Quick start for local development
- Quick start for QNAP deployment
- Environment variables reference (link to .env.example)
- Available make targets
- Architecture overview (backend/frontend/docker split)
- Contributing guidelines placeholder
- License placeholder

Final Checks:
- Verify .gitignore has no gaps (no .env files, no vendor/node_modules, no docker data)
- Verify .env.example has every key from both .env and .env.production
- Run: git status and verify nothing unexpected is being tracked
- Verify no secrets or credentials are in any committed file

When done, run: git add -A && git commit -m "feat: phase 12 — dockerfiles, deploy scripts, and final documentation"

Then output: "All 12 phases complete. The project is ready for local development and QNAP deployment."
```

---

## SECTION C: RECOVERY PROMPT

If Claude Code CLI gets lost, drifts off spec, hits a context limit,
or starts doing something not in the brief, paste this exactly:

```
Stop. Re-read BRIEF.md and CLAUDE.md completely from the start.
Then continue Phase [N] exactly where you left off.
Do not restart the phase from the beginning.
Do not skip anything.
Do not ask questions. Just continue building.
```

Replace [N] with the current phase number.

---

## SECTION D: ENVIRONMENT FILE VERIFICATION

Cross-referencing all services, providers, and integrations against the
.env files. The following variables must exist in all three env files
(.env, .env.production, .env.example). Any missing from the current
BRIEF.md must be added.

### Core Application
```
APP_NAME=
APP_ENV=
APP_KEY=
APP_DEBUG=
APP_URL=
APP_TIMEZONE=America/Los_Angeles
```

### Ports (all must be env-driven)
```
APP_HTTP_PORT=
APP_HTTPS_PORT=
REVERB_EXTERNAL_PORT=
OPENWEBUI_PORT=
GLITCHTIP_PORT=
MINIO_CONSOLE_PORT=
MINIO_API_PORT=
SEARXNG_PORT=
OLLAMA_PORT=
COMFYUI_PORT=
```

### Database
```
DB_CONNECTION=pgsql
DB_HOST=
DB_PORT=5432
DB_DATABASE=
DB_USERNAME=
DB_PASSWORD=
```

### Redis
```
REDIS_HOST=
REDIS_PORT=6379
REDIS_PASSWORD=null
REDIS_MAX_MEMORY=2gb
REDIS_CONTAINER_MEMORY=2G
```

### Session, Cache, Queue
```
SESSION_DRIVER=redis
SESSION_LIFETIME=120
CACHE_STORE=redis
QUEUE_CONNECTION=redis
```

### Sanctum
```
SANCTUM_STATEFUL_DOMAINS=
SESSION_DOMAIN=
```

### Broadcasting (Reverb)
```
BROADCAST_CONNECTION=reverb
REVERB_APP_ID=
REVERB_APP_KEY=
REVERB_APP_SECRET=
REVERB_HOST=
REVERB_PORT=8080
REVERB_SCHEME=https
```

### MinIO (S3)
```
FILESYSTEM_DISK=s3
AWS_ACCESS_KEY_ID=
AWS_SECRET_ACCESS_KEY=
AWS_DEFAULT_REGION=us-east-1
AWS_BUCKET=
AWS_ENDPOINT=
AWS_USE_PATH_STYLE_ENDPOINT=true
MINIO_ROOT_USER=
MINIO_ROOT_PASSWORD=
```

### Mail
```
MAIL_MAILER=smtp
MAIL_HOST=
MAIL_PORT=
MAIL_USERNAME=
MAIL_PASSWORD=
MAIL_ENCRYPTION=tls
MAIL_FROM_ADDRESS=
MAIL_FROM_NAME=
```

### Super Admin
```
SUPER_ADMIN_NAME=
SUPER_ADMIN_EMAIL=
SUPER_ADMIN_PASSWORD=
```

### Registration
```
REGISTRATION_OPEN=false
```

### AI Providers (all 13)
```
ANTHROPIC_API_KEY=
OPENAI_API_KEY=
OPENAI_ORGANIZATION=
GOOGLE_GEMINI_API_KEY=
MISTRAL_API_KEY=
GROQ_API_KEY=
TOGETHER_API_KEY=
OPENROUTER_API_KEY=
OPENROUTER_APP_NAME=
OPENROUTER_APP_URL=
REPLICATE_API_TOKEN=
STABILITY_API_KEY=
ELEVENLABS_API_KEY=
DEEPGRAM_API_KEY=
```

### Ollama
```
OLLAMA_HOST=ollama
OLLAMA_PORT=11434
OLLAMA_NUM_THREAD=4
OLLAMA_INTEL_GPU=1
OLLAMA_CPU_LIMIT=3.0
OLLAMA_MEMORY_LIMIT=16G
```

### ComfyUI
```
COMFYUI_HOST=comfyui
COMFYUI_PORT=8188
```

### Intel GPU (QNAP)
```
INTEL_GPU_DEVICE_CARD=/dev/dri/card0
INTEL_GPU_DEVICE_RENDER=/dev/dri/renderD128
```

### Docker Resource Limits
```
FRANKENPHP_CPU_LIMIT=2.0
FRANKENPHP_MEMORY_LIMIT=4G
REVERB_CPU_LIMIT=0.5
REVERB_MEMORY_LIMIT=512M
POSTGRES_CPU_LIMIT=1.0
POSTGRES_MEMORY_LIMIT=6G
```

### Search
```
BRAVE_SEARCH_API_KEY=
SEARXNG_BASE_URL=http://searxng:8080
SEARXNG_SECRET_KEY=
```

### Integrations: OAuth
```
GOOGLE_CLIENT_ID=
GOOGLE_CLIENT_SECRET=
GOOGLE_REDIRECT_URI=${APP_URL}/api/v1/integrations/google/callback
GITHUB_CLIENT_ID=
GITHUB_CLIENT_SECRET=
GITHUB_REDIRECT_URI=${APP_URL}/api/v1/integrations/github/callback
SLACK_CLIENT_ID=
SLACK_CLIENT_SECRET=
SLACK_REDIRECT_URI=${APP_URL}/api/v1/integrations/slack/callback
SPOTIFY_CLIENT_ID=
SPOTIFY_CLIENT_SECRET=
SPOTIFY_REDIRECT_URI=${APP_URL}/api/v1/integrations/spotify/callback
CALENDLY_CLIENT_ID=
CALENDLY_CLIENT_SECRET=
CALENDLY_REDIRECT_URI=${APP_URL}/api/v1/integrations/calendly/callback
MICROSOFT_CLIENT_ID=
MICROSOFT_CLIENT_SECRET=
MICROSOFT_REDIRECT_URI=${APP_URL}/api/v1/integrations/microsoft/callback
```

### Integrations: API Keys
```
POSTMAN_API_KEY=
CLOUDFLARE_API_TOKEN=
APIFY_API_TOKEN=
HARVEY_API_KEY=
NOTION_API_KEY=
FIGMA_ACCESS_TOKEN=
LINEAR_API_KEY=
JIRA_API_TOKEN=
VERCEL_TOKEN=
PAYPAL_CLIENT_ID=
PAYPAL_CLIENT_SECRET=
STRIPE_SECRET_KEY=
INDEED_API_KEY=
DICE_API_KEY=
```

### Monitoring
```
SENTRY_LARAVEL_DSN=
GLITCHTIP_SECRET_KEY=
GLITCHTIP_DB=glitchtip
GLITCHTIP_DOMAIN=
```

### Cloudflare
```
CLOUDFLARE_ZONE_ID=
CLOUDFLARE_API_TOKEN_CACHE=
```

### Model Routing Defaults
```
DEFAULT_LOCAL_MODEL=llama3.2:latest
DEFAULT_EMBEDDING_MODEL=nomic-embed-text:latest
DEFAULT_VISION_MODEL=llama3.2-vision:11b
DEFAULT_CODE_MODEL=qwen2.5-coder:32b
DEFAULT_IMAGE_MODEL=comfyui:flux-schnell
DEFAULT_REASONING_MODEL=deepseek-r1:32b
DEFAULT_TRANSCRIPTION_MODEL=openai:whisper-1
DEFAULT_TTS_MODEL=elevenlabs:eleven_turbo_v2_5
MODEL_AUTO_ROUTING=true
PREFER_LOCAL_MODELS=true
```

### Dev Tools
```
TELESCOPE_ENABLED=false
LOG_CHANNEL=stack
LOG_LEVEL=error
```

### QNAP Deployment
```
QNAP_HOST=
QNAP_USER=jeremy
QNAP_PROJECT_PATH=/share/CE_CACHEDEV1_DATA/homes/jeremy/sites/ai-platform
QNAP_DOCKER_BINARY=/share/CE_CACHEDEV1_DATA/.qpkg/container-station/usr/bin/.libs/docker
```

---

## SECTION E: MAKEFILE VERIFICATION

The Makefile at project root must include all of the following targets.
The DOCKER variable must auto-detect the binary path. QNAP_HOST must
come from .env.

```makefile
# Auto-detect Docker binary (QNAP vs standard)
QNAP_DOCKER := /share/CE_CACHEDEV1_DATA/.qpkg/container-station/usr/bin/.libs/docker
DOCKER := $(shell if [ -x "$(QNAP_DOCKER)" ]; then echo "$(QNAP_DOCKER)"; else echo "docker"; fi)
COMPOSE := $(DOCKER) compose

# Load .env if it exists
ifneq (,$(wildcard ./.env))
    include .env
    export
endif

.PHONY: up down build fresh migrate seed shell tinker logs lint test deploy-local deploy-qnap ssh-qnap

## Docker targets
up:
	$(COMPOSE) up -d

down:
	$(COMPOSE) down

build:
	$(COMPOSE) build

## Laravel targets (run inside frankenphp container)
fresh:
	$(COMPOSE) exec frankenphp php artisan migrate:fresh --seed

migrate:
	$(COMPOSE) exec frankenphp php artisan migrate

seed:
	$(COMPOSE) exec frankenphp php artisan db:seed

shell:
	$(COMPOSE) exec frankenphp sh

tinker:
	$(COMPOSE) exec frankenphp php artisan tinker

## Logging
logs:
	$(COMPOSE) logs -f --tail=100

## Linting
lint:
	$(COMPOSE) exec frankenphp ./vendor/bin/pint --test
	$(COMPOSE) exec frankenphp ./vendor/bin/phpstan analyse --level=8
	cd frontend && npx eslint src/ --ext .vue,.js
	cd frontend && npx prettier --check "src/**/*.{vue,js,css,json}"
	cd frontend && npx stylelint "src/**/*.{css,vue}"

## Testing
test:
	$(COMPOSE) exec frankenphp php artisan test

## Deployment
deploy-local:
	./deploy.sh local

deploy-qnap:
	./deploy.sh qnap

## SSH
ssh-qnap:
	ssh $(QNAP_USER)@$(QNAP_HOST)
```

---

## END OF BRIEF ADDITIONS
