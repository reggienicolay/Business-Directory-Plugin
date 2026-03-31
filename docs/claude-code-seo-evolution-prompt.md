# Claude Code: BD-Core-SEO Evolution — Planning & Implementation

## Your Role

You are evolving the SEO infrastructure inside **BD Pro** (the Business Directory plugin) for the Love TriValley WordPress multisite platform. This is NOT a new plugin — you are adding classes to the existing `src/SEO/` namespace and consolidating fragmented SEO output that currently lives in four different files across three namespaces into a single coordinated pipeline.

The end result: every page type in the ecosystem (business listings, explore pages, events, lists, generated articles, neighborhoods, trails) gets correct canonical URLs, Open Graph tags, Twitter Cards, meta descriptions, and JSON-LD structured data — all from one orchestrator class that knows how to detect context, defer to third-party SEO plugins when present, and output BD-specific schema that no third-party plugin can provide.

Before writing any code, you must read the spec, audit every existing SEO output point in the codebase, map what moves where, and produce a migration plan that guarantees zero regressions.

---

## Step 1: Read the Spec

Read the complete BD-Core-SEO Evolution specification:

```
cat docs/bd-core-seo-evolution-spec.md
```

This is the authoritative document. Internalize it fully before touching the codebase. Pay special attention to:

- **Section 2.1 (Current State Audit)** — The seven files that currently output SEO data and the specific problems with each
- **Section 3.1 (Core Principle)** — One class controls all `wp_head` output
- **Section 3.3 (Post Meta Key Convention)** — The `_bd_seo_*` keys that become the integration seam for the entire ecosystem
- **Section 4.1 (Orchestrator)** — The third-party plugin detection and graceful deferral pattern
- **Section 4.3 (SchemaManager)** — The `@graph` array pattern and all schema types
- **Section 4.3.1 (LocalBusiness Schema)** — The highest-impact single addition
- **Section 7 (Migration Plan)** — What gets consolidated, what stays, backward compatibility
- **Section 8 (Phased Rollout)** — Phase 1 is deliberately small and safe

---

## Step 2: Audit Every Existing SEO Output Point

This is critical. You are consolidating existing behavior, not just adding new code. You must understand exactly what every file currently outputs, on which hooks, at which priorities, with which guards. A missed hook removal means duplicate tags. A missed guard means broken output.

