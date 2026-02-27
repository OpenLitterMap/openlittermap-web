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

Teams v5 deployment, tagging v5.1 (category disambiguation, type pills, level titles), clustering fixes, school facilitator (delete/revoke/safeguarding), user journey bug fixes, uploads page (delete/edit photos), map popup v5.1 fix, translations. 810 tests passing.

## OpenLitterMap Context
UN-endorsed Digital Public Good for environmental citizen science.
500k+ uploads, 110+ countries, 98+ peer-reviewed citations.
Built by a single developer over 17 years.

## Key Architectural Invariants
- School team photos: `is_public=false` until teacher approval (see `readme/SchoolPipeline.md`)
- Teacher approval fires `TagsVerifiedByAdmin` → MetricsService processes metrics
- All public/global queries MUST use `Photo::public()` scope or `where('is_public', true)`
- School teams must NOT be `is_trusted` (aggregate data would leak before teacher review)
- `AddTagsToPhotoAction` generates summary + XP regardless of trust level (null summary = zero metrics)
- `VerificationStatus` enum cast is on Photo model — use `->value` for `>=`/`<` comparisons, direct enum for `===`
- `Photo.geom` column is binary spatial data — hidden from JSON via `$hidden` array
- `photo_tags` table uses FK columns (`category_id`, `litter_object_id`), NOT string columns
- `AddTagsToPhotoAction` (v5) auto-resolves category from object — frontend need not send category
- `ConvertV4TagsAction` is BUILT and deployed — mobile v4 tags convert to v5 PhotoTags via migration pipeline
- `Photo` model uses `SoftDeletes` — `$photo->delete()` soft-deletes, `Photo::public()` auto-excludes
- Locations API uses `locations`/`location_type` keys (not `children`/`children_type`)
- `UsersUploadsController` returns tags under key `'new_tags'` (frontend reads `photo.new_tags`)
- Untagged photo filter uses `WHERE verified = 0`, NOT `doesntHave('photoTags')`
- `clustering:update --all` flushes `clusters:v5:*` cache keys after regeneration
- Map cluster layer MUST be added to Leaflet map unconditionally (not gated on initial feature count)
- Teacher delete/revoke MUST call `MetricsService::deletePhoto()` before state change to reverse metrics
- PointsController masks student identity on global map when `team.hasSafeguarding()` (name/username/social = null)
- Points API returns `page` (not `current_page`) at root level — frontend normalizes to `current_page`
- Nav.vue `isAdmin` check includes `'superadmin'` role (not just `'admin'` and `'helper'`)

## Verification Pipeline
- 0 UNVERIFIED: uploaded, no tags
- 1 VERIFIED: tagged (school students land here, awaiting teacher approval)
- 2 ADMIN_APPROVED: verified by admin/trusted user OR teacher-approved
- 3 BBOX_APPLIED: bounding boxes drawn
- 4 BBOX_VERIFIED: bounding boxes verified
- 5 AI_READY: ready for OpenLitterAI training

## Teams v5 Status
Fully deployed. 810 tests passing (0 failures). All steps complete:
- VerificationStatus enum + Photo model cast (step 10)
- `is_public=true` filtering on all public-facing queries (step 9)
- Frontend: Pinia stores, 12 Vue components, router updated (steps 11-12)
- Tests: 7 test files, 4 factories, all passing (steps 13-14)
- Production code fixes: PhotoObserver, TeamPhotosController, TeamsDataController, Photo model
- Leaderboard system: Redis ZSETs for all-time, MySQL per-user metrics for time-filtered

Reference files: `~/Code/teams-v5-files/`

## Code Preferences
- Do not over-engineer
- Do not create new files unless asked
- Do not rename files unless asked

## Domain Documentation (read the relevant file before working in that area)
- `readme/Achievements-Audit.md` — Achievements system audit and architecture
- `readme/ArtisanCommands.md` — All custom artisan commands and scheduler config
- `readme/Clustering.md` — Map clustering system (tile keys, zoom levels, dirty tiles, GeoJSON API)
- `readme/Leaderboards.md` — Leaderboard system (Redis ZSETs + MySQL per-user metrics)
- `readme/Locations.md` — Location and geography system
- `readme/Metrics.md` — Metrics pipeline and aggregation
- `readme/Migration.md` — Database migration notes
- `readme/PostMigrationCleanup.md` — Post-migration cleanup tasks
- `readme/SchoolPipeline.md` — School approval pipeline (critical data flow)
- `readme/Tags.md` — Tagging system and categories
- `readme/Teams.md` — Teams architecture, permissions, safeguarding, API routes
- `readme/Upload.md` — Photo upload pipeline

