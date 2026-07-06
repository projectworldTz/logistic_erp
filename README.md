# Logistics ERP

A multi-tenant SaaS ERP platform for Clearing & Forwarding and Logistics companies.

**Phase 1 (platform spine)** is complete — multi-tenant foundation, authentication,
tenant self-registration, RBAC, and both dashboard shells (Super Admin + Tenant).
**Phase 2 (CRM module)** is also complete — Leads, Customers, and Contacts, with
lead-to-customer conversion. **Phase 3 (operational ERP modules)** is complete —
Quotations, Shipments, Clearing, Freight Forwarding, Containers, Warehouse, Fleet,
Finance, Accounting (chart of accounts + double-entry journal entries), Documents,
and Reports are all built end-to-end (backend + frontend + tests). Tenant dashboard
widgets are wired to live data from these modules. Every ERP module originally
scaffolded in the tenant nav is now live — there are no remaining "coming soon" items.

## Structure

```
logistics-erp/
├── backend/     Laravel 12 REST API (SQLite locally / PostgreSQL in prod, Sanctum auth)
└── frontend/    React 19 + Vite + TypeScript + MUI
```

## Stack

- **Frontend**: React 19, Vite, TypeScript, React Router, TanStack Query, React Hook Form + Zod, MUI, Axios, Zustand
- **Backend**: Laravel 12, PHP 8.2+, Sanctum (token auth), spatie/laravel-permission (team-scoped RBAC), Scramble (OpenAPI docs), Reverb (real-time, scaffolded)

## Multi-tenancy

Single shared database, `tenant_id` column on every tenant-scoped table, enforced via
a global Eloquent scope (`TenantScope`) + `ResolveTenant` middleware. Platform admin
routes skip tenant resolution entirely, giving Super Admins unscoped, cross-tenant
visibility. See `backend/app/Support/Tenancy/TenantContext.php` and
`backend/app/Models/Scopes/TenantScope.php`.

Local dev uses SQLite + the `database` cache/queue driver + local disk storage as
stand-ins for PostgreSQL/Redis/S3 — all code is written against Laravel's storage,
cache, and queue facades, so switching `.env` to real Postgres/Redis/S3 in production
requires no code changes.

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
port the backend actually bound to.

## Testing

```
cd backend
php artisan test
```

Covers tenant registration atomicity (all-or-nothing provisioning across tenant,
company, branch, owner user, RBAC roles, subscription, billing profile, dashboard
settings, audit log), login/role-based redirect, platform-admin access control,
cross-tenant data isolation, and full CRUD + permission-enforcement + tenant-isolation
coverage for every Phase 3 module (Quotations, Shipments, Clearing, Freight,
Containers, Warehouse, Fleet, Finance, Accounting — including double-entry balance
validation and the draft→posted→voided journal entry lifecycle — Documents, and
Reports).

## Key flows

- **Public site**: `/` — marketing landing page (hero, features, live pricing, industries, testimonials, about, FAQ, contact/demo forms)
- **Tenant self-registration**: `/register` — 4-step wizard (plan → owner account → company details → review) that atomically provisions a new tenant and logs the owner straight into their dashboard
- **Login**: `/login` — redirects to `/platform` (Super Admin) or `/app/dashboard` (tenant user) based on role
- **Tenant dashboard**: `/app/*` — company dashboard (live widgets), users, branches, audit log, company settings
- **CRM**: `/app/crm` — Leads and Customers (tabbed), lead-to-customer conversion (auto-creates a primary Contact), Customer detail page with a Contacts sub-table (add/edit/delete)
- **Quotations**: `/app/quotations` — pre-sale pricing, auto-numbered (`QT-YYYY-NNNNN`)
- **Shipments**: `/app/shipments` — the execution-side master record, auto-numbered (`SHP-YYYY-NNNNN`); optionally links to an accepted Quotation, a Clearing file, and a Freight booking
- **Clearing**: `/app/clearing` — customs clearing files, auto-numbered (`CLR-YYYY-NNNNN`), status workflow
- **Freight Forwarding**: `/app/freight` — carrier bookings, auto-numbered (`FWD-YYYY-NNNNN`)
- **Containers**: `/app/containers` — container tracking, optionally linked to a clearing file or freight booking
- **Warehouse**: `/app/warehouse` — goods-receipt tracking, auto-numbered (`WH-YYYY-NNNNN`)
- **Fleet**: `/app/fleet` — company vehicles (not customer-scoped)
- **Finance**: `/app/finance` — customer invoices, auto-numbered (`INV-YYYY-NNNNN`)
- **Accounting**: `/app/accounting` — chart of accounts + double-entry journal entries (debit/credit balance enforced server-side); entries are editable while `draft`, immutable once `posted`; `post`/`void` are a separate permission from `manage` for segregation of duties
- **Documents**: `/app/documents` — file uploads (10MB max, PDF/image/Office formats) stored on the `public` disk, categorized, downloadable
- **Reports**: `/app/reports` — read-only cross-module rollup (totals + status breakdowns) over every operational module
- **Platform admin**: `/platform/*` — tenant management (list/suspend/activate), subscription plans (CRUD), platform metrics, platform-wide audit log

## Adding another ERP module

Every Phase 3 module follows the same recipe — reuse it rather than reinventing per
module: migration → model (`BelongsToTenant`, `HasFactory`) → enum(s) → Form Requests
→ API Resource → Observer (audit log via `AuditLogger`, plus reference-number
generation on `created()` if the entity needs one) → Controller (thin, resource
methods) → routes (`permission:<module>.<entity>.<view|manage>` middleware) → RBAC
catalog entry in `config/rbac.php` + `php artisan rbac:sync` → factory → Feature test
→ frontend types → API endpoint file → page → flip `navConfig.ts` `enabled: true` →
register the route in `App.tsx`. See any of `app/Http/Controllers/Api/V1/Clearing`,
`Freight`, `Containers`, `Warehouse`, or `Fleet` for the simplest reference
implementation, or `Accounting` for a more involved example (multi-entity, a
transactional Service class, and a business-rule invariant enforced in the
FormRequest).
