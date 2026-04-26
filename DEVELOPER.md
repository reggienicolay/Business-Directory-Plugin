# Business Directory Pro -- Developer Guide

**Version:** 0.1.11
**Author:** Reggie Nicolay
**License:** GPL v2 or later
**Requires:** PHP 8.0+, WordPress 6.2+

---

## 1. Quick Start

### Prerequisites

| Requirement | Version |
|-------------|---------|
| PHP | 8.0+ |
| Composer | Latest |
| WordPress | 6.2+ |
| Dev Environment | Local by Flywheel (or any WP dev environment) |

### Commands

| Task | Command |
|------|---------|
| Install dependencies | `composer install` |
| Set up test framework (one-time) | `bash bin/install-wp-tests.sh` |
| Run all tests | `WP_TESTS_DIR=/tmp/wordpress-tests-lib php vendor/bin/phpunit` |
| Run single test file | `WP_TESTS_DIR=/tmp/wordpress-tests-lib php vendor/bin/phpunit tests/Unit/FilterHandlerTest.php` |
| Lint (PHPCS) | `composer phpcs` |
| Auto-fix lint | `composer phpcbf` |
| Static analysis | `vendor/bin/phpstan analyse` |

---

## 2. Architecture Overview

The plugin bootstraps in `business-directory.php` through the following sequence:

| Step | Action | Details |
|------|--------|---------|
| 1 | Define constants | `BD_VERSION`, `BD_PLUGIN_FILE`, `BD_PLUGIN_DIR`, `BD_PLUGIN_URL`, `BD_PLUGIN_BASENAME` |
| 2 | Composer autoload | Loads `vendor/autoload.php` if present |
| 3 | PSR-4 fallback autoloader | `spl_autoload_register` maps `BD\` namespace to `src/` |
| 4 | Explicit requires | BadgeSystem, ActivityTracker, Plugin, ListManager, ListCollaborators, 4 API endpoints, 4 Admin classes, 2 DB classes, Exporter, 2 Frontend classes |
| 5 | Activation hook | `DB\Installer::activate()` + `FrontendEditorInstaller::install()` |
| 6 | Deactivation hook | `DB\Installer::deactivate()` (flushes rewrite rules) |
| 7 | DB migration init | `DB\Installer::init()` registers `plugins_loaded` (priority 5) for version checks |
| 8 | `plugins_loaded` hook | `Plugin::instance()` singleton + component initialization (SSO, EditListing, ChangeRequestsQueue, DuplicatesAdmin, ExporterPage, BadgeDisplay, ReviewsAdmin, ShortcodesAdmin, ListsAdmin, ListDisplay, MenuOrganizer, GuideProfileFields) |
| 9 | Feature loaders | Conditional requires from `includes/` directory (see table below) |
| 10 | Template filter | `single_template` filter for `bd_business` post type pointing to `templates/single-business-premium.php` |

### Feature Loaders (includes/)

| Loader | Purpose |
|--------|---------|
| `directory-loader.php` | Search, filters, maps |
| `geolocation-loader.php` | Location features |
| `gamification-loader.php` | Badge system |
| `feature-embed-loader.php` | Featured businesses |
| `social-sharing-loader.php` | Social integration |
| `cover-media-loader.php` | Cover images (loaded from plugin root) |
| `business-tools-loader.php` | Owner dashboard |
| `integrations-loader.php` | External integrations |
| `explore-loader.php` | Explore pages |
| `auth-loader.php` | Authentication |
| `seo-loader.php` | SEO: taxonomy slug migration, 301 redirects, rewrite rule fix |
| `guides-loader.php` | Guides system (unconditional require) |

### Plugin Singleton

`BD\Plugin` (`src/Plugin.php`) handles:
- Post type and taxonomy registration via `init` hook
- Text domain loading
- Admin and frontend asset registration (`wp_enqueue_scripts`, `admin_enqueue_scripts`)
- REST route registration via `rest_api_init`
- Component initialization (admin classes when `is_admin()`, frontend shortcodes and forms always)

---

## 3. Namespaces & Autoloading

| Namespace | Directory | Source |
|-----------|-----------|--------|
| `BD\` | `src/` | PSR-4 via `composer.json` + manual fallback autoloader |
| `BD\Tests\` | `tests/` | PSR-4 via `composer.json` (autoload-dev) |
| `BusinessDirectory\` | `src/` | Used by 20 files across 5 modules (see table below) |

**Known inconsistency:** 20 files use the `BusinessDirectory\` namespace instead of `BD\`. Both namespaces coexist. The manual fallback autoloader only handles the `BD\` prefix — files under `BusinessDirectory\` are loaded via their respective loader files in `includes/` (e.g., `directory-loader.php`, `explore-loader.php`) or explicit `require_once` statements.

### `BusinessDirectory\` Namespace Files

| Module | File Count | Files |
|--------|-----------|-------|
| `Explore\` | 12 | ExploreAssets, ExploreCardRenderer, ExploreEditorial, ExploreLoader, ExploreMapRenderer, ExploreNavigationRenderer, ExploreQuery, ExploreRenderer, ExploreRouter, ExploreCacheInvalidator, ExploreSitemap, ExploreSitemapProvider |
| `Search\` | 3 | FilterHandler, QueryBuilder, Geocoder |
| `API\` | 2 | BusinessEndpoint, GeocodeEndpoint |
| `Utils\` | 2 | Cache, PlaceholderImage |
| `Frontend\` | 1 | Filters |

The fallback autoloader is registered via `spl_autoload_register` in the main plugin file and maps `BD\` class names to file paths under `src/` using PSR-4 conventions (namespace separators become directory separators).

---

## 4. Directory Structure

```
business-directory/
+-- business-directory.php     # Main plugin file, bootstrap
+-- composer.json              # Dependencies & autoloading
+-- phpunit.xml                # Test configuration
+-- phpstan.neon               # Static analysis config
+-- src/                       # All PHP source code
|   +-- Plugin.php             # Singleton, core initialization
|   +-- Admin/                 # Admin UI, settings, moderation
|   +-- API/                   # REST API endpoints
|   +-- Auth/                  # Login, registration, SSO
|   |   +-- SSO/              # Multisite single sign-on
|   +-- BusinessTools/         # Owner dashboard, widgets, QR, badges
|   +-- DB/                    # Database CRUD, installer, duplicates
|   +-- Explore/               # Explore pages, routing, sitemap (12 files)
|   +-- Exporter/              # CSV export
|   +-- Forms/                 # Frontend submission forms
|   +-- Frontend/              # Shortcodes, display, templates
|   +-- Gamification/          # Badges, activity tracking, ranks
|   +-- Importer/              # CSV import
|   +-- Install/               # Activation installers
|   +-- Integrations/          # External integrations
|   |   +-- EventsCalendar/   # The Events Calendar integration
|   +-- Lists/                 # List CRUD, collaborators, covers
|   +-- Moderation/            # Review/submission approval queues
|   +-- Notifications/         # Email notifications
|   +-- PostTypes/             # Custom post type registration
|   +-- REST/                  # REST controllers (4 files)
|   +-- Roles/                 # Custom roles & capabilities
|   +-- Search/                # Query building, filtering
|   +-- Security/              # Rate limiting, CAPTCHA
|   +-- SEO/                   # Taxonomy slug migration, 301 redirects
|   +-- Social/                # Share tracking, OG tags
|   +-- Taxonomies/            # Category, Area, Tag registration
|   +-- Utils/                 # Cache, validation utilities
+-- tests/                     # PHPUnit tests
|   +-- bootstrap.php          # Test framework bootstrap
|   +-- Unit/                  # 11 test files
+-- templates/                 # PHP templates for frontend
+-- assets/
|   +-- css/                   # Stylesheets
|   +-- js/                    # JavaScript files
+-- includes/                  # Feature loader files
+-- bin/
    +-- install-wp-tests.sh    # Test framework installer