<laravel-boost-guidelines>
=== foundation rules ===

# Laravel Boost Guidelines

The Laravel Boost guidelines are specifically curated by Laravel maintainers for this application. These guidelines should be followed closely to ensure the best experience when building Laravel applications.

## Foundational Context

This application is a Laravel application and its main Laravel ecosystems package & versions are below. You are an expert with them all. Ensure you abide by these specific packages & versions.

- php - 8.3.16
- laravel/cashier (CASHIER) - v15
- laravel/framework (LARAVEL) - v11
- laravel/horizon (HORIZON) - v5
- laravel/passport (PASSPORT) - v12
- laravel/prompts (PROMPTS) - v0
- laravel/pulse (PULSE) - v1
- laravel/reverb (REVERB) - v1
- laravel/sanctum (SANCTUM) - v4
- livewire/livewire (LIVEWIRE) - v4
- laravel/boost (BOOST) - v2
- laravel/mcp (MCP) - v0
- phpunit/phpunit (PHPUNIT) - v10
- laravel-echo (ECHO) - v1
- tailwindcss (TAILWINDCSS) - v3
- vue (VUE) - v3
- prettier (PRETTIER) - v3

## Skills Activation

This project has domain-specific skills available. You MUST activate the relevant skill whenever you work in that domain—don't wait until you're stuck.

- `tailwindcss-development` — Styles applications using Tailwind CSS v3 utilities. Activates when adding styles, restyling components, working with gradients, spacing, layout, flex, grid, responsive design, dark mode, colors, typography, or borders; or when the user mentions CSS, styling, classes, Tailwind, restyle, hero section, cards, buttons, or any visual/UI changes.
- `achievements-system` — AchievementEngine, AchievementRepository, milestone checkers, AchievementsSeeder, user_achievements pivot, AchievementsController API, and achievement evaluation flow.
- `clustering-system` — ClusteringService, tile keys, dirty tiles/teams, clustering commands, ClusterController GeoJSON API, PhotoObserver dirty marking, and map cluster rendering.
- `location-system` — Countries, states, cities, ResolveLocationAction, Location base model, LocationType enum, geocoding, and location-level Redis data.
- `metrics-pipeline` — MetricsService, RedisMetricsCollector, ProcessPhotoMetrics, metrics table, Redis stats, leaderboards, XP processing, and photo processing state (processed_at/fp/tags/xp).
- `mobile-shim` — Mobile API endpoints, v4 tag format conversion, AddTagsToUploadedImageController, old mobile tagging routes, and ConvertV4TagsAction shim design.
- `photo-pipeline` — Photo upload, tagging, verification status, summary generation, XP calculation, AddTagsToPhotoAction, UploadPhotoController, and the VerificationStatus enum.
- `tagging-system` — PhotoTag, PhotoTagExtraTags, categories, litter objects, materials, brands, ClassifyTagsService, GeneratePhotoSummaryService, tag migration, and the v4-to-v5 conversion.
- `teams-safeguarding` — Teams, school teams, team photos, approval flow, TeamPhotosController, privacy, is_public, PhotoObserver, MasksStudentIdentity, and safeguarding.
- `testing-patterns` — Writing and fixing tests, test factories, Event::fake patterns, auth guard testing, PHPUnit configuration, deprecated test groups, and common test pitfalls.
- `v5-migration` — The olm:v5 migration script, UpdateTagsService, batch processing, migrated_at, ClassifyTagsService deprecated mappings, and data migration from v4 category tables.

## Conventions

- You must follow all existing code conventions used in this application. When creating or editing a file, check sibling files for the correct structure, approach, and naming.
- Use descriptive names for variables and methods. For example, `isRegisteredForDiscounts`, not `discount()`.
- Check for existing components to reuse before writing a new one.

## Verification Scripts

- Do not create verification scripts or tinker when tests cover that functionality and prove they work. Unit and feature tests are more important.

## Application Structure & Architecture

- Stick to existing directory structure; don't create new base folders without approval.
- Do not change the application's dependencies without approval.

## Frontend Bundling

