# Safer Project File Analysis

This document explains each first-party file in this project:
- what it does
- how it does it
- how it affects other files

I grouped files by folder to keep it readable.

## Root Configuration and Meta Files

### `.cpanel.yml`
- What it does: Defines cPanel deployment steps.
- How it does it: Copies `api`, `scripts`, `xadmin`, top-level `*.html`, `*.php`, and optional static folders to `/home/lyrivers/public_html/safer.ng`.
- Effect on other files: Controls which files are published to production.

### `.gitattributes`
- What it does: Git text normalization.
- How it does it: Uses `* text=auto`.
- Effect on other files: Keeps line endings consistent across files.

### `.gitignore`
- What it does: Excludes local/generated files from Git.
- How it does it: Ignores `.env`, `node_modules`, `dist`, `vendor`, `error.csv`.
- Effect on other files: Prevents dependency/output/log files from being committed.

### `.htaccess`
- What it does: Apache/PHP runtime overrides.
- How it does it: cPanel-generated PHP directives (memory limit, upload limits, display errors, etc.).
- Effect on other files: Changes runtime behavior for all PHP endpoints.

### `.user.ini`
- What it does: PHP runtime config at directory level.
- How it does it: Mirrors cPanel PHP values.
- Effect on other files: Applies PHP limits/settings to app scripts.

### `php.ini`
- What it does: Local PHP config copy.
- How it does it: Same limits/settings as `.user.ini`.
- Effect on other files: Affects PHP execution where this file is loaded.

### `LICENSE`
- What it does: Licensing text (Apache 2.0).
- How it does it: Standard license content.
- Effect on other files: Governs legal use/distribution of the project.

### `README.md`
- What it does: Minimal project description.
- How it does it: Short note on app identity.
- Effect on other files: Entry point for understanding project intent.

### `SETUP_DATABASE.md`
- What it does: Manual DB setup guide.
- How it does it: Step-by-step schema/import/API verification instructions.
- Effect on other files: Describes how required DB tables for API/frontend should exist.

### `composer.json`
- What it does: PHP dependency manifest.
- How it does it: Requires `cloudinary/cloudinary_php`.
- Effect on other files: Enables image upload features in photo endpoints.

### `composer.lock`
- What it does: Locks exact dependency versions.
- How it does it: Pins Cloudinary SDK and transitive packages.
- Effect on other files: Stabilizes runtime behavior for `vendor/autoload.php` consumers.

### `setup.php`
- What it does: One-off DB setup tester page.
- How it does it: Calls `getDatabaseConnection()` and `createIncidentsTable()` then prints status.
- Effect on other files: Bootstraps schema used by incidents APIs/pages.

### `error_logger.php`
- What it does: CSV error logger helper.
- How it does it: `log_error_to_csv(file, error)` appends with file lock.
- Effect on other files: Used by multiple API scripts for lightweight error auditing.

### `error.csv`
- What it does: Stored runtime error logs.
- How it does it: Appended by `error_logger.php`.
- Effect on other files: Historical diagnostics for failing endpoints.

### `db-test`
- What it does: Placeholder file.
- How it does it: Empty.
- Effect on other files: None.

### `x.txt`
- What it does: Scratch note.
- How it does it: Contains a prompt-like sentence.
- Effect on other files: None.

## Root HTML Pages and Fragments

### `index.html`
- What it does: Landing/home page.
- How it does it: Hero map, stats counters, feature blocks, community signup form, shared nav/footer includes.
- Effect on other files: Loads `scripts/landing.js`, `scripts/map.js`, `scripts/include-fragments.js`, `scripts/whatsapp.js`; styled by landing/forms/nav/footer CSS.

### `incidents.html`
- What it does: Public incidents dashboard/list page.
- How it does it: Renders analytics cards/charts/map/table/filter UI and mobile filter sheet.
- Effect on other files: Driven by `scripts/incidents.js` + `scripts/search.js` + `scripts/map.js`; consumes many `api/incidents/*` endpoints.

