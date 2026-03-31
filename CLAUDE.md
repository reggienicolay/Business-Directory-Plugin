# Business Directory Pro

> For full feature docs see README.md, architecture details see DEVELOPER.md, API reference see API.md.
> This file is for **Claude-specific development instructions** — what to do, what not to do, and where things live.

## Identity

- **Version:** 0.2.0 | **Post type:** `bd_business` | **Namespace:** `BD\`
- **Repo:** https://github.com/reggienicolay/Business-Directory-Plugin
- **Part of:** Love Tri Valley plugin suite (this + BD Event Aggregator + BD Outdoor Activities + BD Email Signatures + BD Food Truck Tracker)
- **Environment:** Local WP (dev) → Cloudways (production), WordPress multisite

## Do / Don't

### MUST DO
- Use `$wpdb->prepare()` for ALL database queries — no exceptions
- Use `wp_kses_post()` / `esc_html()` / `esc_attr()` / `esc_url()` for ALL output
- Verify nonces on every form handler and AJAX endpoint
- Check `current_user_can()` before any privileged operation
- Add rate limiting (via `Security\RateLimiter`) to any new public-facing endpoint
- Add Turnstile CAPTCHA check to any new public submission endpoint — use the pattern in `src/REST/SubmitReviewController.php` (reject when token is empty)
- Put new REST endpoints in `src/REST/` (the proper controllers directory)
- Run `composer test` and `composer phpstan` before considering work complete

### MUST NOT
- **Never put REST endpoints in `src/API/`** — that directory contains legacy/helper endpoints. The proper, secured controllers live in `src/REST/`. `src/API/SubmissionEndpoint.php` was deleted in the March 2026 audit.
- Never use `__return_true` as a `permission_callback` on endpoints that modify data
- Never store user-uploaded files without server-side MIME validation — see `SubmitReviewController::get_real_mime_type()` for the correct pattern
- Never skip the Turnstile check when the site key is configured — the condition must be: if key exists AND token is empty → reject. Don't use `&& ! empty($token)` which allows bypass by omission.
- Don't instantiate `Plugin` directly — use `Plugin::instance()` (singleton)
- Don't add new `require_once` to `business-directory.php` unless the class has constants accessed before autoload runs

## Architecture

### Two REST Directories (important!)
```
src/API/     ← Legacy endpoints (ListsEndpoint, BadgeEndpoint, CollaboratorsEndpoint, SubmissionEndpoint)
               Loaded via require_once in business-directory.php
               SubmissionEndpoint.php is a KNOWN INSECURE DUPLICATE — do not use as a pattern

src/REST/    ← Proper controllers with rate limiting + CAPTCHA + validation
               SubmitBusinessController.php  ← correct submission handler
               SubmitReviewController.php    ← correct review handler
               ClaimController.php           ← correct claim handler
               BusinessesController.php      ← GET /businesses