- If the user doesn't see a frontend change reflected in the UI, it could mean they need to run `npm run build`, `npm run dev`, or `composer run dev`. Ask them.

## Documentation Files

- You must only create documentation files if explicitly requested by the user.

## Replies

- Be concise in your explanations - focus on what's important rather than explaining obvious details.

=== boost rules ===

# Laravel Boost

- Laravel Boost is an MCP server that comes with powerful tools designed specifically for this application. Use them.

## Artisan

- Use the `list-artisan-commands` tool when you need to call an Artisan command to double-check the available parameters.

## URLs

- Whenever you share a project URL with the user, you should use the `get-absolute-url` tool to ensure you're using the correct scheme, domain/IP, and port.

## Tinker / Debugging

- You should use the `tinker` tool when you need to execute PHP to debug code or query Eloquent models directly.
- Use the `database-query` tool when you only need to read from the database.
- Use the `database-schema` tool to inspect table structure before writing migrations or models.

## Reading Browser Logs With the `browser-logs` Tool

- You can read browser logs, errors, and exceptions using the `browser-logs` tool from Boost.
- Only recent browser logs will be useful - ignore old logs.

## Searching Documentation (Critically Important)

- Boost comes with a powerful `search-docs` tool you should use before trying other approaches when working with Laravel or Laravel ecosystem packages. This tool automatically passes a list of installed packages and their versions to the remote Boost API, so it returns only version-specific documentation for the user's circumstance. You should pass an array of packages to filter on if you know you need docs for particular packages.
- Search the documentation before making code changes to ensure we are taking the correct approach.
- Use multiple, broad, simple, topic-based queries at once. For example: `['rate limiting', 'routing rate limiting', 'routing']`. The most relevant results will be returned first.
- Do not add package names to queries; package information is already shared. For example, use `test resource table`, not `filament 4 test resource table`.

### Available Search Syntax

1. Simple Word Searches with auto-stemming - query=authentication - finds 'authenticate' and 'auth'.
2. Multiple Words (AND Logic) - query=rate limit - finds knowledge containing both "rate" AND "limit".
3. Quoted Phrases (Exact Position) - query="infinite scroll" - words must be adjacent and in that order.
4. Mixed Queries - query=middleware "rate limit" - "middleware" AND exact phrase "rate limit".
5. Multiple Queries - queries=["authentication", "middleware"] - ANY of these terms.

=== php rules ===

# PHP

- Always use curly braces for control structures, even for single-line bodies.

## Constructors

- Use PHP 8 constructor property promotion in `__construct()`.
    - `public function __construct(public GitHub $github) { }`
- Do not allow empty `__construct()` methods with zero parameters unless the constructor is private.

## Type Declarations

- Always use explicit return type declarations for methods and functions.
- Use appropriate PHP type hints for method parameters.

<!-- Explicit Return Types and Method Params -->
```php
protected function isAccessible(User $user, ?string $path = null): bool
{
    ...
}
```

## Enums

- Typically, keys in an Enum should be TitleCase. For example: `FavoritePerson`, `BestLake`, `Monthly`.

## Comments

- Prefer PHPDoc blocks over inline comments. Never use comments within the code itself unless the logic is exceptionally complex.

## PHPDoc Blocks

- Add useful array shape type definitions when appropriate.

=== tests rules ===

# Test Enforcement

- Every change must be programmatically tested. Write a new test or update an existing test, then run the affected tests to make sure they pass.
- Run the minimum number of tests needed to ensure code quality and speed. Use `php artisan test --compact` with a specific filename or filter.

=== laravel/core rules ===

# Do Things the Laravel Way

- Use `php artisan make:` commands to create new files (i.e. migrations, controllers, models, etc.). You can list available Artisan commands using the `list-artisan-commands` tool.
- If you're creating a generic PHP class, use `php artisan make:class`.
- Pass `--no-interaction` to all Artisan commands to ensure they work without user input. You should also pass the correct `--options` to ensure correct behavior.

## Database

- Always use proper Eloquent relationship methods with return type hints. Prefer relationship methods over raw queries or manual joins.
- Use Eloquent models and relationships before suggesting raw database queries.
- Avoid `DB::`; prefer `Model::query()`. Generate code that leverages Laravel's ORM capabilities rather than bypassing them.
- Generate code that prevents N+1 query problems by using eager loading.
- Use Laravel's query builder for very complex database operations.

### Model Creation