### `incident-detail.html`
- What it does: Single incident detail page.
- How it does it: Placeholder sections populated client-side; includes prev/next buttons, photo gallery, map.
- Effect on other files: Uses `scripts/incident-detail.js`, `scripts/photos.js`, `api/incidents/incident-detail.php`, `api/incidents/photos.php`.

### `report.html`
- What it does: Incident report submission form.
- How it does it: Multi-section form with state/LGA population and optional media upload.
- Effect on other files: Uses `scripts/reports.js` and `scripts/photos.js`; posts to `api/incidents/reports.php` and `api/incidents/photos.php`.

### `agent-request.html`
- What it does: Security agent request form.
- How it does it: Collects requester/job details and validates client-side.
- Effect on other files: Uses `scripts/agent-req.js`; posts to `api/jobs/create-job.php`; redirects to `pay.html`.

### `pay.html`
- What it does: Invoice/payment page for job requests.
- How it does it: Loads job data and invoice config, renders totals and payment actions.
- Effect on other files: Uses `scripts/pay.js`; consumes `api/pay.php`, `api/pay-init.php`, `api/pay-verify.php`, `scripts/invoice.json`.

### `test-api.html`
- What it does: Manual API smoke-test page.
- How it does it: Fetches key endpoints and prints JSON success/failure blocks.
- Effect on other files: Good for debugging incidents/stats/AI endpoints.

### `nav.html`
- What it does: Shared navigation fragment.
- How it does it: Static links and mobile toggle button.
- Effect on other files: Injected by `scripts/include-fragments.js` on pages using `data-include`.

### `footer.html`
- What it does: Shared footer fragment.
- How it does it: Basic copyright and policy links.
- Effect on other files: Injected by `scripts/include-fragments.js`.

## Root Data Files

### `locations.json`
- What it does: Canonical state -> LGA -> wards dataset.
- How it does it: Large nested JSON used for dropdowns.
- Effect on other files: Used by `scripts/reports.js`, `scripts/agent-req.js`, `scripts/search.js`.

### `locations_coords.json`
- What it does: State/LGA centroid coordinates.
- How it does it: Keyed JSON map of lat/lng values.
- Effect on other files: Backend or tooling fallback for geolocation mapping.

### `sample_data.sql`
- What it does: Seeds sample `incidents` records.
- How it does it: Bulk `INSERT` + closed-incident `closed_at` update.
- Effect on other files: Provides test data for incidents pages/charts/APIs.

### `migrations/2026-01-06-add-location-to-incidents.sql`
- What it does: Adds `location` column.
- How it does it: Single `ALTER TABLE incidents ADD COLUMN location TEXT`.
- Effect on other files: Required for `reports.php`/list/detail flows expecting `location`.

## Config Folder

### `config/database.php`
- What it does: Main DB connector + schema helpers.
- How it does it: PDO singleton from env/default credentials; table-creation functions for incidents, media, job requests, reporters, reports.
- Effect on other files: Core dependency for almost every API PHP file.

### `config/lgas.csv`
- What it does: LGA coordinate source data.
- How it does it: CSV rows with state/LGA and lat/lng.
- Effect on other files: Consumed by coordinate transform scripts.

## API Config Folder

### `api/config/database.php`
- What it does: Duplicate DB helper file.
- How it does it: Same contents as root `config/database.php`.
- Effect on other files: Potential divergence risk if one copy changes.

### `api/config/lgas.csv`
- What it does: Duplicate LGA CSV.
- How it does it: Same structure as root CSV.
- Effect on other files: Duplicate dataset maintenance risk.

## Frontend Scripts

### `scripts/incidents.js`
- What it does: Main incidents dashboard controller.
- How it does it: Loads stats/charts/AI/list, manages pagination/table/cards, calls map refresh.
- Effect on other files: Primary consumer of incidents/stats/AI APIs; works with `search.js` and `map.js` globals.