```

---

## 5. Custom Post Types & Taxonomies

| Type | Slug | Rewrite | Hierarchical | Notes |
|------|------|---------|--------------|-------|
| `bd_business` (post type) | /places | /places/%postname% | N/A | Main business listing; supports title, editor, thumbnail, excerpt |
| `bd_category` (taxonomy) | -- | /places/category/%term% | Yes | Business categories (Restaurants, Shops, etc.) |
| `bd_area` (taxonomy) | -- | /places/area/%term% | Yes | Geographic areas (cities, neighborhoods) |
| `bd_tag` (taxonomy) | -- | /places/tag/%term% | No | Free-form business tags |

**Source files:** `src/PostTypes/Business.php`, `src/Taxonomies/Category.php`, `src/Taxonomies/Area.php`, `src/Taxonomies/Tag.php`

---

## 6. Database Schema

All 17 custom tables are created via `src/DB/Installer.php` using `dbDelta()`. Table prefix is `wp_` (configurable via `$wpdb->prefix`).

### `wp_bd_locations`

| Column | Type | Key | Notes |
|--------|------|-----|-------|
| business_id | bigint(20) UNSIGNED | PK | Links to wp_posts.ID |
| lat | double | IDX | Latitude |
| lng | double | IDX | Longitude |
| geohash | char(12) | IDX | Spatial hash for proximity queries |
| address | varchar(255) | | Street address |
| city | varchar(120) | | City |
| state | varchar(80) | | State/province |
| postal_code | varchar(20) | | ZIP/postal code |
| country | varchar(80) | | Country |

### `wp_bd_reviews`

| Column | Type | Key | Notes |
|--------|------|-----|-------|
| id | bigint(20) UNSIGNED | PK, AUTO | |
| business_id | bigint(20) UNSIGNED | IDX | |
| user_id | bigint(20) UNSIGNED | IDX | Nullable for guest reviews |
| author_name | varchar(120) | | |
| author_email | varchar(120) | | Added in 2.2.0 |
| rating | tinyint(1) UNSIGNED | | 1-5 star rating |
| title | varchar(180) | | |
| content | text | | Review body |
| photo_ids | text | | JSON array of attachment IDs |
| status | varchar(20) | IDX | Default: `pending` |
| helpful_count | int(11) UNSIGNED | | Default: 0. Added in 2.6.0 |
| ip_address | varchar(45) | | IPv4/IPv6. Added in 2.2.0 |
| created_at | datetime | IDX | Default: CURRENT_TIMESTAMP |

### `wp_bd_submissions`

| Column | Type | Key | Notes |
|--------|------|-----|-------|
| id | bigint(20) UNSIGNED | PK, AUTO | |
| business_data | longtext | | JSON object with all business fields |
| submitted_by | bigint(20) UNSIGNED | | User ID (nullable) |
| submitter_name | varchar(120) | | |
| submitter_email | varchar(120) | | |
| ip_address | varchar(45) | | |
| status | varchar(20) | IDX | Default: `pending` |
| admin_notes | text | | |
| created_at | datetime | IDX | Default: CURRENT_TIMESTAMP |

### `wp_bd_claim_requests`

| Column | Type | Key | Notes |
|--------|------|-----|-------|
| id | bigint(20) UNSIGNED | PK, AUTO | |
| business_id | bigint(20) UNSIGNED | IDX | |
| user_id | bigint(20) UNSIGNED | IDX | |
| claimant_name | varchar(120) | | NOT NULL |
| claimant_email | varchar(120) | IDX | NOT NULL |
| claimant_phone | varchar(20) | | |
| relationship | varchar(50) | | |
| proof_files | longtext | | JSON. Added in 2.4.0 |
| message | text | | |
| status | varchar(20) | IDX | Default: `pending` |
| admin_notes | text | | |
| reviewed_by | bigint(20) UNSIGNED | | Admin user ID |
| reviewed_at | datetime | | |
| created_at | datetime | IDX | Default: CURRENT_TIMESTAMP |

### `wp_bd_change_requests`

| Column | Type | Key | Notes |
|--------|------|-----|-------|
| id | bigint(20) UNSIGNED | PK, AUTO | |
| business_id | bigint(20) UNSIGNED | IDX | |
| user_id | bigint(20) UNSIGNED | IDX | NOT NULL |
| status | varchar(20) | IDX | Default: `pending` |
| changes_json | longtext | | NOT NULL. JSON of proposed changes |
| original_json | longtext | | JSON snapshot of original data |
| change_summary | text | | Human-readable summary |
| admin_notes | text | | |
| created_at | datetime | IDX | Default: CURRENT_TIMESTAMP |
| updated_at | datetime | | ON UPDATE CURRENT_TIMESTAMP |
| reviewed_at | datetime | | |
| reviewed_by | bigint(20) UNSIGNED | | Admin user ID |

### `wp_bd_user_reputation`

| Column | Type | Key | Notes |
|--------|------|-----|-------|
| user_id | bigint(20) UNSIGNED | PK | |
| points | int(11) | | Default: 0 |
| level | varchar(50) | | Default: `newcomer` |
| reviews_count | int(11) | | Default: 0 |
| helpful_votes | int(11) | | Default: 0 |
| photos_count | int(11) | | Default: 0 |
| lists_count | int(11) | | Default: 0 |
| claims_count | int(11) | | Default: 0 |
| shares_count | int(11) | | Default: 0 |
| streak_days | int(11) | | Default: 0 |
| last_activity | datetime | | |
| created_at | datetime | | Default: CURRENT_TIMESTAMP |
| updated_at | datetime | | ON UPDATE CURRENT_TIMESTAMP |

### `wp_bd_badge_awards`

| Column | Type | Key | Notes |
|--------|------|-----|-------|
| id | bigint(20) UNSIGNED | PK, AUTO | |
| user_id | bigint(20) UNSIGNED | IDX | |
| badge_id | varchar(50) | IDX | |
| awarded_at | datetime | | Default: CURRENT_TIMESTAMP |
| | | UNIQUE(user_id, badge_id) | Prevents duplicate awards |

### `wp_bd_user_activity`

| Column | Type | Key | Notes |
|--------|------|-----|-------|
| id | bigint(20) UNSIGNED | PK, AUTO | |
| user_id | bigint(20) UNSIGNED | IDX | |
| action_type | varchar(50) | IDX | Activity type identifier |
| points_earned | int(11) | | Default: 0 |
| reference_type | varchar(50) | | E.g., `review`, `list` |
| reference_id | bigint(20) UNSIGNED | | ID of related entity |
| metadata | longtext | | JSON additional data |
| created_at | datetime | IDX | Default: CURRENT_TIMESTAMP |

### `wp_bd_lists`

| Column | Type | Key | Notes |
|--------|------|-----|-------|
| id | bigint(20) UNSIGNED | PK, AUTO | |
| user_id | bigint(20) UNSIGNED | IDX | |
| name | varchar(200) | | NOT NULL |
| slug | varchar(220) | UNIQUE | |
| description | text | | |
| visibility | varchar(20) | IDX | Default: `private` |
| featured | tinyint(1) | IDX | Default: 0 |
| cover_image_id | bigint(20) UNSIGNED | | Attachment ID |
| view_count | int(11) | | Default: 0 |
| cached_categories | varchar(500) | | Added in 2.5.0 |
| cached_city | varchar(100) | IDX | Added in 2.5.0 |
| theme_override | varchar(200) | | Added in 2.5.0 |
| invite_token | varchar(64) | IDX | Added in 2.3.0 |
| invite_mode | varchar(20) | | Default: `approval`. Added in 2.3.0 |
| created_at | datetime | | Default: CURRENT_TIMESTAMP |
| updated_at | datetime | IDX | ON UPDATE CURRENT_TIMESTAMP |

### `wp_bd_list_items`

| Column | Type | Key | Notes |
|--------|------|-----|-------|
| id | bigint(20) UNSIGNED | PK, AUTO | |
| list_id | bigint(20) UNSIGNED | IDX | |
| business_id | bigint(20) UNSIGNED | IDX | |
| note | text | | User note about the business |
| sort_order | int(11) | | Default: 0 |
| added_by | bigint(20) UNSIGNED | IDX | Added in 2.3.0 |
| created_at | datetime | | Default: CURRENT_TIMESTAMP |
| | | UNIQUE(list_id, business_id) | One entry per business per list |

### `wp_bd_list_follows`

| Column | Type | Key | Notes |
|--------|------|-----|-------|
| id | bigint(20) UNSIGNED | PK, AUTO | |
| list_id | bigint(20) UNSIGNED | IDX | |
| user_id | bigint(20) UNSIGNED | IDX | |
| created_at | datetime | | Default: CURRENT_TIMESTAMP |
| | | UNIQUE(list_id, user_id) | One follow per user per list |

### `wp_bd_list_collaborators`

| Column | Type | Key | Notes |
|--------|------|-----|-------|
| id | bigint(20) UNSIGNED | PK, AUTO | |
| list_id | bigint(20) UNSIGNED | IDX | |
| user_id | bigint(20) UNSIGNED | IDX | |
| role | varchar(20) | | Default: `contributor` |
| status | varchar(20) | IDX | Default: `pending` |
| invited_by | bigint(20) UNSIGNED | | |
| created_at | datetime | | Default: CURRENT_TIMESTAMP |
| | | UNIQUE(list_id, user_id) | One role per user per list |

### `wp_bd_share_tracking`

| Column | Type | Key | Notes |
|--------|------|-----|-------|
| id | bigint(20) UNSIGNED | PK, AUTO | |
| user_id | bigint(20) UNSIGNED | IDX | Nullable |
| business_id | bigint(20) UNSIGNED | IDX | |
| platform | varchar(50) | IDX | E.g., `facebook`, `twitter` |
| ip_address | varchar(45) | | |
| user_agent | varchar(255) | | |
| created_at | datetime | IDX | Default: CURRENT_TIMESTAMP |

### `wp_bd_qr_scans`

| Column | Type | Key | Notes |
|--------|------|-----|-------|
| id | bigint(20) UNSIGNED | PK, AUTO | |
| business_id | bigint(20) UNSIGNED | IDX | |
| scan_type | varchar(50) | | Default: `qr` |
| ip_address | varchar(45) | | |
| user_agent | varchar(255) | | |
| referrer | varchar(500) | | |
| created_at | datetime | IDX | Default: CURRENT_TIMESTAMP |

### `wp_bd_widget_clicks`

| Column | Type | Key | Notes |
|--------|------|-----|-------|
| id | bigint(20) UNSIGNED | PK, AUTO | |
| business_id | bigint(20) UNSIGNED | IDX | |
| domain | varchar(255) | IDX | Embedding domain |
| click_type | varchar(50) | | Default: `view` |
| ip_address | varchar(45) | | |
| created_at | datetime | IDX | Default: CURRENT_TIMESTAMP |

### `wp_bd_widget_domains`

| Column | Type | Key | Notes |
|--------|------|-----|-------|
| id | bigint(20) UNSIGNED | PK, AUTO | |
| business_id | bigint(20) UNSIGNED | IDX | |
| domain | varchar(255) | IDX | NOT NULL |
| status | varchar(20) | IDX | Default: `pending` |
| created_at | datetime | | Default: CURRENT_TIMESTAMP |

### `wp_bd_review_helpful`

| Column | Type | Key | Notes |
|--------|------|-----|-------|
| id | bigint(20) UNSIGNED | PK, AUTO | |
| review_id | bigint(20) UNSIGNED | IDX | |
| user_id | bigint(20) UNSIGNED | IDX | |
| created_at | datetime | | Default: CURRENT_TIMESTAMP |
| | | UNIQUE(review_id, user_id) | One vote per user per review |

### Database Migrations

Migrations run automatically via `plugins_loaded` hook (priority 5) through `Installer::maybe_upgrade()`. The installer compares the stored `bd_db_version` option against the `DB_VERSION` constant and runs incremental upgrades.

| Version | Changes |
|---------|---------|
| 2.2.0 | Added `author_email`, `ip_address` columns to `wp_bd_reviews` |
| 2.3.0 | Added `invite_token`, `invite_mode` to `wp_bd_lists`; `added_by` to `wp_bd_list_items` |
| 2.4.0 | Added `wp_bd_claim_requests` and `wp_bd_change_requests` tables; `proof_files` column to claims |
| 2.5.0 | Added `cached_categories`, `cached_city`, `theme_override` to `wp_bd_lists`; triggers list cache refresh |
| 2.6.0 | Added `helpful_count` to `wp_bd_reviews`; created `wp_bd_review_helpful` table |
| 2.7.0 | Added composite `idx_lat_lng` index on `wp_bd_locations` for bounding-box queries |

Current DB_VERSION: `2.7.0`

---

## 7. Core Modules

| Module | Key Files | Description |
|--------|-----------|-------------|
| **DB Layer** | `src/DB/ReviewsTable.php`, `LocationsTable.php`, `SubmissionsTable.php`, `ClaimRequestsTable.php`, `ChangeRequestsTable.php`, `DuplicateFinder.php`, `DuplicateMerger.php`, `Installer.php` | Static CRUD classes using `$wpdb` prepared statements. Return `WP_Error` on validation failure. Installer handles table creation and versioned migrations. |
| **Search** | `src/Search/FilterHandler.php`, `QueryBuilder.php`, `Geocoder.php` | FilterHandler sanitizes all search inputs. QueryBuilder constructs WordPress queries with haversine distance calculation and taxonomy/meta filtering. Results cached via 5-minute transients. |
| **Security** | `src/Security/RateLimit.php`, `Captcha.php` | RateLimit checks IP-based request counts via transients. Captcha integrates Cloudflare Turnstile. |
| **Utils** | `src/Utils/Validation.php`, `Cache.php` | Validation provides lat/lng/phone/URL sanitization. Cache provides a transient-based caching layer. |
| **Gamification** | `src/Gamification/BadgeSystem.php`, `ActivityTracker.php`, `GamificationHooks.php`, `CoverMediaGamification.php` | BadgeSystem defines 35 badges as constants (auto-awarded and manual). ActivityTracker logs user actions and calculates points. 6 ranks from Newcomer (0 pts) to Legend (1000+ pts). |
| **Lists** | `src/Lists/ListManager.php`, `ListCollaborators.php`, `CoverManager.php` | ListManager handles list CRUD with slug generation and visibility control. ListCollaborators manages invite tokens, join requests, and role-based permissions (contributor/editor/owner). CoverManager handles list cover images. |
| **Moderation** | `src/Moderation/ReviewsQueue.php`, `SubmissionsQueue.php`; `src/Admin/ClaimsQueue.php`, `ChangeRequestsQueue.php` | Queue classes for reviews, submissions, claims, and change requests. Each provides admin list table with approve/reject actions. |
| **Social** | `src/Social/ShareTracker.php`, `OpenGraph.php`, `ShareButtons.php`, `ImageGenerator.php` | ShareTracker records shares with daily limits per type. OpenGraph generates meta tags. ImageGenerator creates social share images. |
| **Frontend** | `src/Frontend/Shortcodes.php`, `QuickFilterDisplay.php`, `ListDisplay.php`, `BadgeDisplay.php`, `EditListing.php`, `ProfileEditor.php`, `FeatureShortcode.php`, `RegistrationShortcode.php`, `GuidesShortcode.php` | Shortcodes register directory display, featured businesses, guides, registration, and login. Template override via `single_template` filter. Assets conditionally enqueued. |
| **Explore** | `src/Explore/ExploreRouter.php`, `ExploreQuery.php`, `ExploreRenderer.php`, `ExploreSitemap.php`, `ExploreAssets.php`, `ExploreMapRenderer.php`, + 6 more | Router handles `/explore/` URL structure. Query builds explore-specific queries. Sitemap generates XML sitemaps for explore pages. |
| **Auth** | `src/Auth/AuthHandler.php`, `LoginModal.php`, `HeaderButtons.php`, `AuthFilters.php`, `LoginShortcode.php`; `src/Auth/SSO/SSOHandler.php`, `TokenManager.php`, `Loader.php` | AuthHandler manages login/registration. SSO provides multisite single sign-on (initialized only when `is_multisite()`). LoginModal renders the frontend login overlay. |
| **BusinessTools** | `src/BusinessTools/ToolsDashboard.php`, `WidgetGenerator.php`, `WidgetEndpoint.php`, `QRGenerator.php`, `BadgeGenerator.php`, `StatsEmail.php` | Owner dashboard with embeddable widgets, QR code generation, badge display, and periodic stats emails. |
| **SEO** | `src/SEO/SlugMigration.php`; `includes/seo-loader.php` | Taxonomy URL migration with 301 redirects from old slugs (`/category/`, `/area/`, `/tag/`) to new SEO-friendly paths (`/places/category/`, etc.). Supports hierarchical terms, pagination, feeds, Unicode slugs, subdirectory installs, and query string preservation. Includes rewrite rule priority fix to prevent taxonomy/post-type URL conflicts. Loaded via `seo-loader.php` on `init` priority 25. |
| **Integrations** | `src/Integrations/IntegrationsManager.php`, `EventsCalendar/` | External integrations framework. Currently integrates with The Events Calendar (venue syncing, business linking, city events shortcode). |

---

## 8. REST API Layer

The plugin registers endpoints under the `bd/v1` namespace across two patterns:

### WP REST Controllers (`src/REST/`)

Follow the WordPress `WP_REST_Controller` pattern. Registered via `Plugin::register_rest_routes()`.

| Controller | Endpoints |
|-----------|-----------|
| `BusinessesController` | `GET /businesses` |
| `SubmitBusinessController` | `POST /submit-business` |
| `SubmitReviewController` | `POST /submit-review` |
| `ClaimController` | `POST /claim`, `GET /claims`, `POST /claims/{id}/approve`, `POST /claims/{id}/reject` |

### Custom Endpoint Classes (`src/API/`)

Register their own routes independently via `rest_api_init`.

| Class | Purpose |
|-------|---------|
| `ListsEndpoint` | List CRUD operations |
| `CollaboratorsEndpoint` | Collaboration management |
| `BadgeEndpoint` | Embeddable badge images |
| `SubmissionEndpoint` | Enhanced submission with media |
| `CoverEndpoint` | Cover media management |
| `FeatureEndpoint` | Cross-site embed data (CORS) |
| `GeocodeEndpoint` | Forward/reverse geocoding |

---

## 9. Shortcodes

The plugin registers **27 shortcodes** (24 unique + 3 aliases). Grouped by function:

### Core Directory

| Shortcode | Attributes | Source File | Description |
|-----------|-----------|-------------|-------------|
| `[bd_directory]` | `view` (map/list), `category`, `per_page` (20) | `src/Frontend/Shortcodes.php` | Main directory with map/list toggle |
| `[business_filters]` | -- | `src/Frontend/Filters.php` | Standalone filter sidebar panel |
| `[business_directory_complete]` | -- | `src/Frontend/Filters.php` | Complete directory (filters + map + list) with layout detection |

### Forms & Submissions

| Shortcode | Attributes | Source File | Description |
|-----------|-----------|-------------|-------------|
| `[bd_submit_business]` | -- | `src/Forms/BusinessSubmission.php` | Business submission form with photo/video upload |
| `[bd_submit_review]` | `business_id` (0) | `src/Forms/ReviewSubmission.php` | Review submission form for a specific business |
| `[bd_edit_listing]` | -- | `src/Frontend/EditListing.php` | Frontend form for business owners to submit changes |
| `[bd_register]` | -- | `src/Frontend/RegistrationShortcode.php` | Registration page with benefits showcase |

### User & Profile

| Shortcode | Attributes | Source File | Description |
|-----------|-----------|-------------|-------------|
| `[bd_login]` | `tab` (login/register/reset), `redirect_to`, `show_title` (yes) | `src/Auth/LoginShortcode.php` | Login/register/password reset form |
| `[bd_auth_buttons]` | `style` (default/compact/icon-only), `show_avatar` (yes), `show_dropdown` (yes), `login_text`, `register_text` | `src/Auth/HeaderButtons.php` | Header auth buttons or user dropdown |
| `[bd_auth_nav]` | _(alias for `bd_auth_buttons`)_ | `src/Auth/HeaderButtons.php` | Alias |
| `[bd_profile]` | `user` (slug), `user_id` (0) | `src/Frontend/Profile.php` | Profile view with badges, reviews, lists, gamification |
| `[bd_public_profile]` | _(alias for `bd_profile`)_ | `src/Frontend/Profile.php` | Alias |
| `[bd_guides]` | `limit` (-1), `city`, `columns` (3), `layout` (cards/compact/featured) | `src/Frontend/GuidesShortcode.php` | Community guides grid |

### Lists & Bookmarks

| Shortcode | Attributes | Source File | Description |
|-----------|-----------|-------------|-------------|
| `[bd_my_lists]` | -- | `src/Frontend/ListDisplay.php` | Current user's lists |
| `[bd_public_lists]` | -- | `src/Frontend/ListDisplay.php` | All public lists |
| `[bd_list]` | -- | `src/Frontend/ListDisplay.php` | Single list display |
| `[bd_save_button]` | -- | `src/Frontend/ListDisplay.php` | Save/bookmark button for businesses |
| `[bd_network_lists]` | `city`, `source`, `limit` (6), `columns` (3), `layout` (grid/list/compact), `show_covers` (yes), `show_author` (yes), `show_count` (yes), `has_cover`, `min_items` (3), `orderby` (updated_at), `cache` (60), `title`, `view_all_url`, `class` | `src/Frontend/NetworkLists.php` | Display lists from main site on network subsites |

### Business Tools

| Shortcode | Attributes | Source File | Description |
|-----------|-----------|-------------|-------------|
| `[bd_business_tools]` | -- | `src/BusinessTools/ToolsDashboard.php` | Marketing tools dashboard (widgets, QR, badges) |
| `[bd_owner_dashboard]` | _(alias for `bd_business_tools`)_ | `src/BusinessTools/ToolsDashboard.php` | Alias |

### Features & Embeds

| Shortcode | Attributes | Source File | Description |
|-----------|-----------|-------------|-------------|
| `[bd_feature]` | `id`, `ids` (comma-separated), `layout` (card/list/inline/mini), `columns` (3), `show` (image,title,rating,excerpt,category,cta), `cta_text` (View Details), `source`, `class` | `src/Frontend/FeatureShortcode.php` | Embed businesses in posts/pages |

### Social & Gamification

| Shortcode | Attributes | Source File | Description |
|-----------|-----------|-------------|-------------|
| `[bd_share_buttons]` | `type` (business), `id` (0), `url`, `title`, `text`, `style` (horizontal), `show_counts` (yes) | `src/Social/ShareButtons.php` | Social share buttons with point rewards |
| `[bd_badge_gallery]` | -- | `src/Frontend/BadgeDisplay.php` | All available badges in gallery format |

### Events Integration

| Shortcode | Attributes | Source File | Description |
|-----------|-----------|-------------|-------------|
| `[bd_business_events]` | -- | `src/Integrations/EventsCalendar/EventsCalendarIntegration.php` | Events for a specific business |
| `[bd_city_events]` | `city` (required), `limit` (10), `layout` (grid/list/compact), `columns` (3), `show_business` (true), `show_image` (true), `show_venue` (true), `show_time` (true), `title`, `view_all_url`, `source`, `cache` (15) | `src/Integrations/EventsCalendar/CityEventsShortcode.php` | City events from The Events Calendar |

### Utilities

| Shortcode | Attributes | Source File | Description |
|-----------|-----------|-------------|-------------|
| `[bd_placeholder]` | `id` (0), `name`, `category` (default), `lat` (37.6819), `lng` (-121.7680), `width` (300px), `height` (200px) | `includes/placeholder-image-helper.php` | Testing placeholder images (requires `edit_posts` cap) |

---

## 10. Configuration (wp_options)

| Option Key | Type | Description |
|------------|------|-------------|
| `bd_db_version` | string | Current database schema version (compared against `DB_VERSION` constant) |
| `bd_turnstile_site_key` | string | Cloudflare Turnstile site key for CAPTCHA |
| `bd_turnstile_secret_key` | string | Cloudflare Turnstile secret key for CAPTCHA |
| `bd_notification_emails` | string | Comma-separated admin notification email addresses |
| `bd_business_tools_page` | int | Page ID for the business owner tools dashboard |
| `bd_edit_listing_page` | int | Page ID for the frontend listing edit form |
| `bd_directory_layout` | string | Directory view style (`classic` or `quick-filter`) |
| `bd_detail_layout` | string | Business detail page layout (`classic` or `immersive`) |
| `bd_lists_needs_cache_refresh` | bool | Flag to trigger list cache refresh on next `init` hook |
| `bd_enable_local_features` | bool | Multisite: enable local DB tables on a subsite |

---

## 11. WordPress Hooks

### Actions Fired by the Plugin

| Action | When | Parameters |
|--------|------|------------|
| `bd_badges_earned` | After badges are checked and awarded | `$user_id`, `$new_badges` |
| `bd_badge_awarded` | Single badge manually awarded | `$user_id`, `$badge_key`, `$awarded_by` |
| `bd_before_duplicate_merge` | Before merging duplicate businesses | `$canonical_id`, `$duplicate_id` |
| `bd_seo_before_slug_redirect` | Before a 301 taxonomy slug redirect | `$redirect_url`, `$request_uri` |
| `bd_claim_approved` | After a claim is approved (form-submitted OR in-field grant) | `$claim_id`, `$business_id`, `$user_id` |
| `bd_access_granted` | Specifically after an in-field grant (not a form claim) — fires in addition to `bd_claim_approved` so companion plugins can differentiate the two flows | `$claim_id`, `$business_id`, `$user_id`, `$relationship` (`owner`/`manager`/`staff`/`other`), `$granted_by` (admin user ID) |
| `bd_access_revoked` | After an approved claim is revoked via the Grant Access UI | `$claim_id`, `$business_id`, `$user_id`, `$revoked_by`, `$new_primary_id` (null if no remaining owners) |

### Key WordPress Hooks Used

| Hook | Priority | Handler |
|------|----------|---------|
| `plugins_loaded` | 5 | `Installer::maybe_upgrade()` |
| `plugins_loaded` | default | `Plugin::instance()` + feature loaders |
| `init` | default | Post types, taxonomies, text domain |
| `init` | 20 | `Installer::maybe_refresh_list_caches()` |
| `init` | 25 | `SEO\init_seo_components()` (SlugMigration) |
| `template_redirect` | 1 | `SlugMigration::maybe_redirect()` (301 redirects on 404s) |
| `rewrite_rules_array` | 999 | `SEO\fix_taxonomy_rewrite_priority()` (taxonomy rules before post type) |
| `rest_api_init` | default | All REST route registration |
| `admin_enqueue_scripts` | default | Admin CSS/JS (scoped to `bd_business` screens) |
| `wp_enqueue_scripts` | default | Frontend CSS/JS (registered, conditionally enqueued) |
| `single_template` | default | Custom business page template |
| `register_activation_hook` | -- | `Installer::activate()` + `FrontendEditorInstaller::install()` |
| `register_deactivation_hook` | -- | `Installer::deactivate()` |
| `save_post_bd_business` | 10 | `FilterHandler::invalidate_filter_cache()` — clears filter metadata transient |
| `delete_post` | default | `FilterHandler::invalidate_filter_cache()` — same, catches business deletions |
| `admin_bar_menu` | 100 | `GrantAccessToolbar::add_node()` — 🔑 Grant Access on single business pages |

### Performance Architecture (v0.1.11)

Query budget targets established during four-pass production audit:

| Page type | Target queries | Key optimizations |
|-----------|---------------|-------------------|
| Directory search (20 results) | ~5 | Batch cache priming (meta, terms, thumbnails), `get_the_terms()` over `wp_get_post_terms()` |
| Business detail (immersive) | ~8 | `update_object_term_cache()` before term lookups, Leaflet re-init guard |
| Single list view (50 items) | ~7 | Reuse loaded items for map data (no double-fetch) |
| My Lists dashboard (12 cards) | ~30 | Video thumbnails return placeholder on frontend cache miss (no blocking API calls) |
| Featured businesses validation | 1 | Single `get_posts()` with `fields=ids` + `orderby=post__in` |
| Radius search | ~250 candidates | Bounding-box SQL pre-filter (lat/lng BETWEEN) before PHP Haversine loop |

**Rules for new code:**
- Always call `update_object_term_cache()` or `update_meta_cache()` before loops that read term/meta data
- Use `get_the_terms()` (cache-aware) not `wp_get_post_terms()` (always queries)
- Use `_prime_post_caches()` for attachment/thumbnail lookups before loops
- Never call `wp_remote_get/head()` on frontend page renders — cache in admin or cron only
- Filter metadata transient is 60 min — if you add a new filter dimension, add it to `FilterHandler::get_filter_metadata()` and it auto-inherits the TTL + invalidation

---

## 12. Extending the Plugin

### Adding a New Badge

1. Add an entry to `BadgeSystem::BADGES` constant in `src/Gamification/BadgeSystem.php` with required keys: `name`, `icon`, `color`, `description`, `requirement`, `rarity`, `points`.
2. Add the badge key to the appropriate group in `BadgeSystem::BADGE_CATEGORIES`.
3. For auto-awarded badges: set `check` (string matching a stat field or custom check case) and `threshold` (int).
4. For manual badges: set `'manual' => true` and omit `check`/`threshold`.

**Rarity levels:** `common`, `rare`, `epic`, `legendary`, `special`

### Adding a REST Endpoint

1. Create a class in `src/API/` or `src/REST/`.
2. Register routes via the `rest_api_init` hook.
3. Use `register_rest_route('bd/v1', '/your-route', [...])`.
4. Follow existing pattern: `permission_callback`, `validate_callback`, `sanitize_callback`.

REST controllers in `src/REST/` are registered through `Plugin::register_rest_routes()`. API endpoints in `src/API/` register themselves independently.

### Custom Templates

The plugin uses the `single_template` filter to load `templates/single-business-premium.php` for `bd_business` posts. To override in your theme, place a `single-bd_business.php` template in your theme directory -- WordPress will prioritize the theme template.

### Adding a Database Table

1. Add `CREATE TABLE` SQL in `Installer::create_tables()`.
2. Add migration logic in `Installer::upgrade_database()` with a `version_compare` check.
3. Bump the `DB_VERSION` constant in `Installer`.
4. Create a corresponding Table class in `src/DB/` following the existing static CRUD pattern.
5. Write tests in `tests/Unit/`.

---

## 13. Testing

| Setting | Value |
|---------|-------|
| Framework | PHPUnit 9.5 + WordPress test library (`WP_UnitTestCase`) |
| Polyfills | `yoast/phpunit-polyfills` ^4.0 |
| Bootstrap | `tests/bootstrap.php` (loads autoloader + WP test framework) |
| Config | `phpunit.xml` (testsuite points to `tests/Unit/`) |
| Coverage | `src/` directory |

### First-time Setup

The PHPUnit suite needs the WordPress test scaffolding (`wordpress-tests-lib`) installed in your `$TMPDIR`. Run once:

```bash
bin/install-wp-tests.sh <db-name> <db-user> <db-pass> [db-host] [wp-version] [skip-db-create]

