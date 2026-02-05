# Patient Matching System

## Purpose

The patient matching system reconciles patients from MyHealth (Blood Stream) with customers in the Octopus (ODB) database. When lab results arrive from Innoquest, the patient's IC number and reference ID may not exactly match the ODB records due to OCR errors, formatting differences, or missing data. This system uses fuzzy matching with weighted scoring to find and link the correct customer.

---

## Architecture Overview

```
Innoquest Lab Results
        |
        v
ProcessPanelResults (Job)
        |
        v
PatientMatcherService
   |           |              |
   v           v              v
IcNormalizer  RefIdNormalizer  OctopusApiService
Service       Service              |
                                   v
                          ODB API (PHP endpoints)
                                   |
                                   v
                          Octopus MySQL Database
```

---

## Database Tables

### patient_match_candidates
Stores potential matches found by the fuzzy search algorithm.

| Column | Purpose |
|---|---|
| patient_id | FK to patients table |
| source_ic / source_ic_normalized | Patient IC from MyHealth (raw + normalized) |
| source_refid / source_refid_normalized | Patient ref_id from test_results (raw + normalized) |
| candidate_customer_id | Octopus customer.id |
| candidate_ic / candidate_name / candidate_dob / candidate_gender / candidate_refid | Customer data from Octopus |
| ic_score / ic_match_method | IC comparison score (0-1) and method used |
| refid_score / refid_match_method | RefID comparison score (0-1) and method used |
| name_score / name_match_method | Name comparison score (0-1) and method used |
| dob_score / gender_score | DOB and gender comparison scores |
| confidence_score | Final weighted score (0-1) |
| status | `pending_review`, `approved`, `rejected` |
| reviewed_by / reviewed_at / review_notes | Admin review metadata |

### patient_customer_links
Confirmed links between a patient and an Octopus customer after approval.

| Column | Purpose |
|---|---|
| patient_id | FK to patients table (unique with customer_id) |
| customer_id | Octopus customer.id |
| link_type | `exact_match`, `fuzzy_match`, `manual_link` |
| confidence_score | Score at time of linking |
| match_candidate_id | FK to the candidate that was approved (nullable for manual links) |
| linked_by / linked_at | Who approved and when |

### patient_match_audit_logs
Immutable audit trail for all matching operations.

| Column | Purpose |
|---|---|
| action | `match_attempted`, `candidates_found`, `no_candidates_found`, `candidate_approved`, `candidate_rejected`, `link_created`, `link_removed` |
| triggered_by | `system`, `admin`, `job`, `api` |
| input_data / output_data | JSON payloads for full traceability |

---

## Scoring Algorithm (v1.1)

### Weights

| Field | Standard Weight | No-DOB Weight |
|---|---|---|
| IC | 0.35 | 0.40 |
| Name | 0.25 | 0.30 |
| RefID | 0.15 | 0.20 |
| DOB | 0.15 | 0.00 |
| Gender | 0.10 | 0.10 |

When the patient DOB is invalid (e.g., `0000-00-00`), the DOB weight is redistributed to IC, Name, and RefID.

### IC Scoring Methods
1. **Normalized exact** (score: 1.0) -- After normalization, ICs are identical
2. **DOB prefix match** (score: 0.5-1.0) -- First 6 digits of IC match the DOB, remaining digits scored by Levenshtein. **Only applies to Malaysian NRICs, not passports.**
3. **Levenshtein** (score: 0.0-1.0) -- Character-level similarity

### Passport Detection
ICs starting with letters (e.g., `MF957881`, `PA0288102`) are detected as passports. Passports CAN exist in the ODB `customer.ic` column (which stores both Malaysian NRICs and passports).

