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

**Panel Completeness Commands**
```bash
php artisan panels:master-reconcile --limit=200 --force   # Full 4-step reconciliation cycle (scheduled hourly)
php artisan panels:recheck-incomplete       # Step 1: revert is_completed=1 records that no longer meet the threshold
php artisan panels:reconcile-incomplete     # Step 2: promote incomplete_test_results rows that now meet the threshold
php artisan panels:recheck-late-reviews     # Step 3: redispatch review + consult-call re-eval for late panel data
php artisan panels:purge-stale-incomplete   # Step 4: delete incomplete rows of completed + reviewed records (--dry-run, --limit, --force)
php artisan panels:sync-profile-counts      # Keep panel_profiles_count in sync (scheduled daily)
```

**Consult Call / Test Result Maintenance Commands**
```bash
php artisan consult-call:close-add-on-only --dry-run        # Close AO-only consult calls already called; releases held AI reviews
php artisan test-result:revert-held-completion --dry-run    # Set is_completed=0 on results whose AI review is still held
php artisan consult-call:backfill-ai-review-hold            # Backfill AI review holds on existing results
```

**MyHealth Feed Commands**
```bash
php artisan myhealth:send-feed {icno} --dry-run --mark-reviewed   # Send one patient to the MyHealth lab feed (testing tool); a real send is recorded in myhealth_feed_deliveries against the latest result
php artisan myhealth:reconcile-feeds --hours=6 --limit=200 --dry-run   # Testing overlap only: send missing feed copies for results whose AI review was accepted
php artisan myhealth:retry-failed-feeds --hours=12 --limit=50 --dry-run  # Resend FAILED feed deliveries (no attempt cap)
php artisan myhealth:sweep-stale-pending --dry-run                 # Deliveries stuck PENDING > 15 min become FAILED
php artisan myhealth:purge-feed-payloads --days=30 --dry-run       # Clear stored payloads older than 30 days (rows kept)
```

## Architecture Overview

### Project Purpose
Blood Stream is a comprehensive blood test result management and analysis system. It integrates with multiple laboratory systems (ODB, Eurofins/Innoquest), generates AI-powered reviews, and exports/imports test data in various formats.

### Technology Stack
- **Framework**: Laravel 10 with PHP 8.2 (production and local Laragon: PHP 8.2.35 TS x64)
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
- **Consult-call hold**: `TestResultCompletionDispatcher` holds the AI review for results enrolled in a consult call (CC / CC + AO / AO). A held result stays `is_completed=0` / `is_reviewed=0` with `ai_review_held_at` stamped, because add-on panels bought on the call arrive later under the same lab number.
- **Release**: `TestResultCompletionDispatcher::releaseHeldAiReview()` stamps `ai_review_released_at` and promotes the result so `SendToAIServer` picks it up. Triggered by "Release Doctor Review? = Yes" on the consult-call edit screen, or automatically on the first transition of a case into Closed. The answer is stored in `consult_call_details.release_doctor_review` (nullable boolean; backfilled by `ReleaseDoctorReviewBackfillSeeder`). `TestResult` exposes `ai_review_on_hold` and `ai_review_released` accessors.
- **Webhook on a held result**: `ProcessAIWebhookResult` stores the review as `SUPERSEDED` (not `COMPLETED`) and leaves `is_reviewed=0`, so the ODB report does not expose it before release.
- `ai:dispatch-unreviewed-async` skips results updated within the last 5 minutes (`RECENT_UPDATE_GRACE_MINUTES`) so the hold decision is made before the sweep picks them up
- **Cutover to MyHealth**: from `SendToAIServer::MYHEALTH_FEED_FROM` (`2026-11-13`, server time) `SendToAIServer` dispatches `SendToMyHealthFeed` instead of calling the AI server. The gate sits after the hold, completeness and idempotency checks, so every dispatch path switches over together.
- **Testing overlap**: from `SendToAIServer::MYHEALTH_FEED_TEST_FROM` (`2026-11-01`) until `MYHEALTH_FEED_FROM` (end of `2026-11-12`), the AI review runs unchanged and, after the AI server accepts the request, `SendToMyHealthFeed` is also dispatched with `markReviewed = false`. The copy is sent even if the result is already reviewed, never sets `is_reviewed` (the AI review webhook keeps owning it), and its failure never affects the AI review. Insyte = the MyHealth lab feed. Insyte reports back through `POST /api/v1/insyte/updateCompletionByTestResultId` (`api.auth`) with body `{ "test_result_id": 123, "http_status": 200 }` (`InsyteService::handleCallback()`): 200/201/204/409 = completed (delivery SENT, `is_reviewed = 1` from `MYHEALTH_FEED_FROM` only; before that `ignored: true`), 202 = processing (no change), other 4xx = rejected (delivery SKIPPED, not resent), 5xx = failed (delivery FAILED, resent by `myhealth:retry-failed-feeds`).

