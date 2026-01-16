# CLAUDE.md

This file provides guidance to Claude Code (claude.ai/code) when working with code in this repository.

## Quick Start

### Setup & Installation
```bash
# Install PHP dependencies
composer install

# Install Node dependencies
npm install

# Setup environment
cp .env.example .env
php artisan key:generate
php artisan jwt:secret

# Generate Swagger documentation
php artisan l5-swagger:generate

# Run migrations
php artisan migrate
```

### Common Development Commands

**Build & Serve**
```bash
php artisan serve              # Run development server (default: http://127.0.0.1:8000)
npm run dev                    # Run Vite for asset compilation in watch mode
npm run build                  # Build assets for production
```

**Testing**
```bash
php artisan test               # Run all tests
php artisan test --filter=TestName    # Run a specific test
php artisan test tests/Unit    # Run only unit tests
php artisan test tests/Feature # Run only feature tests
```

**Database & Migrations**
```bash
php artisan migrate            # Run pending migrations
php artisan migrate:rollback   # Rollback last migration
php artisan migrate:refresh    # Rollback and re-run all migrations
php artisan migrate:seed       # Seed the database with initial data
php artisan db:seed --class=YourSeeder    # Run a specific seeder
```

**Code Quality**
```bash
./vendor/bin/pint              # Format code with Laravel Pint (PSR-12)
php artisan tinker             # Interactive REPL for testing code
```

**Queue & Jobs**
```bash
php artisan queue:work         # Start queue worker (for local testing)
php artisan queue:failed       # List failed jobs
php artisan queue:retry all    # Retry all failed jobs
```

**AI & Custom Commands**
```bash
php artisan ai:dispatch-unreviewed-async    # Dispatch test results to AI server (webhook)
php artisan ai:reconcile-reviews            # Find orphaned results and dispatch AI review jobs
php artisan ai:retry-failed-reviews         # Retry failed AI reviews from ai_errors table
```

## Architecture Overview

### Project Purpose
Blood Stream is a comprehensive blood test result management and analysis system. It integrates with multiple laboratory systems (ODB, Eurofins/Innoquest), generates AI-powered reviews, and exports/imports test data in various formats.

### Technology Stack
- **Framework**: Laravel 10 with PHP 8.1
- **Authentication**: JWT (Tymon/JWT-Auth) with Sanctum support
- **Frontend Build**: Vite with Tailwind CSS 4
- **Database**: MySQL
- **API Documentation**: L5 Swagger (OpenAPI/Swagger UI)
- **Data Export**: Maatwebsite Excel, mPDF, DomPDF
- **Background Jobs**: Laravel Queue (configurable driver)
- **File Transfer**: SFTP support (League Flysystem)

### Core Domains

#### 1. **API Authentication** (`app/Http/Middleware/APIAuthMiddleware.php`)
- JWT-based API authentication
- All protected routes use `api.auth` middleware
- Custom `ValidateWebhookToken` for webhook security
- Swagger authentication support via `swagger.auth` middleware

#### 2. **Test Result Processing** (`app/Http/Controllers/API/General/LabResultsController.php`)
- Handles patient lab results from multiple sources
- Processes test results, profiles, and panels
- Uses `TestResultCompilerService` for result compilation
- Returns structured lab data with patient/test information

#### 3. **Panel & Innoquest Integration** (`app/Http/Controllers/API/Innoquest/PanelResultsController.php`)
- Manages test panels (groups of tests)
- Processes Innoquest panel results
- Panel structure: `MasterPanel` → `Panel` → `PanelItem` → `TestResultItem`
- Uses `ProcessPanelResults` job for async processing

#### 4. **ODB System Integration** (`app/Http/Controllers/API/ODB/BloodTestController.php`)
- Integrates with ODB database for blood test operations
- Migration system for moving data between systems (`MigrationService`)
- Report generation and review workflows
- Handles report IDs and lab numbers
- Supports batch migrations with job tracking

#### 5. **AI Review System** (`app/Services/AIReviewService.php`)
- Processes test results for AI-powered analysis
- Webhook integration for AI review results (`AIResultController`)
- Error tracking in `AIError` and `AIReview` models
- Commands: `ai:dispatch-unreviewed-async`, `ai:reconcile-reviews`, `ai:retry-failed-reviews`
- HTML review generation via `ReviewHtmlGenerator`

#### 6. **PDF/Document Export** (`app/Http/Controllers/API/PDFController.php`)
- Generates PDF reports for test results
- Supports multiple PDF backends (mPDF, DomPDF)
- Large file handling via memory optimization