### 2a. The SEO Loader (your entry point)
```
cat includes/seo-loader.php
```
This is where your new classes get registered. Note the existing `SlugMigration` class (keep it, don't touch it) and the three commented-out future classes (`AutoLinker`, `RelatedBusinesses`, `MultisiteCanonical`).

### 2b. SlugMigration (DO NOT MODIFY)
```
cat src/SEO/SlugMigration.php
```
Read it to understand the pattern, but do not change this file. It handles 301 redirects for old taxonomy URLs and is working correctly.

### 2c. ExploreRouter — Canonical & Title Output (MOVES TO YOUR CODE)
```
cat src/Explore/ExploreRouter.php
```
Find these two methods and understand them completely:
- `output_canonical()` — hooked to `wp_head` priority 1, removes WP default canonical, outputs custom canonical for explore pages
- `filter_document_title()` — hooked to `document_title_parts`, sets `<title>` for explore hub/city/intersection pages, defers to `BusinessDirectorySEO\Plugin` if present

Both of these move into your `CanonicalManager` and `TitleManager` respectively. The ExploreRouter hooks must be removed once your classes are active.

### 2d. ListSocialMeta — OG Tags for Lists (REPLACED BY YOUR CODE)
```
cat src/Frontend/ListSocialMeta.php
```
This outputs OG + Twitter Card meta tags for list pages. Note:
- Hooked to `wp_head` priority 5
- DOES check for Yoast/RankMath/AIOSEO before outputting (good pattern — preserve this)
- Also hooks into `wpseo_opengraph_image` and `wpseo_twitter_image` filters for Yoast compatibility
- Also hooks into `rank_math/opengraph/facebook/image` and `rank_math/opengraph/twitter/image` for RankMath

Your `OpenGraphManager` must replicate all of these filter registrations.

### 2e. OpenGraph in src/Social/ — OG Tags for Businesses + Lists (REPLACED BY YOUR CODE)
```
cat src/Social/OpenGraph.php
```
This outputs OG tags for business pages and list pages. Note:
- Hooked to `wp_head` priority 5
- Does NOT check for third-party SEO plugins ← THIS IS THE BUG your consolidation fixes
- Handles both `bd_business` singular pages and list pages (via query param detection)
- Generates list share image URLs

Your `OpenGraphManager` must handle all the same content types but with the third-party check that `ListSocialMeta` has and this class lacks.

### 2f. Reviews Section SEO Template (DO NOT MODIFY)
```
cat templates/single-business/reviews-section-seo.php
```
This outputs Review schema using inline microdata (HTML attributes, not JSON-LD). Read it to understand the review data structure, but do NOT migrate this to JSON-LD. The inline microdata is correctly scoped to the review HTML and supplements the page-level JSON-LD you'll add. Both formats coexist fine.

### 2g. Social Share Buttons (check for OG conflicts)
```
cat src/Social/ShareButtons.php
```
Verify this class doesn't output any `<meta>` tags. It should only render share button HTML.

### 2h. Feature Embed Loader (check for meta output)
```
cat includes/feature-embed-loader.php
```
Verify no meta tags are output here.

### 2i. Business Detail Template (check for inline schema)
```
cat templates/single-business/immersive.php
```
Check if any JSON-LD or meta tags are output inline in the template. Your `SchemaManager` must not duplicate anything already in the template.

### 2j. Events Calendar Integration (schema enrichment target)
```
cat src/Integrations/EventsCalendar/EventsCalendarIntegration.php
cat src/Integrations/EventsCalendar/BusinessLinker.php
```
Understand how `get_business_for_event()` resolves a business ID from an event (direct link → venue link → organizer link chain). Your `ContextDetector::detect_event()` calls this same method.

### 2k. Explore Query & Renderer (data source for ItemList schema)
```
cat src/Explore/ExploreQuery.php
cat src/Explore/ExploreRenderer.php
cat src/Explore/ExploreEditorial.php
```
Your `SchemaManager::item_list_schema()` needs the same business data that `ExploreQuery::get_city()` and `get_intersection()` return. Understand the data format.

### 2l. Explore Templates (hook points for breadcrumbs)
```
cat templates/explore-city.php
cat templates/explore-intersection.php
```
Both templates fire `do_action( 'bd_explore_before_header', $area, $tag )`. Your `BreadcrumbManager` hooks here to output breadcrumb HTML. Understand what `$area` and `$tag` contain (WP_Term objects or null).

### 2m. Business Post Type & Taxonomies
```
grep -rn "register_post_type\|register_taxonomy" src/ --include="*.php" | head -20
```
Understand the post type slug (`bd_business`) and taxonomy slugs (`bd_category`, `bd_area`, `bd_tag`).

### 2n. Business Meta Fields (data source for LocalBusiness schema)
```
grep -rn "bd_location\|bd_contact\|bd_hours\|bd_social\|bd_price_level\|bd_avg_rating" src/ --include="*.php" | head -30
```
Map the exact meta keys and their data formats. Your `SchemaManager::local_business_schema()` reads all of these.

### 2o. DB Installer Pattern
```
cat src/DB/Installer.php
```
No new tables are needed for the SEO evolution, but understand `should_create_tables()` for the multisite guard pattern if any future phase needs storage.

### 2p. Design Tokens (for breadcrumb HTML styling)
```
head -100 assets/css/design-tokens.css
```
If `BreadcrumbManager` outputs visible breadcrumb HTML (not just schema), it should use these CSS variables.

### 2q. Plugin Bootstrap
```
cat business-directory.php
```
Understand the load order. The SEO loader runs at the end. Your new classes must not depend on anything that loads after `seo-loader.php`.

---

## Step 3: Create the Before/After Map

This is the most important planning artifact. Create `docs/seo-migration-map.md` with:

### 3a. Hook Inventory

For every `wp_head` hook, `document_title_parts` filter, and any other SEO-related hook currently registered, document:

```
| Hook | Priority | Current Class | Current Method | New Class | New Method | Action |
|------|----------|--------------|----------------|-----------|------------|--------|
| wp_head | 1 | ExploreRouter | output_canonical | CanonicalManager | output | MOVE |
| wp_head | 5 | ListSocialMeta | output_meta_tags | OpenGraphManager | output | REPLACE |
| wp_head | 5 | OpenGraph | output_meta_tags | OpenGraphManager | output | REPLACE |
| document_title_parts | - | ExploreRouter | filter_document_title | TitleManager | filter_title | MOVE |
| wpseo_opengraph_image | 10 | ListSocialMeta | filter_yoast_og_image | OpenGraphManager | filter_third_party_image | MOVE |
| ...
```

### 3b. Output Comparison Matrix

For every page type, document what is currently output and what should be output after migration:

```
PAGE TYPE: Single Business (/places/wente-vineyards/)
─────────────────────────────────────────────────────
CURRENT:
  - <title>: WordPress default (post title + site name)
  - canonical: WordPress default
  - OG tags: OpenGraph class (NO third-party check)
  - schema: Review microdata inline in reviews-section-seo.php
  - meta description: NONE

AFTER:
  - <title>: TitleManager (post title + site name, or _bd_seo_title override)
  - canonical: CanonicalManager (self-canonical, or main site via MultisiteCanonical)
  - OG tags: OpenGraphManager (WITH third-party check)
  - schema: SchemaManager outputs LocalBusiness JSON-LD + BreadcrumbList
           + existing Review microdata unchanged
  - meta description: MetaDescriptionManager (from _bd_seo_description or auto-generated)
```

Do this for EVERY page type:
- Single business page
- Explore hub (`/explore/`)
- Explore city (`/explore/livermore/`)
- Explore intersection (`/explore/livermore/winery/`)
- Single event (`/events/...`) with linked business
- Single event without linked business
- List page
- Taxonomy archive (`/places/tag/winery/`)
- Generated article (has `_bd_ag_generated` meta)
- Standard post (no BD meta)
- Neighborhood page (if BD Neighborhood active)
- Trail page (if BD Outdoor Activities active)

### 3c. Removal Safety Checklist

For every hook you remove from the old location, document:
- The exact `remove_action()` / `remove_filter()` call
- The replacement in the new code
- How to verify the removal worked (what to check in page source)

### 3d. Third-Party Plugin Test Matrix

```
| Scenario | Expected Behavior |
|----------|------------------|
| No SEO plugin | Full BD output: title, desc, canonical, OG, Twitter, JSON-LD |
| Yoast active | BD defers OG/title/desc to Yoast, BUT still outputs JSON-LD schema |
| Yoast active + _bd_seo_title set | BD feeds custom title to Yoast via filter |
| Yoast active + business page | BD feeds business OG image to Yoast via wpseo_opengraph_image |
| RankMath active | Same as Yoast but via rank_math/* filters |
| AIOSEO active | BD defers OG/title/desc, still outputs JSON-LD |
```

---

## Step 4: Create Implementation Plan

Produce `docs/seo-implementation-plan.md` with file creation order, dependencies, and test points.

### File Creation Order (Phase 1 only)

Phase 1 is the foundation that unblocks the Article Generator. Scope from spec Section 8:

```
1. src/SEO/SEOContext.php
   - Data object (type, object_id, area, tag, businesses, etc.)
   - No external dependencies
   - Test: instantiate with each context type, verify properties

2. src/SEO/TitleManager.php
   - document_title_parts filter
   - Reads _bd_seo_title from post meta
   - Absorbs ExploreRouter::filter_document_title() logic
   - Test: set _bd_seo_title on a post, verify <title> changes
   - Test: visit /explore/livermore/, verify title matches current behavior

3. src/SEO/MetaDescriptionManager.php
   - wp_head output for <meta name="description">
   - Reads _bd_seo_description from post meta
   - Third-party SEO plugin check (skip if Yoast/RankMath handles it)
   - Also provides filter_third_party_description() for Yoast/RankMath integration
   - Test: set _bd_seo_description on a post, verify meta tag appears
   - Test: activate Yoast, verify no duplicate description tags

4. src/SEO/SchemaManager.php (MINIMAL — Phase 1 version)
   - Reads _bd_schema_json from post meta, outputs as JSON-LD
   - Provides bd_seo_schema_graph filter
   - Phase 1 does NOT auto-generate schema — only outputs what's in post meta
   - Test: set _bd_schema_json on a post, verify <script type="application/ld+json"> appears
   - Test: verify filter fires and can modify the schema

5. Update includes/seo-loader.php
   - Add new class paths to $bd_seo_classes array
   - Add init calls in init_seo_components()
   - DO NOT remove any existing hooks yet (Phase 1 is purely additive)
   - Test: all new classes load without errors
   - Test: all existing SEO behavior unchanged (SlugMigration, ExploreRouter canonical, etc.)
```

### Phase 2 File Creation Order

```
6. src/SEO/ContextDetector.php
7. src/SEO/Orchestrator.php
8. src/SEO/CanonicalManager.php
9. src/SEO/OpenGraphManager.php
10. src/SEO/BreadcrumbManager.php
11. src/SEO/SchemaManager.php (FULL — adds LocalBusiness, ItemList, Event, BreadcrumbList auto-generation)
12. Update includes/seo-loader.php — activate Orchestrator, remove old hooks
13. Update src/Explore/ExploreRouter.php — remove output_canonical() and filter_document_title()
14. Deprecate src/Frontend/ListSocialMeta.php
15. Deprecate src/Social/OpenGraph.php
```

---

## Step 5: Begin Phase 1 Implementation

Phase 1 is deliberately safe — purely additive, no existing behavior changes.

### 5a. SEOContext Data Object

```php
// src/SEO/SEOContext.php
namespace BD\SEO;

class SEOContext {
    public string $type;           // business|explore_city|explore_intersection|event|list|article|neighborhood|trail|taxonomy|unknown
    public int $object_id;         // Post/term ID
    public ?\WP_Term $area;        // bd_area term (for explore pages)
    public ?\WP_Term $tag;         // bd_tag term (for intersection pages)
    public array $businesses;      // Business data array (for ItemList schema)
    public ?int $linked_business_id; // For events linked to businesses
    public ?string $neighborhood_name;
    // ... other context-specific properties
}
```

### 5b. TitleManager

Implement the `document_title_parts` filter. For Phase 1, this handles:
- `_bd_seo_title` post meta override (for Article Generator)
- Explore page titles (absorb from ExploreRouter — but in Phase 1, ExploreRouter keeps its hook too, so add a guard to avoid double-filtering)

**Critical:** In Phase 1, ExploreRouter still has its `filter_document_title()` hook. Your TitleManager must detect this and not double-process. Add a `did_action()` check or a static flag.

```php
// TitleManager checks if it should handle explore titles
// Phase 1: only handle _bd_seo_title overrides, let ExploreRouter keep explore titles
// Phase 2: absorb explore titles, remove ExploreRouter hook
```

### 5c. MetaDescriptionManager

Simple: if `_bd_seo_description` is set on the current post, output `<meta name="description">`. Skip if third-party SEO plugin is active (it handles this).

Also register the Yoast/RankMath filter to feed the custom description to those plugins when they're active.

### 5d. SchemaManager (Phase 1 minimal)

Read `_bd_schema_json` from post meta. If it contains valid JSON, wrap it in a `@graph` array and output a single `<script type="application/ld+json">` block.

Fire `bd_seo_schema_graph` filter so other plugins (like the Article Generator) can modify the schema programmatically.

### 5e. Update seo-loader.php

Add the new classes to the `$bd_seo_classes` array and call their `init()` methods in `init_seo_components()`.

**Do NOT uncomment AutoLinker, RelatedBusinesses, or MultisiteCanonical yet.** Those are Phase 3.

---

## Key Constraints

- **This is existing plugin modification, not a new plugin.** You are adding files to `src/SEO/` in the BD Pro codebase.
- **Namespace:** `BD\SEO\` (matches existing `SlugMigration`)
- **Phase 1 is purely additive.** No existing hooks removed. No existing files modified except `seo-loader.php`. No behavior changes for pages without `_bd_seo_*` meta.
- **Phase 2 is the consolidation.** That's when old hooks get removed and output moves to the Orchestrator. Phase 2 requires staging QA with before/after `<head>` comparison on every page type.
- **PHPCS:** `WordPress-Extra` ruleset — run `composer phpcs` after every file
- **PHPStan:** Level 5 — run `vendor/bin/phpstan analyse` to verify
- **No new npm dependencies.** SEO is server-side only. No JS, no CSS (except optional breadcrumb HTML styling in Phase 2).
- **No new database tables.** Everything uses `wp_postmeta` and existing term meta.
- **Git:** Commit each file individually with descriptive messages. This makes rollback trivial.

---

## Code Quality Standards

Match the existing `SlugMigration` class exactly in style:

```php
<?php
/**
 * Class Description
 *
 * @package    BusinessDirectory
 * @subpackage SEO
 * @since      0.1.8
 */

namespace BD\SEO;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Class ClassName
 *
 * @since 0.1.8
 */
class ClassName {

    /**
     * Initialize hooks.
     *
     * @since 0.1.8
     * @return void
     */
    public static function init(): void {
        // ...
    }
}
```

- Static methods with `void` return types (matches `SlugMigration` pattern)
- `@since` tags on class and every public method
- `ABSPATH` guard at top of every file
- No constructor — use static `init()` method
- WordPress Coding Standards: tabs, Yoda conditions where PHPCS requires, proper sanitization/escaping

---

## Context: What Reads the Meta Keys You Define

The `_bd_seo_*` meta keys you create in Phase 1 are the integration seam for the entire ecosystem. Here's what writes to them:

| Writer | Keys Written | When |
|--------|-------------|------|
| **BD Article Generator** (separate plugin, in development) | `_bd_seo_title`, `_bd_seo_description`, `_bd_schema_json` | On article generation |
| **Manual override** (Phase 2 admin UI) | `_bd_seo_title`, `_bd_seo_description` | Editor saves post |
| **SchemaManager auto-generation** (Phase 2) | `_bd_schema_json` | On post save for `bd_business` posts |

Your Phase 1 code must handle the case where none of these keys exist (most pages today). The behavior should be: no key → no output → existing behavior unchanged.

---

## Verification Protocol

After Phase 1 implementation, verify on every page type:

```bash
# Check that existing behavior is UNCHANGED on pages without BD meta
curl -s https://lovetrivalley.local/places/wente-vineyards/ | grep -i '<meta\|<link rel="canonical"\|application/ld+json\|<title>'

# Check that _bd_seo_title override works
wp post meta update 123 _bd_seo_title "Custom SEO Title | Love Livermore"
curl -s https://lovelivermore.local/?p=123 | grep '<title>'

# Check that _bd_seo_description works (no third-party SEO plugin)
wp post meta update 123 _bd_seo_description "Custom meta description for this article."
curl -s https://lovelivermore.local/?p=123 | grep 'meta name="description"'

# Check that _bd_schema_json outputs JSON-LD
wp post meta update 123 _bd_schema_json '{"@type":"Article","headline":"Test"}'
curl -s https://lovelivermore.local/?p=123 | grep 'application/ld+json'

# Check that bd_seo_schema_graph filter fires
# (add test filter in functions.php, verify schema is modified)

# Check that explore pages still work identically
curl -s https://lovetrivalley.local/explore/livermore/ | grep '<title>\|<link rel="canonical"'
curl -s https://lovetrivalley.local/explore/livermore/winery/ | grep '<title>\|<link rel="canonical"'

# Check that SlugMigration still works
curl -sI https://lovetrivalley.local/category/wineries/ | grep 'Location:\|301'
```

---

## Begin

Start with Step 1 (read the spec), then Step 2 (audit every existing SEO output point — all 17 files listed), then Step 3 (create the before/after map and migration safety plan), then Step 4 (implementation plan). Do not write any new PHP code until Steps 1-4 are complete and the migration map has been reviewed.

The golden rule for this project: **Phase 1 changes nothing existing. It only adds new capabilities that future plugins can write to.** If any existing page's `<head>` output changes as a result of Phase 1, something is wrong.
