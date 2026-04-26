# Business Directory Pro

A modern, map-first local business directory plugin for WordPress with geolocation, reviews, gamification, and multi-city support.

![WordPress](https://img.shields.io/badge/WordPress-6.2%2B-blue)
![PHP](https://img.shields.io/badge/PHP-8.0%2B-purple)
![License](https://img.shields.io/badge/License-GPL%20v2-green)
![Version](https://img.shields.io/badge/Version-0.1.12-orange)

## Overview

Business Directory Pro is a premium WordPress plugin designed for community-focused business directories. Built with a wine country aesthetic and optimized for local business discovery, it features interactive maps, user reviews, business claiming, and a comprehensive gamification system to drive community engagement.

## Features

### Core Directory
- **Interactive Leaflet Maps** - Clustered markers, custom pins, and smooth navigation
- **Advanced Search & Filtering** - By category, area, tags, price level, and distance
- **Geolocation** - "Near Me" functionality with browser location support
- **Premium Business Listings** - Rich profiles with photos, hours, contact info, and social links
- **Multi-taxonomy Support** - Categories, areas/neighborhoods, and tags

### Reviews & Ratings
- **Star Ratings** - 1-5 star review system with photo uploads
- **Helpful Votes** - Community-driven review ranking
- **Moderation Queue** - Admin approval workflow with spam protection
- **Cloudflare Turnstile** - CAPTCHA integration for spam prevention

### Business Claiming
- **Claim Workflow** - Users can claim and manage their business listings
- **Proof Upload** - Document verification for ownership claims
- **Admin Review Queue** - Approve/reject claims with notes
- **Automatic Account Creation** - New users created on claim approval
- **In-Field Grant Access** - Directory managers can grant a known owner direct access from the business edit screen, list-table row action, or the frontend admin bar (designed for managers meeting owners in person). Supports multiple authorized users per business (e.g. owner + marketing contact) with per-user roles. See `API.md` → `/claims/grant`.

### Gamification System
- **Points & Badges** - Reward users for reviews, photos, and engagement
- **Leaderboards** - Weekly, monthly, and all-time rankings
- **User Ranks** - Progress from Newcomer to Local Legend
- **Activity Tracking** - Comprehensive engagement metrics

### Lists & Collections
- **User Lists** - Create and share curated business collections
- **Collaborative Lists** - Invite others to contribute
- **Public/Private Visibility** - Control who sees your lists
- **Follow System** - Subscribe to lists for updates

### Business Owner Tools
- **Owner Dashboard** - Manage listings, view analytics, respond to reviews
- **Edit Requests** - Submit changes for admin approval
- **Photo Management** - Upload and reorder business photos
- **QR Code Generation** - Downloadable QR codes for marketing

### Integrations
- **The Events Calendar Pro** - Link businesses to events and venues
- **Social Sharing** - Open Graph meta tags for rich social previews
- **Embed System** - Embed business cards on external sites

## Requirements

- WordPress 6.2 or higher (uses `%i` placeholder in $wpdb->prepare for table identifiers)
- PHP 8.0 or higher
- MySQL 8.0 or higher

## Installation

1. Upload the `business-directory` folder to `/wp-content/plugins/`
2. Activate the plugin through the WordPress admin
3. Navigate to **Business Directory > Settings** to configure
4. Create a page and add the `[bd_directory]` shortcode

## Shortcodes

### Recommended Page Setup

| Page Name | Shortcode | Purpose |
|-----------|-----------|---------|
| Business Directory | `[business_directory]` | Main directory with map and filters |
| My Lists | `[bd_my_lists]` | User's saved business lists (requires login) |
| Community Lists | `[bd_public_lists]` | Browse all public lists from users |
| View List | `[bd_list]` | Single list detail page (auto-detects from URL) |
| City Events | `[bd_city_events city="Livermore" source="lovetrivalley.com"]` | Events in a city (use source on sub-sites) |
| My Profile | `[bd_user_profile]` | User stats, badges, and activity |
| Badges | `[bd_badge_gallery]` | All available badges and user progress |
| Leaderboard | `[bd_leaderboard]` | Top community contributors |
| Add a Business | `[bd_submit_business]` | Public business submission form |
| Business Tools | `[bd_business_tools]` | Dashboard for claimed business owners |
| Edit Listing | `[bd_edit_listing]` | Form for owners to edit their listing |

### All Shortcodes by Category

#### Core Directory (4)

| Shortcode | Description | Key Attributes |
|-----------|-------------|----------------|
| `[business_directory]` | Main directory with map, filters, and listings | `city`, `category`, `per_page`, `show_map`, `show_filters` |
| `[bd_search]` | Standalone business search box | `placeholder`, `redirect` |
| `[bd_categories]` | List or grid of business categories | `layout`, `show_count`, `hide_empty` |
| `[bd_featured]` | Grid of featured/promoted businesses | `limit`, `category`, `columns` |

#### User Lists (4)

| Shortcode | Description | Key Attributes |
|-----------|-------------|----------------|
| `[bd_my_lists]` | Current user's saved lists with management | `per_page`, `show_create` |
| `[bd_public_lists]` | Browse public lists from all users | `per_page`, `show_featured`, `orderby` |
| `[bd_list]` | Single list detail page | `id`, `slug` |
| `[bd_save_button]` | Save to list button for business pages | `business_id`, `style` |

#### Events Calendar (2)

| Shortcode | Description | Key Attributes |
|-----------|-------------|----------------|
| `[bd_city_events]` | Events filtered by city | `city`, `source`, `limit`, `layout`, `columns` |
| `[bd_business_events]` | Events for a specific business | `id`, `source`, `limit` |

#### Gamification (3)

| Shortcode | Description | Key Attributes |
|-----------|-------------|----------------|
| `[bd_user_profile]` | User profile with stats, badges, activity | `user_id`, `show_reviews`, `show_activity` |
| `[bd_badge_gallery]` | Complete badge collection with progress | `show_ranks` |
| `[bd_leaderboard]` | Top contributors leaderboard | `period`, `limit` |

#### Business Features (4)

| Shortcode | Description | Key Attributes |
|-----------|-------------|----------------|
| `[bd_business_embed]` | Embed a business card anywhere | `id`, `layout`, `show_map` |
| `[bd_business_hours]` | Business hours with open/closed status | `business_id`, `show_status`, `highlight_today` |
| `[bd_social_share]` | Social sharing buttons | `business_id`, `networks`, `style` |
| `[bd_qr_code]` | QR code linking to business page | `business_id`, `size` |

#### Reviews & Ratings (3)

| Shortcode | Description | Key Attributes |
|-----------|-------------|----------------|
| `[bd_business_reviews]` | Reviews for a specific business | `business_id`, `limit`, `show_form` |
| `[bd_review_form]` | Standalone review submission form | `business_id`, `redirect` |
| `[bd_recent_reviews]` | Recent reviews across all businesses | `limit`, `category` |

#### Forms & Management (4)

| Shortcode | Description | Key Attributes |
|-----------|-------------|----------------|
| `[bd_submit_business]` | New business submission form | `category`, `redirect` |
| `[bd_claim_business]` | Business claim form for owners | `business_id` |
| `[bd_business_tools]` | Owner dashboard for managing listing | - |
| `[bd_edit_listing]` | Edit listing form for owners | `business_id` |

### Shortcode Examples

```php
// Main directory filtered by category
[business_directory category="restaurants" per_page="24"]

// City events on a sub-site (fetches from main site)
[bd_city_events city="Livermore" source="lovetrivalley.com" layout="grid" columns="3"]

// Leaderboard showing top 5 contributors this month
[bd_leaderboard period="month" limit="5"]

// Embed a business card
[bd_business_embed id="123" layout="card" show_map="yes"]

// Recent reviews from restaurant category
[bd_recent_reviews limit="10" category="restaurants"]
```

## File Structure

```
business-directory/
├── assets/
│   ├── css/              # Stylesheets
│   └── js/               # JavaScript files
├── includes/             # Feature loaders (geolocation, gamification, embeds, social, auth, media, etc.)
├── languages/            # Translation files
├── src/
│   ├── Admin/            # Admin interfaces, menus, settings, moderation queues
│   ├── API/              # Legacy REST endpoints (lists, badges, collaborators, geocode, features)
│   ├── Auth/             # Authentication, login, registration, SSO (multisite)
│   ├── BusinessTools/    # Owner dashboard, analytics, widget embed system
│   ├── DB/               # Database tables, installer, migrations (17 tables)
│   ├── Explore/          # Explore pages, cache invalidation, routing
│   ├── Exporter/         # CSV export
│   ├── Forms/            # Form handlers (business submission, reviews, claims)
│   ├── Frontend/         # Shortcodes, profiles, edit listing, view tracking, quick filters
│   ├── Gamification/     # Points, badges (SVG), ranks, leaderboards, activity tracking
│   ├── Importer/         # CSV/bulk import with optional geocoding
│   ├── Install/          # Installation & setup
│   ├── Integrations/     # Third-party integrations (The Events Calendar)
│   ├── Lists/            # User lists, collections, collaboration, covers
│   ├── Media/            # Image optimization (WebP, custom sizes, EXIF stripping)
│   ├── Moderation/       # Submissions queue, reviews queue
│   ├── Notifications/    # Email notifications (submissions, reviews)
│   ├── PostTypes/        # Custom post types (bd_business)
│   ├── REST/             # Secured REST controllers (submissions, reviews, claims, businesses)
│   ├── Roles/            # User roles & capabilities
│   ├── Search/           # QueryBuilder, FilterHandler, Geocoder (Nominatim)
│   ├── Security/         # Rate limiting, Cloudflare Turnstile CAPTCHA
│   ├── SEO/              # Slug migration, 301 redirects
│   ├── Social/           # Social sharing, Open Graph, badge share cards
│   ├── Taxonomies/       # Categories, areas, tags
│   ├── Utils/            # Helper utilities & cache
│   └── Plugin.php        # Main plugin singleton
├── templates/            # Page templates (single business, directory, profile, explore, claim landing)
├── tests/                # PHPUnit tests
├── vendor/               # Composer dependencies
└── business-directory.php  # Main plugin bootstrap
```

## Database Tables

The plugin creates the following custom tables (all prefixed `wp_bd_`):

**Core:**
- `bd_locations` - Business coordinates, address, geohash
- `bd_reviews` - User reviews and ratings
- `bd_review_helpful` - Helpful vote tracking (one vote per user per review)
- `bd_submissions` - Pending business submissions
- `bd_claim_requests` - Business claim requests with proof files
- `bd_change_requests` - Edit requests from owners

**Gamification:**
- `bd_user_reputation` - Points, ranks, streaks
- `bd_user_activity` - Activity log for point awards
- `bd_badge_awards` - Earned badges

**Lists:**
- `bd_lists` - User-created lists
- `bd_list_items` - Businesses in lists
- `bd_list_collaborators` - List collaboration
- `bd_list_follows` - List subscriptions

**Analytics:**
- `bd_share_tracking` - Social share events
- `bd_qr_scans` - QR code scan tracking
- `bd_widget_clicks` - Embed widget click tracking
- `bd_widget_domains` - Allowed embed domains

## REST API

Base URL: `/wp-json/bd/v1/`

| Endpoint | Method | Auth | Description |
|----------|--------|------|-------------|
| `/businesses` | GET | Public | Search/filter businesses |
| `/businesses/{id}` | GET | Public | Get single business |
| `/submit-business` | POST | Public | Submit a new business (rate-limited, CAPTCHA) |
| `/reviews` | POST | Public | Submit a review (rate-limited, CAPTCHA) |
| `/claim` | POST | Public | Submit a claim request (rate-limited, CAPTCHA) |
| `/lists` | GET/POST | Auth | User lists |
| `/lists/{id}/collaborators` | GET/POST | Auth | List collaborators |
| `/geocode` | GET | Public | Geocode an address (Nominatim) |
| `/geocode/reverse` | GET | Public | Reverse geocode coordinates |
| `/feature` | GET | Public | Feature embed data for external sites |
| `/badge/{id}.svg` | GET | Public | Badge SVG rendering |
| `/cover/{list_id}` | POST | Auth | Upload list cover image |

## Configuration

### Settings

Navigate to **Business Directory > Settings** to configure:

- Notification email addresses
- Default map center coordinates
- Turnstile site key (spam protection)
- Review moderation settings
- Gamification point values

### Customization

```php
// Change popup style to detailed
add_filter( 'bd_popup_style', function() {
    return 'detailed';
});

// Customize point values
add_filter( 'bd_points_review', function() {
    return 15; // Default is 10
});
```

## Changelog

### 0.1.12
- **Hours UI: smart defaults for outdoor listings** — new `src/Frontend/HoursDisplay.php` centralises display rules. Two universal changes:
  1. **If no weekday hours are filled, the entire Hours card is hidden** (no more empty grid or misleading "Closed" badge on sparsely-populated listings). Open Now / Closed pills in the hero and sticky bar are also gated on real-hours-exist
  2. **For listings in the Get Outside category (parks, trails, open spaces), the weekday grid is replaced with a single Access line.** New admin field on the business edit screen offers presets (Open daily sunrise to sunset, Open 24/7, Open during daylight hours, Seasonal, Custom freeform). Outdoor listings with no explicit access type and no real hours auto-default to "Open daily, sunrise to sunset"
- **New post meta:** `bd_outdoor_access_type` (preset slug or `custom`) + `bd_outdoor_access_custom` (freeform text). Owner can override either direction — outdoor places with real gates can fill in the weekday grid normally
- **Detection:** primary signal is the `get-outside` `bd_category` slug; secondary signal is any of the outdoor `bd_tag` slugs (park, hiking-trails, open-space, dog-park, playground, gardens, sports-fields)

### 0.1.11
- **Reviews UI: mission-aligned empty state** — when a business has zero reviews, the deflating "0.0 ★★★★★ Based on 0 reviews" header + grey banner is replaced with a heart-led invitation card: "What do you love about [Business]?" + a "Share What You Love" CTA + a small "+10 points • First Steps badge" gamification nudge. Section title shifts from "Reviews" to "Share What You Love" until the first review lands, then becomes "What Locals Love"
- **Reviews UI: prompt chips lower the cognitive load** — `src/Frontend/ReviewPrompts.php` maps `bd_category` to 3-4 click-to-prefill suggestions ("What's the must-order dish here?", "Best feature of this spot", etc.) shown above the textarea. Click a chip → textarea pre-fills with the prompt + space, cursor parked at end. Generic fallback when no category matches
- **Reviews UI: heart accents replace generic icons** throughout the review section — write-review buttons, engagement strip ("Love this spot?" / "Share What You Love"), form heading. Star rating mechanic preserved (familiar, scannable, matches Schema.org rendering in Google SERPs)
- **Reviews UI: form copy refresh** — "Write a Review" → "Share what you love"; "Tell others about your experience" → "Share what made it special — what to order, when to visit, why you love it"; submit button → "Share What You Love"; new italic mission note: "No rants, no critiques — just what makes this spot worth visiting"
- **Security: ownership checks on two AJAX handlers** — `ToolsDashboard::ajax_update_email_prefs` and `StatsEmail::ajax_send_test_email` now verify approved-claim ownership before mutating prefs / sending stats emails (was any-logged-in-user)
- **Security: locked down `ajax_search_users`** — added `_ajax_nonce` + `manage_options` capability check; updated inline JS to send the nonce. Prevents user enumeration
- **Security: rate limits on remaining public REST endpoints** — `/widget/click` (60/min/IP), `/badge/{id}` and `/badge/{id}/code` (120/min/IP), `/lists`, `/lists/{id}`, `/lists/{id}/share-data` (60–120/min/IP), `/feature` and `/feature/search` (60/min/IP), `/events/city/{city}` and `/events/business/{id}` (60/min/IP)
- **Security: prepared SQL for table identifiers** — converted ~14 `$wpdb` calls that interpolated table names directly to use the `%i` placeholder (no exploitable SQLi, but enforces the project's "no exceptions to prepare()" invariant)
- **Bumped minimum WordPress to 6.2** — required for `%i` identifier placeholder support
- **Consolidated `MimeValidator`** — extracted the duplicated `get_real_mime_type()` from SubmitReviewController, ClaimController, and EditListing into `src/Security/MimeValidator.php`
- **API.md: documented QR + TEC integration endpoints** — section 18 (QR Codes: `/qr/generate`, `/qr/go/{code}`) and section 19 (Events: `/events/city/{city}`, `/events/business/{id}`); filled in missing `/widget/click` rate-limit + response
- **CLAUDE.md: expanded Key Directories** — listed every `src/` subfolder; corrected DB table count (12 → 17)
- **Tooling: `bin/install-wp-tests.sh`** — standard WP test scaffold installer so `composer test` can boot
- **Tooling: PHPStan now runs without crashing** — added `--no-ansi` to the composer script (works around a Symfony Console formatter crash in PHPStan 2.1.x parallel workers)
- **Stale `.bak.php` removed** from `src/Integrations/EventsCalendar/`

### 0.1.9
- **SSO security hardening**: token IP validation on redemption, hardened IP detection (delegates to RateLimit::get_client_ip, no longer trusts spoofable X-Forwarded-For), logout redirect validated as network URL early in chain
- **List endpoint security**: reorder and quick-save endpoints now verify list ownership or collaborator status before modifying (was any-authenticated-user). GeocodeEndpoint rate-limited to 10 req/min per IP
- **Open redirect fix**: RegistrationHandler uses wp_safe_redirect with fallback
- **All SQL queries prepared**: last unprepared query (ShareButtons table check) fixed. Four-pass audit complete — codebase clean
- **Video cover performance**: external API calls (YouTube/Vimeo thumbnail fetch) no longer block frontend page renders. Returns placeholder on cache miss; thumbnails populate via admin views
- **ListDisplay double-load eliminated**: single list view reuses loaded items for map data instead of re-fetching (14 queries → 7)
- **Immersive template optimized**: term cache primed before taxonomy lookups (3 queries → 0 after prime)
- **Leaflet reliability**: fixed "Map container already initialized" error on business detail pages. Fixed map not rendering on cold first visit (CDN race condition — polls for Leaflet availability up to 5s)
- **ReviewsQueue optimized**: batch cache priming before render loop (50 queries → 1)

### 0.1.8
- **In-field grant access**: directory managers can grant a known owner direct access from the business edit screen, list-table row action, or the frontend admin bar — no claim form needed. Supports multiple authorized users per business (owner + marketing contact) with per-user roles and revoke
- **Search performance**: eliminated N+1 queries in BusinessesController (wp_get_post_terms → get_the_terms, thumbnail cache priming), FeaturedAdmin validation loop (N queries → 1), and added geo bounding-box pre-filter for radius searches (~120 queries → ~30, ~10x faster Haversine loop)
- **WebP delivery**: new `bd_picture()` / `bd_post_picture()` helpers output `<picture>` elements with WebP `<source>` + JPEG fallback. Applied to search result cards and gallery thumbnails
- **Font Awesome 6.5.1**: consolidated 3 versions (5.15.4, 6.4.0, 6.5.1) down to single 6.5.1 load across all pages. Renamed 17 deprecated v5 icon names to v6 equivalents. Updated SRI hash
- **Lazy Leaflet map**: map no longer initializes on page load — deferred to first "split view" toggle click, saving ~350KB render-blocking assets on list-view sessions
- **Filter cache**: increased transient TTL from 15 min to 60 min with smart invalidation on `save_post_bd_business` / `delete_post`
- **Composite lat/lng index**: added `idx_lat_lng` on `wp_bd_locations` for faster bounding-box queries (DB v2.7.0)
- Password change section on user profile page
- Updated Terms of Service → Terms of Use + Privacy Policy links across login modal, login shortcode, and registration

### 0.1.7
- Fixed Submit Business pipeline: dbDelta table creation, SubmissionsQueue instantiation, MenuOrganizer routing
- Fixed lat/lng `empty()` checks treating valid 0.0 coordinates as missing (LocationsTable, QueryBuilder, BusinessesController)
- Added `batch_load()` to LocationsTable — eliminates N+1 queries on directory listings
- Auto-geocoding via Nominatim when approving business submissions
- Fixed `%d`/null format bugs in SubmissionsTable and ClaimRequestsTable
- Added server-side MIME validation to ClaimController and EditListing file uploads
- Fixed SSRF vulnerability in FeatureShortcode remote fetching
- Fixed contact field sanitization in EditListing (field-specific: email, URL, phone)
- Added error logging throughout submission, email, review, and claim pipelines
- Removed insecure legacy SubmissionEndpoint.php
- Removed orphaned Settings::add_pending_menu() method

### 0.1.6
- Image optimization pipeline (WebP, custom sizes, EXIF stripping)
- Explore pages with cache invalidation
- SEO slug migration with 301 redirects

### 0.1.5
- Business owner edit listing with change request workflow
- Social sharing and Open Graph integration
- QR code generation for business pages
- Widget embed system for external sites

### 0.1.4
- Multisite SSO authentication
- CSV export functionality
- Review helpful votes
- Share and QR scan tracking

### 0.1.3
- Added Photo Gallery metabox for admin
- Database migration system improvements
- Business Tools navigation link
- File naming cleanup and organization

### 0.1.2
- Gamification system with badges and leaderboards
- Collaborative lists feature
- Business owner dashboard

### 0.1.1
- Review system with photo uploads
- Business claiming workflow
- The Events Calendar integration

### 0.1.0
- Initial release
- Core directory functionality
- Interactive maps with clustering
- Search and filtering

## License

This plugin is licensed under the GPL v2 or later.

**This is premium software.** While the code is GPL-licensed, this plugin is sold commercially with paid support. Unauthorized redistribution is not permitted.

## Support

For support, feature requests, or bug reports:

- **GitHub:** [github.com/reggienicolay/Business-Directory-Plugin](https://github.com/reggienicolay/Business-Directory-Plugin)

## Credits

- **Author:** Reggie Nicolay
- **Website:** [narrpr.com](https://narrpr.com)
- **Maps:** [Leaflet](https://leafletjs.com/) with [OpenStreetMap](https://www.openstreetmap.org/)
- **Icons:** [Font Awesome](https://fontawesome.com/)

---

© 2026 Reggie Nicolay. All rights reserved.
