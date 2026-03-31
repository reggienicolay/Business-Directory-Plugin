# SEO Evolution — Phase 1 Changelog

**Date:** 2026-03-30
**Scope:** Post meta integration seam + OG consolidation

## Strategy Change

The original spec proposed building ~12 new files (~2,000 lines) in BD Pro's `src/SEO/`.
During implementation, we discovered the `bd-seo` companion plugin already provides:

- LocalBusiness JSON-LD with full address, hours, rating, reviews, sameAs
- Titles + descriptions for all BD page types (with Yoast/RankMath/AIOSEO filters)
- Canonical URLs (including multisite hub pointing)
- BreadcrumbList schema + HTML breadcrumbs
- CollectionPage + ItemList for explore/taxonomy pages
- Article schema with author authority
- Sitemaps (image, lists, search engine pinging)
- SchemaTypeMap (bd_tag -> schema.org type)
- Robots meta

**Revised approach:** Evolve bd-seo (~280 lines), shrink BD Pro.

## Changes Made

### bd-seo plugin (`wp-content/plugins/bd-seo/`)

#### `src/MetaTags.php` — Post meta title/description overrides

Added `_bd_seo_title` and `_bd_seo_description` post meta checks at the top of
`get_title()` and `get_description()`. These run before all other title/description
generation and allow the Article Generator (and manual overrides) to set per-post
SEO metadata.

- Zero additional DB queries (hits WP object cache on singular pages)
- Early return when meta exists = less work, not more

#### `src/StructuredData.php` — Post meta schema + filter

Added two features to the `output()` method:

1. **`_bd_schema_json` post meta reader** — On any singular page, reads JSON from
   post meta and merges it into the schema output. Handles both single objects
   (`{"@type":"Article",...}`) and arrays of objects. Strips `@context` from
   individual items (the render method adds it per-block).

2. **`bd_seo_schema_graph` filter** — Fires after all schemas are assembled,
   before output. Passes the full `$schemas` array and the current post ID.
   This is the primary integration point for add-on plugins.

#### `src/OpenGraphManager.php` — NEW (consolidates all OG output)

Replaces fragmented OG output from BD Pro's `Social/OpenGraph.php` and
`Frontend/ListSocialMeta.php`. Handles:

- **Business pages:** og:type=business.business, place:location:*, business:contact_data:*
- **List pages:** Cover image detection (custom image, video thumbnail, fallback)
- **Singular pages with `_bd_og_*` meta:** Article Generator can set per-post OG
- **Third-party image filters:** Always registered for Yoast/RankMath compatibility

Proper third-party SEO plugin detection — skips OG output when Yoast/RankMath/AIOSEO
is active, but still feeds BD's images via their filter hooks.

#### `src/Plugin.php` — Registered OpenGraphManager

Added `OpenGraphManager::init()` call in `init_components()`.

### BD Pro plugin (`wp-content/plugins/business-directory/`)

#### `src/Social/OpenGraph.php` — Added bd-seo guard

When `BD\SEO\OpenGraphManager` class exists, `output_meta_tags()` returns early.
BD Pro's OG output remains as a fallback when bd-seo is deactivated.

**This fixes the duplicate OG tag bug** (Section 2.1 of the spec). The old
`OpenGraph` class had no third-party SEO plugin check. Now bd-seo handles all
OG output with proper detection.

#### `src/Frontend/ListSocialMeta.php` — Added bd-seo guard

Same pattern. When bd-seo is active, `output_meta_tags()` returns early.

## Post Meta Key Convention

| Meta Key | Type | Written By | Read By |
|----------|------|-----------|---------|
| `_bd_seo_title` | string | Article Generator, manual | `MetaTags::get_title()` |
| `_bd_seo_description` | string | Article Generator, manual | `MetaTags::get_description()` |
| `_bd_schema_json` | JSON string | Article Generator | `StructuredData::output()` |
| `_bd_og_image` | attachment ID or URL | Cover system, Article Generator | `OpenGraphManager` |
| `_bd_og_title` | string | Override | `OpenGraphManager` |

## Hook Registration (new)

| Hook | Priority | Class | Method |
|------|----------|-------|--------|
| `wp_head` | 3 | `OpenGraphManager` | `output()` |
| `wpseo_opengraph_image` | 10 | `OpenGraphManager` | `filter_third_party_image()` |
| `wpseo_twitter_image` | 10 | `OpenGraphManager` | `filter_third_party_image()` |
| `rank_math/opengraph/facebook/image` | 10 | `OpenGraphManager` | `filter_third_party_image()` |
| `rank_math/opengraph/twitter/image` | 10 | `OpenGraphManager` | `filter_third_party_image()` |
| `bd_seo_schema_graph` | — | `StructuredData` | (filter, not action) |

## What's Next

- **Phase 2:** Event schema enrichment (linked business data in Event JSON-LD)
- **Phase 3:** AutoLinker, RelatedBusinesses, MultisiteCanonical
- **Phase 4:** SEO audit dashboard
