# Message Vault

A backend API for self-destructing messages, built as a Docker midterm project.

A user stores a **message** (text and/or files) and attaches rules to it:

- **Unlock rule**: available right away, or not before a given date (a time capsule).
- **Destroy rule**: after the first read, after N views, at a given date, or never.
- **Password** (optional): readers must send it in the `X-Message-Password` header.
- **Teaser** (optional): a short hint that is always visible, even while the message is sealed.

There are no user accounts. Creating a message returns an **owner token** once. Only its SHA-256 hash is stored, and the token is required (as `X-Owner-Token`) to preview, update or delete the message.

## Stack

| Part | Technology |
|---|---|
| Framework | Laravel 13, PHP 8.4 |
| HTTP server | FrankenPHP 1.13 (Caddy + embedded PHP, classic non-worker mode; no nginx, no PHP-FPM) |
| Database | PostgreSQL 17 |
| File storage | Local Laravel disk `attachments` on the named volume `attachments` |
| Client | Postman (no frontend) |

## Architecture

```
                        host :8000 (API)
                               │
┌──────────────────────────────┼───────── network: vault ─────────────────────┐
│                       ┌──────▼──────┐               ┌─────────────┐         │
│                       │     app     │               │  scheduler  │         │
│                       │ FrankenPHP  │               │ same image, │         │
│                       │   :8000     │               │schedule:work│         │
│                       └──┬───────┬──┘               └──┬───────┬──┘         │
│                          │       └──────────┬──────────┘       │            │
│                          │                  │                  │            │
│                          │       volume: attachments           │            │
│                          │  (/app/storage/app/attachments)     │            │
│                       ┌──▼─────────────────────────────────────▼──┐         │
│                       │                 postgres                  │         │
│                       │             :5432 (internal)              │         │
│                       └─────────────────────┬─────────────────────┘         │
└─────────────────────────────────────────────┼───────────────────────────────┘
                                     volume: postgres_data
```

### Services

| Service | Image | Published port | Role |
|---|---|---|---|
| `app` | built from `Dockerfile` | `8000` | HTTP API. Its entrypoint waits for the DB, then runs migrations and seeds demo data |
| `scheduler` | same image (`message-vault:local`) | — | `php artisan schedule:work`: destroys expired messages and purges files every minute |
| `postgres` | `postgres:17.11-alpine3.23` | — | Database, data kept in the `postgres_data` volume |

**File storage.** Attachments are stored on the Laravel disk `attachments` (`config/filesystems.php`), which points at `storage/app/attachments`. The named volume `attachments` is mounted at that path in **both** `app` (uploads, downloads) and `scheduler` (purge), so the files survive container recreation and are shared by the two containers.

The disk has no `serve` option, so Laravel exposes no `/storage/...` route for it: the only way to get a file is the signed download endpoint. The directory `storage/app/attachments` is part of the image and owned by `appuser`. When Docker creates the empty named volume, it copies that directory with its ownership, so the non-root user can write there.

### Startup order (healthchecks + `depends_on`)

```
postgres (pg_isready) ──healthy──> app (curl /up) ──healthy──> scheduler
```

- `scheduler` starts only after `app` is healthy, by which point migrations have already run.
- Only `app` runs migrations (`RUN_MIGRATIONS=true`), so two containers never migrate concurrently.
- The entrypoint (`docker/entrypoint.sh`) also waits for PostgreSQL itself, so it does not depend solely on Compose ordering.

### Networking

All services share the user-defined bridge network `vault` and reach each other by service name (`postgres`). Only the API port (`8000`) is published to the host. PostgreSQL is not reachable from outside.

`GET /api/messages/{id}` returns a Laravel *temporary signed URL* (5 min TTL) to `/api/attachments/{id}/download`, and the app streams the file from the `attachments` disk.

### Docker image

`Dockerfile` has two stages:

1. **`vendor`** (`composer:2.10.3`): copies only `composer.json`/`composer.lock` and runs `composer install --no-dev`. Docker caches this layer, so it is rebuilt only when dependencies change.
2. **`runtime`** (`dunglas/frankenphp:1.13.4-php8.4.27-trixie`): adds `pdo_pgsql`, copies the code and `vendor/`, and runs as the non-root user `appuser`. It listens on port 8000, which is above 1024, so it needs no extra capabilities.

Config files live in `docker/`:

