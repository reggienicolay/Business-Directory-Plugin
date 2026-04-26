# Business Directory Pro

> For full feature docs see README.md, architecture details see DEVELOPER.md, API reference see API.md.
> This file is for **Claude-specific development instructions** — what to do, what not to do, and where things live.

## Identity

- **Version:** 0.1.12 | **Post type:** `bd_business` | **Namespace:** `BD\`
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
- **Never put REST endpoints in `src/API/`** — that directory contains legacy/helper endpoints. The proper, secured controllers live in `src/REST/`.
- Never use `__return_true` as a `permission_callback` on endpoints that modify data
- Never store user-uploaded files without server-side MIME validation — see `SubmitReviewController::get_real_mime_type()` for the correct pattern
- Never skip the Turnstile check when the site key is configured — the condition must be: if key exists AND token is empty → reject. Don't use `&& ! empty($token)` which allows bypass by omission.
- Don't instantiate `Plugin` directly — use `Plugin::instance()` (singleton)
- Don't add new `require_once` to `business-directory.php` unless the class has constants accessed before autoload runs

## Architecture

### Two REST Directories (important!)
```
src/API/     ← Legacy/helper endpoints (ListsEndpoint, BadgeEndpoint, CollaboratorsEndpoint,
               GeocodeEndpoint, FeatureEndpoint, CoverEndpoint, BusinessEndpoint)
               Loaded via require_once in business-directory.php
               SubmissionEndpoint.php was DELETED in the April 2026 audit (insecure duplicate)

