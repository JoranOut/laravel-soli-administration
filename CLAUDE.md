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
User (1) ←→ (0..1) Relatie
```

- `User.relatie` → hasOne Relatie
- `Relatie.user` → belongsTo User (nullable `user_id`)
- A Relatie can exist without a User (contacts who don't log in)
- `nullOnDelete()` cascade: deleting a User nullifies `relatie.user_id`

### Database Tables

All custom tables use the `soli_` prefix (including Spatie permission tables, configured in `config/permission.php`).

| Table | Model | Purpose |
|-------|-------|---------|
| `soli_relaties` | Relatie | Contact/member records (soft deletes) |
| `soli_relatie_types` | RelatieType | Member types (lid, donateur, docent, dirigent, bestuur, contactpersoon, vrijwilliger) |
| `soli_relatie_relatie_type` | — | M:N pivot with `van`/`tot` dates |
| `soli_adressen` | Adres | Addresses (woon/post/werk) |
| `soli_emails` | Email | Email addresses (prive/werk) |
| `soli_telefoons` | Telefoon | Phone numbers (vast/mobiel/werk) |
| `soli_giro_gegevens` | GiroGegeven | Bank details (IBAN, BIC, machtiging) |
| `soli_relatie_sinds` | RelatieSinds | Membership periods |
| `soli_onderdelen` | Onderdeel | Groups/orchestras/committees (soft deletes) |
| `soli_relatie_onderdeel` | — | M:N pivot with `functie`, `van`/`tot` |
| `soli_relatie_instrument` | RelatieInstrument | Instrument specialties per group |
| `soli_instrumenten` | Instrument | Association instruments (soft deletes) |
| `soli_instrument_bespelers` | InstrumentBespeler | Instrument usage history |
| `soli_instrument_bijzonderheden` | InstrumentBijzonderheid | Instrument notes |
| `soli_instrument_reparaties` | InstrumentReparatie | Repair history |
| `soli_tariefgroepen` | Tariefgroep | Fee categories (Jeugd, Volwassen, Senior, Donateur) |
| `soli_soort_contributies` | SoortContributie | Fee types (Lidmaatschap, Lesgeld, Instrument huur) |
| `soli_contributies` | Contributie | Fee rates per category/type/year |
| `soli_te_betalen_contributies` | TeBetakenContributie | Member fee balances (open/betaald/kwijtgescholden) |
| `soli_betalingen` | Betaling | Fee payments |
| `soli_opleidingen` | Opleiding | Education records |
| `soli_uniformen` | Uniform | Uniform assignments |
| `soli_insignes` | Insigne | Awards/badges |
| `soli_andere_verenigingen` | AndereVereniging | External memberships |
| `soli_roles` | — | Spatie roles |
| `soli_permissions` | — | Spatie permissions |

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

Trait used by Adres, Email, Telefoon, GiroGegeven, InstrumentBespeler, Uniform, AndereVereniging:
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

### Seeders

| Seeder | Purpose |
|--------|---------|
| DatabaseSeeder | Orchestrates all seeders, creates admin + member users |
| RolesAndPermissionsSeeder | 20 permissions (5 resources x 4 actions), 3 roles |
| RelatieTypeSeeder | 7 types: donateur, lid, docent, dirigent, bestuur, contactpersoon, vrijwilliger |
| OnderdeelSeeder | 19 onderdelen across orchestra, training, ensemble, committee types |
| TariefgroepSeeder | 4 groups: Jeugd, Volwassen, Senior, Donateur |
| SoortContributieSeeder | 3 types: Lidmaatschap, Lesgeld, Instrument huur |
| SampleDataSeeder | 35 active members, 10 donateurs, 3 teachers, instruments, contributions |

---

## Authorization Architecture

### Roles & Permissions (Spatie Laravel Permission)

**Resources:** `relaties`, `onderdelen`, `instrumenten`, `financieel`, `users`
**Actions:** `view`, `create`, `edit`, `delete`
**Permission format:** `{resource}.{action}` (e.g. `relaties.view`)

| Role     | Permissions |
|----------|-------------|
| `admin`  | All 20 permissions |
| `bestuur`| Full CRUD on relaties, onderdelen, instrumenten + financieel.view |
| `member` | relaties.view only |

Seeded in `database/seeders/RolesAndPermissionsSeeder.php`.

### Middleware

Registered in `bootstrap/app.php`:

```php
'role'               => RoleMiddleware::class
'permission'         => PermissionMiddleware::class
'role_or_permission' => RoleOrPermissionMiddleware::class
```

Custom middleware:
- `SetLocale` — resolves locale from user → session → app default (nl/en)
- `HandleInertiaRequests` — shares auth, permissions, roles, locale, translations, sidebar state
- `HandleAppearance` — shares appearance cookie for theme (light/dark/system)

### Backend: Checking Permissions

```php
// In controllers — authorize via policy
$this->authorize('view', $user);

