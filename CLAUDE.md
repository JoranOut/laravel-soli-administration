# Laravel Soli Administration

Admin panel for Muziekvereniging Soli at `admin.soli.nl`.

## Stack

- **Backend:** Laravel 12, Fortify (auth), Spatie Laravel Permission (RBAC)
- **Frontend:** React 19, TypeScript, Inertia.js v2, Tailwind CSS v4, shadcn/ui (Radix UI)
- **Testing:** Pest v4, PHPUnit
- **Dev environment:** Laravel Sail (Docker)

## Commands

```bash
sail up -d                          # Start dev environment
sail artisan migrate:fresh --seed   # Reset DB with seed data
sail artisan test                   # Run all tests
sail artisan test --filter=ClassName # Run specific test class
npm run dev                         # Vite dev server
npm run build                       # Production build
```

## Seed Users

| Email                            | Role                | Password |
|----------------------------------|---------------------|----------|
| `admin@example.com`              | admin               | password |
| `ledenadministratie@example.com` | ledenadministratie  | password |
| `member@example.com`             | member              | password |

---

## Data Model

### Central Concept: Relatie

The **Relatie** (relation/contact) is the central model representing a person in the system. It uses soft deletes and links to a User for authentication. All domain tables use the `soli_` prefix.

```
User (1) ←→ (0..N) Relatie
```