### `scripts/search.js`
- What it does: Filter/search state manager for incidents page.
- How it does it: Uses URL query params as source-of-truth, desktop form + mobile sheet sync.
- Effect on other files: Updates global `currentFilters` used by `incidents.js` and `map.js`.

### `scripts/map.js`
- What it does: Incident map rendering (Mapbox).
- How it does it: Builds GeoJSON from `api/incidents/list.php`, adds styled circle layer and popups.
- Effect on other files: Exposes `window.updateMapIncidents()` called by incidents/search flows.

### `scripts/incident-detail.js`
- What it does: Detail-page loader.
- How it does it: Reads `id`, fetches incident data, renders fields/map/share, loads photo module dynamically.
- Effect on other files: Depends on `api/incidents/incident-detail.php`; references neighbor endpoint (`/safer/api/incidents/neighbor.php`) which is not present.

### `scripts/reports.js`
- What it does: Report form logic.
- How it does it: Loads locations, validates fields, sends JSON report payload, optionally uploads photos.
- Effect on other files: Writes new incidents via `api/incidents/reports.php`; uploads media via `api/incidents/photos.php`.

### `scripts/report.js`
- What it does: Older/alternate report form script.
- How it does it: Legacy state list, geolocation button, CAPTCHA flow against `report.php`.
- Effect on other files: Not used by `report.html` now; can confuse maintenance.

### `scripts/photos.js`
- What it does: Shared media helper module.
- How it does it: Exposes `window.Photos` methods for render, fetch, upload.
- Effect on other files: Used by `reports.js` and `incident-detail.js`.

### `scripts/agent-req.js`
- What it does: Agent request form controller.
- How it does it: Validates input, loads locations, POSTs to create-job API, redirects to invoice page.
- Effect on other files: Feeds `job_requests` data consumed by admin + pay flows.

### `scripts/pay.js`
- What it does: Invoice rendering + payment action logic.
- How it does it: Fetches job pricing data, binds Paystack and WhatsApp actions, prints invoice.
- Effect on other files: Depends on payment APIs and invoice config JSON.

### `scripts/landing.js`
- What it does: Home page interactions.
- How it does it: Leaflet hero map animation, marker pulses, stat counters, signup form interaction.
- Effect on other files: UX behavior on `index.html`; posts WhatsApp form to a placeholder endpoint (`/api/whatsapp/collect`) unlike `scripts/whatsapp.js`.

### `scripts/whatsapp.js`
- What it does: Community signup submission handler.
- How it does it: Validates form and posts URL-encoded payload to `api/subscribe.php`.
- Effect on other files: Main subscription path for index page.

### `scripts/include-fragments.js`
- What it does: Injects shared nav/footer HTML fragments.
- How it does it: Fetches `data-include` targets, marks active nav link, wires mobile toggle.
- Effect on other files: Central shared-layout behavior across pages.

### `scripts/generate-locations-from-lgas.js`
- What it does: CLI transform from CSV to coordinate JSON.
- How it does it: Reads `config/lgas.csv`, outputs `locations_coords.json`.
- Effect on other files: Regenerates geodata used by map-related logic.

### `scripts/transform-lgas-to-coords.js`
- What it does: Alternate CSV parser/transform script.
- How it does it: Robust CSV parsing with quoted-field support, writes same output file.
- Effect on other files: Duplicate tooling path for `locations_coords.json` generation.

### `scripts/upload.php`
- What it does: Standalone upload utility page/API.
- How it does it: Accepts multipart images, uploads to Cloudinary, stores media metadata.
- Effect on other files: Overlaps with `api/incidents/photos.php`; adds another upload path.

### `scripts/pricing.json`
- What it does: Pricing constants for job invoices.
- How it does it: Base and surcharge values + currency.
- Effect on other files: Used by `api/jobs/create-job.php` and `api/pay.php`.

### `scripts/invoice.json`
- What it does: Invoice/payment UI config.
- How it does it: Bank/contact info, Paystack public key, WhatsApp number.
- Effect on other files: Used by `scripts/pay.js` and `api/pay-init.php`.

