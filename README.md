# Public Services Portal & Document Tracking System

Citizens file a service application (birth certificate, building permit, tax clearance, driving-licence
upgrade), get a tracking number, and follow the case in real time while officers and administrators work
it through a fixed workflow. Every status change, assignment, document read and failed login is written to
an append-only audit trail.

Built to be demoed: `C:\Wamp.NET\sites\services-portal`, running on Wamp.NET (Apache + PHP 8.3 + MariaDB).

| | |
|---|---|
| Backend | Laravel 13.38, PHP 8.3.1, MariaDB 11.2 |
| Auth | Laravel Sanctum 4.3 bearer tokens + custom role/permission layer + policies |
| Frontend | Inertia.js 3.5, Vue 3 `<script setup>`, Tailwind CSS 4, Vite 8 |
| Tests | PHPUnit 12 — **113 tests, 910 assertions, all green** (SQLite in-memory) |
| API docs | `docs/openapi.yaml` (OpenAPI 3.0, validated) + Swagger UI at `/docs` + `docs/postman_collection.json` |
| Code style | Laravel Pint |

---

## 1. Run it on Wamp.NET

### 1.1 Point the hostname at your machine (one time, needs admin)

Add this line to `C:\Windows\System32\drivers\etc\hosts` in an **elevated** editor:

```
127.0.0.1   services-portal
```

The Apache vhost is already installed at
`C:\Wamp.NET\servers\1-apache-2.4.58\conf\vhosts\services-portal.vhost` and points
`DocumentRoot` at `C:/Wamp.NET/sites/services-portal/public` with the PHP 8.3 FastCGI backend
(`proxy:fcgi://127.0.0.1:831#`). **Restart Apache** from the Wamp.NET tray menu so it is picked up.

### 1.2 Install

```bash
cd C:\Wamp.NET\sites\services-portal

composer install
copy .env.example .env
php artisan key:generate
```

Create the database (Wamp.NET → MariaDB → phpMyAdmin → *Create database*, or the CLI):

```sql
CREATE DATABASE services_portal CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
```

`.env` already ships with the right defaults for Wamp.NET — `DB_HOST=127.0.0.1`, `DB_PORT=3306`,
`DB_DATABASE=services_portal`, `DB_USERNAME=root`, empty `DB_PASSWORD`. Change only what differs on
your machine.

```bash
php artisan migrate --seed
npm install
npm run build
```

Open **http://services-portal** — that is the whole thing: landing page, tracking, login, dashboard.

> No hostname yet? Use `php artisan serve` and open http://127.0.0.1:8000 instead. Everything below
> works the same; only `APP_URL` and `SANCTUM_STATEFUL_DOMAINS` differ.
>
> While developing the Vue layer, run `php artisan serve` and `npm run dev` in two terminals so Vite
> serves HMR assets.

### 1.3 Demo accounts (password `password` for all of them)

| Account | Role | What it shows |
|---|---|---|
| `admin@portal.local` | Administrator | Full register, approvals, users, catalogue, the whole audit trail |
| `officer@portal.local` | Case Officer (Home Affairs) | Department queue, transitions, document review, own workload |
| `citizen@portal.local` | Public User | Own filings only, upload, withdraw, masked public tracking |

The login page has **Fill in** buttons for each of the three, so you can switch roles in seconds.
`php artisan migrate:fresh --seed` rebuilds 20 demo cases with timelines, documents and audit entries.

---

## 2. Two-minute demo path

1. **Anonymous tracking** — home page → paste `PSP-2026-000001` → the applicant name comes back masked.
   Add the seeded citizen number `3000000001` and `owner_verified` unlocks the full timeline.
2. **Citizen** — sign in as `citizen@portal.local` → *New application* → submit → you land on the case
   page with the reference, a live badge polling every 15 s, and only the *Withdraw* action.
3. **Officer** — sign in as `officer@portal.local` → the queue shows the department's cases → open one →
   move it to *Under review* (the case auto-claims to you), upload/review paperwork, add an **internal
   note** and watch it disappear from the citizen's timeline.
4. **Try to break the workflow** — from *Submitted* press a jump to *Approved*: the API answers
   `422 { "code": "workflow_violation" }` and the case does not move.
