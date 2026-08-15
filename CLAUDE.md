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

## Migrations

**Default to expand/contract.** Never ship a destructive schema change in the same deploy as the code that needs it.

1. Deploy N — additive only: add the column, backfill, dual-write if needed. The *previous* release still runs against the new schema.
2. Deploy N+1, once N has proven healthy — drop the old column.

This is what makes a failed deploy safe. A rollback rewinds the code but never the database (see [Deploy](#deploy)), so the schema the old release lands on has to be one it can still run against. 63 of the current 69 migrations are already additive; the six that are not are the ones that would have needed splitting.

### Reversibility

`down()` restores **schema, not data**. Re-adding a dropped column gives you a column full of NULLs, which is the dangerous failure mode: the site comes back up looking healthy with the data silently gone. Prefer a loud failure.

| Change | Fallback |
|--------|----------|
| Drops personal data | **No archive table.** The deletion is the deliverable. The pre-deploy backup covers the emergency. |
| Restructures reference/taxonomy data | **Archive table** — dated name, e.g. `soli_instrument_soorten_archive_20260504` |
| Everything else | **Expand/contract** — the overlap window *is* the safety net, no archive needed |

**Archive-table cleanup is a later migration**, not a deploy step and not a cron — so it is reviewed, versioned, and runs through the same tested path as everything else. Do not gate it on the deploy health check: that check is a homepage 200, which proves the release boots, not that the data is right.

**Never archive personal data to make a migration reversible.** Dropping `bsn`, `geslacht`, `geboorteplaats` and `nationaliteit` was data minimisation (GDPR Art. 5(1)(c)). A shadow copy means still holding it — unread by the app, unaudited, and carried in every backup. "We clean it up a few deploys later" does not fix that; it just shortens the period of retaining it without a basis.

**When reversal is genuinely impossible, `throw` rather than writing a `down()` that lies.** See `2026_05_04_100001_restructure_instrument_families`, which deletes and restructures rows across tables and correctly refuses to pretend otherwise.

### down() is unproven

Nothing runs `down()` — not CI, not the deploy. Treat every one as untested until executed. Quick manual check against a scratch database, never your dev DB:

```bash
# expect it to unwind ~60 migrations and stop at restructure_instrument_families, by design
docker compose exec -T -e DB_DATABASE=<scratch> laravel.test php artisan migrate --force
docker compose exec -T -e DB_DATABASE=<scratch> laravel.test php artisan db:seed --force
docker compose exec -T -e DB_DATABASE=<scratch> laravel.test php artisan migrate:reset --force
```

Seed first — an empty table hides `down()` bugs like adding a `NOT NULL` column with no default.

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

### SAD Member Import

`import:sad-members {path}`. Matches by `relatie_nummer`, falls back to exact name. Sub-resources always upserted. Lid type only assigned on create — re-imports preserve manual corrections. SAD has no concept of relatie types; types are an admin-only feature managed manually.

Post-import: caps Drumfanfare at Klein Orkest start, closes onderdelen for ex-members, deactivates empty onderdelen.

`TYPE_MAP` maps SAD instruments to relatie types. `INSTRUMENT_MAP` normalizes instrument names. SAD data is double-encoded UTF-8 — decoded with `mb_convert_encoding` before `json_decode`.

### Google Contact Sync

Syncs active relaties as Google Contacts to all Workspace users under `soli.nl`. Service Account with domain-wide delegation, impersonates each user. Kill switch: `GOOGLE_CONTACTS_SYNC_ENABLED=false`.

**Triggers:** Manual via `POST /admin/google-contacts-sync`, automatic `.afterResponse()` from RelatieController, RelatieTypeController, RelatieLidmaatschapController.

**Change detection:** SHA-256 hash of name/emails/active onderdelen/active type assignments. Same hash → skip. Rename a hash key to force full re-sync.

**Contact groups:** Prefixed `"Soli - "`. Onderdeel groups only for `CONTACT_GROUP_TYPES` (currently `muziekgroep`). Type groups for all relatie types. Per-user, lazily created, auto-cleaned.

**Split contacts:** Google group membership is per contact, not per email, so a type assignment with a functional email (pivot `email` on relatie↔type) gets its own contact — "Peter Jansen (Bestuur)" with only that email, member of only that type's group. The main contact keeps personal emails and all other groups, and is excluded from type groups that have a split. Sync rows are keyed (relatie_id, relatie_type_id, google_user_email); `relatie_type_id = null` is the main contact.

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

Hetzner VPS. Builds → rsync to `staging/` → symlinks .env/storage → **backup DB** → migrate → swap `staging`→`current` → cache warm → health check. Previous release kept in `previous/`, last failed one in `failed/`.

**A failed deploy rewinds the code, not the database.**

| | rewound? |
|---|---|
| Release directory (`current` ← `previous`) | yes, and the rollback is health-checked |
| Migrations already applied | **no** — `migrate --force` never runs `down()` |
| Shared application cache | cleared, repopulates on its own |
| Files written to shared `storage/` | no (logs, uploads) |
| Queue workers | not restarted by deploy *or* rollback |

Migrations run **before** the swap, so a failing migration aborts with the live site untouched. The dangerous case is a migration that *succeeds* followed by a post-swap failure: the schema has moved on and the restored release may not run against it. Six of the 69 migrations drop columns in `up()`, so this is not hypothetical. When that happens the deploy says `ROLLBACK DID NOT RESTORE A WORKING SITE` and prints the command to restore the pre-deploy backup from `shared/storage/backups/` (last 5 kept). Restoring the database is deliberately manual.

Cache warming runs **after** the swap on purpose — `config:cache` bakes absolute `storage_path()` values into `bootstrap/cache/config.php`, so warming in `staging/` would bake a path that dies at the swap.

If a deploy ever dies without rolling back (SIGKILL, runner death), `.deploy-state` is left on the server and **the next deploy refuses to start** rather than overwriting the last known-good release. Inspect, fix, then delete the file.

### Config

- Fortify: registration disabled, 2FA enabled
- Passwords (prod): min 12, mixed case, numbers, symbols, uncompromised
- Spatie Permission: `soli_` prefix, 24h cache