#### 7. **Data Import/Export** (`app/Http/Controllers/ImportController.php`, `app/Http/Controllers/ExportController.php`)
- Import: Code mappings, panel sequences, doctor codes, bill codes
- Export: Lab numbers, test results with age analysis
- Uses Maatwebsite Excel for spreadsheet operations

#### 8. **Comments & Annotations**
- `PanelComment` for panel-level comments
- `TestResultComment` for test-level comments
- `MasterPanelComment` for template comments
- Processed via `ProcessPanelComments` job

### Data Models & Relationships

**Core Patient/Result Models**:
- `Patient` - Patient information
- `TestResult` - Individual test result record
- `TestResultReport` - Report metadata for a test result
- `TestResultItem` - Specific test value within a result
- `TestResultProfile` - Test profile configuration

**Panel & Master Data**:
- `Panel` - Test panel grouping
- `PanelItem` - Items within a panel
- `MasterPanel` - Template/master panel definition
- `MasterPanelItem` - Items in master panel
- `PanelProfile` - Profile associations for panels

**System Integration**:
- `Lab` - Laboratory information and credentials (`LabCredential`)
- `Doctor` - Doctor/provider information
- `Eurofins\ReportRecord` - Eurofins/Innoquest integration data
- `MigrationBatch` - ODB migration batches
- `AIReview` - AI review records and results
- `AIError` - Errors from AI processing

**Reference Data**:
- `ReferenceRange` - Test reference ranges
- `PanelCategory` - Test categorization
- `PanelTag` - Test tagging system

### Services Layer

**Key Service Classes**:
- `TestResultCompilerService` - Compiles test results from raw data
- `AIReviewService` - Handles AI review workflows
- `ReviewHtmlGenerator` - Generates HTML for AI reviews
- `ODB\MigrationService` - Manages ODB data migrations
- `MyHealthService` - External MyHealth system integration
- `QueueJobTrackerService` - Tracks async job progress

Services handle business logic—controllers remain thin by delegating to these services.

### Middleware Stack

**Global Middleware** (`app/Http/Kernel.php`):
- CORS handling
- Request logging (`LogRequestDuration`)

**API-Specific** (`api` middleware group):
- Throttling (1000 requests/minute by default)
- Route model binding

**Route Middleware Aliases**:
- `api.auth` - JWT authentication for API
- `webhook.auth` - Webhook token validation
- `swagger.auth` - Swagger UI authentication

### Jobs & Async Processing

**Async Jobs** (`app/Jobs/`):
- `ProcessPanelResults` - Async panel result processing
- `ProcessPanelComments` - Async comment processing
- `ProcessMigrationBatch` - Async ODB migration batches
- `ProcessMigrationReport` - Generate migration reports
- `ExportBpJob` - Background export jobs
- Tracked via `QueueJobTrackerService`

### Imports & Exports

**Import Classes** (`app/Imports/`):
- `CodeMappingImport` - Map lab codes
- `PanelSequenceImport` - Import Innoquest panel sequences
- `DoctorCodeImport` - Doctor/provider codes
- `BillCodeImport` - Billing codes
- `TagOnImport` - Test tags
- `CompletedLabNoImport` - Completed lab numbers

**Export Classes** (`app/Exports/`):
- `LabNumberMatchExport` - Export matched lab numbers

All use Maatwebsite Excel for Excel file handling.

### API Routes (`routes/api.php`)

**Auth Routes** (public):
- `POST /api/register` - User registration
- `POST /api/login` - JWT login
- `POST /api/logout` - Logout (requires auth)

**Protected Routes** (require `api.auth`):
- `POST /api/result/patient` - Get patient lab results
- `GET /api/result/{id}` - Get specific result
- `POST /api/result/panel` - Get panel results
- `POST /api/odb/*` - ODB operations (migration, search, update)
- `POST /api/pdf/export` - Generate PDF export
- `GET /api/import/*` - Import reference data
- `GET /api/export/*` - Export results
- `GET /api/comment/update` - Update comments

**Webhook Routes** (require `webhook.auth`):
- `POST /api/webhook/ai-result` - Receive AI review results

## Important Notes

### Database Write Operations
All database write operations must follow this pattern:
```php
try {
    DB::beginTransaction();
    // Perform writes
    DB::commit();
    Log::info('Operation succeeded', ['context' => 'value']);
} catch (Exception $e) {
    DB::rollBack();
    Log::error('Operation failed', ['error' => $e->getMessage()]);
    throw $e;
}
```

### Logging
Every significant operation must log:
- Start of operation: `Log::info('Starting operation', ['context' => 'data'])`
- Success completion: `Log::info('Operation completed', ['context' => 'data'])`
- Errors: `Log::error('Operation failed', ['error' => 'details'])`