| File | Purpose |
|---|---|
| `docker/Caddyfile` | FrankenPHP/Caddy: listen on `:8000`, serve `public/` through `php_server` |
| `docker/php.ini` | Upload limits read from the environment (`${PHP_UPLOAD_MAX_FILESIZE}`, `${PHP_POST_MAX_SIZE}`) |
| `docker/entrypoint.sh` | Waits for the DB, migrates and seeds (app only), caches config/routes, then `exec`s the command |

## Running

Requirements: Docker with Compose v2.

```bash
cp .env.example .env
# optional but recommended: set APP_KEY in .env
docker compose run --rm --no-deps --entrypoint php app artisan key:generate --show
docker compose up --build
```

| URL | What |
|---|---|
| http://localhost:8000/api/messages | API |
| http://localhost:8000/up | Health endpoint |

Useful commands:

```bash
docker compose ps                         # health status of every service
docker compose logs -f app scheduler      # application + scheduler logs
docker compose exec app php artisan app:messages:destroy-expired
docker compose exec app php artisan app:attachments:purge
docker compose down                       # stop, keep volumes (data survives)
docker compose down -v                    # stop and delete volumes (clean start)
```

Code is baked into the image, so rebuild after editing: `docker compose up --build`.

## Environment variables

All configuration comes from `.env`, which is git-ignored. `.env.example` documents each variable. Compose passes the values to the containers. Infrastructure wiring that follows from the compose topology (`DB_HOST=postgres`, `LOG_CHANNEL=stderr`) is set directly in `docker-compose.yml`.

| Variable | Default | Description |
|---|---|---|
| `APP_NAME` | `Message Vault` | Application name |
| `APP_ENV` | `production` | `local` enables lazy-loading checks |
| `APP_KEY` | empty | Key used to sign download links. If empty, each container start generates a temporary one |
| `APP_DEBUG` | `false` | Adds stack traces to JSON errors |
| `APP_URL` | `http://localhost:8000` | Public URL |
| `APP_PORT` | `8000` | Host port of the API |
| `LOG_LEVEL` | `info` | Log level (logs go to stderr) |
| `DB_DATABASE` / `DB_USERNAME` / `DB_PASSWORD` | `message_vault` / `vault` / `vault-secret` | PostgreSQL database and credentials (also used to initialise the postgres container) |
| `VAULT_MAX_FILES` | `5` | Max attachments per message |
| `VAULT_MAX_FILE_SIZE_MB` | `20` | Max size of one file; also becomes PHP `upload_max_filesize` |
| `PHP_POST_MAX_SIZE` | `110M` | PHP `post_max_size`; keep ≥ files × size + room |
| `VAULT_ALLOWED_MIME_TYPES` | images, video, audio, pdf, txt | Whitelist of MIME types, detected from file content |
| `VAULT_DOWNLOAD_LINK_TTL_MINUTES` | `5` | Signed link lifetime and grace period before files of destroyed messages are purged |
| `VAULT_PER_PAGE` | `15` | Page size of the message list |

### Upload limits

These three limits must agree, and all of them come from `.env`:

| Limit | Source | Effect |
|---|---|---|
| Laravel validation | `max:` from `VAULT_MAX_FILE_SIZE_MB`, `mimetypes:` from `VAULT_ALLOWED_MIME_TYPES` | Readable `422` errors |
| PHP `upload_max_filesize` | `${VAULT_MAX_FILE_SIZE_MB}M` (set in compose) | Per-file limit enforced by PHP |
| PHP `post_max_size` | `PHP_POST_MAX_SIZE` | Whole request; above it Laravel answers `413` |

## Message status

The status is **computed** from the stored fields, never stored itself (`Message::status()`):

| Status | Condition | `GET /api/messages/{id}` |
|---|---|---|
| `sealed` | `unlock_at` is in the future | `423 Locked` with `unlock_at` and `seconds_remaining` |
| `available` | not sealed, not destroyed | `200` with body and attachment links; one view is spent |
| `destroyed` | `destroyed_at` is set, or `destroy_mode=at_date` and `destroy_at` has passed | `410 Gone` with a tombstone (no content) |

Order of checks in `GET /api/messages/{id}`: lazy `destroy_at` check → destroyed (410) → sealed (423) → password (403) → spend a view.

### Destroy modes

| `destroy_mode` | Destroyed when | Extra field |
|---|---|---|
| `on_read` | First successful read | — |
| `after_views` | View number `max_views` | `max_views` (required) |
| `at_date` | `destroy_at` passes (scheduler, or lazily on the next GET) | `destroy_at` (required, after `unlock_at`) |
| `never` | Only by the owner via DELETE | — |

