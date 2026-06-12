# CLAUDE.md

This file provides guidance to Claude Code (claude.ai/code) when working with code in this repository.

## Project Overview

StockPilot is a multi-tenant stock management SaaS (projet académique — ISET Sousse 2025/2026). Each **Organisation** is an isolated tenant; all business data (products, movements, categories…) is scoped to it automatically.

## Starting the Application

```bat
# Double-click or run from repo root:
lancer-stockpilot.bat
```

Or manually:
```bash
# Backend (from backend/)
php -d max_execution_time=0 artisan serve --port=8000

# Frontend (from frontend/)
npm run dev   # → http://localhost:5173
```

DB: `C:\dev\stockpilot.sqlite` — Dev credentials: `admin@test.tn` / `Password123!`

## Common Commands

```bash
# Backend
php artisan migrate:fresh --seed   # reset + seed demo data
php artisan route:list             # list API routes
php artisan cache:clear

# Frontend
npm run build   # production build (runs vue-tsc first)
npm run lint    # eslint --fix

# Tests (from backend/)
./vendor/bin/pest                          # all tests
./vendor/bin/pest tests/Feature/AuthApiTest.php   # single suite
./vendor/bin/pest --filter "can login"            # single test
```

## Architecture

### Backend — Laravel 11 (`backend/`)

**Multi-tenancy** is enforced at model level, not at DB level:
- `BaseModel` registers `TenantScope` as a global Eloquent scope → every query automatically adds `WHERE organisation_id = ?`
- `AuthenticateTenant` middleware (registered as `auth.tenant`) validates the JWT, resolves the organisation, then binds `current_organisation_id` and `current_user` into the Laravel container for the lifetime of the request
- On `creating`, `BaseModel::boot()` auto-injects `organisation_id` from the container — never set it manually in controllers

**DB compatibility** — The app runs on **SQLite** locally and **Oracle** in production:
- `StockService` updates `products.quantite` in PHP after every movement (Oracle uses a DB trigger `trg_update_stock`, but the PHP path runs on both)
- Date truncation must use `DATE()` on SQLite and `TRUNC()` on Oracle — always check `DB::connection()->getDriverName() === 'oracle'` before writing raw date SQL
- `Product` virtual columns (`prix_achat_ttc`, `prix_vente_ttc`, `en_alerte`, `en_rupture`, `statut`) are Oracle DB columns but are computed via `$appends` + accessors on SQLite

**Key services:**
- `StockService` — creates movements inside a transaction, validates stock availability, updates quantity
- `AIService` — wraps OpenAI GPT-4o-mini; results are cached (file cache). Prompts live in `config/ai.php`. All methods fail silently and return empty arrays if OpenAI is down

**CA (Chiffre d'affaires)** is calculated as `SUM(stock_movements.quantite × products.prix_vente_ht)` on rows where `type_mouvement = 'sortie'`. When writing JOIN queries, always qualify column names with the table name because `TenantScope` adds `WHERE stock_movements.organisation_id = ?` which can conflict with ambiguous column names.

### Frontend — Vue 3 + Vite (`frontend/`)

- **`src/services/api.ts`** — single Axios instance; JWT attached via request interceptor; 401 globally redirects to `/login`
- **`src/stores/auth.ts`** — Pinia store; exposes `initPromise` (a Promise that resolves after the initial `/auth/me` call). The router `beforeEach` guard **must** `await auth.initPromise` before checking `isAuthenticated` to avoid race conditions on page reload
- **`src/router/index.ts`** — lazy-loaded routes; `requiresAuth` meta triggers auth guard; after login the guard redirects to `/onboarding` if `organisation.onboarding_complete === false`
- **`AppLayout.vue`** wraps all authenticated views (sidebar + slot)

### AI features (plan-gated)

`Organisation.hasAIEnabled()` checks `plan.ia_activee`. Controllers call this before any `AIService` method and return 403 if false. The dashboard only renders the `kpis_ia` section when `auth.hasAI` is true in the frontend store.

## Testing

Tests use Pest with `RefreshDatabase`. The base `TestCase` provides:
- `createOrg()` — creates a Plan + Organisation pair
- `actingAsOrg(org)` — creates a user, binds tenant context, calls `actingAs()`
- `withJwt(user)` — issues a real JWT token for routes that validate JWT explicitly

phpunit.xml needs configuring to point at a test SQLite DB (not the dev one).