### `scripts/advice.js`
- What it does: Placeholder file.
- How it does it: Empty.
- Effect on other files: None.

## Styles

### `styles/landing.css`
- What it does: Landing page styling.
- How it does it: Theme variables, hero/map layout, cards, CTA, floating report button.
- Effect on other files: Visual presentation for `index.html`.

### `styles/incidents.css`
- What it does: Incidents page styling.
- How it does it: Dashboard grid, charts, table/cards, filters, mobile sheet.
- Effect on other files: Required for readable analytics/list UI in `incidents.html` and reused in detail page.

### `styles/incident-detail.css`
- What it does: Detail page-specific overrides.
- How it does it: Gallery, map container, status states, responsive detail grid.
- Effect on other files: Complements base incidents styles in `incident-detail.html`.

### `styles/report.css`
- What it does: Report form page styles.
- How it does it: Carded form layout, sections, controls, responsive behavior.
- Effect on other files: Applied by `report.html`.

### `styles/pay.css`
- What it does: Invoice page theme.
- How it does it: Print-friendly invoice layout with action buttons and footer icons.
- Effect on other files: Applied by `pay.html`.

### `styles/forms.css`
- What it does: Shared form control styles.
- How it does it: Global input/select/textarea styles and focus states.
- Effect on other files: Cross-page form consistency; can override page-specific controls.

### `styles/nav.css`
- What it does: Shared top navigation styles.
- How it does it: Fixed header, mobile menu behavior, baseline responsive resets.
- Effect on other files: Used by pages injecting `nav.html`.

### `styles/footer.css`
- What it does: Shared footer styles.
- How it does it: Layout/typography for footer fragment.
- Effect on other files: Used with `footer.html`.

### `styles/agent.css`
- What it does: Placeholder stylesheet.
- How it does it: Empty.
- Effect on other files: None.

### `old.css`
- What it does: Older incidents-page stylesheet.
- How it does it: Full legacy layout rules overlapping with current styles.
- Effect on other files: Potential confusion/tech debt if reintroduced.

## API Endpoints

### `api/subscribe.php`
- What it does: Saves community subscriptions.
- How it does it: Validates POST, tries DB insert (`comm_sub`), falls back to CSV.
- Effect on other files: Called by `scripts/whatsapp.js`.

### `api/pay.php`
- What it does: Returns job + computed invoice totals.
- How it does it: Loads `job_requests` row and computes price from `scripts/pricing.json`.
- Effect on other files: Core data source for `scripts/pay.js`.

### `api/pay-init.php`
- What it does: Payment initialization payload for Paystack.
- How it does it: Recomputes amount in kobo and returns public key/customer info.
- Effect on other files: Used by `scripts/pay.js` before opening Paystack widget.

### `api/pay-verify.php`
- What it does: Verifies Paystack transaction server-side.
- How it does it: Calls Paystack verify API with secret key, inserts into `payments`, marks job as paid.
- Effect on other files: Finalizes payment state consumed by operations/admin.

### `api/ai/summary/incidents.php`
- What it does: Creates a simple 7-day incident summary text.
- How it does it: Aggregates recent incidents in PHP and builds human-readable sentence.
- Effect on other files: Populates AI summary cards in `incidents.js`.

### `api/incidents/list.php`
- What it does: Paginated incidents list with filters/sort.
- How it does it: Dynamic WHERE clause + schema-aware SELECT fallback for missing columns.
- Effect on other files: Feeds table/cards and map in incidents/admin flows.

### `api/incidents/incident-detail.php`
- What it does: Returns one incident with derived fields.
- How it does it: Fetches by ID and computes duration/is_closed.
- Effect on other files: Used by detail page and admin edit/view.

### `api/incidents/reports.php`
- What it does: Inserts new incident reports.
- How it does it: Validates required fields from JSON, inserts with pending status.
- Effect on other files: Main write endpoint for `report.html`/`scripts/reports.js`.

