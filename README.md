# Logistics ERP

A multi-tenant SaaS ERP platform for Clearing & Forwarding and Logistics companies.

**All 15 build phases are complete** — every module originally scoped, plus the
full follow-on feature list, is built end-to-end (backend + frontend + tests):

- **Phase 1** — platform spine: multi-tenant foundation, authentication, tenant
  self-registration, RBAC, Super Admin + Tenant dashboard shells.
- **Phase 2** — CRM: Leads, Customers, Contacts, lead-to-customer conversion.
- **Phase 3** — core operational ERP: Quotations, Shipments, Clearing, Freight
  Forwarding, Containers, Warehouse, Fleet, Finance, Accounting, Documents, Reports.
- **Phase 4** — shipment milestone tracking, the public `/track` lookup, and a real
  Analytics page.
- **Phase 5** — Client Portal: a customer-facing login (`User.customer_id`) onto
  their own shipments/invoices/quotations/documents/messages.
- **Phase 6** — Demurrage Calculator: tiered rate cards, a live exception board,
  charge-to-invoice generation.
- **Phase 7** — Expense Management: submit → approve/reject → paid lifecycle.
- **Phase 8** — Workflow/Approval engine: generic multi-step, role-gated approval
  chains, retrofitted onto Expenses with a legacy single-approver fallback.
- **Phase 9** — HR/Attendance: Departments, Employees, Attendance records.
- **Phase 10** — White-label branding: per-tenant logo + primary/secondary colors.
- **Phase 11** — Notification dispatch: Email/SMS/WhatsApp fan-out per tenant toggle.
- **Phase 12** — QR codes: shipment-tracking QR (staff, portal, and embedded in the
  invoice PDF).
- **Phase 13** — 2FA + login lockout: TOTP two-factor auth, account lockout after
  repeated failed logins, login history.
- **Phase 14** — Report export: CSV/Excel export for six core data sets.
- **Phase 15** — Branch-level rollups: per-branch operational + financial
  performance table.

There are no remaining "coming soon" items anywhere in the tenant nav.

## Structure

```
logistics-erp/
├── backend/     Laravel 12 REST API (MySQL locally via XAMPP, Sanctum auth)
└── frontend/    React 19 + Vite + TypeScript + MUI
```

## Stack

- **Frontend**: React 19, Vite, TypeScript, React Router, TanStack Query, React Hook
  Form + Zod, MUI, Axios, Zustand, react-i18next (English + Swahili)
- **Backend**: Laravel 12, PHP 8.2+, Sanctum (token auth), spatie/laravel-permission
  (team-scoped RBAC), Scramble (OpenAPI docs), Reverb (real-time, scaffolded),
  barryvdh/laravel-dompdf (invoice PDFs), intervention/image (logo uploads),
  simplesoftwareio/simple-qrcode (SVG QR codes — no Imagick needed),
  pragmarx/google2fa (TOTP 2FA), maatwebsite/excel (CSV/XLSX report export)

## Multi-tenancy

Single shared database, `tenant_id` column on every tenant-scoped table, enforced via
a global Eloquent scope (`TenantScope`) + `ResolveTenant` middleware. Platform admin
routes skip tenant resolution entirely, giving Super Admins unscoped, cross-tenant
visibility. See `backend/app/Support/Tenancy/TenantContext.php` and
`backend/app/Models/Scopes/TenantScope.php`.

**Important ordering constraint**: `ResolveTenant` (which sets `TenantContext`) must
run *before* Laravel's `SubstituteBindings` middleware (which resolves implicit
route-model bindings like `Shipment $shipment`). Otherwise a route parameter can be
resolved with no tenant filter at all — a real cross-tenant IDOR this project hit
once. Fixed via `$middleware->prependToPriorityList(before: SubstituteBindings::class,
prepend: ResolveTenant::class)` in `backend/bootstrap/app.php`. Apply the same
ordering to any future tenant-scoping (or auth-adjacent) middleware.

**Client Portal is a second, narrower scope inside the same tenant boundary.** A
portal login is just a `User` row with `customer_id` set (see Phase 5). `TenantScope`
only filters by `tenant_id`, so every portal controller *manually* filters by the
authenticated user's `customer_id` on top of that — and single-record endpoints never
rely on implicit route-model binding, always an explicit `Model::where('customer_id',
$id)->findOrFail(...)` or 404. See `App\Http\Middleware\EnsurePortalUser` and any
controller under `app/Http/Controllers/Api/V1/Portal`.

Local dev uses the `database` cache/queue driver and local disk storage as
stand-ins for Redis/S3 — all code is written against Laravel's storage, cache, and
queue facades, so switching `.env` to real Redis/S3 in production requires no code
changes. Email is genuinely functional locally via `MAIL_MAILER=log` (writes to
`storage/logs/laravel.log` instead of actually sending); SMS/WhatsApp default to
log-only drivers (`LogSmsChannel`/`LogWhatsAppChannel`) behind swappable interfaces
(`App\Contracts\SmsChannel` / `WhatsAppChannel`) for a real provider to be dropped in
later without touching call sites.

