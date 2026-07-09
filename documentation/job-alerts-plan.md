# Job alerts & scraping — implementation plan

Plan for admin-configured job source scraping, AI-assisted fit matching, and user digest notifications. Written against the current Apleqz stack (Laravel 13, Inertia, Vue 3, PostgreSQL, Filament admin, database queue, localized mail).

---

## Goals

| Feature | Summary |
|---------|---------|
| **Admin job sources** | Admins define career pages to monitor; low-code visual mapping of fields (title, URL, company, etc.) |
| **Scheduled scraping** | System fetches sources 1–2× per day, deduplicates listings, logs runs |
| **Browser interactions** | Optional pre-scrape steps (accept cookies, wait for list, load more) via Playwright |
| **User subscriptions** | Users opt into sources and maintain a short job-search profile |
| **AI fit scoring** | AI compares profile to new listings; stores score + reason (Mistral API or local Ollama) |
| **Digest notifications** | Email users when new high-fit positions appear (batched, localized) |

---

## Current baseline (relevant today)

- **Email**: SMTP/Mailpit locally; `ApplicationReminderNotification` with localized templates; `HasLocalePreference` on `User`; scheduler in `bootstrap/app.php`.
- **Admin**: Filament panel for users and translation lines; no job-source resources yet.
- **Queues**: `QUEUE_CONNECTION=database`; reminder dispatch via `ApplicationReminderDispatchService` + artisan commands.
- **Areas**: User-defined categories (e.g. Engineering) — optional filter for subscriptions later.
- **Applications**: UUID route keys; no link yet from job matches to “create application”.

---

## Terminology

| Term | Meaning |
|------|---------|
| **Job source** | Admin-managed careers page (URL + scrape config). Table: `job_sources`. |
| **Extraction config** | JSONB rules for interactions + field mappings. Stored on `job_sources`. |
| **Job listing** | A single position found on a source (deduped by source + external id). |
| **Interaction step** | Pre-scrape browser action (click, wait, scroll). Playwright only. |
| **Match** | AI fit result for `(user, listing)` with score and reason. |
| **Digest** | One email per user per run summarizing pending matches. |

---

## Architecture overview

```
Admin (Filament)
  └─ Visual configurator → extraction_config (jsonb)

Scheduler (2×/day)
  └─ jobs:scrape-sources
       └─ ScrapeJobSourceJob (queue, one per source)
            ├─ Playwright (optional interactions) → HTML snapshot
            ├─ JobListingExtractor (config-driven) → upsert job_listings
            └─ MatchNewListingsJob for new rows only
                 └─ EvaluateJobMatchJob (AI provider) per subscriber

Scheduler (+30 min after scrape)
  └─ jobs:send-digests
       └─ JobDigestDispatchService → localized email
```

**Principle:** scrape → extract → match → notify. Do not call the AI during scraping.

---

## AI provider choice (cloud vs local)

Matching is a small, structured task: short prompt in, JSON out (`fit_score` + `reason`). Any text model works; swap providers via config without changing scrape or email logic.

### Recommended default: Mistral API (`ministral-3b-latest`)