// Direct permission check
$user->can('relaties.edit');
$user->hasRole('admin');

// In routes via middleware
Route::middleware(['permission:relaties.view'])->get('/relaties', ...);
```

### Frontend: Sharing Permissions via Inertia

`HandleInertiaRequests` shares on every request:

```php
'auth' => [
    'user' => $request->user(),
    'permissions' => $request->user()?->getAllPermissions()->pluck('name')->toArray() ?? [],
    'roles' => $request->user()?->getRoleNames()->toArray() ?? [],
]
```

### Frontend: usePermissions Hook

Located at `resources/js/hooks/use-permissions.ts`:

```tsx
const { can, canAny, canAll, hasRole, hasAnyRole } = usePermissions();

// Guard UI elements
{can('financieel.view') && <FinancialSection />}
{hasRole('admin') && <AdminActions />}
```

### Frontend: TypeScript Types

Defined in `resources/js/types/auth.ts`:

```typescript
type PermissionResource = 'relaties' | 'onderdelen' | 'instrumenten' | 'financieel' | 'users';
type PermissionAction = 'view' | 'create' | 'edit' | 'delete';
type Permission = `${PermissionResource}.${PermissionAction}`;
type Role = 'admin' | 'bestuur' | 'member';
```

When adding new resources or roles, update these types to keep frontend type-safe.

### Guarding Pages vs. Guarding Fields

Two layers of authorization must always be applied together:

1. **Page-level** — middleware on routes (e.g. `role:admin`, `permission:relaties.view`)
2. **Field-level** — controller filters data before sending to Inertia; frontend hides UI with `can()`

**Important:** Never rely on frontend-only guards. Data in Inertia page props is visible in browser devtools. Always filter sensitive fields in the controller.

### Account Management

- Users **cannot** delete their own accounts (self-service deletion is disabled)
- Account deletion is admin-only via the "Account" tab on the relatie detail page
- Setting a relatie to inactive automatically deletes the linked user account

---

## Routes

### Main Routes (`routes/web.php`)

| Method | URI | Controller | Middleware |
|--------|-----|------------|------------|
| GET | `/` | Redirect to dashboard/login | — |
| GET | `/dashboard` | DashboardController@index | auth, verified |
| POST | `/locale/{locale}` | Store locale in session + user | — |

### Settings Routes (`routes/settings.php`)

| Method | URI | Controller | Middleware |
|--------|-----|------------|------------|
| GET | `/settings` | Redirect to /settings/profile | auth |
| GET | `/settings/profile` | ProfileController@edit | auth |
| PATCH | `/settings/profile` | ProfileController@update | auth |
| GET | `/settings/password` | PasswordController@edit | auth, verified |
| PUT | `/settings/password` | PasswordController@update | auth, verified, throttle:6,1 |
| GET | `/settings/appearance` | Inertia render | auth, verified |
| GET | `/settings/two-factor` | TwoFactorAuthenticationController@show | auth, verified |

### Admin Routes (`routes/admin.php`)

**Admin-only (role:admin):**

| Method | URI | Controller | Name |
|--------|-----|------------|------|
| GET | `/admin/roles` | RolePermissionController@index | admin.roles.index |
| PUT | `/admin/roles/{role}` | RolePermissionController@update | admin.roles.update |
| GET | `/admin/users` | UserRoleController@index | admin.users.index |
| PUT | `/admin/users/{user}` | UserRoleController@update | admin.users.update |
| GET | `/admin/koppelingen` | UserRelatieLinkController@index | admin.koppelingen.index |
| POST | `/admin/koppelingen` | UserRelatieLinkController@store | admin.koppelingen.store |
| DELETE | `/admin/koppelingen/{relatie}` | UserRelatieLinkController@destroy | admin.koppelingen.destroy |
| DELETE | `/admin/relaties/{relatie}/account` | RelatieController@destroyAccount | admin.relaties.account.destroy |

**Relaties (permission:relaties.view, sub-permissions on mutations):**

| Method | URI | Extra Middleware | Name |
|--------|-----|-----------------|------|
| GET | `/admin/relaties` | — | admin.relaties.index |
| GET | `/admin/relaties/create` | relaties.create | admin.relaties.create |
| POST | `/admin/relaties` | relaties.create | admin.relaties.store |
| GET | `/admin/relaties/{relatie}` | — | admin.relaties.show |
| PUT | `/admin/relaties/{relatie}` | relaties.edit | admin.relaties.update |
| DELETE | `/admin/relaties/{relatie}` | relaties.delete | admin.relaties.destroy |

Sub-resources (all require `relaties.edit`): adressen, emails, telefoons, giro-gegevens, types, lidmaatschap, onderdelen, opleidingen — each with POST/PUT/DELETE.

**Onderdelen (permission:onderdelen.view):** CRUD at `/admin/onderdelen`

**Instrumenten (permission:instrumenten.view):** CRUD at `/admin/instrumenten` + sub-resources for bespelers and reparaties

**Financieel (permission:financieel.view):** tariefgroepen, contributies, betalingen at `/admin/financieel/*`

---

## Controllers

### Admin Controllers (`app/Http/Controllers/Admin/`)

| Controller | Purpose |
|------------|---------|
| RelatieController | CRUD relaties, `destroyAccount`, auto-delete user on inactivation |
| RelatieContactController | CRUD adressen, emails, telefoons, giro gegevens |
| RelatieTypeController | Attach/update/detach relatie types (pivot) |
| RelatieLidmaatschapController | CRUD lidmaatschap periods + onderdeel pivot |
| RelatieOpleidingController | CRUD opleiding records |
| OnderdeelController | CRUD onderdelen (groups/orchestras) |
| InstrumentController | CRUD instrumenten |
| InstrumentBespelerController | Assign/remove bespelers, manages instrument status |
| InstrumentReparatieController | CRUD reparaties, manages instrument status |
| TariefgroepController | CRUD tariefgroepen |
| ContributieController | CRUD contributies (fee rates) |
| BetalingController | Record payments, auto-update contribution status |
| RolePermissionController | Permission matrix management |
| UserRoleController | User-role assignment |
| UserRelatieLinkController | Link/unlink users to relaties |

### Other Controllers

| Controller | Purpose |
|------------|---------|
| DashboardController | Statistics dashboard (admin/bestuur) or member relatie view |
| ProfileController | User profile settings (name, email) |
| PasswordController | Password change |
| TwoFactorAuthenticationController | 2FA settings |

### Form Requests

| Request | Authorization | Key Rules |
|---------|--------------|-----------|
| StoreRelatieRequest | relaties.create | All relatie fields + nested sub-resources; first email must be unique in users table |
| UpdateRelatieRequest | relaties.edit | Base relatie fields (voornaam, achternaam, geslacht, etc.) |
| ProfileUpdateRequest | — | name, email (unique per user) |
| ProfileDeleteRequest | — | current password validation |
| PasswordUpdateRequest | — | current_password + new password with confirmation |

---

## Frontend Architecture

### Inertia Setup

- **`resources/js/app.tsx`** — Client bootstrap with Vite dynamic imports, title template `{page} - Soli Administratie`
- **`resources/js/ssr.tsx`** — Server-side rendering setup
- **Shared props** (every page): `auth`, `locale`, `translations`, `sidebarOpen`, `sidebarRelatieTypes`

### Layout Hierarchy

```
AppLayout (app-layout.tsx)
├── AppSidebarLayout — Main shell with sidebar + header + content
│   ├── Admin pages use AppLayout directly
│   ├── AdminLayout — Left nav sidebar for admin system pages (roles, users, links)
│   ├── FinancieelLayout — Tabs for financial pages
│   └── SettingsLayout — Left nav sidebar for settings pages
└── AuthLayout (auth-layout.tsx)
    └── AuthSimpleLayout — Centered form for login/register/reset
```

### Pages

#### Auth Pages (`pages/auth/`)
login, register, forgot-password, reset-password, verify-email, confirm-password, two-factor-challenge

#### Dashboard (`pages/dashboard.tsx`)
- Admin/bestuur: stat cards (active members, donors, instruments, repairs) + link alerts
- Member: redirects to own relatie show page

#### Settings Pages (`pages/settings/`)
profile, password, appearance, two-factor

#### Admin Pages (`pages/admin/`)

| Page | Description |
|------|-------------|
| `relaties/index.tsx` | Paginated list with search, type filter, active/inactive toggle |
| `relaties/create.tsx` | 5-step wizard for creating relatie + user account |
| `relaties/show.tsx` | Tabbed detail view (8 tabs: overview, types, contact, membership, education, financial, instruments, account) |
| `relaties/not-linked.tsx` | Shown to members without a linked relatie |
| `onderdelen/index.tsx` | List with type filter |
| `onderdelen/show.tsx` | Onderdeel detail with active relaties |
| `instrumenten/index.tsx` | List with search and status filter |
| `instrumenten/show.tsx` | Detail with bespelers, bijzonderheden, reparaties |
| `financieel/tariefgroepen.tsx` | Fee category management |
| `financieel/contributies.tsx` | Fee rates per year/category |
| `financieel/betalingen.tsx` | Payment tracking |
| `users.tsx` | User-role assignment |
| `roles.tsx` | Permission matrix |
| `koppelingen.tsx` | User-relatie linking |

### Relatie Creation Wizard Steps

1. **step-1-personal** — Name, gender, birth date, birthplace, nationality
2. **step-2-contact** — Addresses, emails (required, min 1), phones, bank details
3. **step-3-membership** — Relatie types, membership periods, onderdelen
4. **step-4-education** — Opleiding records
5. **step-5-summary** — Review all data and submit

### Relatie Show Tabs

1. **overview-tab** — Edit personal info (read-only for members)
2. **types-tab** — Manage relatie type assignments with date ranges
3. **contact-tab** — CRUD addresses, emails, phones, bank details
4. **lidmaatschap-tab** — Membership periods + onderdeel assignments
5. **opleiding-tab** — Education records
6. **financieel-tab** — Contribution balances and payments
7. **instrumenten-tab** — Instrument assignments
8. **account-tab** — Admin-only: linked user info + delete user account

### Key Components

| Component | Purpose |
|-----------|---------|
| `app-sidebar.tsx` | Main sidebar navigation (conditional admin/member items) |
| `app-header.tsx` | Top header with breadcrumbs, search, user menu |
| `locale-switcher.tsx` | NL/EN language toggle |
| `heading.tsx` | Page/section heading with description |
| `delete-user.tsx` | Account deletion dialog (dead code — deletion moved to admin) |

### Admin Components (`components/admin/`)

| Component | Purpose |
|-----------|---------|
| `data-table.tsx` | Generic sortable table with column definitions |
| `pagination.tsx` | Page navigation controls |
| `search-input.tsx` | Debounced search input |
| `tab-navigation.tsx` | Tab switcher for detail pages |
| `date-range-display.tsx` | Van/tot date display with "current" badge |
| `wizard-step-indicator.tsx` | Multi-step wizard progress bar |

### Hooks

| Hook | Purpose |
|------|---------|
| `use-permissions.ts` | `can()`, `canAny()`, `canAll()`, `hasRole()`, `hasAnyRole()` |
| `use-translation.ts` | `t(key, replacements?)` — i18n with `:placeholder` support |
| `use-appearance.tsx` | Theme management (light/dark/system) |
| `use-current-url.ts` | URL comparison helpers for navigation |
| `use-mobile.tsx` | Responsive breakpoint detection (<768px) |
| `use-clipboard.ts` | Copy to clipboard |
| `use-initials.tsx` | Extract initials from name |
| `use-two-factor-auth.ts` | 2FA setup flow management |

### Type Definitions (`types/`)

| File | Key Types |
|------|-----------|
| `auth.ts` | User, Permission, PermissionResource, PermissionAction, Role, Auth |
| `admin.ts` | Relatie, RelatieType, Adres, EmailRecord, Telefoon, GiroGegeven, Onderdeel, Instrument, InstrumentBespeler, Tariefgroep, Contributie, Betaling, Opleiding + wizard form types |
| `navigation.ts` | NavItem, BreadcrumbItem |
| `ui.ts` | Layout prop types |
| `global.d.ts` | Inertia shared props interface |
| `index.ts` | Re-exports all |

### UI Component Library (shadcn/ui)

Available components: alert, badge, breadcrumb, button, card, checkbox, dialog, dropdown-menu, icon, input, input-otp, label, navigation-menu, select, separator, sheet, sidebar, skeleton, spinner, toggle, toggle-group, tooltip, placeholder-pattern

---

## Internationalization (i18n)

### How It Works

1. **Translation files** — `lang/en.json` and `lang/nl.json` with identical keys
2. **Backend** — `HandleInertiaRequests` shares `locale` and `translations` object on every request
3. **Frontend** — `useTranslation()` hook provides `t(key, replacements?)` function
4. **Locale switching** — `LocaleSwitcher` component calls `POST /locale/{locale}`; stored in session + user record

### Adding Translations

Add the key to both `lang/en.json` and `lang/nl.json`, then use `t('Key')` in components.

Placeholder support: `t('Hello :name', { name: 'Jan' })` → replaces `:name` with `Jan`.

---

## Testing

### Test Structure

```
tests/
├── Unit/ExampleTest.php
├── Feature/
│   ├── Admin/
│   │   ├── RelatieTest.php              # Relatie CRUD, member access, wizard
│   │   ├── RelatieAccountTest.php       # Account deletion, auto-delete on inactivation
│   │   ├── RelatieContactTest.php       # Adressen, emails, telefoons, giro gegevens
│   │   ├── RelatieLidmaatschapOpleidingTest.php  # Types, lidmaatschap, onderdelen, opleidingen
│   │   ├── OnderdeelTest.php            # Onderdeel CRUD
│   │   ├── InstrumentTest.php           # Instrument CRUD
│   │   ├── InstrumentBespelerTest.php   # Bespeler assignment
│   │   ├── InstrumentReparatieTest.php  # Reparatie CRUD
│   │   ├── FinancieelTest.php           # Tariefgroepen, contributies, betalingen
│   │   ├── RolePermissionTest.php       # Permission matrix
│   │   ├── UserRoleTest.php             # User role assignment
│   │   └── UserRelatieLinkTest.php      # User-relatie linking
│   ├── Auth/
│   │   ├── AuthenticationTest.php       # Login, 2FA redirect, rate limiting
│   │   ├── RegistrationTest.php         # Registration disabled
│   │   ├── PasswordResetTest.php        # Password reset flow
│   │   ├── EmailVerificationTest.php    # Email verification
│   │   ├── PasswordConfirmationTest.php # Password confirmation
│   │   ├── TwoFactorChallengeTest.php   # 2FA challenge
│   │   └── VerificationNotificationTest.php
│   ├── Authorization/
│   │   ├── RolesAndPermissionsTest.php  # Seeder correctness, role permissions
│   │   └── InertiaPermissionSharingTest.php  # Frontend sharing
│   ├── Settings/
│   │   ├── ProfileUpdateTest.php        # Profile name/email update
│   │   ├── PasswordUpdateTest.php       # Password change
│   │   └── TwoFactorAuthenticationTest.php
│   ├── DashboardTest.php               # Dashboard rendering per role
│   └── ExampleTest.php
└── Pest.php                             # Pest configuration
```

### Test Conventions

- Uses Pest v4 syntax (`test()`, `expect()`)
- `beforeEach` seeds `RolesAndPermissionsSeeder` + `$this->withoutVite()` where needed
- Every protected route has at minimum 3 tests: authorized (200), unauthorized (403), guest (302)
- Admin tests create users via factory and assign roles: `User::factory()->create()->assignRole('admin')`

### Running Tests

```bash
sail artisan test                          # All tests
sail artisan test --filter=RelatieTest     # Specific test class
sail artisan test --filter="admin can"     # Matching test names
```

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

### Deactivating a Relatie

When a relatie's `actief` field is set to `false`:
1. `RelatieController@update` detects the change from active to inactive
2. If a linked user exists, it is automatically deleted
3. The relatie's `user_id` is set to null
4. The relatie record itself is preserved

### Deleting a User Account (Admin)

1. Admin navigates to relatie detail → Account tab
2. Clicks "Delete user account" → confirmation dialog
3. `RelatieController@destroyAccount` deletes the User and nullifies `relatie.user_id`
4. The relatie record is preserved

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

### Dashboard Behavior

- **Admin/bestuur:** Statistics dashboard with member counts, instrument stats, and admin alerts (unlinked users/relaties)
- **Member with linked relatie:** Directly renders their own relatie show page
- **Member without linked relatie:** Shows "not linked" page

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

### Layout Nesting

Pages use nested layouts: `AppLayout` > optional sub-layout (AdminLayout/SettingsLayout/FinancieelLayout) > Page content.

### Testing Authorization

Every protected route needs three test cases minimum:
1. Authorized user can access (200)
2. Unauthorized user gets forbidden (403)
3. Guest gets redirected to login (302)

For ownership-based access, add:
4. User can access own resource (200)
5. User cannot access other's resource without permission (403)

---

## Project Structure

```
app/
├── Concerns/
│   ├── HasDateRange.php               # Trait: actueel scope + is_actueel accessor
│   ├── PasswordValidationRules.php    # Password validation rules
│   └── ProfileValidationRules.php     # Profile validation rules
├── Http/
│   ├── Controllers/
│   │   ├── Admin/
│   │   │   ├── BetalingController.php
│   │   │   ├── ContributieController.php
│   │   │   ├── InstrumentBespelerController.php
│   │   │   ├── InstrumentController.php
│   │   │   ├── InstrumentReparatieController.php
│   │   │   ├── OnderdeelController.php
│   │   │   ├── RelatieContactController.php
│   │   │   ├── RelatieController.php
│   │   │   ├── RelatieLidmaatschapController.php
│   │   │   ├── RelatieOpleidingController.php
│   │   │   ├── RelatieTypeController.php
│   │   │   ├── RolePermissionController.php
│   │   │   ├── TariefgroepController.php
│   │   │   ├── UserRelatieLinkController.php
│   │   │   └── UserRoleController.php
│   │   ├── DashboardController.php
│   │   └── Settings/
│   │       ├── PasswordController.php
│   │       ├── ProfileController.php
│   │       └── TwoFactorAuthenticationController.php
│   ├── Middleware/
│   │   ├── HandleAppearance.php       # Theme cookie sharing
│   │   ├── HandleInertiaRequests.php  # Shares auth, translations, locale
│   │   └── SetLocale.php             # Resolves user/session locale
│   ├── Requests/
│   │   ├── StoreRelatieRequest.php
│   │   ├── UpdateRelatieRequest.php
│   │   └── Settings/
│   └── Responses/
│       └── LoginResponse.php          # Custom Fortify login response
├── Models/
│   ├── User.php                       # HasRoles trait, relatie() hasOne
│   ├── Relatie.php                    # Central model, soft deletes
│   ├── Adres.php, Email.php, Telefoon.php, GiroGegeven.php
│   ├── RelatieType.php, RelatieSinds.php, RelatieInstrument.php
│   ├── Onderdeel.php                  # Soft deletes
│   ├── Instrument.php                 # Soft deletes
│   ├── InstrumentBespeler.php, InstrumentBijzonderheid.php, InstrumentReparatie.php
│   ├── Tariefgroep.php, SoortContributie.php, Contributie.php
│   ├── TeBetakenContributie.php, Betaling.php
│   ├── Opleiding.php, Uniform.php, Insigne.php, AndereVereniging.php
│   └── ...
└── Providers/
    ├── AppServiceProvider.php         # CarbonImmutable, password rules, LoginResponse
    └── FortifyServiceProvider.php     # Auth views via Inertia, rate limiting

resources/js/
├── app.tsx                            # Inertia client bootstrap
├── ssr.tsx                            # SSR bootstrap
├── components/
│   ├── admin/                         # data-table, pagination, search-input, tab-navigation, etc.
│   ├── ui/                            # shadcn/ui components
│   ├── app-sidebar.tsx                # Main sidebar navigation
│   ├── locale-switcher.tsx            # NL/EN toggle
│   └── ...
├── hooks/
│   ├── use-permissions.ts
│   ├── use-translation.ts
│   └── ...
├── layouts/
│   ├── app-layout.tsx
│   ├── auth-layout.tsx
│   ├── admin/layout.tsx, financieel-layout.tsx
│   └── settings/layout.tsx
├── pages/
│   ├── auth/                          # Login, register, reset, verify, 2FA
│   ├── dashboard.tsx
│   ├── settings/                      # Profile, password, appearance, two-factor
│   ├── admin/
│   │   ├── relaties/                  # index, create, show + tabs/ + wizard/
│   │   ├── onderdelen/                # index, show
│   │   ├── instrumenten/             # index, show
│   │   ├── financieel/               # tariefgroepen, contributies, betalingen
│   │   ├── users.tsx, roles.tsx, koppelingen.tsx
│   │   └── ...
│   └── welcome.tsx
└── types/
    ├── auth.ts, admin.ts, navigation.ts, ui.ts, global.d.ts
    └── index.ts

routes/
├── web.php                            # Dashboard, locale switch
├── admin.php                          # All admin routes
└── settings.php                       # Settings routes

lang/
├── en.json                            # English translations
└── nl.json                            # Dutch translations

tests/Feature/
├── Admin/                             # All admin feature tests
├── Auth/                              # Authentication tests
├── Authorization/                     # RBAC tests
├── Settings/                          # Settings tests
└── DashboardTest.php
```

## Configuration

### Fortify (`config/fortify.php`)
- Registration: **disabled**
- Password reset: enabled
- Email verification: enabled
- Two-factor authentication: enabled (with confirmation)
- Username field: `email`
- Home: `/dashboard`

### Password Requirements
- Production: min 12 chars, mixed case, letters, numbers, symbols, uncompromised
- Development: no requirements

### Spatie Permission (`config/permission.php`)
- All tables use `soli_` prefix
- Cache: 24 hours