#### 6. **PDF/Document Export** (`app/Http/Controllers/API/Innoquest/PDFController.php`)
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

#### 9. **Consult Call System** (`app/Http/Controllers/API/ConsultCall/`)
- Manages patient consultation call workflows (enrollment, scheduling, follow-ups)
- **Own authentication**: Uses `consult-call.auth` middleware (separate from `api.auth`)
- Auth controller: `ConsultCallAuthController` -- issues/verifies JWTs via `tymon/jwt-auth`
- CRUD controller: `ConsultCallController` -- consult calls, details sub-resource, follow-ups sub-resource
- Status lookups: `StatusLibraryController` -- static enum endpoints for frontend dropdowns
- Models: `ConsultCall`, `ConsultCallDetails`, `ConsultCallFollowUp`
- ODB frontend authenticates via proxy (`api-jwt.php`) that reads ODB `staff` table
- API documentation: `docs/consult-call-api.md`
- **Add-on sales**: `AddOnResultLinkService` links Add-On invoices (ODB `blood_test_sales.id`) and their lab reports back to the consult call that recommended the add-ons. The lab returns the sales id inside `test_results.ref_id` as `<lab code><id>` (e.g. `INN123500`). A linked report marks the add-ons on that sale Completed. ODB merge/repoint support via `/api/odb/addOnSalesForMerge`, `repointAddOnSale`, `addOnParentSale`, `repointCustomer`.
- **Outlet eligibility (ARR)**: `App\Services\ARR\ARREligibilityService::eligibleCustomerByRefId()` is the single entry point (dispatcher + `consult-call` eligibility commands). From `ARREligibilityService::ARR_FROM` (`2026-11-23`, server time) it makes one call to ODB `/eligibleConsultCallByOutletCluster.php`, which combines both rules and returns `is_cluster`: an outlet in ODB `outlet_cluster` with `arr_active = 1` gives `is_cluster = true` (ARR flow), any other outlet not on the consult call exclusion list gives `is_cluster = false` (consult call flow). Before `ARR_FROM` it calls `/eligibleConsultCallByOutlet.php` and `is_cluster` is always false. ARR-related code lives in `app/Services/ARR/` and `app/Models/ARR/`.
- **Clinical condition scope**: `clinical_conditions.cluster_scope` (1 = consult call only, default; 2 = cluster/ARR only; 3 = both). `ARREligibilityService` tags the customer with `is_cluster`, which flows through `ConsultCallEligibilityService::checkAndCreate()` into `ConditionEvaluatorService::evaluateSinglePatient()` / `ClinicalCondition::getIdsSortedByPriority()` (scope `forFlow`). The healthy condition (risk_tier 0) is shared by both flows.
- **ARR enrollment**: in `ConsultCallEligibilityService::checkAndCreate()`, a cluster patient (`is_cluster = true`) that matches a cluster clinical condition is enrolled into `arr_enrollments` (`App\Models\ARR\ARREnrollment`, via `App\Services\ARR\ARREnrollmentService::enroll()`, one row per `test_result_id`, storing the matched `clinical_condition_id`, updated if a re-evaluation matches a different condition) instead of a consult call. Healthy cluster patients are not enrolled and go to AI review as usual. An ARR-enrolled result gets no AI review or MyHealth feed: skipped in `TestResultCompletionDispatcher::dispatch()`, gated in `SendToAIServer`, and excluded from `ai:dispatch-unreviewed-async` and `ai:reconcile-reviews`. ARR documentation and follow-up are managed outside this system.