5. **Admin** — sign in as `admin@portal.local` → *Approve* a `pending_approval` case (only an admin can),
   then open **Audit trail**, filter `event = status_changed` and read the before/after values, the actor's
   role, IP and user agent. Admin → **Users** → deactivate an officer → their tokens are revoked and their
   next API call is a `401`.

---

## 3. REST API

Base path `/api/v1`, 39 endpoints, every response wrapped in `{ data, meta?, message? }`.
Interactive docs: **http://services-portal/docs** · contract: `docs/openapi.yaml` · Postman:
`docs/postman_collection.json` (import it, run *01 Auth → Login as administrator*, and the bearer token
is stored for every following request).

```bash
# 1. Get a token
curl -X POST http://services-portal/api/v1/auth/login \
  -H "Content-Type: application/json" -H "Accept: application/json" \
  -d '{"email":"officer@portal.local","password":"password","device_name":"curl"}'

# 2. File a case (citizen token)
curl -X POST http://services-portal/api/v1/applications \
  -H "Authorization: Bearer $TOKEN" -H "Content-Type: application/json" -H "Accept: application/json" \
  -d '{"service_type_id":3,"title":"Renewal of my national identity card",
       "description":"My card expires next month and the counter needs an appointment.",
       "applicant_name":"Amina Yusuf","applicant_phone":"+1 555 0100",
       "applicant_address":"12 Riverside Road, Block C, Apt 4"}'

# 3. Move it (officer token) — writes status, timeline and audit row in one transaction
curl -X POST http://services-portal/api/v1/applications/21/status \
  -H "Authorization: Bearer $TOKEN" -H "Content-Type: application/json" -H "Accept: application/json" \
  -d '{"status":"under_review","note":"Registry match confirmed; opening the file."}'

# 4. Anonymous poll, cheap
curl "http://services-portal/api/v1/track?reference=PSP-2026-000021"

# 5. Compliance
curl -H "Authorization: Bearer $ADMIN_TOKEN" \
  "http://services-portal/api/v1/audit-logs?event=status_changed&from=2026-10-01"
```

### Errors are machine readable

| Situation | Status | Body |
|---|---|---|
| Missing/expired token | 401 | `{ "message": "Unauthenticated.", "code": "unauthenticated" }` |
| Role, ability or policy refused | 403 | `{ "message": "...", "code": "http_error" }` |
| Unknown id or out-of-scope row | 404 | `{ "message": "Resource not found.", "code": "not_found" }` |
| Validation | 422 | `{ "message": "...", "errors": { "field": ["..."] } }` |
| Workflow edge does not exist | 422 | `{ "message": "...", "code": "workflow_violation" }` |

### Rate limits (`AppServiceProvider`)

`login` 10/min · `register` 5/min · `track` 30/min · authenticated API 120/min per user+route.

---

## 4. Tests

```bash
php artisan test                     # 113 passed (910 assertions), ~16 s
php artisan test --filter=WorkflowTest
./vendor/bin/pint                    # fix style
./vendor/bin/pint --test             # style gate
```

Tests run on SQLite `:memory:` with `array` session and cache drivers (`phpunit.xml`), so they never
touch the demo database. `tests/PortalTestCase.php` seeds roles, permissions and the catalogue, then
hands out **real Sanctum tokens from the login endpoint** — the API tests exercise the same middleware
stack a client would, not `actingAs` shortcuts.

| File | Tests | Covers |
|---|---|---|
| `tests/Feature/Api/AuthTest.php` | 12 | register, login, me, refresh, logout, abilities, deactivated token |
| `tests/Feature/Api/ApplicationTest.php` | 21 | filing, validation messages, per-role scoping, filters, pagination, live poll |
| `tests/Feature/Api/WorkflowTest.php` | 13 | every legal edge, illegal edge → `workflow_violation`, auto-claim, terminal states |
| `tests/Feature/Api/DocumentTest.php` | 13 | private disk, sha256, mime/size rules, officer review, audited download |
| `tests/Feature/Api/PublicTrackingTest.php` | 9 | name masking, ownership proof, `tracking_miss`, throttle |
| `tests/Feature/Api/AdministrationTest.php` | 11 | users, roles, catalogue, audit scoping, export cap |
| `tests/Feature/Api/DashboardTest.php` | 6 | role-aware counters, workload report, 30-day trend |
| `tests/Feature/Web/WebPortalTest.php` | 15 | the Inertia pages: session login, submission, transitions, flash errors |
| `tests/Feature/ReferenceNumberTest.php` | 5 | per-year sequence, no reuse after withdrawal |
| `tests/Unit/WorkflowRulesTest.php` | 8 | `config/portal.php` integrity, disk has no public URL, limiter sanity |