### `api/incidents/report.php`
- What it does: Older report endpoint with CAPTCHA and reporter table handling.
- How it does it: Session CAPTCHA + schema checks + inserts reporter/incidents row.
- Effect on other files: Legacy path, mostly tied to old `scripts/report.js` flow.

### `api/incidents/photos.php`
- What it does: Uploads and retrieves incident photos.
- How it does it: POST uploads to Cloudinary + DB insert; GET returns media rows.
- Effect on other files: Used by `scripts/photos.js`.

### `api/incidents/status-update.php`
- What it does: Updates incident status.
- How it does it: POST JSON update with closed-time handling.
- Effect on other files: Used by admin pages for moderation.

### `api/incidents/stats/summary.php`
- What it does: Summary counts endpoint.
- How it does it: Returns totals for incidents/LGAs/communities with filters.
- Effect on other files: Used by `scripts/incidents.js` summary card.

### `api/incidents/stats/status-summary.php`
- What it does: Aggregated status counts.
- How it does it: GROUP BY status with filter support.
- Effect on other files: Preferred source for status donut chart.

### `api/incidents/stats/types.php`
- What it does: Top incident types.
- How it does it: GROUP BY type count, optional filters.
- Effect on other files: Used for type bar chart.

### `api/incidents/stats/timeline.php`
- What it does: Daily timeline counts.
- How it does it: Fetches rows then groups by date in PHP.
- Effect on other files: Used for timeline line chart.

### `api/incidents/stats/victims.php`
- What it does: Victim/casualty/injured/missing totals.
- How it does it: SUM queries with column-existence fallbacks.
- Effect on other files: Used by victim stats cards.

### `api/incidents/advice.php`
- What it does: Placeholder endpoint file.
- How it does it: Empty.
- Effect on other files: None currently.

### `api/jobs/create-job.php`
- What it does: Creates job requests with dynamic pricing response.
- How it does it: Validates JSON payload, computes price, inserts row, returns request id + price.
- Effect on other files: Main endpoint for `scripts/agent-req.js`.

### `api/jobs/jobs.php`
- What it does: Lists/fetches/updates job requests.
- How it does it: GET for list/single, POST for status update.
- Effect on other files: Used by admin request dashboard scripts.

### `api/jobs/agent-req.php`
- What it does: Older simple job creation endpoint.
- How it does it: Validates limited fields then inserts pending request.
- Effect on other files: Alternative legacy API path.

### `api/jobs/pay.php`
- What it does: Placeholder file.
- How it does it: Empty.
- Effect on other files: None.

### `api/incidents/error_log`
- What it does: Runtime log output.
- How it does it: PHP error_log writes captured errors.
- Effect on other files: Diagnostic artifact from incidents endpoints.

### `api/incidents/stats/error_log`
- What it does: Runtime log output for stats.
- How it does it: PHP error_log writes DB/auth issues.
- Effect on other files: Diagnostic artifact for stats endpoints.

## Admin (`xadmin/`)

### `xadmin/index.html`
- What it does: Incidents admin table page.
- How it does it: Displays list with sortable created-time and quick status actions.
- Effect on other files: Uses `xadmin/adminy.js`; hits incidents list/status APIs.

### `xadmin/adminx.html`
- What it does: Agent requests admin page.
- How it does it: Table + modal for viewing/updating job request status.
- Effect on other files: Uses `xadmin/agent-req-admin.js`; depends on jobs APIs.

### `xadmin/rprt.html`
- What it does: Admin report/edit form page.
- How it does it: Similar form structure to public report page.
- Effect on other files: Uses `xadmin/rprt.js` and `xadmin/rprt.php` update route.

### `xadmin/adminy.js`
- What it does: Incident admin interactions.
- How it does it: Loads incidents, merges status-based fetches, updates statuses.
- Effect on other files: Writes to `api/incidents/status-update.php`.

