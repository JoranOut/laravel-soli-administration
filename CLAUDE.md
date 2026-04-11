# Laravel Soli Administration

Admin panel at `admin.soli.nl`.

## Stack

Laravel 12, React 19, Inertia v2, Tailwind v4, shadcn/ui, Pest v4, Laravel Sail.

## Commands

```bash
sail up -d                            # Start dev
sail artisan migrate:fresh --seed     # Reset DB
sail artisan test                     # Run tests
sail artisan test --filter=ClassName  # Run one test
npm run dev                           # Vite dev server
npm run build                         # Production build
```

## Seed Users

| Email | Role | Password |
|-------|------|----------|
| `admin@example.com` | admin | password |
| `ledenadministratie@example.com` | ledenadministratie | password |
| `member@example.com` | member | password |

---

## Data Model

All tables use `soli_` prefix. Relatie = central model (a person). Soft deletes.

```
User (1) ←→ (0..N) Relatie
```

User can have many relaties. Relatie can exist without User. `nullOnDelete()` on user_id.

### Relatie Relationships

```
Relatie
├── belongsTo: User
├── belongsToMany: RelatieType (pivot: van, tot, functie, email, onderdeel_id)
├── belongsToMany: Onderdeel (pivot: functie, van, tot)
├── hasMany: Adres, Email, Telefoon, GiroGegeven
├── hasMany: RelatieSinds, RelatieInstrument, InstrumentBespeler
├── hasMany: Opleiding, Uniform, Insigne, AndereVereniging
└── hasMany: TeBetakenContributie
```

### Key Scopes

- `Relatie::actief()`, `::search($term)`, `::ofType($name)` (respects `tot`)
- `Onderdeel::actief()`
- `Instrument::beschikbaar()` / `inGebruik()` / `inReparatie()`

### HasDateRange

Trait on InstrumentBespeler, Uniform, AndereVereniging. Gives `van`/`tot`, scope `actueel()`, accessor `is_actueel`. Adres/Email/Telefoon/GiroGegeven intentionally don't have date ranges.

### Factories

| Factory | States |
|---------|--------|
| RelatieFactory | `inactief()` — relatie_nummer auto-increments from 1000 |
| UserFactory | `unverified()`, `withTwoFactor()` |
| InstrumentFactory | `inGebruik()`, `inReparatie()` |

---

## Authorization

Spatie Laravel Permission. Format: `{resource}.{action}` (e.g. `relaties.view`).

| Role | Permissions |
|------|-------------|
| admin | All |
| ledenadministratie | All except users.* |
| bestuur | *.view only |
| member | relaties.view only |

Seeded in `RolesAndPermissionsSeeder`. New roles/resources → also update `resources/js/types/auth.ts`.

**Two auth layers required:** middleware on routes + controller filters data. Never rely on frontend-only guards (Inertia props visible in devtools).

Frontend: `const { can } = usePermissions()`.

### Account Rules

- No self-delete. Account management needs `users.edit`.
- Relatie inactive → linked user auto-deleted.
- Login email edit → syncs to user record, clears `email_verified_at`.
- Login email can't be deleted from relatie emails.

---

## Frontend

### Layouts

```
AppLayout → AppSidebarLayout → FinancieelLayout | SettingsLayout
AuthLayout → AuthSimpleLayout
```

### Dashboard

- Admin/bestuur: stats dashboard
- Member with relatie(s): relatie show page (switcher if multiple)
- Member without relatie: "not linked" page

### Relatie Show Tabs

overview, types, contact, lidmaatschap, opleiding, financieel, instrumenten, account (needs `users.edit`)

---

## i18n

`lang/en.json` + `lang/nl.json`. Always add to both. Frontend: `t('Key')` via `useTranslation()`. Placeholders: `t('Hello :name', { name: 'Jan' })`.

---

## Testing

Pest v4. `beforeEach` seeds permissions + `$this->withoutVite()`. Every route: 200 (authorized), 403 (unauthorized), 302 (guest). Tests in `tests/Feature/`.

---

## Key Workflows

### Creating a Relatie

5-step wizard. `RelatieController@store` in DB transaction: creates relatie, attaches all sub-resources, creates User with first email + random password + member role.

### Deactivating a Relatie

`actief` → false: linked user auto-deleted, `user_id` nullified, relatie preserved.

### Instrument Assignment

New bespeler → previous bespeler closed (tot = today), status = `in_gebruik`. Last bespeler removed → `beschikbaar`.

### Financial Flow

```
Tariefgroep + SoortContributie + Jaar → Contributie (rate)
Contributie + Relatie → TeBetakenContributie (open/betaald/kwijtgescholden)
TeBetakenContributie → Betaling
```

Payment covers balance → auto `betaald`.

### Google Contact Sync

Syncs active relaties as Google Contacts to all Workspace users under `soli.nl`. Service Account with domain-wide delegation, impersonates each user. Kill switch: `GOOGLE_CONTACTS_SYNC_ENABLED=false`.

**Triggers:** Manual via `POST /admin/google-contacts-sync`, automatic `.afterResponse()` from RelatieController, RelatieTypeController, RelatieLidmaatschapController.

**Change detection:** SHA-256 hash of name/emails/active onderdelen/active type assignments. Same hash → skip. Rename a hash key to force full re-sync.

**Contact groups:** Prefixed `"Soli - "`. Onderdeel groups only for `CONTACT_GROUP_TYPES` (orkest/ensemble/opleidingsgroep). Type groups for all relatie types. Per-user, lazily created, auto-cleaned.

**Gotchas:**
- Stats use first user's counts (syncAll) or max (syncRelatie) — same relaties sync to every user, don't multiply
- `clientData` tags contacts with `managed_by=soli_admin` + `relatie_id` — prevents touching personal contacts
- Deleted Google contact with existing sync record → recreated, not skipped

---

## Patterns

### Adding a New Admin Page

Controller → routes in `admin.php` → page in `pages/admin/` → sidebar nav → translations → tests.

### Adding a Sub-Resource to Relatie

Model with `relatie_id` → relationship on Relatie → eager-load in RelatieController@show + DashboardController@memberDashboard → controller → routes under `/admin/relaties/{relatie}/...` → tab component → register in show.tsx → TS types.

### Auth Tests

Every route: 200 (authorized), 403 (unauthorized), 302 (guest). Ownership routes add: 200 (own), 403 (other's).

### Deploy

```bash
gh workflow run deploy.yml --ref main
gh run watch $(gh run list --workflow=deploy.yml --limit 1 --json databaseId --jq '.[0].databaseId')
```

Hetzner VPS. Builds → rsync → symlinks .env/storage → migrate → swap staging→current → cache warm → health check (auto-rollback on 5xx). Previous release kept in `previous/`.

### Config

- Fortify: registration disabled, 2FA enabled
- Passwords (prod): min 12, mixed case, numbers, symbols, uncompromised
- Spatie Permission: `soli_` prefix, 24h cache
