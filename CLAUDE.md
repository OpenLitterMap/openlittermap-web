# OpenLitterMap Web

Open-source platform for mapping and tagging litter worldwide. Laravel 11 + Vue 3 SPA.

## Quick Reference

```bash
# Install
composer install && npm install
cp .env.example .env && php artisan key:generate

# Dev servers
php artisan serve          # Backend (localhost:8000)
npm run dev                # Frontend Vite HMR

# Build
npm run build

# Tests (PHPUnit 10)
php artisan test                                          # All tests
php artisan test tests/Feature/Teams/CreateTeamTest.php   # Single file
php artisan test --filter=test_method_name                # Single test

# Database
php artisan migrate
php artisan migrate:rollback

# Queues & WebSockets
php artisan queue:work
php artisan reverb:start
php artisan horizon
```

## Tech Stack

- **Backend:** PHP 8.2, Laravel 11
- **Frontend:** Vue 3 (Composition API + `<script setup>`), Pinia, Vue Router 4, Tailwind CSS 3.4, Vite 6
- **Database:** MySQL 5.7+, Redis 7+
- **Auth:** Laravel Passport (OAuth2) + Sanctum
- **Storage:** AWS S3 (prod), MinIO (dev)
- **Real-time:** Laravel Reverb / Pusher + Echo
- **Payments:** Stripe via Laravel Cashier
- **Permissions:** Spatie Laravel Permission 6

## Project Structure

```
app/
  Actions/           # Command-pattern action classes
  Http/Controllers/  # Thin controllers
  Http/Requests/     # Form request validation
  Http/Resources/    # API response transformers
  Models/            # Eloquent models
  Services/          # Business logic
  Jobs/              # Queued background jobs
  Events/            # Domain events
  Listeners/         # Event handlers
  Traits/            # Shared traits
  Console/Commands/  # Artisan commands

resources/js/
  app.js             # Vue entry point
  App.vue            # Root component
  components/        # Reusable components
  views/             # Page components (by feature)
  stores/            # Pinia stores
  router/index.js    # Vue Router config
  i18n.js            # Internationalization
  echo.js            # WebSocket setup
  langs/             # Translation files

routes/
  web.php            # SPA catch-all
  api.php            # API routes (v1, v2, v3)

tests/
  Feature/           # Integration tests
  Unit/              # Unit tests
  Helpers/           # Test utilities
```

## Code Style

**PHP:** Laravel StyleCI preset (PSR-4 autoloading)
**JS/Vue:** Prettier — 4-space indent, 120 char width, single quotes, trailing commas (ES5)
**General:** UTF-8, LF line endings, no trailing whitespace

## Conventions

- Vue components use Composition API with `<script setup>`
- PascalCase for Vue component filenames
- camelCase for JS functions/variables
- Business logic goes in Actions or Services, not controllers
- Tests use `RefreshDatabase` trait for isolation
- Factories in `database/factories/`, seeders in `database/seeds/`
- Vite aliases: `@` = `resources/js/`, `@stores` = `resources/js/stores/`

## API

- Current version: v3 (`/api/v3/...`)
- Auth: Passport/Sanctum tokens or session-based web guard
- Resources in `app/Http/Resources/` for response formatting

## CI (GitHub Actions)

Runs on push to `master`, `staging`, `upgrade/tagging-2025` and PRs.
Pipeline: PHP 8.2, Node 18, MySQL 5.7, Redis 7 — composer install, npm build, phpunit.

## Current Branch: `upgrade/tagging-2025`

Active work on teams feature (school/community types), student identity masking, school manager role.
