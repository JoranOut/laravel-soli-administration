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
| `member@example.com` | minimal | password |
| `contactpersoon@example.com` | contactpersoon | password |

`contactpersoon` is not assigned by the seeder — that account gets it through an active `contactpersoon` relatie type, so a fresh seed also exercises `DerivedRoleSyncService`. The member account is deliberately linked to a relatie with no mapped type; `Relatie::first()` handed it a `bestuur` type and with it the stats dashboard.

### Bootstrapping this release in production

`permission:beheer.manage` goes through Spatie's `canAny()`, which returns **false** for a permission that does not exist (`PermissionMiddleware.php:37`) — so until the two new permissions exist, every admin gets 403 on the whole `/admin` authentication group, and nobody holds `relaties.view.all`, which clamps all staff to their own relatie. The deploy runs no seeders, so grant one account by hand first, then use the UI for the rest:

```sql
INSERT INTO soli_permissions (name, guard_name, created_at, updated_at)
SELECT 'beheer.manage', 'web', NOW(), NOW()
WHERE NOT EXISTS (SELECT 1 FROM soli_permissions WHERE name = 'beheer.manage' AND guard_name = 'web');

INSERT INTO soli_permissions (name, guard_name, created_at, updated_at)
SELECT 'relaties.view.all', 'web', NOW(), NOW()
WHERE NOT EXISTS (SELECT 1 FROM soli_permissions WHERE name = 'relaties.view.all' AND guard_name = 'web');

INSERT IGNORE INTO soli_model_has_permissions (permission_id, model_type, model_id)
SELECT p.id, 'App\\Models\\User', u.id
FROM soli_permissions p
JOIN users u ON u.email = '<your login>'
WHERE p.name IN ('beheer.manage', 'relaties.view.all') AND p.guard_name = 'web';
```

Then `php artisan permission:cache-reset` — the permission cache holds for 24h and a raw `INSERT` does not clear it. The grant is direct to the user rather than to a role on purpose: it works even if the roles are misconfigured. Verified against a database with both permissions deleted.

From there, `/admin/roles` assigns the permissions to roles and `/admin/relatie-type-rollen` fills the mapping table. **Until the mapping table has rows, no account gets any role** — including every relatie created or SAD-imported in the meantime — so do it in the same sitting, and run `roles:sync-derived --dry-run` before letting the nightly run loose.

**Roles and mappings do not reach production through a deploy.** `deploy.yml` runs `migrate --force` and never `db:seed`, so a new role or mapping needs `db:seed --class=RolesAndPermissionsSeeder --force` on the server (idempotent) plus the mapping set in the UI at `/admin/relatie-type-rollen`. **The `member`→`minimal` rename is expand/contract across two deploys.** This deploy adds `minimal` beside `member` and copies its permissions and every assignment, leaving `member` untouched, because migrations run before the swap and the previous release still calls `hasRole('member')`. Dropping `member` is a separate migration for the *next* deploy, once this one is healthy.

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
| ledenadministratie | All except users.* and beheer.manage |
| bestuur | *.view only, plus relaties.view.all |
| contactpersoon | contact.view + relaties.view (own record) |
| minimal | relaties.view only (own record) |

Besides `{resource}.{action}` there are four standalone permissions: `dashboard.view`, `contact.view`, `relaties.view.all` and `beheer.manage`.

`relaties.view` and `relaties.view.all` are **not** the same thing, and conflating them was a live authorization bug. `relaties.view` means "my own record"; `relaties.view.all` means "everyone's". `RelatieController` clamps to the user's own relatie whenever `relaties.view.all` is missing. `beheer.manage` gates the whole `/admin` authentication group (roles, users, the type→role mapping, links, activity log, OAuth clients, both sync pages) plus the dashboard alerts and job statuses.

Seeded in `RolesAndPermissionsSeeder`. New roles/resources/permissions → also update `resources/js/types/auth.ts`.

**Two auth layers required:** middleware on routes + controller filters data. Never rely on frontend-only guards (Inertia props visible in devtools).

