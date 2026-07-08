# Feature implementation plan

Implementation plan for four related features in Apleqz. Written against the current stack (Laravel 13, Inertia, Vue 3, PostgreSQL, Filament admin).

---

## Goals

| Feature | Summary |
|---------|---------|
| **Multiple document uploads** | Upload several PDF/DOCX files per application, including during create |
| **Application seasons (waves)** | Group applications into user-defined waves; migrate existing data; require a wave for new users |
| **Email notifications** | Remind users about deadlines, follow-ups, and other application events |
| **Favourite applications** | Star/unstar applications from the index table |

---

## Current baseline (what exists today)

- **Files**: `ApplicationFile` model, single-file upload on **edit only**, gated by `users.application_files_enabled` (admin toggle). PDF/DOCX, 10 MB, via `StoredFileService`.
- **Applications**: Belong to `user` + `area` (required). Timeline via `ApplicationMoment`. No season/wave or favourite field.
- **Index table**: Server-side sort/filter in `FilteredApplicationsQuery`. Columns: position, company, area, applied at, status, actions.
- **Email**: Laravel auth mail only (`MAIL_MAILER=log`). `User` is `Notifiable` but there are no app notifications or scheduled jobs.

---

## Terminology

| Term | Meaning |
|------|---------|
| **Wave** | User-facing label in UI (e.g. “Application wave”, “Season”). Internal model name: `ApplicationWave` (table: `application_waves`). |
| **Season** | Avoid in code/UI copy if “wave” is clearer; use one term consistently in translations (`app.waves.*`). |
| **Favourite** | Boolean (or `favourited_at`) on `applications`; star icon in table. |

---

## Recommended implementation order

Phases are ordered to reduce rework and keep migrations safe.

```
Phase 1 — Waves (schema + migration + gating)
Phase 2 — Favourites (small, independent)
Phase 3 — Multiple file uploads (depends on stable application create flow)
Phase 4 — Email notifications (benefits from waves + moments being stable)
```

---

## Phase 1: Application waves

### 1.1 Data model

**New table: `application_waves`**

| Column | Type | Notes |
|--------|------|-------|
| `id` | UUID | Primary key (match `areas` pattern) |
| `user_id` | FK | Owner |
| `name` | string | e.g. “Summer 2026”, “Q1 job hunt” |
| `starts_at` | date, nullable | Optional wave window |
| `ends_at` | date, nullable | Optional wave window |
| `is_default` | boolean | One per user after migration; hidden from “pick wave” UI if desired |
| `timestamps` | | |

**Change `applications`**

| Column | Type | Notes |
|--------|------|-------|
| `application_wave_id` | UUID FK, nullable → **not null** after backfill | Required for new apps once feature ships |

Indexes: `(user_id, application_wave_id)`, `(user_id, is_default)` on waves.

**Model: `ApplicationWave`**

- `user()`, `applications()`
- Scope: `forUser(User $user)`
- Validation: `ends_at >= starts_at` when both set

**Update `Application`**

- `wave()` / `applicationWave()` relation
- Add `application_wave_id` to `$fillable`

**Update `User`**

- `waves(): HasMany`

### 1.2 Migration for existing data

Single migration (or migration + seeder command):

1. Create `application_waves` table.
2. Add nullable `application_wave_id` to `applications`.
3. For **each existing user**:
   - Create one wave, e.g. name from translation key `app.waves.default_name` → “Imported applications” / localized.
   - Set `is_default = true`.
   - `UPDATE applications SET application_wave_id = <wave.id> WHERE user_id = <user.id>`.
4. Alter `application_wave_id` to `NOT NULL` (only after backfill).
5. Add FK constraint.

**Edge cases**

- Users with zero applications still get a default wave (needed for “create wave first” UX for new apps).
- Demo/admin seed data: assign seeded apps to that user’s default wave.

### 1.3 Business rules

| Rule | Behaviour |
|------|-----------|
| New user (post-launch) | Must create at least one wave before creating an application (mirror `EnsureUserHasAreas`). |
| New application | `application_wave_id` required; must belong to authenticated user. |
| Delete wave | Block if it has applications (same pattern as areas), or offer “move applications to…” (v2). |
| Default wave from migration | Can be renamed; `is_default` is informational only. |
| Active wave | Optional UX: remember last-selected wave in session; filter index by current wave. |

### 1.4 Backend

| Area | Changes |
|------|---------|
| `ApplicationWaveController` | CRUD (index, store, update, destroy) — mirror `AreaController` |
| `EnsureUserHasWaves` middleware | Redirect to waves index with flash if user has no waves |
| `ApplicationRequest` | `application_wave_id` required, `exists:application_waves,id` scoped to user |
| `ApplicationController` | Pass `waves` to form; include `wave` in index props |
| `FilteredApplicationsQuery` | Filter `wave_id`; optional sort by wave name |
| `ApplicationStatisticsService` | Optional `by_wave` breakdown on dashboard (follow-up) |
| Routes | `Route::resource('waves', ...)` or `application-waves` |
| Filament | Optional read-only wave count on user resource (low priority) |