#### 10. **Panel Completeness** (`app/Services/PanelCompletenessService.php`)
- Decides whether a test result has every expected panel (compares against ODB invoice packages via `OctopusApiService`)
- Incomplete results are tracked in `incomplete_test_results` and shown on the ODB Incomplete Test Results page (`IncompleteTestResultsController`, which hides stale rows)
- `resolve()` promotes a record to complete and marks any existing `COMPLETED` AI review as `STALE_AMENDED` so the review is regenerated
- Reconciled hourly by `panels:master-reconcile` (see Panel Completeness Commands)

#### 11. **Ref ID Verification** (`app/Services/RefIdVerificationService.php`)
- Called from `ProcessPanelResults` before the result is stored
- Verifies the lab `ref_id` (ODB `blood_test_sales.id`) belongs to the patient by comparing IC and name of the sale owner
- Outcomes: `verified` (same IC, or IC within 2 characters plus a similar name or the delivered DOB), `corrected` (unique replacement sale found by IC and collected date), `filled` (no ref_id delivered; unique sale found by IC and collected date), `unresolved` (ref_id set to null), `not_found`, `skipped`, `unavailable` (ODB lookup failed; fail-open)
- ODB endpoint `verifyRefIdByIc.php` accepts an empty `refid` (returns only `ic_sales`) for the fill case
- `PatientIdentityService`: for `verified` / `corrected` / `filled` the ODB customer name (registered at the sale) replaces the patient name, because Innoquest sometimes syncs a wrong name. The IC is never changed automatically; a delivered IC that differs from the ODB customer IC is recorded in `failed_validations` (reason `ic_mismatch`, one row per lab_no) for manual review. Runs at ingestion (`ProcessPanelResults`) and again in `SendToMyHealthFeed` before the payload is built (`verifyForFeed()`: fills a missing ref_id, fixes the name, records an IC mismatch; never changes a stored non-empty ref_id; fail-open)

#### 12. **MyHealth Lab Feed** (`app/Services/MyHealthFeedService.php`)
- Replaces the AI review for results completed or released on/after `SendToAIServer::MYHEALTH_FEED_FROM`
- `SendToMyHealthFeed` job POSTs the patient profile; a successful POST sets `is_reviewed=1` so sweepers do not re-send
- Payload: `{ "feed": { "success": true, "data": <PatientProfileService::build() output> } }`, limited to results collected within the last `FEED_HISTORY_YEARS` years. Same data as `GET /api/patients/by-icno/{icno}`. Each test result carries `special_tests`: the special tests already stored for it, in the same format as `ai_reviews.compiled_results` "SPECIAL TESTS" (`TestResultCompilerService::formatSpecialTests()`; never recalculated, `[]` when none).
- Config: `services.myhealth_feed` (`MH_KEY_FEED_URL`, `MH_KEY_FEED`, `MH_KEY_FEED_TIMEOUT` default 60s)
- Log channel: `myhealth-feed`
- Manual testing: `php artisan myhealth:send-feed {icno} --dry-run`
- Feed body carries `feed.test_result_id` (the result the feed is sent for); Insyte reports back against it. Partner API doc: `docs/insyte-api.md`
- **Delivery tracking**: `myhealth_feed_deliveries` (`MyHealthFeedDelivery`, one row per `test_result_id`): `status` PENDING / SENT / FAILED / SKIPPED, `http_status`, `attempts`, `payload` (2-year profile last sent, like `ai_reviews.compiled_results`; cleared after 30 days), `sent_at`. Written by `SendToMyHealthFeed` through `MyHealthFeedDeliveryService` (non-critical). SKIPPED = feed cannot be built (e.g. no IC) and is never retried.
- **Scheduled** (production): `myhealth:reconcile-feeds` hourly at :10 (testing overlap only; from `MYHEALTH_FEED_FROM` missed results are picked up by `ai:dispatch-unreviewed-async` / `ai:reconcile-reviews`), `myhealth:retry-failed-feeds` hourly at :25, `myhealth:sweep-stale-pending` every 10 min, `myhealth:purge-feed-payloads` daily 02:30. From `MYHEALTH_FEED_FROM` the two AI sweepers skip results with a feed delivery attempted since that date, so a failing feed is retried only by the `myhealth:*` commands.

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