On destruction, `body` is wiped from the database and `destroyed_at` and `destroyed_reason` are set (`read_once`, `view_limit_reached` or `destroy_date_reached`). The reader who spends the last view still receives the content in that response.

### Concurrency

Spending a view runs inside a DB transaction with `SELECT … FOR UPDATE` (`MessageAccessService::openMessage`). Two simultaneous requests for an `on_read` message are serialised by the row lock: the second one waits, then sees the message as destroyed and gets `410`. Updates use the same lock, so an owner cannot edit a message at the moment it is being read.

### File cleanup

Files are not deleted at the moment of destruction, so the signed links from the last view still work. The scheduler deletes them from the `attachments` volume once `destroyed_at` is older than `VAULT_DOWNLOAD_LINK_TTL_MINUTES` + 1 minute. Deleting a message as the owner removes its files immediately.

## Code map

| Path | Responsibility |
|---|---|
| `app/Services/MessageAccessService.php` | Unlock/destroy rules: open a message, spend views atomically, destroy expired messages |
| `app/Services/MessageService.php` | Create/update/delete, file storage, list, stats, purge |
| `app/Models/Message.php` | Computed status, remaining views, password/owner checks, `whereStatus` scope |
| `app/Enums/` | `DestroyMode`, `DestroyReason`, `MessageStatus` |
| `app/Http/Controllers/` | Thin controllers |
| `app/Http/Requests/` | Validation (Form Requests) |
| `app/Http/Resources/` | JSON shape (API Resources) |
| `app/Http/Middleware/EnsureOwnerToken.php` | `X-Owner-Token` check, runs before validation |
| `app/Exceptions/` | Domain exceptions; mapped to HTTP codes in `bootstrap/app.php` |
| `app/Console/Commands/`, `routes/console.php` | Scheduled commands (every minute) |
| `database/seeders/DatabaseSeeder.php` | Demo messages (skipped if the table is not empty) |

## Demo data

The seeder creates these messages on the first start. Their owner tokens are fixed, so they can be used with preview, update and delete:

| Title | Rules | Owner token |
|---|---|---|
| Welcome to the Vault | `never` | `demo-owner-welcome` |
| Time capsule | sealed for 1 week, `on_read` | `demo-owner-capsule` |
| Secret recipe | password `letmein`, `after_views` = 3 | `demo-owner-recipe` |
| Burn after reading | `on_read` | `demo-owner-burn` |
| Short-lived note | `at_date`, 10 minutes after the first start | `demo-owner-ttl` |
| Already burned | already destroyed | `demo-owner-ashes` |

## Postman

- Collection: `postman/message-vault.postman_collection.json`
- Environment: `postman/message-vault.postman_environment.json` (`base_url = http://localhost:8000`)
- Sample files: `postman/samples/` (`image.png`, `document.pdf`, `video.mp4`)

The upload request references the files as `samples/image.png` and so on. In Postman, open **Settings → General → Working directory** and set it to the repository's `postman/` folder, or re-select the files in the request body.

Run the collection in the **Collection Runner** from top to bottom. The create requests save `message_id`, `owner_token` and friends into collection variables, and later requests use them. Each request has tests for status codes and key fields.

## Demo script

1. `docker compose up --build`, then `docker compose ps`: `postgres` and `app` are healthy, `scheduler` is running.
2. `GET /api/messages`: the seeded messages show titles, teasers and statuses, but no bodies.
3. `POST /api/messages` (multipart with files, `on_read`): `201` with `owner_token`.
4. `GET /api/messages/{id}/preview` with `X-Owner-Token`: the author sees the content and no view is spent.
5. `GET /api/messages/{id}`: the content plus signed download links, and the message is destroyed.
6. Open a `download_url`: the file streams through the app. Show it on the volume: `docker compose exec app ls -R storage/app/attachments`.
7. `GET /api/messages/{id}` again: `410` with a tombstone.
8. "Time capsule": `423` with `seconds_remaining`.
9. "Secret recipe": `403` without the password, `200` with `X-Message-Password: letmein`.
10. `PATCH` a message that was already read: `409`.
11. `GET /api/stats`.
12. `docker compose logs scheduler`: the per-minute commands. After about 6 minutes the files of the destroyed message disappear from the volume.
13. `docker compose down && docker compose up`: data survives thanks to the named volumes. `docker compose down -v` wipes everything.