For passport holders:
- DOB prefix matching is **disabled** (passports don't follow YYMMDD format like Malaysian NRICs)
- IC scoring uses Levenshtein only
- **Name matching becomes critical** to prevent false positives

Example of why name matching matters:
```
Patient A: LEE KIAN TIN, IC: 971202XXXXXX, DOB: 1997-12-02
Patient B: SAMUEL LEE,   Passport: M12345, DOB: 1997-12-02
```
Both share the same DOB but are different people. When ODB returns candidates matched by DOB, the name similarity check prevents incorrectly linking SAMUEL LEE to LEE KIAN TIN.

### Name Scoring Methods
1. **Exact** (score: 1.0) -- Normalized names are identical
2. **Token match** (score: 0.90) -- Same name parts in different order (e.g., "WONG KAI MING" vs "KAI MING WONG")
3. **Partial contains** (score: 0.85) -- One name contains the other
4. **Partial token** (score: 0.70) -- At least 50% of name tokens overlap
5. **Levenshtein** (score: 0.0-1.0) -- Character-level similarity

Name normalization: Uppercased, titles removed (MR, MRS, DR, DATO, etc.), punctuation stripped.

### RefID Scoring Methods
1. **Normalized exact** (score: 1.0) -- After normalization, RefIDs are identical
2. **Levenshtein** (score: 0.0-1.0) -- Character-level similarity

### DOB and Gender
- DOB: 1.0 for exact match, 0.0 for mismatch, 0.5 for neutral (missing/invalid)
- Gender: 1.0 for match, 0.0 for mismatch, 0.5 for neutral (missing). Normalizes M/Male/L/Lelaki and F/Female/P/Perempuan.

### Candidate Rejection Rules
Candidates are **rejected** (not saved) when:
1. **DOB prefix match with low name similarity** -- IC matched via DOB prefix but name score < 0.60
2. **Passport holder with weak IC match** -- Source IC is a passport AND IC levenshtein score < 0.5 AND name score < 0.60

These rules prevent false positives like:
- `SAMUEL LEE` (passport `M12345`, DOB `1997-12-02`) incorrectly matched to
- `LEE KIAN TIN` (IC `971202XXXXXX`, DOB `1997-12-02`)

They share the same DOB but are different people. The name check (SAMUEL LEE vs LEE KIAN TIN = low similarity) catches this.

### Auto-Approval
Candidates with confidence = 1.0 (exact IC match from Octopus API) are automatically approved without admin review.

---

## Normalization

### IcNormalizerService
Handles Malaysian NRIC numbers. Removes separators (`-`, spaces), uppercases, then applies visual-similarity character substitutions:

| Character | Replaced With |
|---|---|
| O | 0 |
| I | 1 |
| S | 5 |
| B | 8 |
| Z | 2 |
| G | 6 |

Also provides DOB prefix extraction (`YYMMDD` from first 6 digits) and state code extraction (digits 7-8).

### RefIdNormalizerService
Handles reference IDs like `INN10256`. Preserves the alphabetic prefix, then applies character substitutions only to the numeric portion:

| Character | Replaced With |
|---|---|
| O | 0 |
| I | 1 |
| S | 5 |

---

## ODB API Endpoints

All endpoints are POST, authenticated via `wa_api_user` table (username + SHA-256 hashed password).

| Endpoint | Input | Output |
|---|---|---|
| `/customerFuzzySearch.php` | ic, ic_normalized, dob, gender, refid, lab_code | exact_match or candidates array (includes refid from latest blood_test_sales) |
| `/customerByRefId.php` | refid, lab_code | Single customer record with blood_test_sales data |
| `/customerSalesByCustomerId.php` | customer_id | Array of `{id, date}` from blood_test_sales |

Base URL configured at `config/services.php` -> `services.octopus.api_url` (env: `ODB_API_URL_PROD`).
Credentials at `config/credentials.php` -> `credentials.odb_api.username` / `credentials.odb_api.password`.

### Fuzzy Search Logic
The `/customerFuzzySearch.php` endpoint searches using OR conditions:
1. **Exact IC match** -- Returns immediately if found
2. **IC prefix match** -- First 6 digits of IC (for Malaysian NRICs starting with YYMMDD)
3. **DOB + Gender match** -- Same birthday and gender
4. **RefID match** -- Via blood_test_sales table

Each candidate includes `refid` (format: `{lab_code}{sales_id}`) from their most recent blood_test_sales record. If the candidate has no sales, refid is null.

### Candidate RefID Enrichment
If the fuzzy search returns a candidate without a refid, the `PatientMatcherService` makes a secondary call to `/customerSalesByCustomerId.php` to fetch the customer's blood_test_sales records. The most recent sale ID is used as the candidate's refid. This is a fallback mechanism.

---

## Entry Points

### 1. Real-time: ProcessPanelResults Job

When Innoquest lab results arrive via the panel results API:

```
ProcessPanelResults::processPanel()
    -> DB::commit() (save test result)
    -> runPatientMatching($testResult)
        -> Skip if patient already has a customerLink
        -> PatientMatcherService::findMatchCandidates()
        -> If confidence = 1.0: createMatchCandidate() (auto-approve)
        -> If confidence < 1.0: log as fuzzy match, skip (no candidate created)
    -> Dispatch AI review
    -> Dispatch ConsultCall
```

Patient matching runs after the transaction commits and before AI/ConsultCall dispatch. It is non-critical: failures are caught and logged as warnings without affecting the rest of the pipeline.

### 2. Batch: patients:find-mismatches Command

```
php artisan patients:find-mismatches --lab-code=INN --batch-size=10 --max-batches=1 --stop-on-mismatch=100
```

Processes patients in batches. For each patient without a customer link or pending candidate:
- Calls `findMatchCandidates()` and `createMatchCandidate()` for the top result
- Tracks exact matches vs mismatches (confidence below `--min-confidence`)
- Stops after reaching `--stop-on-mismatch` count

This is the command run by the scheduled batch scripts (`scripts/patient_reconcile_local.bat` and `scripts/patient_reconcile_prod.bat`).

### 3. Batch: patients:reconcile Command

```
php artisan patients:reconcile --lab-code=INN --batch-size=100 --sync
```

Dispatches `ReconcileUnmatchedPatientsJob` to the queue (or runs synchronously with `--sync`). Creates candidate records for all matches above the minimum threshold, not just the top one. Use `--stats` to view statistics without processing.

---

## Date-Validated ref_id Assignment

When a 100% confidence match is auto-approved, the system assigns `ref_id` values to the patient's test results using date-based matching against `blood_test_sales`:

1. Fetch all `blood_test_sales` for the matched customer via `OctopusApiService::getBloodTestSalesByCustomerId()`
2. Get all test_results for the patient where `ref_id` is NULL/empty and `collected_date` is not NULL
3. For each test_result, find the `blood_test_sales` record whose `date` is within **14 days** of the `collected_date`
4. If multiple sales fall within range, pick the **closest by date**
5. Before assigning, verify the ref_id (format: `{lab_code}{sales_id}`, e.g. `INN12345`) does not already exist in test_results
6. Update only that specific test_result

### Edge Cases Handled
- Test result has no `collected_date` -> skipped
- No `blood_test_sales` within 14 days -> skipped, ref_id remains NULL
- `blood_test_sales.id` already assigned to another test_result -> skipped (no duplicates)
- API call fails -> logged as warning, ref_id update skipped (non-critical)

---

## File Inventory

### Laravel Application (C:\laragon\www\blood-stream-v1)

| File | Type |
|---|---|
| `app/Services/PatientMatcherService.php` | Core matching orchestration |
| `app/Services/IcNormalizerService.php` | IC number normalization |
| `app/Services/RefIdNormalizerService.php` | Reference ID normalization |
| `app/Services/OctopusApiService.php` | ODB API HTTP client |
| `app/Models/PatientMatchCandidate.php` | Match candidate model |
| `app/Models/PatientCustomerLink.php` | Confirmed link model |
| `app/Models/PatientMatchAuditLog.php` | Audit log model |
| `app/Console/Commands/FindMismatchedPatients.php` | `patients:find-mismatches` command |
| `app/Console/Commands/ReconcileUnmatchedPatients.php` | `patients:reconcile` command |
| `app/Jobs/ReconcileUnmatchedPatientsJob.php` | Queued reconciliation job |
| `app/Jobs/Innoquest/ProcessPanelResults.php` | Integration point (`runPatientMatching`) |
| `database/migrations/2026_02_05_100000_create_patient_match_candidates_table.php` | Migration |
| `database/migrations/2026_02_05_100001_create_patient_customer_links_table.php` | Migration |
| `database/migrations/2026_02_05_100002_create_patient_match_audit_logs_table.php` | Migration |
| `scripts/patient_reconcile_local.bat` | Local batch script |
| `scripts/patient_reconcile_prod.bat` | Production batch script |

### ODB API (C:\xampp\htdocs\api)

| File | Type |
|---|---|
| `customerFuzzySearch.php` | Fuzzy IC/RefID search endpoint |
| `customerByRefId.php` | Lookup by blood_test_sales reference ID |
| `customerSalesByCustomerId.php` | Fetch blood_test_sales by customer ID |
| `db.php` | Database connection |
| `jwt_utils.php` | Authentication utilities |