---

## 5. How it is put together

```
app/
  Enums/            RoleName, ApplicationStatusCode (owns the transition map), Priority, DocumentStatus
  Http/Middleware/  EnsureUserHasRole (role:x,y + active-account check), Sanctum ability aliases
  Http/Controllers/
    Api/V1/         9 REST controllers  — the JSON surface
    Web/            7 Inertia controllers — same service layer, session auth
  Http/Requests/    one form request per write: validation lives here, not in controllers
  Http/Resources/   serializers; hide fields per role instead of returning null columns
  Policies/         ApplicationPolicy, ApplicationDocumentPolicy — row-level rules
  Services/         ApplicationService, DashboardService — workflow transactions
  Support/Audit/    AuditLogger (single write path, credentials stripped) + Auditable trait
  Support/          ReferenceNumberGenerator, ApplicationPresenter
config/portal.php   statuses, workflow edges with allowed roles, document rules, tracking settings
```

**Roles.** `roles`, `permissions`, `permission_role` are real tables and the token's abilities are derived
from them (`applications:read|write|decide|assign`, `users:manage`, `audit:read`, `catalog:manage`). A
route can therefore be guarded twice: `role:admin` middleware plus `abilities:users:manage`, then the
policy decides the individual row.

**Workflow.** Statuses are rows, not an enum column, so an administrator can rename *Under review* without
a migration. The legal edges live in `config/portal.php` as `from => [to => [roles allowed]]`; the engine
checks the edge, the role and the terminal state in one place, and both surfaces share it — that is why the
Inertia form only ever offers moves the API would accept.

**Audit.** `AuditLogger::log()` is the only writer. It keeps the actor's name and role so the trail stays
readable after the account is deleted, denormalises the case reference (child records inherit their
application's), and drops `password`, `token`, `remember_token` before the row lands. Model events
(`created`/`updated`/`deleted`) are captured by the `Auditable` trait; business events
(`status_changed`, `officer_assigned`, `document_downloaded`, `login_failed`, `tracking_miss`) are explicit.

**Documents.** Stored on the `documents` disk, a private `local` driver with **no `url` key**, so there is
no public path to guess. A sha256 checksum is taken at upload and re-quoted in the download audit entry;
`GET /documents/{id}/download` behind a policy is the only way to read one.

**Deactivation.** Setting `is_active = false` deletes that user's tokens and the `role` middleware rejects
sessions too, so leaving takes effect immediately on both surfaces — including a signed-in browser tab.

---

## 6. Troubleshooting on Wamp.NET

| Symptom | Fix |
|---|---|
| `services-portal` does not resolve | Add the `hosts` line as Administrator, then `ipconfig /flushdns` |
| Apache 404 on every route | The vhost must point at `public/`, not the project root; restart Apache |
| 403 "company directory denied" | In the vhost, `AllowOverride all` must be kept so `.htaccess` rewrites work |
| `could not find driver: pdo_mysql` | Wamp.NET tray → PHP → extensions → enable `pdo_mysql` |
| Login works, CSS/JS missing | `npm run build`, then hard-refresh (the manifest lives in `public/build`) |
| `Failed to open stream: storage/...` | The storage cache dirs must be writable; `php artisan storage:link` for the public disk |
| Token rejected across hosts | `SANCTUM_STATEFUL_DOMAINS` must list every host you use |
| Tests slow or DB errors | They use SQLite `:memory:` — check you did not override `DB_CONNECTION` in `.env` for the test run |

## 7. Useful commands

```bash
php artisan migrate:fresh --seed    # rebuild demo data
php artisan tinker                  # App\Services\ApplicationService, AuditLogger
php artisan route:list --path=api   # the whole REST surface
php artisan serve --port=8000       # without Apache
```