### `xadmin/agent-req-admin.js`
- What it does: Agent requests admin interactions.
- How it does it: Contains two IIFEs; both implement list/view/status update flows.
- Effect on other files: Talks to `/safer/api/jobs/jobs.php` and `/safer/api/jobs.php`; duplicate logic increases maintenance risk.

### `xadmin/incidents-admin.js`
- What it does: Alternate incidents admin script.
- How it does it: List/view/approve/close actions.
- Effect on other files: Similar responsibility to `adminy.js`; duplicate admin logic.

### `xadmin/rprt.js`
- What it does: Admin report editor controller.
- How it does it: If `id` exists, loads incident and updates via `xadmin/rprt.php`; otherwise creates new report.
- Effect on other files: Bridges admin form to incident update/create APIs.

### `xadmin/rprt.php`
- What it does: Incident update endpoint for admin report editor.
- How it does it: Maps input keys to DB columns and runs dynamic `UPDATE`.
- Effect on other files: Used by `xadmin/rprt.js` for edits.

### `xadmin/admin.css`
- What it does: Admin dashboard styling.
- How it does it: Table, modal, status select theme.
- Effect on other files: Visual framework for `index.html`/`adminx.html`.

### `xadmin/rprt.css`
- What it does: Admin report form styling.
- How it does it: Fork of report page CSS.
- Effect on other files: Visual style for `xadmin/rprt.html`.

## Assets

### `assets/logo.svg`
- What it does: Brand logo.
- How it does it: Vector image used in nav/invoice.
- Effect on other files: Appears in `nav.html`, `pay.html`, and placeholder photo rendering.

### `assets/community-index.png`
- What it does: Community CTA image.
- How it does it: Static image in landing page CTA section.
- Effect on other files: Used by `index.html`.

### `assets/speeding driver.jpg`
- What it does: Static image asset.
- How it does it: Stored image file.
- Effect on other files: No direct current reference found.

## Third-Party and Generated Directories

### `vendor/` (many files)
- What it does: Composer-installed third-party libraries and autoloader.
- How it does it: Generated by Composer from `composer.json`/`composer.lock`.
- Effect on other files: Required by Cloudinary-dependent PHP scripts (`api/incidents/photos.php`, `scripts/upload.php`).

## Key Cross-File Flows

1. Public incident browsing
- `incidents.html` -> `scripts/incidents.js`/`scripts/search.js`/`scripts/map.js` -> `api/incidents/list.php` + `api/incidents/stats/*` + `api/ai/summary/incidents.php`.

2. Incident reporting
- `report.html` -> `scripts/reports.js` -> `api/incidents/reports.php` -> optional media upload to `api/incidents/photos.php`.

3. Incident detail
- `incident-detail.html` -> `scripts/incident-detail.js` + `scripts/photos.js` -> `api/incidents/incident-detail.php` + `api/incidents/photos.php`.

4. Agent request and payment
- `agent-request.html` -> `scripts/agent-req.js` -> `api/jobs/create-job.php` -> redirect `pay.html` -> `scripts/pay.js` -> `api/pay.php` / `api/pay-init.php` / `api/pay-verify.php`.

5. Admin operations
- `xadmin/*.html` + scripts -> incidents/job admin APIs for moderation and status updates.

## Important Maintenance Notes

- There are duplicate/legacy paths: `scripts/report.js` vs `scripts/reports.js`, `api/incidents/report.php` vs `api/incidents/reports.php`, and multiple admin scripts for similar duties.
- Some files are placeholders/empty (`scripts/advice.js`, `styles/agent.css`, `api/incidents/advice.php`, `api/jobs/pay.php`, `db-test`).
- `config/database.php` contains hardcoded production-like credentials; this is high risk and should be moved fully to environment variables.
- There are hardcoded Cloudinary credentials in upload endpoints; also high risk.

## Scraper Module Updates (`/scraper`)

This section reflects the new scraper pipeline currently in `safer/scraper`.