### Code Style
- Follow PSR-12 via Laravel Pint (`./vendor/bin/pint`)
- Use proper import statements (never inline fully-qualified names)
- Leverage service classes for business logic
- Keep controllers thin

### Authentication
- JWT tokens configured in `config/jwt.php`
- TTL: 30 days (43,200 minutes)
- Refresh TTL: 20,160 minutes (14 days)
- Token blacklisting enabled by default
- Set `JWT_SECRET` in `.env` (generated via `php artisan jwt:secret`)

### Configuration Files
- `.env.example` contains all required environment variables
- Key integrations: ODB, Eurofins/Innoquest, AI Review service, SFTP
- L5 Swagger documentation auto-generated from code annotations

## Common Workflows

### Adding a New API Endpoint
1. Create controller in `app/Http/Controllers/API/[Domain]/`
2. Define business logic in a service class in `app/Services/`
3. Add routes to `routes/api.php` with appropriate middleware
4. Add request validation class if needed
5. Log start, success, and error states
6. Return consistent JSON responses

### Processing Test Results
1. Receive result via `LabResultsController::labResults()`
2. Validate with `LabRequest`
3. Use `TestResultCompilerService` to compile result data
4. Create/update `TestResult`, `TestResultItem`, `TestResultReport` records
5. Dispatch `ProcessPanelResults` job if needed
6. Trigger AI review via `AIReviewService` if configured
7. Log all steps

### Working with ODB Integration
1. Use `BloodTestController` for ODB interactions
2. Create migrations with `MigrationService`
3. Track migration status via `MigrationBatch` and `MigrationBatchItem`
4. Use `ProcessMigrationBatch` job for async processing
5. Generate reports with `ProcessMigrationReport` job

## Testing

Run tests with context available. The test environment uses:
- In-memory cache
- Sync queue (jobs execute immediately)
- File session driver
- Array mail driver (no emails sent)

## Engineering & Automation Rules

### 1. Framework & Runtime
- Use **Laravel 10**
- Use **PHP 8.1**

### 2. Imports
- Always use proper `use` import statements.
- Never use inline or fully qualified class paths inside logic.
- This rule also applies to PHP core exception types such as `Exception` and `Throwable`.

Example:
```php
use App\Models\User;
use Exception;
use Throwable;
```

### 3. Database Write Safety
- All database write operations must be wrapped in try/catch.
- Always use:
  - DB::beginTransaction()
  - DB::commit()
  - DB::rollBack()
- No exceptions.

### 4. Logging (Mandatory)
- Always log actions using:
  - Log::info()
  - Log::warning()
  - Log::error()
- Logs must clearly state:
  - What action occurred
  - Context identifiers (IDs, environment, operation)
- Silent failures are not allowed.

### 5. Architecture
- Prefer service classes.
- Keep controllers thin.
- Business logic must not live in controllers.

### 6. Code Style
- Use clean, PSR-12 compliant formatting.
- Keep code readable and consistent.

### 7. Batch File (.bat) Rules — CRITICAL

#### 7.1 Mandatory Two-File Requirement
When creating or updating any .bat file:
- Always generate TWO separate files:
  1. Local development version
  2. Production version
- Generating a single batch file is invalid.

#### 7.1.1 Content Parity Requirement (STRICT)
When generating the LOCAL and PRODUCTION .bat files:
- The two files MUST have IDENTICAL content.
- The ONLY permitted difference is the working directory path in this line:

```batch
REM Set working directory
cd /d "<ENVIRONMENT PATH>"
```

- No other line, comment, variable, command, spacing, or ordering may differ.
- Any additional difference makes the output invalid.

#### 7.2 Local Development Environment
- Filename must end with _local.bat
- Path (FIXED):
  `C:\laragon\www\blood-stream-v1`
- Read and write operations are allowed.
- File must be runnable on a local machine.

#### 7.3 Production Environment
- Filename must end with _prod.bat
- Path (FIXED):
  `C:\xampp\htdocs\production`
- Do not attempt to read or write production files from a local machine.
- Only generate the production batch file as TEXT OUTPUT.
- The file must include this label:
  `PRODUCTION VERSION — DO NOT RUN LOCALLY`

#### 7.4 Environment Safety Rules
- The production path does not exist on the local machine.
- Any attempt to access it locally will fail.
- Never auto-detect environments in batch files.
- Never mix local and production paths.
- Never guess production intent.

### 8. Emoji Usage
- Never use emoji in any output.

### 9. Comprehensive Summary Logging
- Every significant operation must include:
  - A start log
  - A success log
  - A failure or error log
- Logs must allow full traceability of the operation flow.