- When creating new models, create useful factories and seeders for them too. Ask the user if they need any other things, using `list-artisan-commands` to check the available options to `php artisan make:model`.

### APIs & Eloquent Resources

- For APIs, default to using Eloquent API Resources and API versioning unless existing API routes do not, then you should follow existing application convention.

## Controllers & Validation

- Always create Form Request classes for validation rather than inline validation in controllers. Include both validation rules and custom error messages.
- Check sibling Form Requests to see if the application uses array or string based validation rules.

## Authentication & Authorization

- Use Laravel's built-in authentication and authorization features (gates, policies, Sanctum, etc.).

## URL Generation

- When generating links to other pages, prefer named routes and the `route()` function.

## Queues

- Use queued jobs for time-consuming operations with the `ShouldQueue` interface.

## Configuration

- Use environment variables only in configuration files - never use the `env()` function directly outside of config files. Always use `config('app.name')`, not `env('APP_NAME')`.

## Testing

- When creating models for tests, use the factories for the models. Check if the factory has custom states that can be used before manually setting up the model.
- Faker: Use methods such as `$this->faker->word()` or `fake()->randomDigit()`. Follow existing conventions whether to use `$this->faker` or `fake()`.
- When creating tests, make use of `php artisan make:test [options] {name}` to create a feature test, and pass `--unit` to create a unit test. Most tests should be feature tests.

## Vite Error

- If you receive an "Illuminate\Foundation\ViteException: Unable to locate file in Vite manifest" error, you can run `npm run build` or ask the user to run `npm run dev` or `composer run dev`.

=== laravel/v11 rules ===

# Laravel 11

- CRITICAL: ALWAYS use `search-docs` tool for version-specific Laravel documentation and updated code examples.
- This project upgraded from Laravel 10 without migrating to the new streamlined Laravel 11 file structure.
- This is perfectly fine and recommended by Laravel. Follow the existing structure from Laravel 10. We do not need to migrate to the Laravel 11 structure unless the user explicitly requests it.

## Laravel 10 Structure

- Middleware typically lives in `app/Http/Middleware/` and service providers in `app/Providers/`.
- There is no `bootstrap/app.php` application configuration in a Laravel 10 structure:
    - Middleware registration is in `app/Http/Kernel.php`
    - Exception handling is in `app/Exceptions/Handler.php`
    - Console commands and schedule registration is in `app/Console/Kernel.php`
    - Rate limits likely exist in `RouteServiceProvider` or `app/Http/Kernel.php`

## Database

- When modifying a column, the migration must include all of the attributes that were previously defined on the column. Otherwise, they will be dropped and lost.
- Laravel 11 allows limiting eagerly loaded records natively, without external packages: `$query->latest()->limit(10);`.

### Models

- Casts can and likely should be set in a `casts()` method on a model rather than the `$casts` property. Follow existing conventions from other models.

## New Artisan Commands

- List Artisan commands using Boost's MCP tool, if available. New commands available in Laravel 11:
    - `php artisan make:enum`
    - `php artisan make:class`
    - `php artisan make:interface`

=== phpunit/core rules ===

# PHPUnit

- This application uses PHPUnit for testing. All tests must be written as PHPUnit classes. Use `php artisan make:test --phpunit {name}` to create a new test.
- If you see a test using "Pest", convert it to PHPUnit.
- Every time a test has been updated, run that singular test.
- When the tests relating to your feature are passing, ask the user if they would like to also run the entire test suite to make sure everything is still passing.
- Tests should cover all happy paths, failure paths, and edge cases.
- You must not remove any tests or test files from the tests directory without approval. These are not temporary or helper files; these are core to the application.

## Running Tests

- Run the minimal number of tests, using an appropriate filter, before finalizing.
- To run all tests: `php artisan test --compact`.
- To run all tests in a file: `php artisan test --compact tests/Feature/ExampleTest.php`.
- To filter on a particular test name: `php artisan test --compact --filter=testName` (recommended after making a change to a related file).

=== tailwindcss/core rules ===

# Tailwind CSS

- Always use existing Tailwind conventions; check project patterns before adding new ones.
- IMPORTANT: Always use `search-docs` tool for version-specific Tailwind CSS documentation and updated code examples. Never rely on training data.
- IMPORTANT: Activate `tailwindcss-development` every time you're working with a Tailwind CSS or styling-related task.

</laravel-boost-guidelines>