### `scraper/index.php`
- What it does: Renders the scraper dashboard.
- How it does it: Loads scraper classes/config, reads summary counts and latest records from `RawStore`, prints sources/raw/candidates/log tables, and offers a "Run scraper now" form.
- Effect on other files: Entry UI that depends on all scraper classes and reads `scraper/data/*.txt`.

### `scraper/run.php`
- What it does: Executes one scraper cycle.
- How it does it: POST-only route that wires dependencies (`HttpClient`, `LinkExtractor`, `ArticleFetcher`, `DuplicateDetector`, `CandidateDetector`, `ScrapeRunner`), runs `runOnce()`, then redirects back to dashboard with run stats in query string.
- Effect on other files: Main trigger for writes to `raw_items.txt`, `candidates.txt`, seen files, and run log.

### `scraper/README.md`
- What it does: Documents the scraper MVP.
- How it does it: Explains requirements, workflow, source config, dedupe logic, candidate scoring, and NDJSON storage.
- Effect on other files: Operational guide for editing `config/*` and understanding `src/*` behavior.

### `scraper/assets/app.css`
- What it does: Styles scraper dashboard UI.
- How it does it: Card/table/log/accordion layout with responsive rules and badge states.
- Effect on other files: Visual presentation for `scraper/index.php`.

### `scraper/assets/app.js`
- What it does: Small UX control for run button.
- How it does it: Disables the submit button and switches label to "Running..." on form submit.
- Effect on other files: Prevents repeat submits against `run.php`.

### `scraper/config/app.php`
- What it does: Runtime tuning config.
- How it does it: Defines limits and thresholds (max sources/links, min text length, candidate threshold, near-duplicate threshold, user-agent, timeout).
- Effect on other files: Used by `HttpClient`, `DuplicateDetector`, `CandidateDetector`, and `ScrapeRunner` decision logic.

### `scraper/config/keywords.php`
- What it does: Incident keyword dictionary by category.
- How it does it: Maps incident types (kidnapping, violence, crash, fire, flood/disaster) to keyword lists.
- Effect on other files: `CandidateDetector` scoring and matched incident-type output.

### `scraper/config/places.php`
- What it does: Nigeria place keyword dictionary.
- How it does it: Flat list of states/cities/place aliases.
- Effect on other files: `CandidateDetector` place match scoring.

### `scraper/config/sources.php`
- What it does: Source registry for scraping.
- How it does it: Defines source arrays (`name`, `domain`, `enabled`, `rss_urls`, `list_urls`, `allowed_path_hints`, `blocked_path_hints`); currently ~39 configured sources including media + agency/government sites.
- Effect on other files: `SourceManager` and `ScrapeRunner` use it to determine what to fetch and which links are valid.

### `scraper/src/Helpers.php`
- What it does: Shared utility layer.
- How it does it: File I/O helpers (safe append/read/count), JSONL helpers, URL normalization, HTML escaping, date parsing, similarity text normalization, simple language heuristic.
- Effect on other files: Foundation utility used throughout all scraper classes.

### `scraper/src/HttpClient.php`
- What it does: Fetches listing/article HTML.
- How it does it: cURL GET with redirects, UA/timeout config, SSL checks, content-type gate for HTML responses.
- Effect on other files: Used by `ScrapeRunner` listing fetch and `ArticleFetcher` article fetch.

### `scraper/src/SourceManager.php`
- What it does: Source access helper.
- How it does it: Returns all sources or enabled subset, applying max limit.
- Effect on other files: `ScrapeRunner` uses it to choose sources per run.

### `scraper/src/LinkExtractor.php`
- What it does: Extracts candidate article links from listing pages.
- How it does it: Parses `<a>` tags, resolves/normalizes URLs, filters by same domain + allowed path hints, blocks known non-article patterns.
- Effect on other files: Feeds article URL queue in `ScrapeRunner`.