## Local development

### Backend

```
cd backend
composer install
cp .env.example .env
php artisan key:generate
php artisan migrate --seed
php artisan serve --port=8001
```

This project's local `.env` points at MySQL via XAMPP (`DB_CONNECTION=mysql`,
`127.0.0.1:3306`) — **XAMPP's MySQL must be running** before `migrate`/`serve` will
work (it is not a Windows service here; start it manually if needed:
`c:\xampp\mysql\bin\mysqld.exe --defaults-file="c:\xampp\mysql\bin\my.ini"
--standalone`). The automated test suite is unaffected — it runs against an
in-memory SQLite database (see `phpunit.xml`), not MySQL.

`--seed` creates the RBAC permission catalog, the three subscription plans
(Starter/Professional/Enterprise), and a bootstrap Super Admin user
(`SUPER_ADMIN_EMAIL` / `SUPER_ADMIN_PASSWORD` in `.env`, defaults in `.env.example`).

API docs (Scramble, auto-generated from FormRequests/Resources): `http://127.0.0.1:8001/docs/api`

**After pulling changes that add a new permission module**, run `php artisan
rbac:sync` instead of `migrate:fresh` — it backfills the new permissions onto
every existing tenant's roles without touching any other data. See
`backend/config/rbac.php` and `backend/app/Console/Commands/SyncRbacCommand.php`.

### Frontend

```
cd frontend
npm install
cp .env.example .env
npm run dev
```

Set `VITE_API_URL` in `frontend/.env` to match whatever port the backend is running
on (defaults to `http://127.0.0.1:8001/api/v1`).

### Running both together

Backend and frontend are independent dev servers — run both `php artisan serve` and
`npm run dev` at the same time, in separate terminals. Ports 8000/5173 may already be
taken by other local projects; both `artisan serve` and `vite` will auto-pick the next
free port if so — just make sure `frontend/.env`'s `VITE_API_URL` matches whatever
port the backend actually bound to. **This codebase's convention is port 8001** — the
frontend's default `VITE_API_URL` assumes it, so `php artisan serve --port=8001` is
the expected way to start the backend.

## Testing

```
cd backend
php artisan test          # 160+ Feature tests

cd frontend
npx tsc --noEmit          # type check
npm run build             # production build
npm run test -- --run     # Vitest unit/component tests
```

Backend coverage spans tenant registration atomicity (all-or-nothing provisioning
across tenant, company, branch, owner user, RBAC roles, subscription, billing
profile, dashboard settings, audit log), login/2FA/lockout, role-based redirects,
platform-admin access control, cross-tenant *and* cross-customer (portal) data
isolation, and full CRUD + permission-enforcement + tenant-isolation coverage for
every module listed under Key flows below.

**A recurring test-harness gotcha worth knowing before writing a new test**: Laravel's
auth guard caches the first resolved user for the life of a PHPUnit test *process*,
not per simulated request. A test that authenticates as two different tenants in one
method via two different bearer tokens (`withHeader('Authorization', "Bearer
{$tokenB}")` after already having authenticated as token A) will silently keep
resolving as the first user. Use `Laravel\Sanctum\Sanctum::actingAs($userB, ['*'])`
(after `app(TenantContext::class)->clear()`) for a second identity instead — see any
`*_isolated_per_tenant` test for the pattern.

## Key flows