# Example, Local WP defaults (MySQL on 127.0.0.1, root/root):
bin/install-wp-tests.sh wordpress_test root root 127.0.0.1 latest
```

The script downloads WP source to `/tmp/wordpress/`, the test framework to `/tmp/wordpress-tests-lib/`, and creates a fresh `<db-name>` database. Re-running is safe — it skips re-downloads. After install, `composer test` should boot.

### Test Inventory

| File | Tests | Module Covered |
|------|-------|----------------|
| `ValidationTest.php` | 32 | Input validation (lat/lng/phone/URL) |
| `FilterHandlerTest.php` | 46 | Search filter sanitization |
| `QueryBuilderTest.php` | 39 | Query building, haversine distance |
| `BadgeSystemTest.php` | 19 | Badge/rank structure validation |
| `ReviewsTableTest.php` | 51 | Review CRUD + moderation |
| `LocationsTableTest.php` | 21 | Location CRUD |
| `SubmissionsTableTest.php` | 13 | Business submission queue |
| `ClaimRequestsTableTest.php` | 17 | Claim workflow |
| `ChangeRequestsTableTest.php` | 21 | Edit request queue |
| `RateLimitTest.php` | 21 | Rate limiting + IP detection |
| `CacheTest.php` | 12 | Transient caching |
| **Total** | **292** | **11 files, ~1,150 assertions** |

### Writing a New Test

Create a file in `tests/Unit/` following this pattern:

1. Use the `BD\Tests\Unit` namespace.
2. Extend `WP_UnitTestCase`.
3. Name the class to match the filename (e.g., `YourClassTest` in `YourClassTest.php`).
4. Prefix test methods with `test_`.
5. Call `Installer::activate()` in `wpSetUpBeforeClass` if your test needs custom tables.
6. Use WordPress factory methods (`self::factory()->post->create()`, etc.) for test data.
7. Each test runs in a transaction that is rolled back automatically.

---

## 14. Frontend Assets

Assets are **registered** on `wp_enqueue_scripts` and **conditionally enqueued** by shortcodes and templates as needed. All assets use the `BD_VERSION` constant (`0.1.11`) for cache busting.

### Key JavaScript Files

| File | Size | Purpose |
|------|------|---------|
| `business-directory.js` | 51 KB | Main directory functionality |
| `quick-filters.js` | 42 KB | Advanced filtering UI |
| `lists.js` | 38 KB | List display and interaction |
| `cover-editor.js` | 34 KB | Cover image editor |
| `auth.js` | 19 KB | Authentication flows |
| `business-detail-immersive.js` | 13 KB | Immersive detail layout |
| `business-detail.js` | 11 KB | Single business page |
| `list-collaborators.js` | -- | Collaborative list editing |
| `social-sharing.js` | -- | Social share integration |
| `explore-map.js` | -- | Explore page map |

### Admin Assets

| Handle | File | Loaded On |
|--------|------|-----------|
| `bd-admin` | `assets/css/admin.css` | `bd_business` admin screens |
| `bd-admin` | `assets/js/admin.js` | `bd_business` admin screens |
| `bd-admin-map` | `assets/js/admin-map.js` | `post.php` and `post-new.php` only |

### CSS Theme Variants

| File | Purpose |
|------|---------|
| `filters-modern.css` | Modern filter bar theme |
| `filters-playful.css` | Playful filter bar theme |
| `filters-premium.css` | Premium filter bar theme |
| `design-tokens.css` | Design system tokens (colors, spacing, typography) |

### Asset Registration Pattern

Admin assets are enqueued on the `admin_enqueue_scripts` hook, scoped to the `bd_business` post type screen. The admin map script is further scoped to `post.php` and `post-new.php` hooks only.

Frontend assets are registered globally but only enqueued when a relevant shortcode or template is rendered.

---

## 15. Gamification Ranks

| Points Threshold | Rank Name | Description |
|-----------------|-----------|-------------|
| 0 | Newcomer | Just getting started |
| 50 | Local | Finding your way around |
| 150 | Regular | A familiar face in the community |
| 300 | Insider | You know all the best spots |
| 600 | VIP | Elite community member |
| 1000 | Legend | A true legend |

### Badge Categories

| Category | Badge Count | Type |
|----------|------------|------|
| Community Status | 4 | Mostly manual/special |
| Review Milestones | 5 | Auto (review_count threshold) |
| Quality & Engagement | 5 | Auto (helpful_votes, photos, detailed reviews) |
| Explorer | 3 | Auto (categories_reviewed, first_reviews) |
| Specialty | 4 | Auto (food/wine/retail/wellness reviews) |
| Timing & Consistency | 4 | Auto (morning/evening/weekend/streak) |
| Curator | 6 | Auto (list_count, qualifying_lists, saves, collaborators, covers) |
| Social | 2 | Auto (social_shares, follower_count) |
| Seasonal & Events | 2 | Manual |
| **Total** | **35** | |

**Badge rarity levels:** `common`, `rare`, `epic`, `legendary`, `special`

---

## 16. Dev Dependencies

| Package | Version | Purpose |
|---------|---------|---------|
| `phpunit/phpunit` | ^9.5 | Unit testing |
| `wp-coding-standards/wpcs` | ^3.0 | WordPress coding standards |
| `squizlabs/php_codesniffer` | ^3.7 | PHP linting |
| `phpstan/phpstan` | ^2.1 | Static analysis |
| `php-stubs/wordpress-stubs` | ^6.9 | WP function stubs for PHPStan |
| `szepeviktor/phpstan-wordpress` | ^2.0 | WordPress-specific PHPStan rules |
| `yoast/phpunit-polyfills` | ^4.0 | PHPUnit polyfills for WP test compat |

---

## 17. SEO Architecture

The SEO system consists of two layers: core plugin enhancements and a companion plugin.

### Core SEO (`src/SEO/` + `includes/seo-loader.php`)

Taxonomy URL migration and rewrite rule management, loaded via `seo-loader.php`.

| Component | File | Purpose |
|-----------|------|---------|
| SEO Loader | `includes/seo-loader.php` | Central loader with defensive `file_exists()` checks. Defines `BD_SEO_LOADER_LOADED` constant. Hooks `init` (priority 25) and `rewrite_rules_array` (priority 999). |
| SlugMigration | `src/SEO/SlugMigration.php` | 301 redirects from old taxonomy URLs to new SEO-friendly paths. Only runs on 404 pages for performance. |
| Rewrite Fix | `includes/seo-loader.php` | `fix_taxonomy_rewrite_priority()` moves `places/(category\|area\|tag)/` rules before post type rules to prevent URL conflicts. |

**Taxonomy URL Migration:**

| Old URL | New URL | Taxonomy |
|---------|---------|----------|
| `/category/{slug}/` | `/places/category/{slug}/` | `bd_category` |
| `/area/{slug}/` | `/places/area/{slug}/` | `bd_area` |
| `/tag/{slug}/` | `/places/tag/{slug}/` | `bd_tag` |

SlugMigration supports: hierarchical terms (`/category/food/italian/`), pagination (`/page/2/`), feeds (`/feed/rss/`), query strings, subdirectory installs, and Unicode slugs.

**Future SEO components** (stubs in `seo-loader.php`):
- `AutoLinker` — Auto-link city/category mentions in descriptions
- `RelatedBusinesses` — "Also in [City]" cross-links
- `MultisiteCanonical` — REST API canonical URLs for subsites

### BD-SEO Companion Plugin (`wp-content/plugins/bd-seo/`)

Standalone plugin providing structured data and meta tags. Requires Business Directory Pro to be active.

| Feature | Class | Description |
|---------|-------|-------------|
| JSON-LD Structured Data | `StructuredData.php` | LocalBusiness schema (with subtype mapping), AggregateRating, Review schema |
| Schema Type Mapping | `SchemaTypeMap.php` | Maps BD tags to 40+ Schema.org types (e.g., `winery` → `Winery`) |
| Meta Tags | `MetaTags.php` | Dynamic `<title>`, `<meta description>`, Open Graph tags, canonical URLs |
| Breadcrumbs | `Breadcrumbs.php` | BreadcrumbList JSON-LD + HTML breadcrumbs. Shortcode: `[bd_breadcrumbs]` |
| Sitemap | `Sitemap.php` | Extends WordPress core sitemaps for BD content |
| Taxonomy Meta | `TaxonomyMeta.php` | Admin fields for per-taxonomy SEO title/description overrides |
| Author Schema | `Author/PersonSchema.php`, `ArticleSchema.php`, `AuthorCanonical.php` | Person and Article schema for author authority |

```
bd-seo/
+-- bd-seo.php              # Main plugin file (v1.0.1, BD\SEO namespace)
+-- src/
|   +-- Plugin.php           # Core initialization
|   +-- StructuredData.php   # JSON-LD generation
|   +-- MetaTags.php         # Meta tags + canonicals
|   +-- Breadcrumbs.php      # BreadcrumbList schema + HTML
|   +-- SchemaTypeMap.php    # Tag-to-Schema.org type mapping
|   +-- Sitemap.php          # WordPress sitemap extension
|   +-- TaxonomyMeta.php     # Admin SEO fields for taxonomies
|   +-- Author/
|       +-- PersonSchema.php
|       +-- ArticleSchema.php
|       +-- AuthorCanonical.php
+-- assets/css/
    +-- breadcrumbs.css      # Breadcrumb styling
```