### `scraper/src/ContentExtractor.php`
- What it does: Extracts title/snippet/content/publish date from article HTML.
- How it does it: DOM parse + meta tag fallbacks (`og:title`, description, article publish time), removes noisy nodes, derives plain-text body and language guess.
- Effect on other files: `ArticleFetcher` uses it for normalized article payload.

### `scraper/src/ArticleFetcher.php`
- What it does: Fetch + extract wrapper for one article URL.
- How it does it: Calls `HttpClient->get()`, then `ContentExtractor->extract()`, returns normalized article array with status/error.
- Effect on other files: Called by `ScrapeRunner` for each queued article link.

### `scraper/src/RawStore.php`
- What it does: Append-only storage gateway.
- How it does it: Manages `raw_items.txt`, `candidates.txt`, `seen_hashes.txt`, `seen_urls.txt`, `run_log.txt`; supports append/read/count/latest helpers.
- Effect on other files: Central persistence for run outputs, dedupe lookups, and dashboard reads.

### `scraper/src/DuplicateDetector.php`
- What it does: Exact + near-duplicate detection.
- How it does it: Exact hash from `url|title` + seen URL/hash checks; near-duplicate check via normalized fingerprint and `similar_text` threshold against recent fingerprints.
- Effect on other files: `ScrapeRunner` uses it to skip duplicate raw saves.

### `scraper/src/CandidateDetector.php`
- What it does: Rule-based incident candidacy scoring.
- How it does it: Scores keyword matches (type buckets), place matches, recency hints/date, and strong-source bonus; returns candidate only when score >= threshold.
- Effect on other files: Drives writes to `candidates.txt` and candidate metrics in dashboard.

### `scraper/src/RssCollector.php`
- What it does: Collects article links from RSS/Atom feeds.
- How it does it: Fetches feed XML by cURL, parses RSS and Atom items, normalizes links/titles/snippets/published date, filters by source domain, deduplicates by URL.
- Effect on other files: `ScrapeRunner` merges RSS-derived links with listing-derived links before article fetch.

### `scraper/src/ScrapeRunner.php`
- What it does: End-to-end orchestrator for one scraping cycle.
- How it does it: Iterates enabled sources, collects links from RSS + listing pages, fetches articles, enforces content/language checks, runs dedupe checks, saves raw items, runs candidate detection, logs detailed run steps/stats.
- Effect on other files: Main engine that produces all artifacts in `scraper/data` and powers dashboard counters/tables.

### `scraper/data/raw_items.txt`
- What it does: NDJSON store of normalized raw fetched items.
- How it does it: One JSON object per line appended by `RawStore::appendRawItem()`.
- Effect on other files: Source for dashboard "Latest Raw Items" and dedupe fingerprint history.

### `scraper/data/candidates.txt`
- What it does: NDJSON store of scored candidate items.
- How it does it: Appended when `CandidateDetector` returns a passing score.
- Effect on other files: Source for dashboard "Latest Candidates".

### `scraper/data/seen_hashes.txt`
- What it does: Exact dedupe hash list.
- How it does it: Appends each saved raw item's `exact_hash`.
- Effect on other files: Used by `DuplicateDetector` exact duplicate check.

### `scraper/data/seen_urls.txt`
- What it does: Seen article URL list.
- How it does it: Appends each saved raw item's `article_url`.
- Effect on other files: Used by `RawStore::hasSeenUrl()` and duplicate skipping.

### `scraper/data/run_log.txt`
- What it does: Operational run logs.
- How it does it: Timestamped line appends from `RawStore::log()`.
- Effect on other files: Displayed in dashboard and used for latest run time.

## Scraper Flow (Current)

1. `scraper/index.php` dashboard -> POST `scraper/run.php`.
2. `run.php` builds dependencies -> `ScrapeRunner::runOnce()`.
3. For each enabled source: RSS links + listing-page links collected.
4. Each article fetched/extracted -> duplicate checks -> raw save.
5. Candidate scoring runs on saved raw items -> candidate save if score passes threshold.
6. Dashboard reload reads updated data/log artifacts from `scraper/data`.
