# Blood Stream V1

A comprehensive blood test result management and analysis system. Integrates with multiple laboratory systems (ODB, Eurofins/Innoquest), generates AI-powered reviews, and handles export/import of test data in various formats.

## Technology Stack

- **Framework**: Laravel 10 with PHP 8.1
- **Authentication**: JWT (Tymon/JWT-Auth) with Sanctum support
- **Frontend Build**: Vite with Tailwind CSS 4
- **Database**: MySQL
- **API Documentation**: L5 Swagger (OpenAPI/Swagger UI)
- **Data Export**: Maatwebsite Excel, mPDF, DomPDF
- **Background Jobs**: Laravel Queue (configurable driver)
- **File Transfer**: SFTP support (League Flysystem)

## Setup & Installation

```bash
# Install PHP dependencies
composer install

# Install Node dependencies
npm install

# Setup environment
cp .env.example .env
php artisan key:generate
php artisan jwt:secret

# Run migrations
php artisan migrate

# Generate Swagger documentation
php artisan l5-swagger:generate
```

## Development Commands

### Build & Serve

```bash
php artisan serve              # Run development server (http://127.0.0.1:8000)
npm run dev                    # Run Vite in watch mode
npm run build                  # Build assets for production
```

### Testing

```bash
php artisan test                              # Run all tests
php artisan test --filter=TestName           # Run a specific test
php artisan test tests/Unit                  # Run unit tests only
php artisan test tests/Feature               # Run feature tests only
```

### Database

```bash
php artisan migrate                          # Run pending migrations
php artisan migrate:rollback                 # Rollback last migration
php artisan migrate:refresh                  # Rollback and re-run all migrations
php artisan db:seed --class=YourSeeder      # Run a specific seeder
```

### Code Quality

```bash
./vendor/bin/pint                            # Format code with Laravel Pint (PSR-12)
php artisan tinker                           # Interactive REPL
```

### Queue & Jobs

```bash
php artisan queue:work                       # Start queue worker
php artisan queue:failed                     # List failed jobs
php artisan queue:retry all                  # Retry all failed jobs
```

### AI Review Commands

```bash
php artisan ai:dispatch-unreviewed-async     # Dispatch test results to AI server
php artisan ai:reconcile-reviews             # Find orphaned results and dispatch AI review jobs
php artisan ai:retry-failed-reviews          # Retry failed AI reviews from ai_errors table
```

## Architecture Overview

### Core Domains

**API Authentication** - JWT-based authentication via `api.auth` middleware. Consult-call routes use a separate `consult-call.auth` middleware with ODB staff credentials.

**Test Result Processing** - Handles patient lab results from multiple sources using `TestResultCompilerService`. Supports ODB and Eurofins/Innoquest integrations.

**AI Review System** - Processes test results for AI-powered analysis via webhook integration. Error tracking via `AIError` and `AIReview` models.

**PDF/Document Export** - Generates PDF reports using mPDF or DomPDF backends.

**Consult Call System** - Manages patient consultation workflows (enrollment, scheduling, follow-ups). Uses its own JWT authentication separate from the main API.

**Data Import/Export** - Excel-based import/export using Maatwebsite Excel for code mappings, panel sequences, doctor codes, and bill codes.

### Key Services

| Service | Purpose |
|---|---|
| `TestResultCompilerService` | Compiles test results from raw data |
| `AIReviewService` | Handles AI review workflows |
| `ReviewHtmlGenerator` | Generates HTML for AI reviews |
| `ODB\MigrationService` | Manages ODB data migrations |
| `MyHealthService` | External MyHealth system integration |
| `QueueJobTrackerService` | Tracks async job progress |

### Authentication

**API Auth** (`api.auth`):
- JWT tokens via Tymon guard with `LabCredential` model
- TTL: 30 days
- Set `JWT_SECRET` in `.env`

**Consult Call Auth** (`consult-call.auth`):
- Separate JWT, no user model or Laravel guard
- TTL: 24 hours
- Tokens include `token_type: "consult_call"` claim to prevent cross-use
- ODB frontend authenticates via proxy (`api-jwt.php`) reading ODB `staff` table

### Consult Call Roles

| Value | Role |
|---|---|
| 0 | Normal User |
| 1 | Super Admin (bypasses all filters) |
| 2 | Doctor |
| 3 | Pharmacy |
| 4 | HQ |
| 5 | Outlet |

## API Documentation

Swagger UI is available at `/api/documentation` after generating docs:

```bash
php artisan l5-swagger:generate
```

## Environment Variables

Copy `.env.example` to `.env` and fill in the required values. Key variables:

- `DB_*` - Database connection
- `JWT_SECRET` - JWT signing secret (generate with `php artisan jwt:secret`)
- `SFTP_*` - SFTP server for Innoquest file transfer
- `ODB_USERNAME` / `ODB_PASSWORD` - ODB system credentials
- `ODB_API_USERNAME` / `ODB_API_PASSWORD` - ODB API credentials
- `AI_REVIEW_*` - AI review service endpoints
- `WEBHOOK_AI_RESULT_TOKEN` - Webhook token for AI result callbacks
- `CREDENTIALS_LAB_*` - Lab credential seeders

## Code Style

PSR-12 enforced via Laravel Pint. Run `./vendor/bin/pint` before committing.

All database write operations must use transactions:

```php
try {
    DB::beginTransaction();
    // ...
    DB::commit();
} catch (Exception $e) {
    DB::rollBack();
    Log::error('Operation failed', ['error' => $e->getMessage()]);
    throw $e;
}
```

## License

Proprietary. All rights reserved.