Use [Mistral API pricing](https://mistral.ai/pricing/api/?currency=eur) for the cheapest model that fits this task.

| Model | Slug | Input | Output | Notes |
|-------|------|-------|--------|-------|
| **Ministral 3B** | `ministral-3b-latest` | **€0.10 / M** | **€0.10 / M** | **Default — lowest cost, sufficient for scoring** |
| Ministral 8B | `ministral-8b-latest` | €0.15 / M | €0.15 / M | Fallback if 3B returns flaky JSON or weak reasons |
| Mistral NeMo | `open-mistral-nemo` | €0.15 / M | €0.15 / M | Fine, no advantage here |
| Mistral Small 4 | `mistral-small-latest` | €0.15 / M | €0.60 / M | Overkill; higher output cost |

**Per evaluation** (~1,000 input + 80 output tokens on Ministral 3B):

```
Input:  1,000 × €0.10 / 1,000,000 ≈ €0.00010
Output:    80 × €0.10 / 1,000,000 ≈ €0.000008
Total:                              ≈ €0.00011 per match
```

≈ **€0.11 per 1,000 matches**.

**Monthly estimates** (`new_jobs_per_day × subscribers × 2 scrapes/day × 30 days`):

| Scenario | Matches/month | Cost/month (3B) |
|----------|---------------|-----------------|
| Beta (5 jobs × 10 users) | ~3,000 | **~€0.33** |
| Early (10 jobs × 20 users) | ~12,000 | **~€1.30** |
| Growing (20 jobs × 50 users) | ~60,000 | **~€6.50** |
| Heavier (50 jobs × 100 users) | ~300,000 | **~€33** |

**Cost controls** (apply regardless of provider):

- Only score **new listings × active subscribers**
- **Cache** by `hash(profile_text + listing content_hash)` — never re-score unchanged pairs
- **Truncate** job description to ~500–800 chars in the prompt
- **Batch API** (50% off on [Mistral pricing](https://mistral.ai/pricing/api/?currency=eur)) — fits async digest flow (scrape → match → email 30+ min later)

### Alternative: local Ollama on VPS

Ollama exposes an OpenAI-compatible API (`http://127.0.0.1:11434/v1`). Same prompt contract; profile and listing text stay on your infrastructure.

| | Mistral API (Ministral 3B) | Ollama on VPS |
|--|---------------------------|---------------|
| **Monthly API cost** | ~€1–7 at beta scale | €0 |
| **Speed** | ~1–2 s per call | ~10–30 s on CPU-only VPS |
| **Privacy** | Profile sent to Mistral | Stays on server |
| **Ops** | API key only | Model install, RAM limits, uptime |
| **Quality** | Consistent JSON | Depends on model (`phi3:mini`, `llama3.2:3b`, `mistral` 7B) |

**Suggested Ollama models** (by RAM):

| Model | RAM (approx.) | Quality for scoring |
|-------|---------------|---------------------|
| `llama3.2:3b` / `phi3:mini` | ~2 GB | OK for obvious fits |
| `mistral` (7B Q4) | ~4.5 GB | Better, tight on 8 GB VPS |

Set `OLLAMA_MAX_LOADED_MODELS=1` and `OLLAMA_NUM_PARALLEL=1`; run matching in a single queue worker.

### Deployment: KVM 2 (8 GB RAM, 2 vCPU, CyberPanel)

Reference production VPS: **2 vCPU, 8 GB RAM**, AlmaLinux + CyberPanel (Laravel, MariaDB/PostgreSQL, queue worker already resident).

| Component | Rough RAM |
|-----------|-----------|
| OS + CyberPanel / web server | ~1–2 GB |
| Database | ~0.5–1 GB |
| PHP-FPM + queue worker | ~0.7–1.5 GB |
| **Available for Ollama** | **~3–5 GB** |

| Use case on this VPS | Feasible? |
|----------------------|-----------|
| Job fit scoring only (async, few users, small model) | **Yes, with care** |
| Ollama + Playwright on same host | **Risky** — RAM spikes |
| Many subscribers × many new jobs/day | **No** — use cloud API or upgrade RAM |

**Throughput** (CPU-only, ~15 s per evaluation): 50 matches ≈ 12 min; 400 matches ≈ 1.7 h. Fine for beta; schedule scrape and inference separately.

**Recommendation for this VPS:**

1. **Default:** `ministral-3b-latest` via Mistral API — negligible cost, no RAM contention, fastest.
2. **Privacy-first:** Ollama with `phi3:mini` or `llama3.2:3b`, single worker, no Playwright on same box.
3. **Scale-up:** `ministral-8b-latest` API if 3B quality is insufficient; or KVM 4 (16 GB) for local `mistral` 7B.

### Provider-agnostic implementation

```
JobMatchEvaluator
  └─ AiChatClient (interface)
       ├─ MistralCloudClient   → api.mistral.ai
       └─ OllamaClient         → OLLAMA_BASE_URL (OpenAI-compatible)
```

Tests use a **fake client** returning fixed JSON — no API key or GPU in CI.

---

## Implementation phases (checklist)

| Done | Phase | Scope | Exit criteria |
|:----:|-------|-------|---------------|
| [x] | **A** | Schema & admin CRUD | `job_sources` table, Filament resource, manual JSON config (no visual picker yet) |
| [ ] | **B** | HTTP scraper + extractor | `JobListingExtractor`, `jobs:scrape-sources`, `job_listings` dedupe, scrape run logs |
| [ ] | **C** | Visual field configurator | Proxied preview, click-to-map fields, test extraction table in admin |
| [ ] | **D** | Playwright interactions | Node bridge, `interactions` in config, cookie/load-more support |
| [ ] | **E** | Visual interaction recorder | Admin clicks cookie button etc.; steps saved to config |
| [ ] | **F** | User subscriptions & profile | Profile text, source checkboxes, `job_alerts_enabled` toggle |
| [ ] | **G** | AI matching | `JobMatchEvaluator`, `AiChatClient`, `job_matches` table, in-app matches list |
| [ ] | **H** | Digest emails | `JobMatchesDigestNotification`, `jobs:send-digests`, localized templates |
| [ ] | **I** | Hardening & polish | SSRF guards, config versioning, failure alerts, “create application” from match |

---

## Data model

### `job_sources`

| Column | Type | Notes |
|--------|------|-------|
| `id` | UUID | PK |
| `name` | string | Display name |
| `url` | string | Listing page URL |
| `company_name` | string, nullable | Default company when not extracted |
| `is_active` | boolean | |
| `extraction_config` | jsonb | Interactions + listing (+ optional detail) mappings |
| `config_version` | unsigned int | Increment on config save |
| `last_scraped_at` | timestamp, nullable | |
| `last_scrape_status` | string, nullable | `success`, `failed`, `partial` |
| `timestamps` | | |

### `job_source_scrape_runs`

| Column | Type | Notes |
|--------|------|-------|
| `id` | bigint | PK |
| `job_source_id` | UUID FK | |
| `started_at` / `finished_at` | timestamp | |
| `status` | string | |
| `listings_found` | int | |
| `listings_new` | int | |
| `error_message` | text, nullable | |
| `meta` | jsonb, nullable | Duration, engine used (`http` vs `playwright`) |

### `job_listings`

| Column | Type | Notes |
|--------|------|-------|
| `id` | UUID | PK |
| `job_source_id` | UUID FK | |
| `external_id` | string | Hash or normalized URL; unique per source |
| `title` | string | |
| `url` | string | |
| `company` | string, nullable | |
| `location` | string, nullable | |
| `salary` | string, nullable | |
| `application_deadline` | string, nullable | Raw text in v1 |
| `description` | text, nullable | Snippet or full text |
| `raw_fields` | jsonb, nullable | Any extra mapped fields |
| `content_hash` | string, nullable | Detect title/description changes |
| `first_seen_at` | timestamp | |
| `last_seen_at` | timestamp | |

Unique: `(job_source_id, external_id)`.

### `user_job_profiles`

| Column | Type | Notes |
|--------|------|-------|
| `user_id` | FK | PK |
| `profile_text` | text | User’s job-search summary for AI |
| `min_fit_score` | tinyint | Default `70` |
| `job_alerts_enabled` | boolean | Like `email_reminders_enabled` |
| `timestamps` | | |

### `user_job_source_subscriptions`

| Column | Type | Notes |
|--------|------|-------|
| `user_id` | FK | |
| `job_source_id` | UUID FK | |
| `is_active` | boolean | |
| `timestamps` | | |

Unique: `(user_id, job_source_id)`.

### `job_matches`

| Column | Type | Notes |
|--------|------|-------|
| `id` | bigint | PK |
| `user_id` | FK | |
| `job_listing_id` | UUID FK | |
| `fit_score` | tinyint | 0–100 |
| `reason` | text | From AI evaluator |
| `status` | string | `pending_notify`, `notified`, `dismissed`, `applied` |
| `notified_at` | timestamp, nullable | |
| `timestamps` | | |

Unique: `(user_id, job_listing_id)`.

---

## Predefined field catalog

Fixed vocabulary for the visual configurator. Only **`job_title`** and **`url`** are required for v1.

| Key | Label | Required |
|-----|-------|----------|
| `job_title` | Job title | Yes |
| `url` | Application / detail URL | Yes |
| `company` | Company | No (can be static) |
| `location` | Location | No |
| `application_deadline` | Application deadline | No |
| `salary` | Salary | No |
| `description` | Description | No |
| `employment_type` | Employment type | No |
| `department` | Department | No |
| `posted_at` | Posted date | No |
| `external_id` | External ID | No (auto from URL if omitted) |

PHP: `App\Enums\JobListingField` + `config/job_listing_fields.php` for labels.

---

## Extraction config (JSONB schema)

Single document on `job_sources.extraction_config`:

```json
{
  "version": 1,
  "engine": "playwright",
  "sample_url": "https://careers.example.com/jobs",
  "interactions": [
    {
      "type": "click",
      "selector": "#onetrust-accept-btn-handler",
      "optional": true,
      "wait_after_ms": 500
    },
    {
      "type": "wait_for",
      "selector": "article.job-card",
      "timeout_ms": 10000
    },
    {
      "type": "click",
      "selector": "button.load-more",
      "optional": true,
      "repeat_until_gone": true,
      "max_clicks": 5
    }
  ],
  "listing": {
    "item_selector": "article.job-card",
    "fields": {
      "job_title": {
        "selector": "h2 a",
        "scope": "item",
        "extract": "text"
      },
      "url": {
        "selector": "h2 a",
        "scope": "item",
        "extract": "attribute",
        "attribute": "href",
        "absolute": true
      },
      "company": {
        "source": "static",
        "value": "NovaStack GmbH"
      },
      "application_deadline": {
        "selector": ".deadline",
        "scope": "item",
        "extract": "text",
        "optional": true
      }
    }
  },
  "detail": null,
  "pagination": { "type": "none" },
  "meta": {
    "configured_by": 1,
    "configured_at": "2026-07-09T10:00:00Z"
  }
}
```

### Interaction action types

| Type | Purpose |
|------|---------|
| `click` | Cookie accept, dismiss modal, “Load more” |
| `wait_for` | Wait until job list selector appears |
| `scroll` | Trigger lazy-loaded content |
| `sleep` | Fixed delay (last resort) |

All interaction steps support `optional: true` (continue if element missing).

### Extraction modes

| `engine` | When |
|----------|------|
| `http` | Server-rendered HTML, no JS/cookies |
| `playwright` | Cookie banners, JS-rendered lists, clicks |

---

## Phase A — Schema & admin CRUD

### Backend

- Migrations: `job_sources`, `job_source_scrape_runs`, `job_listings`
- Models + factories for tests
- Filament `JobSourceResource`: name, url, company_name, is_active, extraction_config (JSON textarea for now)
- Policy: admin only

### Tests

- CRUD via Filament or feature test
- JSON config validation (required listing.item_selector when active)

---

## Phase B — HTTP scraper + extractor

### Services

| Class | Responsibility |
|-------|----------------|
| `JobSourceFetcher` | HTTP GET with timeout, size limit, SSRF guard |
| `JobListingExtractor` | Read config → array of normalized listings |
| `JobListingUpserter` | Dedupe by `(source_id, external_id)`; set `first_seen_at` / `last_seen_at` |

### Commands & jobs

- `jobs:scrape-sources` — dispatch `ScrapeJobSourceJob` per active source
- `ScrapeJobSourceJob` — fetch (HTTP only in this phase) → extract → upsert → log run
- `jobs:scrape-source {id}` — manual single-source scrape (admin/testing)

### Scheduler

```php
$schedule->command('jobs:scrape-sources')->twiceDaily(8, 20);
```

### Tests

- Fixture HTML files in `tests/fixtures/job-sources/`
- Extractor unit tests per fixture
- Assert dedupe and `listings_new` count

---

## Phase C — Visual field configurator

### Problem

Cross-origin iframes cannot be click-inspected. Use a **proxied preview** on your domain.

### Admin flow

1. Enter sample URL → **Load preview**
2. Backend fetches HTML, sanitizes (strip scripts), rewrites relative URLs
3. Render in sandboxed iframe (`srcdoc`) with injected picker script
4. **Select list item** — click one job card → detect `item_selector`
5. **Map field** — choose field from catalog → click element → store relative selector
6. **Test extraction** — table preview of extracted rows
7. Save → writes `extraction_config.listing`

### Endpoints (Filament or dedicated admin routes)

| Route | Purpose |
|-------|---------|
| `POST /admin/job-sources/preview` | Fetch + sanitize HTML |
| `POST /admin/job-sources/test-extraction` | Run extractor on preview HTML or live URL |

### Picker JS

- Generate robust CSS selector (e.g. `@medv/finder` or equivalent)
- `postMessage` to Livewire parent with `{ field, selector, scope }`
- Highlight mapped elements; warn on fragile selectors

### Tests

- Preview fetch blocks private IPs (SSRF)
- Picker integration smoke test (optional browser test later)

---

## Phase D — Playwright interactions

### Node bridge

```
scripts/scrape-page.mjs
  stdin:  { url, interactions[], timeout_ms }
  stdout: { html, error?, screenshots? }
```

PHP `PlaywrightPageFetcher` shells out to Node; returns HTML for `JobListingExtractor`.

### Docker / local dev

- `package.json` with `playwright` dependency
- Document `npx playwright install chromium` in README
- Production: Chromium in deploy image or dedicated worker container

### Scrape job update

If `config.engine === 'playwright'` (or `interactions` non-empty), use Playwright; else HTTP.

### Preset interaction templates (admin shortcuts)

| Preset | Selector hint |
|--------|----------------|
| OneTrust accept | `#onetrust-accept-btn-handler` |
| Cookiebot accept | `#CybotCookiebotDialogBodyLevelButtonLevelOptinAllowAll` |
| Generic “Accept all” | `[aria-label*="Accept"]` (fragile — prefer click-record) |

### Tests

- Mock Node script in CI returning fixture HTML
- Integration test with real Playwright optional (nightly)

---

## Phase E — Visual interaction recorder

Extend configurator with **Interaction mode**:

1. Admin clicks “Add interaction → Click”
2. Clicks cookie button in preview (selector captured)
3. Marks step `optional: true` if banner may be absent
4. **Test full flow** runs Playwright with all steps → shows extraction table

Same picker as field mapping; different payload shape (`type: click`).

---

## Phase F — User subscriptions & profile

### UI (Inertia)

New pages under e.g. `/job-alerts`:

- **Settings** — profile textarea, min fit score slider, email toggle, source checklist
- **Matches** (stub until Phase G) — placeholder list

### Backend

- Migrations: `user_job_profiles`, `user_job_source_subscriptions`
- `JobAlertSettingsController` — update profile + subscriptions
- Gate: `email_verified_at` for enabling alerts (same as reminders)

### Translations

`lang/{en,pt,de}/app.php` → `job_alerts.*` keys

---

## Phase G — AI matching

See **AI provider choice** above for model selection, pricing, and VPS constraints.

### Config

```env
# driver: mistral_cloud | ollama
JOB_MATCH_AI_DRIVER=mistral_cloud

# Mistral API (recommended default)
MISTRAL_API_KEY=
MISTRAL_MODEL=ministral-3b-latest

# Ollama (optional local alternative)
OLLAMA_BASE_URL=http://127.0.0.1:11434/v1
OLLAMA_MODEL=phi3:mini
```

Fallback model if 3B quality is insufficient: `ministral-8b-latest`.

### Services

| Class | Responsibility |
|-------|----------------|
| `AiChatClient` | Interface — `chat(array $messages): string` |
| `MistralCloudClient` | Mistral API (`/v1/chat/completions`) |
| `OllamaClient` | Local OpenAI-compatible endpoint |
| `JobMatchEvaluator` | Build prompt → parse `{ fit_score, reason }` JSON; retry once on invalid JSON |

Bind `AiChatClient` in a service provider from `JOB_MATCH_AI_DRIVER`.

### Prompt contract

Input: user `profile_text`, listing title/company/location/description snippet (truncated).  
Output (JSON only): `fit_score` 0–100, `reason` (one sentence).

Use `response_format: { type: "json_object" }` (Mistral) or Ollama `format: "json"` when supported.

### Jobs

- `MatchNewListingsJob` — given new listing IDs, find subscribers, dispatch evaluations
- `EvaluateJobMatchJob` — one listing × one user; skip if score < `min_fit_score`; check cache first

### Cost & capacity controls

- Only new listings × active subscribers
- Cache by `hash(profile_text + listing content_hash)`
- Optional batch prompt (multiple listings per call) at scale
- Single concurrent AI request when using Ollama on 8 GB VPS
- Optional Mistral Batch API for 50% discount on async runs

### UI

- Matches index: title, company, score, reason, external link, dismiss

---

## Phase H — Digest emails

Mirror application reminders:

| Piece | Notes |
|-------|-------|
| `JobDigestDispatchService` | Group `pending_notify` matches per user |
| `JobMatchesDigestNotification` | Localized; reuse mail header/logo |
| `jobs:send-digests` | `twiceDaily(8, 30)` and `twiceDaily(20, 30)` — after scrape window |
| `lang/*/notifications.php` | `job_digest.*` strings |

Email content: count + table of matches (title, company, score, reason, link).  
Mark matches `notified` after send.

### Tests

- Locale-specific digest content (like `ApplicationReminderTest`)
- User with `job_alerts_enabled = false` receives nothing

---

## Phase I — Hardening & polish

- **SSRF**: block private IPs, link-local, metadata endpoints on all fetches
- **robots.txt**: optional respect flag per source
- **Config versioning**: keep previous `extraction_config` on change; alert if scrape yields 0 listings
- **Rate limits**: one concurrent Playwright run per source; global cap
- **Create application from match**: pre-fill position, company, `job_url` on application form
- **Detail page scraping** (v2 within I): second config block for full description
- **Privacy**: document profile text sent to AI provider (Mistral API) or kept on-server (Ollama) in cookies/policy copy

---

## Suggested file layout

```
app/
  Enums/JobListingField.php
  Models/JobSource, JobListing, JobMatch, UserJobProfile, ...
  Services/
    JobSourceFetcher.php
    PlaywrightPageFetcher.php
    JobListingExtractor.php
    JobListingUpserter.php
    JobMatchEvaluator.php
    AiChatClient.php          # interface
    MistralCloudClient.php
    OllamaClient.php
    JobDigestDispatchService.php
  Jobs/
    ScrapeJobSourceJob.php
    MatchNewListingsJob.php
    EvaluateJobMatchJob.php
  Notifications/JobMatchesDigestNotification.php
  Console/Commands/
    ScrapeJobSourcesCommand.php
    SendJobDigestsCommand.php
  Filament/Resources/JobSources/
    JobSourceResource.php
    Pages/ConfigureScrape.php

resources/
  views/filament/job-sources/   # preview iframe, picker assets
  js/Pages/JobAlerts/

scripts/
  scrape-page.mjs

tests/
  fixtures/job-sources/
  Unit/JobListingExtractorTest.php
  Feature/JobScrapeTest.php
  Feature/JobMatchTest.php
```

---

## Environment variables

| Variable | Purpose |
|----------|---------|
| `JOB_MATCH_AI_DRIVER` | `mistral_cloud` (default) or `ollama` |
| `MISTRAL_API_KEY` | Mistral API key (when driver is `mistral_cloud`) |
| `MISTRAL_MODEL` | Default `ministral-3b-latest`; fallback `ministral-8b-latest` |
| `OLLAMA_BASE_URL` | Local OpenAI-compatible base URL (default `http://127.0.0.1:11434/v1`) |
| `OLLAMA_MODEL` | Ollama model tag (e.g. `phi3:mini`, `llama3.2:3b`) |
| `JOB_SCRAPE_HTTP_TIMEOUT` | Seconds (default 15) |
| `JOB_SCRAPE_MAX_BYTES` | Max HTML size (default 2MB) |
| `PLAYWRIGHT_NODE_PATH` | Optional path to `node` binary |
| `PLAYWRIGHT_SCRIPT` | Path to `scrape-page.mjs` |

---

## Legal & operational notes

- Scrape only sources you are permitted to use; prefer official APIs/RSS when available.
- Cookie automation is for accessing publicly listed jobs, not bypassing paywalls or auth.
- Headless Chrome needs ~200–400 MB RAM per run; size queue workers accordingly.
- Some sites block datacenter IPs; may need proxies later (out of v1 scope).

---

## Recommended build order (summary)

```
A → B → C → D → E   (scraping pipeline + admin tooling)
F → G → H           (user value + AI + email)
I                   (production hardening)
```

Phases **C** and **D** can overlap: ship HTTP extractor (B) before visual picker (C), and add Playwright (D) when a real source requires cookies or JS.

---

## Open decisions

| Decision | Recommendation |
|----------|----------------|
| Global vs per-user sources | **Global** sources, user subscriptions |
| Match threshold | Per-user `min_fit_score` (default 70) |
| Digest vs instant email | **Digest** per scrape window |
| Detail page scrape in v1 | **No** — listing fields only until stable |
| Engine default | `http` when no interactions; else `playwright` |
| AI provider | **`mistral_cloud` + `ministral-3b-latest`** for launch; Ollama optional for privacy |
| Playwright on 8 GB VPS | **Avoid** on same host as Ollama; HTTP scrape or separate worker |

---

*Last updated: July 2026*