**Consult Call**:
- `ConsultCall` - Patient consultation call record (enrollment, consent, scheduling)
- `ConsultCallDetails` - Individual consultation session (diagnosis, treatment, actions)
- `ConsultCallFollowUp` - Follow-up scheduling and tracking (reminders, referrals)

**Reference Data**:
- `ReferenceRange` - Test reference ranges
- `PanelCategory` - Test categorization
- `PanelTag` - Test tagging system

### Services Layer

**Key Service Classes**:
- `TestResultCompilerService` - Compiles test results from raw data; auto-selects log channel based on calling context (queue job → `job`, ODB request → `odb-log`, otherwise default)
- `AIReviewService` - Orchestrates AI review: compiles data, calls `AIApiClient`, stores via `ReviewHtmlGenerator`; same context-aware log channel selection
- `ReviewHtmlGenerator` - Generates HTML output for AI reviews
- `ODB\MigrationService` - Manages ODB data migrations
- `MyHealthService` - External MyHealth system integration
- `QueueJobTrackerService` - Tracks async job progress by UUID
- `ConsultCallEligibilityService` - Evaluates clinical condition rules and auto-creates `ConsultCall` records post-result
- `ConditionEvaluatorService` - Low-level rule evaluator used by `ConsultCallEligibilityService`
- `OctopusApiService` - ODB API client (patient matching, dynamic exports); config at `services.octopus.api_url`
- `DynamicExportService` - Builds configurable exports using `OctopusApiService`
- `TokenValidationService` / `TokenValidationRateLimiter` - JWT format pre-validation and per-IP rate limiting (used by `APIAuthMiddleware` only)
- `PanelInterpretationService` - Evaluates panel-level interpretations
- `ApiTokenService` / `TokenCacheService` - Token lifecycle helpers used by `BloodTestController`
- `TestResultCompletionDispatcher` - Runs once a result passes completeness: links add-on results, runs consult-call eligibility, then holds or dispatches the AI review; also `releaseHeldAiReview()`
- `PanelCompletenessService` - Panel completeness evaluation, incomplete tracking, resolve/undo/late-data review revert
- `AddOnResultLinkService` - Add-On invoice and lab report linkage to consult calls; ODB sale merge/repoint helpers
- `RefIdVerificationService` - Verifies/corrects lab `ref_id` against the patient's IC before storage
- `PatientProfileService` - Builds the patient profile by IC number (used by `PatientController::byIcNo` and the MyHealth feed)
- `MyHealthFeedService` - Builds and POSTs the MyHealth lab feed payload

Services handle business logic—controllers remain thin by delegating to these services.

### Middleware Stack

**Global Middleware** (`app/Http/Kernel.php`):
- CORS handling
- Request logging (`LogRequestDuration`)

**Route Middleware Aliases**:
- `api.auth` - JWT authentication for API (Tymon guard, requires `LabCredential` user)
- `consult-call.auth` - Custom JWT authentication for consult-call routes (ODB staff, no user model)
- `webhook.auth` - Webhook token validation
- `swagger.auth` - Swagger UI authentication

**Rate Limiting Groups** (`routes/api.php`):
- `throttle:auth` - stricter limit (brute-force protection) on `/register`, `/login`, `/logout`
- `throttle:lab-results` - 500/min on `/result/*` to handle batch lab ingestion
- `throttle:api` - 1000/min on all other protected routes

**`APIAuthMiddleware` caching behaviour**:
JWT user lookups are cached for 5 minutes using key `jwt_user:{xxh128_hash_of_token}`. Token revocation (blacklist) may take up to 5 minutes to propagate. Failed lookups evict the cache key immediately. `TokenValidationService` pre-validates JWT format (length, character set, segment count) before the expensive Tymon parse; repeated format failures from the same IP are rate-limited via `TokenValidationRateLimiter`.