**Check permissions, not roles.** `hasRole` in an authorization decision is a bug waiting for the next role to be added: derived roles now stack (a staff member who is also a lid holds both `admin` and `minimal`), so `hasRole('minimal')` silently locked admins out of the relatie list until this was fixed. Exactly two role checks are legitimate, because they are *about* roles rather than about access: the last-admin guard in `UserRoleController@update`, and `DerivedRoleSyncService::NEVER_MANAGED`.

Frontend: `const { can } = usePermissions()`.

### Roles derived from relatie types

`soli_relatie_type_role_mappings` maps a relatie type to an internal role, so an active `bestuur` type grants the `bestuur` role. **One role per relatie type** (unique on `relatie_type_id`), edited as a dropdown per type at `/admin/relatie-type-rollen`, one `PUT` per type so two admins editing at once cannot overwrite each other's rows. Several types may point to the same role, which is how `lid`, `donateur` and `vrijwilliger` all feed `minimal`. Seeded by `RelatieTypeRoleMappingSeeder`.

**`minimal` (renamed from `member`) is derived, not granted on account creation.** `RelatieController` and `MemberSyncService` no longer call `assignRole`; the role arrives because the relatie holds a mapped type. Two consequences worth knowing: a relatie created through the wizard *without* a type yields an account with no role at all, and in `MemberSyncService` the sync must run **after** the `lid` type is attached — it used to sit inside `ensureUserAccount`, which runs before, and would have revoked the role it just granted. Managed in the UI at `/admin/relatie-type-rollen`; `DerivedRoleSyncService` applies it.

**Every role except `NEVER_MANAGED` (`admin`, `ledenadministratie`) is managed by the sync**, which grants and revokes it purely from the types. Those three are the only roles `/admin/users` hands out, and the sync can neither grant nor revoke them; the filter sits in the service itself, not only in the controller's validation, so a mapping row inserted by a seeder or a manual `INSERT` still cannot take over the escape hatch.

Managed is deliberately *not* "roles some mapping currently points at". That earlier definition meant un-mapping a type stopped the role being managed at the exact moment you wanted it revoked: everyone who had earned it kept it forever, and the users page then showed it as a manual role. `UserRoleController@update` preserves the roles a user **earns**, not every managed role they hold, so a role someone no longer earns disappears on a hand edit too.

**Multiple roles are additive, so there is no priority.** A user gets the union of the roles mapped to every active type across every relatie, and Spatie treats permissions as a union too, so nothing has to win. `ClientRoleResolver` needs its `priority` column only because a WordPress user gets exactly one role. Hand-granted roles stack on top: giving someone `admin` leaves their derived `bestuur` in place, and the union makes them admin in practice.

A managed role cannot be assigned by hand; `UserRoleController@update` refuses it and preserves the derived roles a user already holds, because `syncRoles()` would drop them and the nightly command would silently put them back.

**`roles:sync-derived` (daily) is the correctness-critical part, not the triggers.** A `tot` date passing fires no event, so without the scheduled run an ex-board member keeps their permissions until someone happens to edit their types. Everything else — `RelatieTypeController`, `UserRelatieLinkController`, `RelatieController`, `MemberSyncService` — only makes the change immediate. Run `roles:sync-derived --dry-run` before the first real run: the first sync revokes any hand-granted `bestuur` from users without the type, and revoking a role is not a migration, so a deploy rollback does not bring it back.

This is the internal counterpart to `ClientRoleResolver`, which maps the same relatie types to roles in *external* clients (WordPress, muziekbibliotheek). The two are independent: internal roles never affect what a client gets, except as `ClientRoleResolver`'s fallback when a client has no settings row.

### Account Rules

- **No self-delete, anywhere.** There is no "delete my account" screen; the `delete-user.tsx` component that offered one was removed — it was unreachable and posted to a `ProfileController::destroy` route that never existed. `destroyAccount` also refuses when the account is the caller's own.
- Account management needs `users.edit`; **deleting an account needs `relaties.delete`**, which is what `ledenadministratie` holds and `users.edit` does not imply. The account tab is therefore visible with either permission, and gates the password-reset block on `users.edit` and the delete block on `relaties.delete`.
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