src/REST/    ← Proper controllers with rate limiting + CAPTCHA + server-side MIME validation
               SubmitBusinessController.php  ← business submissions
               SubmitReviewController.php    ← review submissions
               ClaimController.php           ← claim submissions
               BusinessesController.php      ← GET /businesses (with batch_load for N+1 prevention)
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
src/API/            # Legacy/helper REST endpoints (Lists, Badge, Collaborators, Geocode, Feature, Cover, Business)
src/Auth/SSO/       # Multisite single sign-on
src/BusinessTools/  # Owner dashboard, widgets, badge generator, QR generator, stats email
src/DB/             # Tables, installer, migrations (DB version in bd_db_version option)
src/Explore/        # Explore pages + router + cache invalidation
src/Exporter/       # CSV/JSON export pipeline
src/Forms/          # Form handlers (BusinessSubmission, ReviewSubmission, ClaimRequest)
src/Frontend/       # Shortcodes, profile, registration, edit listing, view tracker, ReviewPrompts (category → prompt-chip suggestions)
src/Gamification/   # Points, badges, leaderboards, ranks, activity tracking
src/Importer/       # Batch importer (CSV/JSON ingestion)
src/Install/        # Activation hooks beyond DB installer
src/Integrations/   # Third-party integrations (EventsCalendar/, etc.)
src/Lists/          # User-curated lists + collaboration + network lists
src/Media/          # Image optimization pipeline (WebP, custom sizes, EXIF stripping)
src/Moderation/     # Cross-cutting moderation helpers
src/Notifications/  # Email/in-app notification dispatch
src/PostTypes/      # bd_business CPT registration
src/REST/           # Hardened public-submission controllers (rate limit + CAPTCHA + MIME validation)
src/Roles/          # Custom capabilities + role mapping
src/Search/         # FilterHandler, QueryBuilder, Geocoder
src/Security/       # RateLimit, Captcha, MimeValidator
src/SEO/            # SlugMigration (301 redirects for old taxonomy URLs)
src/Social/         # OpenGraph, social sharing (OG defers to bd-seo when active)
src/Taxonomies/     # bd_category, bd_area, bd_tag registration
src/Utils/          # Misc helpers (PlaceholderImage, etc.)
includes/           # Feature loaders (geolocation, gamification, embeds, social, auth, seo, media, etc.)
templates/          # WP templates (single-business-premium.php, directory.php, profile.php, explore-*)
tests/Unit/         # PHPUnit tests
```

### Database — 17 Custom Tables
All prefixed `wp_bd_`. Schema managed by `DB\Installer` using `dbDelta()`. Version tracked in `bd_db_version` option (current: 2.7.0).

Core: `locations`, `reviews`, `submissions`, `claim_requests`, `change_requests`, `review_helpful`
Gamification: `user_reputation`, `user_activity`, `badge_awards`
Lists: `lists`, `list_items`, `list_collaborators`, `list_follows`
Sharing/Widgets: `share_tracking`, `qr_scans`, `widget_clicks`, `widget_domains`

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
- `bd_filter_metadata` transient — 60 min TTL, auto-invalidated on `save_post_bd_business` / `delete_post` via `FilterHandler::invalidate_filter_cache()`
- `CacheWarmer.php` pre-populates directory transients
- `ExploreCacheInvalidator` clears on business/review changes
- `batch_load_locations()` uses bounding-box SQL pre-filter when radius is set (eliminates 90-95% of rows before PHP Haversine)
- `update_object_term_cache()` primed before term loops in `BusinessesController` and `immersive.php`
- `_prime_post_caches()` for thumbnails in `BusinessesController` and reviews in `ReviewsQueue`
- Video cover thumbnails: external API calls blocked on frontend (admin/cron only), cached 24h
- Composite `idx_lat_lng` index on `wp_bd_locations` for bounding-box queries (DB v2.7.0)

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

## Image Optimizer

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

### WebP Delivery (Phase 2 — v0.1.8)

`src/Media/ImageHelper.php` + `includes/image-helper-functions.php` — reads `_bd_webp_sizes` meta and builds `<picture>` elements with WebP `<source>` + JPEG/PNG `<img>` fallback:
- `bd_picture( $attachment_id, $size, $attrs )` — builds `<picture>` for any attachment
- `bd_post_picture( $post_id, $size, $attrs )` — resolves featured image, then calls `bd_picture()`
- Falls back to plain `<img>` when no WebP variant exists (safe for pre-optimizer uploads)
- Currently used by: explore/search result cards (`ExploreCardRenderer`), business detail gallery thumbnails (`immersive.php`)
- Hero background image uses CSS `background-image` and cannot use `<picture>` — would need a template refactor to convert to an `<img>` element

## SEO Integration

SEO is handled by the **bd-seo companion plugin** (separate repo). BD Pro's role:
- `src/SEO/SlugMigration.php` — 301 redirects for old taxonomy URLs
- `src/Social/OpenGraph.php` — OG output, defers to bd-seo when active (`class_exists('BD\SEO\OpenGraphManager')`)
- `src/Frontend/ListSocialMeta.php` — List OG output, same deferral pattern
- `src/Explore/ExploreRouter.php` — Canonical + title for explore pages (bd-seo coordinates with these)
- Post meta keys `_bd_seo_title`, `_bd_seo_description`, `_bd_schema_json` are the integration seam — any plugin writes, bd-seo reads and outputs

## Security Audit Status (v0.1.8, April 2026)

Four-pass audit completed. Issue trajectory: 15 → 10 → 7 → 1 → clean.

**All fixed:**
- REST N+1 patterns, unprepared SQL, missing permission callbacks
- SSO token IP validation, spoofable header hardening, logout redirect validation
- List reorder/quick-save IDOR (ownership + collaborator checks added)
- GeocodeEndpoint rate limiting (10 req/min per IP)
- Open redirect in RegistrationHandler (wp_safe_redirect)
- Font Awesome 6.5.1 (17 v5 icon renames)
- ReviewsQueue batch cache priming
- Leaflet re-init guard on detail pages

**Remaining documented items (low priority / other repos):**
- bd-seo AutoLinker: nested `<a>` tags on Guides page city names (separate repo, not a BD Pro fix)
- List invite token rate limiting on `/lists/join` (token is 32 bytes, brute-force impractical)
- render_my_lists card-level N+1 (low traffic page)

## Gotchas
- Two namespaces coexist: `BD\` (primary) and `BusinessDirectory\` (legacy in Search/) — both map to `src/`
- Multisite features gated behind `bd_enable_local_features` option
- Review photos stored as WP attachment IDs in `photo_ids` column (comma-separated)
- Contact data stored in serialized `bd_contact` post meta (not individual `bd_phone`/`bd_email`/`bd_website` keys)
- Social links stored in serialized `bd_social` post meta (not `bd_social_facebook` etc.)
- Address: check `wp_bd_locations` table first, fall back to `bd_location` post meta (legacy)
- Font Awesome 6.5.1: all icons use v6 names (`fa-xmark` not `fa-times`, `fa-circle-check` not `fa-check-circle`, etc.). See FA 6 migration guide if adding new icons.
- Video cover thumbnails: `CoverManager::get_video_thumbnail_url()` returns false on frontend cache miss — thumbnails populate via admin views only. If you need immediate thumbnails, trigger from WP-Cron.
- Public submission endpoints live in `src/REST/` (not `src/API/`) — SubmitBusinessController, SubmitReviewController, ClaimController