### 1.5 Frontend

| Screen | Changes |
|--------|---------|
| **Waves index** | New page (copy `Areas/Index.vue` patterns): list, create, edit, delete, explain permanent vs deletable |
| **Nav** | Link under user dropdown (near Areas) |
| **Applications form** | Wave `<select>` next to area |
| **Applications index** | Wave filter dropdown; optional column “Wave” |
| **Create application gate** | If no waves: CTA “Create a wave first” (like areas) |
| **i18n** | `lang/en/app.php`, `lang/pt/app.php` — `waves.*` keys; seed via `TranslationService` |

### 1.6 Tests

- Migration test: users with apps get one default wave; apps linked.
- Cannot create application without wave.
- Cannot assign another user’s wave.
- Cannot delete wave with applications.
- Index filter by wave.

---

## Phase 2: Favourite applications

### 2.1 Data model

**Change `applications`**

| Column | Type | Notes |
|--------|------|-------|
| `is_favourite` | boolean, default `false` | Or `favourited_at` timestamp if “when starred” matters |

Index: `(user_id, is_favourite)` for “favourites only” filter.

### 2.2 Backend

| Area | Changes |
|------|---------|
| `ApplicationController::toggleFavourite` | `PATCH applications/{application}/favourite` — authorize ownership, flip boolean, return redirect back or JSON for Inertia partial reload |
| `FilteredApplicationsQuery` | Optional filter `favourites=1`; sort: favourites first when `sort=status` (or dedicated `sort=favourite`) |
| `ApplicationRequest` | No change (favourite not on main form) |
| Export | Include favourite column if exporting status/fields (optional) |

**Sort interaction with status priority**

Default list order suggestion:

1. Favourites first (`is_favourite DESC`)
2. Then existing status priority (`ApplicationStatus::listSortOrderSql`)
3. Then `applied_at DESC`

### 2.3 Frontend

| Area | Changes |
|------|---------|
| `Applications/Index.vue` | Star column (first column or before actions); filled vs outline icon |
| Toggle | `router.patch(route('applications.favourite', id), {}, { preserveScroll: true })` |
| Filter | Checkbox or toggle “Favourites only” |
| Accessibility | `aria-pressed`, label from `app.applications.favourite` |

### 2.4 Tests

- Toggle favourite on own application.
- Cannot toggle another user’s application.
- Index respects favourites-only filter and favourites-first sort.

---

## Phase 3: Multiple document uploads (including on create)

### 3.1 Current limitations

- Upload only on **edit** (`Form.vue` shows “Save the application first…”).
- `FileManager.vue`: single `<input type="file">`, one POST per file.
- `ApplicationFileController::store` accepts one file per request.
- DB already supports many files per application.

### 3.2 Approach options

| Option | Pros | Cons |
|--------|------|------|
| **A. Multi-file on create in one request** | Single submit UX | Large payloads; need `files[]` on `store`; harder validation errors |
| **B. Create app, then batch upload** | Reuses storage logic | Two steps unless client chains requests |
| **C. Staged files (client) → create → upload queue** | Best UX on create | More frontend work |

**Recommendation: C for create, enhance edit with multi-select**

1. **Create flow**: User selects multiple files in form → POST create application → on success, loop `POST` uploads (or one new batch endpoint) before redirect.
2. **Edit flow**: `FileManager` accepts `multiple`; optional batch endpoint `POST applications/{id}/files` with `files[]`.

### 3.3 Backend

| Area | Changes |
|------|---------|
| `StoreApplicationFileRequest` | Support `files` array: `files.*` same rules as single `file` |
| `ApplicationFileController` | `store`: loop validated files; return count in flash; optional `storeBatch` |
| `ApplicationController::store` | Optional: accept nested `files[]` if doing Option A |
| `StoredFileService` | Add `storeMany(Application $app, array $uploadedFiles): Collection` |
| `ApplicationController::formProps` | On create, still pass `canUploadApplicationFiles`; pass `files: []` |
| `ApplicationController::index` | Optional: `withCount('files')` for paperclip indicator column |
| Policy | Unchanged: `application_files_enabled` + ownership |

**Validation (unchanged rules, applied per file)**

- MIME: PDF, DOCX
- Max size: 10 MB per file
- Max files per application (new): e.g. 10 — config or constant in `StoredFileService`

### 3.4 Frontend