**`ConsultCallAuthMiddleware`**:
Decodes the JWT and enforces `token_type === 'consult_call'` to prevent API-auth tokens from being accepted on consult-call routes. Extracted claims (`staff_id`, `staff_department_id`, `consult_call`) are attached to `$request->attributes` for downstream use.

### Jobs & Async Processing

**Async Jobs** (`app/Jobs/`):
- `ProcessPanelResults` - Async panel result processing (timeout=300s, tries=3, backoff=[30,60,120]s)
- `ProcessPanelComments` - Async comment processing
- `ProcessMigrationBatch` - Async ODB migration batches
- `ProcessMigrationReport` - Generate migration reports
- `ExportBpJob` / `DynamicExportJob` - Background export jobs (tracked by UUID via `QueueJobTrackerService`)
- `ProcessAIWebhookResult` - Handles incoming AI webhook payloads asynchronously
- `SendToAIServer` - Dispatches test results to the AI review service (routes to `SendToMyHealthFeed` from `MYHEALTH_FEED_FROM`)
- `SendToMyHealthFeed` - Sends the patient profile to the MyHealth lab feed

**Post-result processing chain** (inside `ProcessPanelResults`):
1. Before storage: `ref_id` verification via `RefIdVerificationService`
2. After `DB::commit()`: patient matching via `OctopusApiService`
3. Once completeness passes, `TestResultCompletionDispatcher::dispatch()`:
   - Add-on result linkage via `AddOnResultLinkService`
   - Consult-call eligibility check via `ConsultCallEligibilityService` (uses `ConditionEvaluatorService` to evaluate `ClinicalCondition` rules; creates `ConsultCall` record automatically when a condition matches)
   - AI review hold (consult-call enrolled) or dispatch via `SendToAIServer` (MyHealth feed from `2026-11-13`)

Each of these post-transaction steps is wrapped in its own try/catch so a failure in one does not roll back the main transaction.

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
- `POST /api/odb/*` - ODB operations (migration, search, update, consult-call checks, add-on sale merge: `addOnSalesForMerge`, `repointAddOnSale`, `addOnParentSale`, `repointCustomer`)
- `GET /api/patients/by-icno/{icno}` - Patient profile by IC number (built by `PatientProfileService`)
- `POST /api/pdf/export` - Generate PDF export
- `GET /api/import/*` - Import reference data
- `GET /api/export/*` - Export results
- `GET /api/comment/update` - Update comments

**Consult Call Auth Routes** (public, no middleware):
- `POST /api/consult-call/auth` - Obtain consult-call JWT (from ODB proxy)
- `POST /api/consult-call/auth/verify` - Verify token validity

**Consult Call Routes** (require `consult-call.auth`):
- `GET /api/consult-call` - List consult calls (paginated, filterable)
- `GET /api/consult-call/summary` - Dashboard aggregate counts
- `POST /api/consult-call` - Create consult call
- `GET|PUT|DELETE /api/consult-call/{id}` - Show/update/delete consult call
- `POST /api/consult-call/{id}/details` - Create consultation detail
- `PUT|DELETE /api/consult-call/{id}/details/{detailId}` - Update/delete detail
- `POST /api/consult-call/{id}/follow-up` - Create follow-up
- `PUT|DELETE /api/consult-call/{id}/follow-up/{followUpId}` - Update/delete follow-up
- `GET /api/consult-call/statuses/*` - Status library lookup endpoints (11 endpoints)

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

**Log channels** (`config/logging.php`) — use the appropriate channel rather than the default:

| Channel | File | Use for |
|---|---|---|
| `auth` | `logs/auth.log` | All authentication events (`APIAuthMiddleware`) |
| `odb-log` | `logs/odb.log` | ODB controller and migration operations |
| `job` | `logs/job.log` | Queue job execution |
| `webhook` | `logs/webhook.log` | Incoming webhook events |
| `migrate-log` | `logs/migration.log` | ODB batch migrations |
| `gpt-log` | `logs/gpt.log` | AI/GPT review processing |
| `ai-command` | `logs/ai-command.log` | Artisan AI commands |
| `bp-log` | `logs/bp-export.log` | BP export jobs |
| `thyroid-export` | `logs/thyroid-export.log` | Thyroid export jobs |
| `performance` | `logs/performance.log` | Performance timing |
| `myhealth-feed` | `logs/myhealth-feed.log` | MyHealth lab feed dispatch and sends |