- `User.relaties()` → hasMany Relatie
- `Relatie.user` → belongsTo User (nullable `user_id`)
- A User can be linked to multiple relaties (1:N)
- A Relatie can have at most one User
- A Relatie can exist without a User (contacts who don't log in)
- `nullOnDelete()` cascade: deleting a User nullifies `relatie.user_id`

### Relatie Relationships

```
Relatie
├── belongsTo: User
├── belongsToMany: RelatieType (pivot: van, tot)
├── belongsToMany: Onderdeel (pivot: functie, van, tot)
├── hasMany: Adres, Email, Telefoon, GiroGegeven
├── hasMany: RelatieSinds, RelatieInstrument
├── hasMany: InstrumentBespeler, Opleiding
├── hasMany: Uniform, Insigne, AndereVereniging
└── hasMany: TeBetakenContributie
```

### Key Scopes

- `Relatie::actief()` — active relaties only
- `Relatie::search($term)` — search voornaam, achternaam, relatie_nummer
- `Relatie::ofType($name)` — filter by relatie type name (respects `tot` expiration)
- `Onderdeel::actief()` — active onderdelen only
- `Instrument::beschikbaar()` / `inGebruik()` / `inReparatie()` — filter by status

### HasDateRange Concern

Trait used by InstrumentBespeler, Uniform, AndereVereniging.
Implementation for Adres, Email, Telefoon, GiroGegeven is **intentionally deferred** — these models currently don't have `van`/`tot` fields.

Models using HasDateRange:
- `van` (date) — start date
- `tot` (nullable date) — end date (NULL = currently active)
- Scope `actueel()` — where tot is NULL or >= today
- Accessor `is_actueel` — boolean

### Factories

| Factory | States |
|---------|--------|
| UserFactory | `unverified()`, `withTwoFactor()` |
| RelatieFactory | `inactief()` — default: relatie_nummer auto-increments from 1000 |
| OnderdeelFactory | — |
| InstrumentFactory | `inGebruik()`, `inReparatie()` |

---

## Authorization

### Roles & Permissions (Spatie Laravel Permission)

**Resources:** `relaties`, `onderdelen`, `instrumenten`, `financieel`, `users`
**Actions:** `view`, `create`, `edit`, `delete`
**Permission format:** `{resource}.{action}` (e.g. `relaties.view`)

| Role     | Permissions |
|----------|-------------|
| `admin`  | All 20 permissions |
| `ledenadministratie` | Full CRUD on all resources except users.* |
| `bestuur`| Full CRUD on relaties, onderdelen, instrumenten + financieel.view |
| `member` | relaties.view only |

Seeded in `database/seeders/RolesAndPermissionsSeeder.php`. When adding new resources or roles, also update `resources/js/types/auth.ts`.

### Guarding Pages vs. Fields

Two layers of authorization must always be applied together:

1. **Page-level** — middleware on routes (e.g. `role:admin`, `permission:relaties.view`)
2. **Field-level** — controller filters data before sending to Inertia; frontend hides UI with `can()`

**Important:** Never rely on frontend-only guards. Data in Inertia page props is visible in browser devtools. Always filter sensitive fields in the controller.

### Frontend: usePermissions Hook

```tsx
const { can, canAny, canAll, hasRole, hasAnyRole } = usePermissions();

{can('financieel.view') && <FinancialSection />}
```

### Account Management

- Users **cannot** delete their own accounts (self-service deletion is disabled)
- Account management (create, disconnect, delete, password reset) requires `permission:users.edit`
- Available via the "Account" tab on the relatie detail page
- Setting a relatie to inactive automatically deletes the linked user account
- **Login email sync:** editing a relatie email that matches the user's login email also updates the user record and clears `email_verified_at`
- **Login email protection:** the email used for login cannot be deleted from the relatie's email list
- **Disconnect vs delete:** "Disconnect" removes the user link (nullifies `user_id`) but preserves the user account; "Delete" removes the user account entirely

---

## Routes Overview

Routes are defined in `routes/web.php`, `routes/admin.php`, and `routes/settings.php`. Key route groups:

- **Admin-only (role:admin):** roles, users, koppelingen, activity-log
- **Relaties (permission:relaties.view):** CRUD + sub-resources (adressen, emails, telefoons, giro-gegevens, types, lidmaatschap, onderdelen, opleidingen, insignes, diplomas) all requiring `relaties.edit`
- **Account routes (permission:users.edit):** store, update, reset-password, destroy under `/admin/relaties/{relatie}/account`
- **Onderdelen (permission:onderdelen.view):** CRUD at `/admin/onderdelen`
- **Instrumenten (permission:instrumenten.view):** CRUD + bespelers, reparaties
- **Financieel (permission:financieel.view):** tariefgroepen, contributies, betalingen

---

## Frontend Architecture

### Layout Hierarchy

```
AppLayout (app-layout.tsx)
├── AppSidebarLayout — Main shell with sidebar + header + content
│   ├── FinancieelLayout — Tabs for financial pages
│   └── SettingsLayout — Left nav sidebar for settings pages
└── AuthLayout (auth-layout.tsx)
    └── AuthSimpleLayout — Centered form for login/register/reset
```

Admin system pages (roles, users, links, activity log) use the sidebar's collapsible "Authentication" submenu — no separate inner layout.

### Dashboard

- **Admin/bestuur:** Statistics dashboard with member counts, instrument stats, and admin alerts
- **Member with linked relatie(s):** Renders their relatie show page with a dropdown switcher when linked to multiple relaties (supports `?relatie={id}` query param)
- **Member without linked relatie:** Shows "not linked" page

### Relatie Show Tabs

1. **overview-tab** — Edit personal info (read-only for members)
2. **types-tab** — Manage relatie type assignments with date ranges
3. **contact-tab** — CRUD addresses, emails (with login email badge + sync), phones, bank details
4. **lidmaatschap-tab** — Membership periods + onderdeel assignments
5. **opleiding-tab** — Education records
6. **financieel-tab** — Contribution balances and payments
7. **instrumenten-tab** — Instrument assignments
8. **account-tab** — Requires `users.edit`: create/disconnect/delete user account, reset password

---

## Internationalization (i18n)

- Translation files: `lang/en.json` and `lang/nl.json` with identical keys
- Frontend: `useTranslation()` hook provides `t(key, replacements?)` function
- Always add keys to **both** files, then use `t('Key')` in components
- Placeholder support: `t('Hello :name', { name: 'Jan' })` → replaces `:name` with `Jan`

---

## Testing

### Conventions

- Uses Pest v4 syntax (`test()`, `expect()`)
- `beforeEach` seeds `RolesAndPermissionsSeeder` + `$this->withoutVite()` where needed
- Every protected route has at minimum 3 tests: authorized (200), unauthorized (403), guest (302)
- Admin tests create users via factory: `User::factory()->create()->assignRole('admin')`
- Tests live in `tests/Feature/Admin/`, `tests/Feature/Auth/`, `tests/Feature/Authorization/`, `tests/Feature/Settings/`

---

## Key Workflows

### Creating a Relatie (with User Account)

1. Admin fills 5-step wizard (personal → contact → membership → education → summary)
2. `RelatieController@store` runs in a DB transaction:
   - Creates Relatie with base fields
   - Attaches types, adressen, emails, telefoons, giro gegevens, lidmaatschappen, onderdelen, opleidingen
   - Creates a User account using the first email address with a random password
   - Assigns `member` role to the user
   - Links user to relatie via `user_id`

Account linking (`storeAccount`) also ensures the user's email is added to the relatie's `soli_emails` table if not already present.

### Deactivating a Relatie

When a relatie's `actief` field is set to `false`:
1. `RelatieController@update` detects the change from active to inactive
2. If a linked user exists, it is automatically deleted
3. The relatie's `user_id` is set to null
4. The relatie record itself is preserved

### Instrument Assignment

1. Admin assigns a relatie as bespeler via `InstrumentBespelerController@store`
2. Any existing active bespeler is closed (tot = today)
3. Instrument status is set to `in_gebruik`
4. Removing last bespeler sets status back to `beschikbaar`

### Financial Flow

```
Tariefgroep (category) + SoortContributie (type) + Jaar → Contributie (rate)
Contributie + Relatie → TeBetakenContributie (balance: open/betaald/kwijtgescholden)
TeBetakenContributie → Betaling (payments)
```

When a payment fully covers the balance, status auto-updates to `betaald`.

---

## Patterns & Conventions

### Adding a New Admin Page

1. Create controller in `app/Http/Controllers/Admin/`
2. Add routes to `routes/admin.php` (with appropriate middleware)
3. Create page in `resources/js/pages/admin/`
4. Use `AppLayout` wrapper
5. Add nav item to sidebar (`resources/js/components/app-sidebar.tsx`)
6. Add translations to `lang/en.json` and `lang/nl.json`
7. Write tests in `tests/Feature/Admin/`

### Adding a New Resource

1. Create model in `app/Models/` with `soli_` prefixed table
2. Create migration in `database/migrations/`
3. Create controller with `index`, `show`, `store`, `update`, `destroy`
4. Create Form Request classes with authorization checks
5. Add routes with `permission:` middleware
6. Create Inertia pages — filter data in controller based on permissions
7. Add TypeScript types in `resources/js/types/admin.ts`
8. Use `can()` in frontend to conditionally show edit/delete buttons
9. Write tests covering: authorized, unauthorized (403), guest (302)

### Adding a Sub-Resource to Relatie

1. Create model with `relatie_id` foreign key
2. Add relationship to `Relatie` model
3. Add to `RelatieController@show` eager-loading
4. Add to `DashboardController@memberDashboard` eager-loading
5. Create controller with store/update/destroy methods
6. Add routes under `/admin/relaties/{relatie}/...` with `relaties.edit` middleware
7. Create tab component in `resources/js/pages/admin/relaties/tabs/`
8. Register tab in `show.tsx`
9. Add TypeScript type and update `Relatie` type with optional field

### Testing Authorization

Every protected route needs three test cases minimum:
1. Authorized user can access (200)
2. Unauthorized user gets forbidden (403)
3. Guest gets redirected to login (302)

For ownership-based access, add:
4. User can access own resource (200)
5. User cannot access other's resource without permission (403)

### Deploying to Production

The app is hosted on a Hetzner VPS at `admin.soli.nl`. Deployment uses a GitHub Actions workflow (`deploy.yml`) triggered manually:

```bash
gh workflow run deploy.yml --ref main
gh run watch $(gh run list --workflow=deploy.yml --limit 1 --json databaseId --jq '.[0].databaseId')
```

The workflow:
1. Builds PHP (composer) and frontend (npm) dependencies
2. Rsync's the build to the VPS staging directory
3. Symlinks shared `.env` and `storage`
4. Runs `php artisan migrate --force`
5. Swaps `staging` → `current` (previous release kept in `previous/`)
6. Warms config/route/view caches
7. Health check — auto-rollback if site returns HTTP 5xx

**VPS structure:**
```
/var/www/admin.soli.nl/
├── current/    ← live (symlinks .env and storage to shared/)
├── previous/   ← previous release (for manual rollback)
└── shared/     ← .env, storage (persistent across deploys)
```

**SSH access:** `ssh -i ~/.ssh/antagonist-ssh root@178.104.30.49`

### Configuration

- Fortify: registration **disabled**, password reset enabled, 2FA enabled
- Password (production): min 12 chars, mixed case, letters, numbers, symbols, uncompromised
- Spatie Permission: all tables use `soli_` prefix, 24h cache