- **Public site**: `/` — marketing landing page (hero, features, live pricing, industries, testimonials, about, FAQ, contact/demo forms)
- **Tenant self-registration**: `/register` — 4-step wizard (plan → owner account → company details → review) that atomically provisions a new tenant and logs the owner straight into their dashboard
- **Public shipment tracking**: `/track` — look up any shipment by its public tracking code (also embedded as a QR code on the shipment record and on invoice PDFs)
- **Login**: `/login` — redirects to `/platform` (Super Admin), `/portal/dashboard` (customer portal user), or `/app/dashboard` (tenant staff) based on role; if the account has 2FA enabled, an inline challenge step asks for a 6-digit code (or a one-time recovery code) before issuing a session; 5 failed attempts locks the account for 15 minutes
- **Tenant dashboard**: `/app/*` — company dashboard (live widgets), users, branches (+ per-branch performance rollup), audit log, login history, company settings, account security (2FA setup/disable, self-service)
- **CRM**: `/app/crm` — Leads and Customers (tabbed), lead-to-customer conversion (auto-creates a primary Contact), Customer detail page with a Contacts sub-table and a message thread (mirrors the portal's customer-facing messaging)
- **Quotations**: `/app/quotations` — pre-sale pricing, auto-numbered (`QT-YYYY-NNNNN`)
- **Shipments**: `/app/shipments` — the execution-side master record, auto-numbered (`SHP-YYYY-NNNNN`); optionally links to an accepted Quotation, a Clearing file, a Freight booking, and a Branch; milestone timeline + public tracking code + SVG QR code
- **Clearing**: `/app/clearing` — customs clearing files, auto-numbered (`CLR-YYYY-NNNNN`), status workflow
- **Freight Forwarding**: `/app/freight` — carrier bookings, auto-numbered (`FWD-YYYY-NNNNN`)
- **Containers**: `/app/containers` — container tracking, optionally linked to a clearing file or freight booking; demurrage calculation entry point
- **Demurrage**: `/app/demurrage` — tiered rate cards, live exception board (containers approaching/past free time), charge history, waive/generate-invoice actions
- **Warehouse**: `/app/warehouse` — goods-receipt tracking, auto-numbered (`WH-YYYY-NNNNN`)
- **Fleet**: `/app/fleet` — company vehicles (not customer-scoped)
- **Finance**: `/app/finance` — customer invoices, auto-numbered (`INV-YYYY-NNNNN`), optionally linked to a Branch; PDF download (with embedded tracking QR when linked to a shipment)
- **Expenses**: `/app/expenses` — submit → approve/reject → mark-paid lifecycle; approval either falls back to a flat `expenses.items.approve` permission or is driven by a configured multi-step Workflow
- **Workflows**: `/app/workflows` — define ordered, role-gated approval chains (by amount threshold) reusable across modules; currently wired onto Expenses
- **Accounting**: `/app/accounting` — chart of accounts + double-entry journal entries (debit/credit balance enforced server-side); entries are editable while `draft`, immutable once `posted`; `post`/`void` are a separate permission from `manage` for segregation of duties
- **HR**: `/app/hr` — Departments, Employees, Attendance records
- **Documents**: `/app/documents` — file uploads (10MB max, PDF/image/Office formats) stored on the `public` disk, categorized, downloadable
- **Reports**: `/app/reports` — read-only cross-module rollup (totals + status breakdowns) over every operational module, plus a Data Export card (CSV/Excel download for Customers, Leads, Quotations, Shipments, Invoices, or Expenses)
- **Branches**: `/app/branches` — branch list + a Branch Performance rollup table (employees/vehicles/warehouse items/shipments/invoices/revenue per branch, with an "Unassigned" bucket for records not yet assigned to a branch)
- **Account Security**: `/app/security` — enable/disable 2FA (QR-code TOTP setup + one-time recovery codes), self-service for any tenant user
- **Login History**: `/app/login-history` — tenant admin view of every login attempt (success/failure/reason) for the company
- **Company Settings**: `/app/settings` — company profile, white-label branding (logo upload, primary/secondary colors), Email/SMS/WhatsApp notification toggles
- **Client Portal**: `/portal/*` — a customer's own view of their Shipments, Invoices, Quotations, Documents, and Messages; invited from a Customer's contact record by staff
- **Platform admin**: `/platform/*` — tenant management (list/suspend/activate), subscription plans (CRUD), platform metrics, platform-wide audit log, error log

## Adding another ERP module

Every module follows the same recipe — reuse it rather than reinventing per
module: migration → model (`BelongsToTenant`, `HasFactory`) → enum(s) → Form Requests
→ API Resource → Observer (audit log via `AuditLogger`, plus reference-number
generation on `created()` if the entity needs one, plus
`NotificationService::notifyModuleUsers()` if the module should fan out
email/SMS/WhatsApp notifications) → Controller (thin, resource methods) → routes
(`permission:<module>.<entity>.<view|manage>` middleware) → RBAC catalog entry in
`config/rbac.php` + `php artisan rbac:sync` → factory → Feature test → frontend
types → API endpoint file → page (following the i18n/toast/ConfirmDialog
conventions below) → flip `navConfig.ts` `enabled: true` → register the route in
`App.tsx` → i18n entries in **both** `en` and `sw` locale files, registered in
`frontend/src/i18n/index.ts`.

See any of `app/Http/Controllers/Api/V1/Clearing`, `Freight`, `Containers`,
`Warehouse`, or `Fleet` for the simplest reference implementation; `Accounting` for a
more involved example (multi-entity, a transactional Service class, and a
business-rule invariant enforced in the FormRequest); `Workflow` for a generic engine
retrofitted onto an existing module with a legacy fallback; or `Portal` for the
customer-facing variant of the same recipe (manual `customer_id` filtering, no
implicit route-model binding).

**Frontend UI conventions** (mandatory for new pages): `useTranslation('<module>')`
as `t` + `useTranslation('common')` as `tc`; Zod schemas built as `buildSchema(t)` for
translated validation messages; `ConfirmDialog` instead of native `confirm()`;
`useToast().showToast(t('toast.x'))` on every mutation success; `TableContainer`
wrapping every table; a disabled placeholder `<MenuItem>` as the first child of any
`<TextField select>` that depends on async-loaded options, to avoid an MUI
uncontrolled-to-controlled console warning before the data arrives.