All daily channels retain 3 days of logs. Default log level is driven by `LOG_LEVEL` env var (defaults to `warning` in production, `debug` locally).

### Code Style
- Follow PSR-12 via Laravel Pint (`./vendor/bin/pint`)
- Use proper import statements (never inline fully-qualified names)
- Leverage service classes for business logic
- Keep controllers thin

### Authentication

**API Auth** (`api.auth` middleware -- lab systems):
- JWT tokens configured in `config/jwt.php`, signed with `JWT_SECRET`
- Uses Tymon guard with `LabCredential` model (`Auth::guard('lab')`)
- TTL: 30 days (43,200 minutes)
- Refresh TTL: 20,160 minutes (14 days)
- Token blacklisting enabled by default
- Set `JWT_SECRET` in `.env` (generated via `php artisan jwt:secret`)

**Consult Call Auth** (`consult-call.auth` middleware -- ODB staff):
- Separate JWT auth, does NOT use a user model or Laravel guard
- Uses `JWTFactory` + `JWTAuth::encode()` with same `JWT_SECRET`
- TTL: 24 hours (1,440 minutes)
- Tokens include a `token_type: "consult_call"` claim to prevent cross-use with `api.auth`
- Auth controller: `ConsultCallAuthController`
- Middleware: `ConsultCallAuthMiddleware` -- extracts `staff_id`, `staff_department_id`, `consult_call_role` onto `$request->attributes`
- Access control roles (from ODB `staff.consult_call` column):

| Value | Role | Description |
|-------|------|-------------|
| 0 | Normal User | Standard access |
| 1 | Super Admin | Full access, bypasses all filters (dept 16 auto-assigned) |
| 2 | Doctor | Doctor access |
| 3 | Pharmacy | Pharmacy access |
| 4 | HQ | HQ access |
| 5 | Outlet | Outlet access |

- Flow: ODB frontend -> `api-jwt.php` proxy (reads staff table) -> `POST /api/consult-call/auth` -> JWT -> all subsequent calls with `Authorization: Bearer {token}`

### Configuration Files
- `.env.example` contains all required environment variables
- Key integrations: ODB, Eurofins/Innoquest, AI Review service, MyHealth lab feed (`MH_KEY_FEED_URL`, `MH_KEY_FEED`, `MH_KEY_FEED_TIMEOUT`), SFTP
- L5 Swagger documentation auto-generated from code annotations

### Scheduler (`app/Console/Kernel.php`)
Periodic commands only; most are restricted to `environments(['production'])`:
- `ai:reconcile-reviews --hours=6 --limit=200` - hourly
- `ai:retry-failed-reviews --hours=12 --limit=50`
- `ai:dispatch-unreviewed-async`, `ai:sweep-stale-pending`
- `myhealth:reconcile-feeds` (hourly :10), `myhealth:retry-failed-feeds` (hourly :25), `myhealth:sweep-stale-pending` (every 10 min), `myhealth:purge-feed-payloads --days=30` (daily 02:30)
- `panels:sync-profile-counts` - daily 00:00
- `panels:master-reconcile --limit=200 --force` - hourly at :40
- `queue:auto-retry-failed --hours=24 --limit=100 --max-attempts=3`, `queue:prune-failed --hours=720`
- `queue:work --queue=exports --stop-when-empty` - export queue drain
- The daily `consult-call:daily-eligibility-sweep` was removed from the schedule (command still exists for manual runs)

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

The test environment (`phpunit.xml`) uses:
- Array cache driver (in-memory)
- Sync queue (jobs execute immediately in-process)
- Array mail driver (no emails sent)
- **Real MySQL database** — SQLite/in-memory is explicitly disabled. Tests require a configured `DB_*` connection. There is no automatic schema setup; run `php artisan migrate` against the test database before running tests.

## Engineering & Automation Rules

### 1. Framework & Runtime
- Use **Laravel 10**
- Use **PHP 8.2** (production runs 8.2.35 TS; required extensions: redis, zip, curl, mbstring, openssl, pdo_mysql)

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