```

### Bootstrap Sequence
1. `business-directory.php` → constants, composer autoload, PSR-4 fallback (`BD\` → `src/`)
2. Explicit `require_once` for gamification, lists, API, admin, DB, exporter, frontend classes
3. Activation hook → `DB\Installer::activate()` + `FrontendEditorInstaller::install()`
4. `DB\Installer::init()` runs migration checks (before plugins_loaded)
5. `plugins_loaded` → `Plugin::instance()` initializes singleton, SSO, EditListing, admin queues

### Key Directories
```
src/Admin/          # Admin pages, metaboxes, moderation queues (claims, reviews, changes, covers)
src/Auth/SSO/       # Multisite single sign-on
src/BusinessTools/  # Owner dashboard
src/DB/             # Tables, installer, migrations (DB version in bd_db_version option)
src/Explore/        # Explore pages + cache invalidation
src/Forms/          # Form handlers (BusinessSubmission, ReviewSubmission, ClaimRequest)
src/Frontend/       # Shortcodes, profile, registration, edit listing, view tracker
src/Gamification/   # Points, badges, leaderboards, ranks, activity tracking
src/Lists/          # User-curated lists + collaboration + network lists
src/Media/          # Image optimization pipeline (WebP, custom sizes, EXIF stripping)
src/Search/         # FilterHandler, QueryBuilder, Geocoder
src/Security/       # RateLimiter, Captcha
src/SEO/            # SlugMigration (301 redirects for old taxonomy URLs)
src/Social/         # OpenGraph, social sharing (OG defers to bd-seo when active)
includes/           # Feature loaders (geolocation, gamification, embeds, social, auth, seo, media, etc.)
templates/          # WP templates (single-business-premium.php, directory.php, profile.php, explore-*)
tests/Unit/         # PHPUnit tests
```

### Database — 12 Custom Tables
All prefixed `wp_bd_`. Schema managed by `DB\Installer` using `dbDelta()`. Version tracked in `bd_db_version` option.

Core: `locations`, `reviews`, `submissions`, `claim_requests`, `change_requests`
Gamification: `user_reputation`, `user_activity`, `badge_awards`
Lists: `lists`, `list_items`, `list_collaborators`, `list_follows`

### Cross-Plugin Data Contract
Other plugins in the suite read/write these on `bd_business` posts:
- **BD SEO** (separate plugin, v1.4.0) reads `bd_location`, `bd_contact`, `bd_hours`, `bd_social`, `bd_avg_rating`, `bd_review_count` for schema. Reads `_bd_seo_title`, `_bd_seo_description`, `_bd_schema_json` from any post. BD Pro's `Social/OpenGraph.php` and `Frontend/ListSocialMeta.php` defer OG output to bd-seo when `BD\SEO\OpenGraphManager` class exists.
- **BD Article Generator** writes `_bd_seo_title`, `_bd_seo_description`, `_bd_schema_json`, `_bd_ag_*` meta on generated posts. BD SEO reads these.
- **BD Event Aggregator** reads `bd_business` post type, writes `bd_linked_business` meta on TEC events
- **BD Outdoor Activities** reads `bd_location` meta (lat/lng), writes `bdoutdoor_*` meta keys
- **BD Food Truck Tracker** identifies trucks via `food-trucks` term in `bd_tag` taxonomy, reads `bd_location` meta + `bd_claimed_by` meta, writes `bd_truck_*` meta keys, hooks into `bd_business_tools_after_cards` + `bd_after_about_section`
- **BD Email Signatures** reads `bd_contact` meta (phone/email/website), `bd_social` meta, `bd_location` meta + `wp_bd_locations` table, `bd_claimed_by` meta. Hooks into `bd_business_tools_after_cards`. Fires on `save_post_bd_business` and `bd_review_approved` for auto-regeneration.
- Changing the `bd_business` post type slug, `bd_location`/`bd_contact`/`bd_social` meta structure, or taxonomy slugs (`bd_category`, `bd_area`, `bd_tag`) will break satellite plugins

### Caching
- Transients for query results (5-min default), `CacheWarmer.php` pre-populates
- `ExploreCacheInvalidator` clears on business/review changes
- Geohash indexing for distance queries

## Common Tasks

**New REST endpoint:** Create in `src/REST/`, register via `rest_api_init`, add `permission_callback`, rate limiting, CAPTCHA if public-facing. Document in API.md.

**New shortcode:** Register in `src/Frontend/Shortcodes.php`, enqueue assets conditionally, document in README.md.

**DB migration:** Increment `DB_VERSION` in `DB\Installer`, add logic to `maybe_upgrade()`, use `dbDelta()`.

**Testing:**
```bash
composer test       # PHPUnit
composer phpstan    # Static analysis
```

## Gamification Badges v10

Metallic SVG badge system with 5 materials (bronze, silver, gold, platinum, diamond) and 5 shapes:
- `src/Gamification/BadgeSVG.php` — PHP port of badge-v10.jsx, renders full SVG with gradients/shimmer
- `src/Gamification/BadgeIcons.php` — ~37 Font Awesome icon SVG paths
- `src/Social/BadgeShareCard.php` — Branded share card + modal for social sharing
- `src/Frontend/BadgeDisplay.php` — SVG rendering on gallery, profile, review cards
- `assets/css/badges.css` + `assets/js/badges.js` — styles, share modal, animations

## Business Tools Widgets

Premium embeddable widgets for business owners:
- `src/BusinessTools/WidgetEndpoint.php` — SVG stars, polished cards, ResizeObserver
- `src/API/BadgeEndpoint.php` — 4 SVG themes (minimal, dark, glass, premium) with fractional stars
- `src/BusinessTools/ToolsDashboard.php` — Theme selector, live badge preview, breakdown toggle

## Multisite

- City subsites (lovedublin, lovepleasanton, etc.) do NOT have BD tables — they query the main site via API
- `bd_enable_local_features` option controls table creation per subsite
- Admin menu count queries (ReviewsQueue, ClaimRequestsTable, ChangeRequestsTable) have table existence guards — return 0 if table doesn't exist
- `CitySettings::apply_explore_card_tag_slugs()` is called from the child theme `homepage.php` — always wrapped in `method_exists()` guard

## Image Optimizer (Phase 1)

`src/Media/ImageOptimizer.php` — hooks `wp_generate_attachment_metadata` to process every upload:
- Registers 6 custom sizes: `bd-hero` (1600x900), `bd-card` (600x400), `bd-gallery-thumb` (400x300), `bd-lightbox` (1400x1050), `bd-review` (800x600), `bd-og` (1200x630)
- Generates WebP siblings for each size + original (per-size quality 75-85)
- Strips EXIF from JPEG originals (GPS, device info) via GD re-encode at quality 92
- Stores relative paths in `_bd_webp_sizes` meta (portable across servers)
- Stores absolute path in `_bd_webp_file` for CoverManager backward compat
- Cleans up all WebP siblings on `delete_attachment`
- Skips already-stripped JPEGs (prevents quality loss on `wp media regenerate`)
- `bd_image_optimizer_should_process` filter to constrain processing scope
- Loaded via `includes/media-loader.php` (immediate init, not deferred — `after_setup_theme` timing)

Phase 2 (templates + `<picture>` tags) not yet implemented — WebP files sit as unused siblings.

## SEO Integration

SEO is handled by the **bd-seo companion plugin** (separate repo). BD Pro's role:
- `src/SEO/SlugMigration.php` — 301 redirects for old taxonomy URLs
- `src/Social/OpenGraph.php` — OG output, defers to bd-seo when active (`class_exists('BD\SEO\OpenGraphManager')`)
- `src/Frontend/ListSocialMeta.php` — List OG output, same deferral pattern
- `src/Explore/ExploreRouter.php` — Canonical + title for explore pages (bd-seo coordinates with these)
- Post meta keys `_bd_seo_title`, `_bd_seo_description`, `_bd_schema_json` are the integration seam — any plugin writes, bd-seo reads and outputs

## Gotchas
- Two namespaces coexist: `BD\` (primary) and `BusinessDirectory\` (legacy in Search/) — both map to `src/`
- Multisite features gated behind `bd_enable_local_features` option
- Review photos stored as WP attachment IDs in `photo_ids` column (comma-separated)
- Contact data stored in serialized `bd_contact` post meta (not individual `bd_phone`/`bd_email`/`bd_website` keys)
- Social links stored in serialized `bd_social` post meta (not `bd_social_facebook` etc.)
- Address: check `wp_bd_locations` table first, fall back to `bd_location` post meta (legacy)
- `src/API/SubmissionEndpoint.php` was deleted (insecure duplicate) — use `src/REST/SubmitBusinessController.php`