| Component | Changes |
|-----------|---------|
| `FileManager.vue` | `multiple` on input; show selected queue before upload; upload all button; progress per file |
| `Form.vue` | Show `FileManager` on **create** when `canUploadApplicationFiles`; pass `applicationId` as null until saved — use staged mode |
| Staged mode | `pendingFiles` ref; on submit: `form.post` → onSuccess upload queue with `application.id` from response (may need `store` to redirect to edit or return id in flash/Inertia props) |

**Create redirect adjustment**

- Today: `store` redirects to index.
- For uploads: redirect to `edit` with flash, or stay on page with returned `application` prop so client can upload immediately.

Suggested: `store` → redirect to `applications.edit` when files were staged; else index.

### 3.5 Tests

- Multi-upload on edit (3 files).
- Create with staged files (if implemented).
- Reject when `application_files_enabled` is false.
- Enforce per-file and per-application limits.
- Delete application cascades files on disk (`ApplicationFile` deleting hook).

---

## Phase 4: Email notifications

### 4.1 Notification types (v1)

| Type | Trigger | Typical timing |
|------|---------|----------------|
| **Follow-up reminder** | User sets “check again on” date on application or moment | Morning of due date |
| **Moment reminder** | Interview / feedback / offer moment with `occurred_at` in future | 1 day before (configurable) |
| **Deadline reminder** | Application in `esperando` with no activity for N days | e.g. 14 days after `applied_at` |
| **Wave summary** (optional v2) | Weekly digest per wave | Monday 9:00 user TZ |

Start with **follow-up date** + **moment reminder**; add idle-application nudge later.

### 4.2 Data model

**Option A — fields on existing models**

- `applications.check_in_at` (date, nullable) — “remind me to check this application”
- Moments already have `occurred_at` for scheduled events

**Option B — dedicated reminders table** (more flexible)

**Table: `application_reminders`**

| Column | Type |
|--------|------|
| `id` | bigint |
| `user_id` | FK |
| `application_id` | FK |
| `remind_at` | datetime |
| `type` | enum: `check_in`, `moment`, `custom` |
| `application_moment_id` | FK, nullable |
| `sent_at` | datetime, nullable |
| `channel` | string, default `mail` |

**User notification preferences**

**Table: `user_notification_preferences`** (or columns on `users`)

| Setting | Default |
|---------|---------|
| `email_reminders_enabled` | true |
| `email_moment_reminders` | true |
| `email_check_in_reminders` | true |
| `email_digest_enabled` | false |
| `timezone` | nullable (fallback `config('app.timezone')`) |

### 4.3 Delivery infrastructure

| Piece | Implementation |
|-------|----------------|
| Notifications | `app/Notifications/ApplicationReminderNotification.php` (Laravel `Notification` + `MailMessage`) |
| Queue | `QUEUE_CONNECTION=database` (already default); implement `ShouldQueue` |
| Scheduler | `routes/console.php`: `Schedule::command('applications:send-reminders')->dailyAt('08:00')` |
| Command | `SendApplicationRemindersCommand`: query due reminders, respect prefs, mark `sent_at`, dedupe |
| Mail config | Document production `MAIL_*` in README / DEPLOY; keep `log` for local |
| i18n | Notification subject/body per user `locale` via `TranslationService` or dedicated `lang/*/notifications.php` |

### 4.4 UI

| Screen | Changes |
|--------|---------|
| Application form | “Remind me on” date field (`check_in_at`) |
| Moment row | Optional “Email me before this” checkbox → creates reminder |
| Profile | Notification preferences section |
| Admin (Filament) | Optional: enable/disable email per user (v2) |

### 4.5 Edge cases

- Do not email unverified users (or verify first).
- One email per application per day max (dedupe in command).
- User disables email → skip, leave reminder row for if re-enabled.
- Application deleted → cascade delete reminders.
- Test with `Mail::fake()` and `Notification::fake()`.

### 4.6 Tests

- Command sends notification when `remind_at` is today and not sent.
- Command skips when preference off.
- Command does not resend when `sent_at` set.
- Feature test: updating `check_in_at` schedules reminder.

---

## Cross-cutting concerns

### Migrations order

1. `application_waves` + backfill + NOT NULL on `applications.application_wave_id`
2. `applications.is_favourite`
3. (Optional) `applications.check_in_at` and/or `application_reminders`
4. (Optional) `user_notification_preferences`

File uploads need **no migration** unless adding `files_count` cache or upload limits table.

### Authorization

- All new endpoints scoped by `$request->user()->id`.
- Policies for `ApplicationWave`, extend `ApplicationPolicy` for favourite toggle.
- Waves and areas are independent; both required on application form.

### i18n

Add keys to `lang/en/app.php` and `lang/pt/app.php`:

- `waves.*` (CRUD, default migration name, empty state)
- `applications.favourite`, `applications.favourites_only`
- `applications.check_in_at`, `notifications.*`
- Run `TranslationService::seedFromFiles` or admin sync after deploy.

### Filament admin

| Feature | Admin impact |
|---------|----------------|
| Waves | Optional resource to inspect user waves |
| Favourites | None |
| Files | Existing user toggle unchanged |
| Notifications | Optional per-user email prefs |

### Performance

- Index: `withCount('files')` only if showing attachment indicator.
- Eager load `wave` on index when wave column/filter enabled.
- Reminder command: chunk queries; index on `remind_at`, `sent_at`.

### Rollout / feature flags (optional)

If gradual rollout is needed:

- `config('features.waves')` — middleware only enforces waves when true.
- Existing users already backfilled; flag mainly for UI gating during development.

---

## File touch list (by phase)

### Phase 1 — Waves

```
database/migrations/*_create_application_waves_table.php
database/migrations/*_add_application_wave_id_to_applications_table.php
app/Models/ApplicationWave.php
app/Models/Application.php
app/Models/User.php
app/Http/Controllers/ApplicationWaveController.php
app/Http/Middleware/EnsureUserHasWaves.php
app/Http/Requests/ApplicationWaveRequest.php
app/Http/Requests/ApplicationRequest.php
app/Http/Controllers/ApplicationController.php
app/Queries/FilteredApplicationsQuery.php
routes/web.php
bootstrap/app.php (middleware alias)
resources/js/Pages/Waves/*
resources/js/Pages/Applications/Form.vue
resources/js/Pages/Applications/Index.vue
lang/en/app.php
lang/pt/app.php
tests/Feature/ApplicationWaveTest.php
```

### Phase 2 — Favourites

```
database/migrations/*_add_is_favourite_to_applications_table.php
app/Models/Application.php
app/Http/Controllers/ApplicationController.php
app/Queries/FilteredApplicationsQuery.php
routes/web.php
resources/js/Pages/Applications/Index.vue
lang/en/app.php
lang/pt/app.php
tests/Feature/ApplicationFavouriteTest.php
```

### Phase 3 — Multi-upload

```
app/Http/Requests/StoreApplicationFileRequest.php
app/Http/Controllers/ApplicationFileController.php
app/Http/Controllers/ApplicationController.php
app/Services/StoredFileService.php
resources/js/Components/FileManager.vue
resources/js/Pages/Applications/Form.vue
tests/Feature/ApplicationFilesTest.php
```

### Phase 4 — Notifications

```
database/migrations/*_application_reminders_table.php
database/migrations/*_user_notification_preferences.php
app/Models/ApplicationReminder.php
app/Notifications/ApplicationReminderNotification.php
app/Console/Commands/SendApplicationRemindersCommand.php
routes/console.php
app/Http/Controllers/ProfileController.php (or dedicated prefs)
resources/js/Pages/Profile/*
lang/en/notifications.php
lang/pt/notifications.php
tests/Feature/ApplicationRemindersTest.php
```

---

## Open decisions (confirm before implementation)

| # | Question | Recommendation |
|---|----------|----------------|
| 1 | UI term: “Wave” vs “Season” | **Wave** in EN; PT: “Onda” or “Época” — pick one with product owner |
| 2 | Can one application belong to multiple waves? | **No** — single `application_wave_id` |
| 3 | New users: wave before area, or both independent? | **Both required**; order in UI: wave then area |
| 4 | Favourites sort above status priority? | **Yes** — stars first, then status order |
| 5 | Batch file endpoint vs multiple POSTs | **Batch endpoint** for create flow UX |
| 6 | Notification timezone | Store on user profile; default UTC or browser-detected on first visit |

---

## Success criteria

- [ ] User can create a wave and assign applications to it; existing users have a default wave with all legacy applications.
- [ ] New user cannot add an application without at least one wave.
- [ ] User can star/unstar from the applications table; favourites appear first in default sort.
- [ ] User with file upload enabled can attach multiple PDF/DOCX on create and edit.
- [ ] User receives email (or logged mail in dev) for configured reminders; preferences respected.
- [ ] EN/PT strings for all new UI.
- [ ] Feature tests cover migrations, authorization, and main flows.

---

## Estimated effort (rough)

| Phase | Size | Notes |
|-------|------|-------|
| 1 — Waves | Medium–large | Migration + CRUD + gating + index/filter |
| 2 — Favourites | Small | Column + toggle + sort |
| 3 — Multi-upload | Medium | UX on create is main complexity |
| 4 — Notifications | Large | Scheduler, prefs, i18n mail, edge cases |

**Total**: plan for 3–4 focused iterations; phases 1–2 can ship before 3–4.
