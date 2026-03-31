# BD-Core-SEO Evolution — Technical Specification

**Scope:** Consolidation and enhancement of all SEO output across the Love TriValley ecosystem
**Version:** 2.1.0
**Author:** Reggie Nicolay
**Status:** Phase 1 Implemented (2026-03-30)

> **Implementation Note (2026-03-30):** This spec was written before discovering
> that the `bd-seo` companion plugin (v1.0.1) already handles ~80% of the proposed
> functionality — including LocalBusiness JSON-LD, titles, descriptions, canonicals,
> breadcrumbs, sitemaps, and author authority schema. The implementation strategy
> was revised to **evolve bd-seo** rather than build duplicate infrastructure in
> BD Pro's `src/SEO/`. Phase 1 adds the `_bd_seo_*` post meta convention and OG
> consolidation to bd-seo. See `docs/seo-phase1-changelog.md` for details.

---

## 1. Why This Spec Exists

SEO output across the Love TriValley platform is currently fragmented across seven different files in four different namespaces. This works — nothing is broken — but it creates three problems that will compound as the platform grows:

1. **Conflict risk.** `ListSocialMeta` checks for Yoast/RankMath before outputting OG tags, but `OpenGraph` in `src/Social/` does not. Installing a third-party SEO plugin produces duplicate OG tags on business pages but not list pages.

2. **No post-level SEO meta convention.** There is no mechanism for any plugin (including the planned Article Generator) to say "this post should have this specific `<title>` and `<meta description>`." WordPress does not provide this natively without Yoast/RankMath.

3. **Missing high-value schema.** The platform outputs `Review` schema on business pages (excellent) but has no standalone `LocalBusiness` JSON-LD, no `Event` schema enrichment, no `ItemList` for explore pages, no `BreadcrumbList`, and no `Article` schema infrastructure for editorial content. These are the schema types that win featured snippets and local pack placements.

This spec consolidates everything into a single pipeline, fills the gaps, and creates the integration seam that the Article Generator (and future plugins) need.

### What This Spec Does NOT Do

- Does not replace Yoast/RankMath if installed. Detects them and defers gracefully for areas they handle, while adding BD-specific schema they cannot.
- Does not change any existing URL structures. The `/explore/`, `/places/`, and business slug patterns remain identical.
- Does not modify the BD Event Aggregator or The Events Calendar plugins. It reads their data and enriches the schema output.
- Does not touch the BD Neighborhood plugin's templates directly. It hooks into action hooks the neighborhood pages already fire.

---

## 2. Current State Audit

### 2.1 Where SEO Output Lives Today

| File | What It Outputs | Hook | Problem |
|------|----------------|------|---------|
| `ExploreRouter::output_canonical()` | `<link rel="canonical">` on explore pages | `wp_head` priority 1 | Removes WP default canonical, outputs its own. No coordination with other canonical sources. |
| `ExploreRouter::filter_document_title()` | `<title>` for explore pages | `document_title_parts` filter | Defers to `BusinessDirectorySEO\Plugin` if present. Good pattern, should be universal. |
| `ListSocialMeta::output_meta_tags()` | OG + Twitter Card for list pages | `wp_head` priority 5 | Checks for Yoast/RankMath/AIOSEO before outputting. Also hooks Yoast/RankMath image filters. |
| `OpenGraph::output_meta_tags()` | OG for business + list pages | `wp_head` priority 5 | Does NOT check for third-party SEO plugins. Will produce duplicate OG tags if Yoast is active. |
| `reviews-section-seo.php` | `Review` + `LocalBusiness` microdata (within reviews HTML) | Inline in template | Microdata, not JSON-LD. Correctly scoped to reviews section. Keep as-is. |
| `SlugMigration::maybe_redirect()` | 301 redirects for old taxonomy URLs | `template_redirect` priority 1 | Excellent. No changes needed. |
| `seo-loader.php` + `fix_taxonomy_rewrite_priority()` | Rewrite rule ordering fix | `rewrite_rules_array` | Excellent. No changes needed. |

### 2.2 What the Companion Plugins Need

| Plugin | Needs From BD-Core-SEO |
|--------|----------------------|
| **BD Article Generator** | Post meta keys for title/description. JSON-LD output hook for `Article` + `ItemList` schema. Filter for schema modification. |
| **BD Neighborhood** | `BreadcrumbList` schema on neighborhood pages. OG tags with neighborhood-specific images. Schema for `Place` or `AdministrativeArea`. |
| **BD Event Aggregator** | `Event` schema enrichment (when a `tribe_events` post is linked to a `bd_business`, the schema should include the business as `organizer` or `location` with full `LocalBusiness` data). |
| **BD Outdoor Activities** | Schema for trails (`SportsActivityLocation` or `Place` with geo coordinates). Canonical handling for trail pages. |
| **BD-Core (Explore pages)** | `ItemList` schema for explore intersection pages (e.g., "Wineries in Livermore" = an ItemList of LocalBusiness). `BreadcrumbList` for all explore pages. |

### 2.3 What's Stubbed But Not Built

In `seo-loader.php`, three classes are registered but commented out:

- `AutoLinker` — Auto-link city/category mentions in business descriptions
- `RelatedBusinesses` — "Also in [City]" cross-links on business pages
- `MultisiteCanonical` — Canonical URL coordination across the multisite network

All three remain relevant and are included in this spec's phased roadmap.

---

## 3. Architecture: The SEO Orchestrator

### 3.1 Core Principle

**One class controls all `wp_head` SEO output.** Every other class writes data; the Orchestrator decides what gets rendered and when.

```
┌─────────────────────────────────────────────────────┐
│                  SEO Orchestrator                     │
│          (single wp_head hook, priority 1)           │
│                                                       │
│   Detects context:                                    │
│   ├── business page → LocalBusiness + Review schema  │
│   ├── explore city → ItemList + BreadcrumbList       │
│   ├── explore intersection → ItemList + Breadcrumb   │
│   ├── list page → CollectionPage schema              │
│   ├── event page → Event + linked LocalBusiness      │
│   ├── article (generated) → Article + ItemList       │
│   ├── trail page → SportsActivityLocation            │
│   ├── neighborhood page → Place + BreadcrumbList     │
│   └── other → defer entirely to WP/third-party       │
│                                                       │
│   For each context, outputs:                          │
│   1. Canonical URL (if we own the page)              │
│   2. Meta description (if post meta exists)          │
│   3. Open Graph tags                                  │
│   4. Twitter Card tags                                │
│   5. JSON-LD schema (one unified script block)       │
│   6. Breadcrumbs (if applicable)                     │
│                                                       │
│   Third-party plugin gate:                            │
│   ├── Yoast/RankMath active → skip OG, title, desc  │
│   │   but STILL output BD-specific JSON-LD schema    │
│   └── No third-party → full output                   │
└─────────────────────────────────────────────────────┘
```

### 3.2 Where This Lives

Two options, depending on whether BD-Core-SEO is a separate plugin or lives in BD Pro:

**Option A (recommended): Keep in BD Pro under `src/SEO/`**

The `seo-loader.php` + `src/SEO/` namespace already exists. Adding classes here means zero new plugin activation, zero new dependency. The Article Generator and other add-ons write to post meta; BD Pro reads it.

```
src/SEO/
├── Orchestrator.php          # Central wp_head controller
├── ContextDetector.php       # Determines page type + data
├── SlugMigration.php         # (existing) 301 redirects
├── TitleManager.php          # document_title_parts filter
├── MetaDescriptionManager.php # Meta description output
├── OpenGraphManager.php      # OG + Twitter Card (replaces ListSocialMeta + OpenGraph)
├── SchemaManager.php         # All JSON-LD output
├── BreadcrumbManager.php     # BreadcrumbList schema + HTML
├── CanonicalManager.php      # Canonical URL (replaces ExploreRouter::output_canonical)
├── AutoLinker.php            # (new) Internal linking engine
├── RelatedBusinesses.php     # (new) Cross-links
└── MultisiteCanonical.php    # (new) Network canonical coordination
```

**Option B: Separate BD-Core-SEO plugin**

If you prefer keeping it as a standalone plugin for modularity, that works too. It would follow the add-on pattern (own repo, PSR-4, `Installer.php`). The downside is another activation step and dependency management.

**Recommendation:** Option A. The SEO components are core platform behavior, not optional add-on features. They should ship with BD Pro.

### 3.3 Post Meta Key Convention

This is the integration seam. Every plugin in the ecosystem writes to these keys; the Orchestrator reads them.

| Meta Key | Type | Written By | Read By |
|----------|------|-----------|---------|
| `_bd_seo_title` | string | Article Generator, manual override | `TitleManager` |
| `_bd_seo_description` | string (≤155 chars) | Article Generator, manual override | `MetaDescriptionManager` |
| `_bd_seo_canonical` | URL string | `MultisiteCanonical`, manual override | `CanonicalManager` |
| `_bd_seo_noindex` | bool | Manual (e.g., draft landing pages) | `Orchestrator` |
| `_bd_schema_json` | JSON string | Article Generator, `SchemaManager` auto | `SchemaManager` |
| `_bd_og_image` | attachment ID or URL | Cover system, Article Generator | `OpenGraphManager` |
| `_bd_og_title` | string | Override (falls back to `_bd_seo_title`) | `OpenGraphManager` |

**Important:** These keys use the `_bd_` prefix (leading underscore hides from Custom Fields UI, `bd_` namespaces to our plugin). They never collide with Yoast (`_yoast_wpseo_*`) or RankMath (`rank_math_*`) keys.

---

## 4. Component Specifications

### 4.1 Orchestrator

The single entry point for all SEO `wp_head` output.

```php
namespace BD\SEO;

class Orchestrator {

    public static function init(): void {
        // Remove fragmented output sources
        remove_action( 'wp_head', [ \BD\Frontend\ListSocialMeta::class, 'output_meta_tags' ], 5 );
        // OpenGraph in src/Social/ — remove its wp_head hook too

        // Single consolidated output
        add_action( 'wp_head', [ __CLASS__, 'output' ], 1 );

        // Title filtering
        add_filter( 'document_title_parts', [ TitleManager::class, 'filter_title' ], 20 );
    }

    public static function output(): void {
        $context = ContextDetector::detect();

        // Always output BD-specific schema (even with Yoast/RankMath)
        SchemaManager::output( $context );

        // Canonical — we handle explore pages, business pages, event pages
        CanonicalManager::output( $context );

        // Skip OG/description if third-party SEO plugin handles it
        if ( self::third_party_handles_meta() ) {
            // But still filter their OG image for our custom content
            self::register_third_party_filters();
            return;
        }

        // Full output when we're the SEO authority
        MetaDescriptionManager::output( $context );
        OpenGraphManager::output( $context );
    }

    private static function third_party_handles_meta(): bool {
        return defined( 'WPSEO_VERSION' )       // Yoast
            || class_exists( 'RankMath' )         // Rank Math
            || defined( 'AIOSEO_VERSION' );       // All in One SEO
    }

    private static function register_third_party_filters(): void {
        // Feed our OG image to Yoast/RankMath for BD content types
        add_filter( 'wpseo_opengraph_image', [ OpenGraphManager::class, 'filter_third_party_image' ] );
        add_filter( 'wpseo_twitter_image', [ OpenGraphManager::class, 'filter_third_party_image' ] );
        add_filter( 'rank_math/opengraph/facebook/image', [ OpenGraphManager::class, 'filter_third_party_image' ] );
        add_filter( 'rank_math/opengraph/twitter/image', [ OpenGraphManager::class, 'filter_third_party_image' ] );

        // Feed our meta description to Yoast/RankMath
        add_filter( 'wpseo_metadesc', [ MetaDescriptionManager::class, 'filter_third_party_description' ] );
        add_filter( 'rank_math/frontend/description', [ MetaDescriptionManager::class, 'filter_third_party_description' ] );
    }
}
```

### 4.2 ContextDetector

Determines what type of page we're on and gathers the data needed for SEO output.

```php
class ContextDetector {

    public static function detect(): SEOContext {
        // Explore pages (custom rewrite rules)
        if ( get_query_var( 'bd_explore' ) ) {
            return self::detect_explore();
        }

        // Single business page
        if ( is_singular( 'bd_business' ) ) {
            return self::detect_business();
        }

        // Single event page (with possible BD business link)
        if ( is_singular( 'tribe_events' ) ) {
            return self::detect_event();
        }

        // List page
        if ( self::is_list_page() ) {
            return self::detect_list();
        }

        // Standard post with BD article meta (generated articles)
        if ( is_singular( 'post' ) && get_post_meta( get_the_ID(), '_bd_ag_generated', true ) ) {
            return self::detect_article();
        }

        // BD Neighborhood page (if plugin active)
        if ( self::is_neighborhood_page() ) {
            return self::detect_neighborhood();
        }

        // Trail page (if BD Outdoor Activities active)
        if ( is_singular( 'bd_trail' ) ) {
            return self::detect_trail();
        }

        // Taxonomy archives
        if ( is_tax( 'bd_category' ) || is_tax( 'bd_area' ) || is_tax( 'bd_tag' ) ) {
            return self::detect_taxonomy();
        }

        return new SEOContext( 'unknown' );
    }
}
```

### 4.3 SchemaManager — The Big One

Outputs a single `<script type="application/ld+json">` block containing all applicable schema for the page.

**Key design decision:** One JSON-LD block with `@graph` array, not multiple `<script>` blocks. Google recommends this pattern for pages with multiple entity types.

```php
class SchemaManager {

    public static function output( SEOContext $context ): void {
        $graph = [];

        // Website schema (every page)
        $graph[] = self::website_schema();

        // Organization schema (every page)
        $graph[] = self::organization_schema();

        // Context-specific schema
        switch ( $context->type ) {
            case 'business':
                $graph[] = self::local_business_schema( $context );
                // Breadcrumb: Home > Explore > City > Business
                $graph[] = self::breadcrumb_schema( $context );
                break;

            case 'explore_city':
                $graph[] = self::item_list_schema( $context );
                // Breadcrumb: Home > Explore > City
                $graph[] = self::breadcrumb_schema( $context );
                break;

            case 'explore_intersection':
                $graph[] = self::item_list_schema( $context );
                // Breadcrumb: Home > Explore > City > Tag
                $graph[] = self::breadcrumb_schema( $context );
                break;

            case 'event':
                $graph[] = self::event_schema( $context );
                $graph[] = self::breadcrumb_schema( $context );
                break;

            case 'article':
                // Generated article: Article + ItemList from post meta
                $custom_schema = get_post_meta( get_the_ID(), '_bd_schema_json', true );
                if ( ! empty( $custom_schema ) ) {
                    $decoded = json_decode( $custom_schema, true );
                    if ( is_array( $decoded ) ) {
                        $graph[] = $decoded;
                    }
                }
                $graph[] = self::breadcrumb_schema( $context );
                break;

            case 'list':
                $graph[] = self::collection_page_schema( $context );
                break;

            case 'neighborhood':
                $graph[] = self::neighborhood_schema( $context );
                $graph[] = self::breadcrumb_schema( $context );
                break;

            case 'trail':
                $graph[] = self::trail_schema( $context );
                $graph[] = self::breadcrumb_schema( $context );
                break;

            case 'taxonomy':
                $graph[] = self::item_list_schema( $context );
                $graph[] = self::breadcrumb_schema( $context );
                break;
        }

        // Filter for add-on plugins to modify
        $graph = apply_filters( 'bd_seo_schema_graph', $graph, $context );

        // Remove empty entries
        $graph = array_filter( $graph );

        if ( empty( $graph ) ) {
            return;
        }

        $output = [
            '@context' => 'https://schema.org',
            '@graph'   => array_values( $graph ),
        ];

        printf(
            '<script type="application/ld+json">%s</script>' . "\n",
            wp_json_encode( $output, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE )
        );
    }
}
```

#### 4.3.1 LocalBusiness Schema (NEW — highest impact)

This is the single most impactful missing schema. Every business page should output this.

```php
private static function local_business_schema( SEOContext $context ): array {
    $business_id = $context->object_id;
    $location    = get_post_meta( $business_id, 'bd_location', true );
    $contact     = get_post_meta( $business_id, 'bd_contact', true );
    $hours       = get_post_meta( $business_id, 'bd_hours', true );
    $social      = get_post_meta( $business_id, 'bd_social', true );

    $schema = [
        '@type'   => self::map_category_to_schema_type( $business_id ),
        '@id'     => get_permalink( $business_id ) . '#business',
        'name'    => get_the_title( $business_id ),
        'url'     => get_permalink( $business_id ),
        'image'   => get_the_post_thumbnail_url( $business_id, 'large' ),
    ];

    // Address
    if ( is_array( $location ) && ! empty( $location['address'] ) ) {
        $schema['address'] = [
            '@type'           => 'PostalAddress',
            'streetAddress'   => $location['address'] ?? '',
            'addressLocality' => $location['city'] ?? '',
            'addressRegion'   => $location['state'] ?? 'CA',
            'postalCode'      => $location['zip'] ?? '',
            'addressCountry'  => 'US',
        ];
    }

    // Geo coordinates
    if ( ! empty( $location['lat'] ) && ! empty( $location['lng'] ) ) {
        $schema['geo'] = [
            '@type'     => 'GeoCoordinates',
            'latitude'  => (float) $location['lat'],
            'longitude' => (float) $location['lng'],
        ];
    }

    // Contact
    if ( is_array( $contact ) ) {
        if ( ! empty( $contact['phone'] ) ) {
            $schema['telephone'] = $contact['phone'];
        }
    }

    // Price range
    $price_level = get_post_meta( $business_id, 'bd_price_level', true );
    if ( ! empty( $price_level ) ) {
        $schema['priceRange'] = $price_level; // "$", "$$", "$$$", "$$$$"
    }

    // Aggregate rating from BD reviews
    $rating_data = self::get_aggregate_rating( $business_id );
    if ( $rating_data ) {
        $schema['aggregateRating'] = [
            '@type'       => 'AggregateRating',
            'ratingValue' => $rating_data['average'],
            'reviewCount' => $rating_data['count'],
            'bestRating'  => 5,
            'worstRating' => 1,
        ];
    }

    // Opening hours
    if ( is_array( $hours ) ) {
        $schema['openingHoursSpecification'] = self::format_hours_schema( $hours );
    }

    // Social profiles (sameAs)
    $same_as = [];
    if ( is_array( $social ) ) {
        foreach ( $social as $platform => $url ) {
            if ( ! empty( $url ) ) {
                $same_as[] = $url;
            }
        }
    }
    if ( ! empty( $same_as ) ) {
        $schema['sameAs'] = $same_as;
    }

    return $schema;
}

/**
 * Map BD categories to Schema.org types.
 * Falls back to LocalBusiness for unmapped categories.
 */
private static function map_category_to_schema_type( int $business_id ): string {
    $categories = wp_get_post_terms( $business_id, 'bd_category', [ 'fields' => 'slugs' ] );

    if ( is_wp_error( $categories ) || empty( $categories ) ) {
        return 'LocalBusiness';
    }

    $map = [
        'restaurant'    => 'Restaurant',
        'restaurants'   => 'Restaurant',
        'cafe'          => 'CafeOrCoffeeShop',
        'coffee'        => 'CafeOrCoffeeShop',
        'bar'           => 'BarOrPub',
        'brewery'       => 'Brewery',
        'winery'        => 'Winery',
        'hotel'         => 'Hotel',
        'lodging'       => 'LodgingBusiness',
        'gym'           => 'ExerciseGym',
        'fitness'       => 'ExerciseGym',
        'salon'         => 'BeautySalon',
        'spa'           => 'DaySpa',
        'dentist'       => 'Dentist',
        'doctor'        => 'Physician',
        'medical'       => 'MedicalBusiness',
        'veterinary'    => 'VeterinaryCare',
        'auto-repair'   => 'AutoRepair',
        'store'         => 'Store',
        'shopping'      => 'Store',
        'real-estate'   => 'RealEstateAgent',
        'insurance'     => 'InsuranceAgency',
        'bank'          => 'BankOrCreditUnion',
        'legal'         => 'LegalService',
        'accounting'    => 'AccountingService',
        'entertainment' => 'EntertainmentBusiness',
        'park'          => 'Park',
    ];

    foreach ( $categories as $slug ) {
        if ( isset( $map[ $slug ] ) ) {
            return $map[ $slug ];
        }
    }

    return 'LocalBusiness';
}
```

#### 4.3.2 ItemList Schema for Explore Pages

Every explore intersection page (e.g., `/explore/livermore/winery/`) is a natural `ItemList`. This is the schema type that wins "best X in Y" featured snippets.

```php
private static function item_list_schema( SEOContext $context ): array {
    $businesses = $context->businesses ?? [];

    if ( empty( $businesses ) ) {
        return [];
    }

    $items = [];
    foreach ( $businesses as $position => $business ) {
        $item = [
            '@type'    => 'ListItem',
            'position' => $position + 1,
            'item'     => [
                '@type' => 'LocalBusiness',
                'name'  => $business['title'],
                'url'   => $business['permalink'] ?? '',
            ],
        ];

        if ( ! empty( $business['location']['address'] ) ) {
            $item['item']['address'] = [
                '@type'           => 'PostalAddress',
                'addressLocality' => $business['location']['city'] ?? '',
                'addressRegion'   => 'CA',
            ];
        }

        if ( ! empty( $business['rating'] ) ) {
            $item['item']['aggregateRating'] = [
                '@type'       => 'AggregateRating',
                'ratingValue' => $business['rating'],
            ];
        }

        $items[] = $item;
    }

    $name = '';
    if ( $context->type === 'explore_intersection' && $context->tag && $context->area ) {
        $name = sprintf( '%s in %s', $context->tag->name, $context->area->name );
    } elseif ( $context->type === 'explore_city' && $context->area ) {
        $name = sprintf( 'Local Businesses in %s', $context->area->name );
    }

    return [
        '@type'           => 'ItemList',
        'name'            => $name,
        'numberOfItems'   => count( $items ),
        'itemListElement' => $items,
    ];
}
```

#### 4.3.3 Event Schema Enrichment

When a `tribe_events` post is linked to a `bd_business` via the `BusinessLinker`, the event schema should include the business as the location/organizer with full structured data. The Events Calendar outputs basic event schema; we enrich it.

```php
private static function event_schema( SEOContext $context ): array {
    $event_id    = $context->object_id;
    $business_id = $context->linked_business_id;

    $schema = [
        '@type'     => 'Event',
        'name'      => get_the_title( $event_id ),
        'url'       => get_permalink( $event_id ),
        'startDate' => get_post_meta( $event_id, '_EventStartDate', true ),
        'endDate'   => get_post_meta( $event_id, '_EventEndDate', true ),
    ];

    // If linked to a BD business, use full LocalBusiness as location
    if ( $business_id ) {
        $location = get_post_meta( $business_id, 'bd_location', true );
        $schema['location'] = [
            '@type'   => 'Place',
            'name'    => get_the_title( $business_id ),
            'url'     => get_permalink( $business_id ),
            'address' => [
                '@type'           => 'PostalAddress',
                'streetAddress'   => $location['address'] ?? '',
                'addressLocality' => $location['city'] ?? '',
                'addressRegion'   => $location['state'] ?? 'CA',
                'postalCode'      => $location['zip'] ?? '',
            ],
        ];

        if ( ! empty( $location['lat'] ) && ! empty( $location['lng'] ) ) {
            $schema['location']['geo'] = [
                '@type'     => 'GeoCoordinates',
                'latitude'  => (float) $location['lat'],
                'longitude' => (float) $location['lng'],
            ];
        }
    }

    // Event image
    $thumbnail = get_the_post_thumbnail_url( $event_id, 'large' );
    if ( $thumbnail ) {
        $schema['image'] = $thumbnail;
    }

    // Description
    $schema['description'] = wp_trim_words( get_the_excerpt( $event_id ), 30 );

    return $schema;
}
```

#### 4.3.4 BreadcrumbList Schema

Every BD page type should output breadcrumbs. This hooks into the `bd_explore_before_header` action that explore templates already fire.

```php
private static function breadcrumb_schema( SEOContext $context ): array {
    $items = [
        [ 'name' => 'Home', 'url' => home_url( '/' ) ],
    ];

    switch ( $context->type ) {
        case 'explore_city':
            $items[] = [ 'name' => 'Explore', 'url' => home_url( '/explore/' ) ];
            $items[] = [ 'name' => $context->area->name, 'url' => '' ]; // current page
            break;

        case 'explore_intersection':
            $items[] = [ 'name' => 'Explore', 'url' => home_url( '/explore/' ) ];
            $items[] = [
                'name' => $context->area->name,
                'url'  => home_url( '/explore/' . $context->area->slug . '/' ),
            ];
            $items[] = [ 'name' => $context->tag->name, 'url' => '' ];
            break;

        case 'business':
            $items[] = [ 'name' => 'Explore', 'url' => home_url( '/explore/' ) ];
            $area = self::get_business_area( $context->object_id );
            if ( $area ) {
                $items[] = [
                    'name' => $area->name,
                    'url'  => home_url( '/explore/' . $area->slug . '/' ),
                ];
            }
            $items[] = [ 'name' => get_the_title( $context->object_id ), 'url' => '' ];
            break;

        case 'neighborhood':
            $items[] = [ 'name' => 'Neighborhoods', 'url' => home_url( '/neighborhoods/' ) ];
            $items[] = [ 'name' => $context->neighborhood_name, 'url' => '' ];
            break;

        case 'event':
            $items[] = [ 'name' => 'Events', 'url' => home_url( '/events/' ) ];
            $items[] = [ 'name' => get_the_title( $context->object_id ), 'url' => '' ];
            break;
    }

    $list_items = [];
    foreach ( $items as $position => $item ) {
        $list_item = [
            '@type'    => 'ListItem',
            'position' => $position + 1,
            'name'     => $item['name'],
        ];
        if ( ! empty( $item['url'] ) ) {
            $list_item['item'] = $item['url'];
        }
        $list_items[] = $list_item;
    }

    return [
        '@type'           => 'BreadcrumbList',
        'itemListElement' => $list_items,
    ];
}
```

### 4.4 TitleManager

Consolidates all `<title>` tag logic into one filter. Currently, `ExploreRouter::filter_document_title()` handles explore pages and defers to `BusinessDirectorySEO\Plugin` if present. This new class subsumes that logic and adds post-meta-based overrides.

```php
class TitleManager {

    public static function filter_title( array $parts ): array {
        // 1. Post meta override (highest priority — Article Generator, manual)
        if ( is_singular() ) {
            $custom_title = get_post_meta( get_the_ID(), '_bd_seo_title', true );
            if ( ! empty( $custom_title ) ) {
                $parts['title'] = $custom_title;
                return $parts;
            }
        }

        // 2. Explore pages (moved from ExploreRouter)
        $explore_type = get_query_var( 'bd_explore' );
        if ( ! empty( $explore_type ) ) {
            $parts = self::explore_title( $parts, $explore_type );
            return $parts;
        }

        // 3. Taxonomy archives
        if ( is_tax( 'bd_category' ) || is_tax( 'bd_area' ) || is_tax( 'bd_tag' ) ) {
            $parts = self::taxonomy_title( $parts );
            return $parts;
        }

        return $parts;
    }
}
```

### 4.5 AutoLinker (NEW)

The internal linking engine that benefits all content across the network. Processes `the_content` output to add contextual links.

```php
class AutoLinker {

    private const MAX_LINKS_PER_POST    = 10;
    private const MAX_LINKS_PER_PARAGRAPH = 2;
    private const MIN_CONTENT_LENGTH     = 500; // Don't link in short content

    public static function init(): void {
        add_filter( 'the_content', [ __CLASS__, 'process' ], 99 );
    }

    public static function process( string $content ): string {
        // Only on singular pages (not archives, not admin)
        if ( ! is_singular() || is_admin() ) {
            return $content;
        }

        // Skip if content is too short
        if ( strlen( wp_strip_all_tags( $content ) ) < self::MIN_CONTENT_LENGTH ) {
            return $content;
        }

        // Skip inside bd/feature blocks (they already have links)
        // Skip inside existing <a> tags
        // Skip inside headings

        $link_map    = self::build_link_map();
        $used_targets = [];
        $total_links  = 0;

        // Process paragraph by paragraph, skipping block markup
        // ... (implementation follows InternalLinker pattern from Article Generator spec)

        return $content;
    }

    private static function build_link_map(): array {
        $links = [];

        // City names → explore city pages
        $areas = get_terms( [ 'taxonomy' => 'bd_area', 'hide_empty' => false ] );
        if ( ! is_wp_error( $areas ) ) {
            foreach ( $areas as $area ) {
                $links[ strtolower( $area->name ) ] = home_url( '/explore/' . $area->slug . '/' );
            }
        }

        // Tag names → explore intersection pages (using current page's city)
        // Only link to intersections that have 2+ businesses
        // ... (city-aware linking)

        // Cache for 1 hour
        return $links;
    }
}
```

### 4.6 MultisiteCanonical (NEW)

Coordinates canonical URLs across the multisite network. The core challenge: business listings can be rendered on both the main site and subsites via the `bd/feature` block and REST API. Google must see a single canonical source.

**Rules:**

| Content Type | Canonical Points To |
|-------------|-------------------|
| Business listing page | Main site: `lovetrivalley.com/places/{slug}/` |
| Explore page | Whichever site renders it (main site or subsite) |
| Article (generated) | Subsite where it was published |
| Event page | Main site (where TEC is installed) |
| List page | Main site (where lists DB lives) |
| Neighborhood page | Subsite for that city |

```php
class MultisiteCanonical {

    public static function init(): void {
        if ( ! is_multisite() ) {
            return;
        }

        add_filter( 'bd_seo_canonical_url', [ __CLASS__, 'filter_canonical' ], 10, 2 );
    }

    public static function filter_canonical( string $url, SEOContext $context ): string {
        // Business pages on subsites should canonical to main site
        if ( $context->type === 'business' && ! is_main_site() ) {
            $main_site_url = get_site_url( get_main_site_id() );
            return trailingslashit( $main_site_url ) . 'places/' . get_post_field( 'post_name', $context->object_id ) . '/';
        }

        return $url;
    }
}
```

---

## 5. Neighborhood Pages Integration

The BD Neighborhood plugin creates pages for specific neighborhoods within each city (e.g., "Downtown Livermore," "Ruby Hill in Pleasanton"). These pages need SEO treatment.

### 5.1 Schema for Neighborhood Pages

```php
private static function neighborhood_schema( SEOContext $context ): array {
    return [
        '@type'       => 'Place',
        'name'        => $context->neighborhood_name,
        'description' => $context->neighborhood_description,
        'geo'         => [
            '@type'     => 'GeoCoordinates',
            'latitude'  => $context->neighborhood_lat,
            'longitude' => $context->neighborhood_lng,
        ],
        'containedInPlace' => [
            '@type' => 'City',
            'name'  => $context->city_name,
            'containedInPlace' => [
                '@type' => 'State',
                'name'  => 'California',
            ],
        ],
    ];
}
```

### 5.2 Neighborhood OG Tags

Neighborhood pages should have specific OG images (neighborhood hero/cover images) and descriptions that reference the city and neighborhood character.

### 5.3 Hook Points

The Orchestrator listens for neighborhood pages via `ContextDetector::is_neighborhood_page()`. The BD Neighborhood plugin should fire an action hook that provides its data:

```php
// BD Neighborhood plugin fires this:
do_action( 'bd_neighborhood_seo_data', [
    'name'        => 'Downtown Livermore',
    'description' => 'The heart of Livermore...',
    'city'        => 'Livermore',
    'lat'         => 37.6819,
    'lng'         => -121.7681,
    'image'       => 'https://...',
    'businesses_count' => 45,
] );

// Orchestrator catches it and stores for schema output
```

---

## 6. Events Calendar SEO Enhancement

### 6.1 Current State

The `EventsCalendarIntegration` already links businesses to events/venues/organizers via `bd_linked_business` meta. The `BusinessLinker` provides the admin UI. The `VenueSyncer` auto-links venues to businesses.

### 6.2 What This Spec Adds

**Event schema enrichment.** When TEC outputs its own Event schema (which it does natively), our `SchemaManager` adds the linked business's full `LocalBusiness` data as the event's `location`. TEC only outputs the venue name and address; we add geo coordinates, phone, website, rating, hours, and social links from BD Pro's much richer data.

**Cross-site event schema.** The `CityEventsShortcode` renders events on subsites via REST API. The schema for these remote events needs to include the correct `url` pointing back to the main site's event page, not a URL on the subsite.

**Event-to-business internal links.** The `AutoLinker` should recognize event names mentioned in articles and link to event pages, just as it links city and tag mentions to explore pages.

---

## 7. Migration Plan

### 7.1 What Gets Consolidated

| Current Location | Moves To | Notes |
|-----------------|----------|-------|
| `ExploreRouter::output_canonical()` | `CanonicalManager::output()` | Remove from ExploreRouter after Orchestrator is active |
| `ExploreRouter::filter_document_title()` | `TitleManager::filter_title()` | ExploreRouter keeps its deference check during transition |
| `ListSocialMeta` (entire class) | `OpenGraphManager` | Can be removed entirely once Orchestrator handles all OG |
| `OpenGraph` in `src/Social/` | `OpenGraphManager` | Same — consolidated into one OG output path |
| `reviews-section-seo.php` microdata | **No change** | Inline review microdata is fine. It supplements the page-level JSON-LD. |

### 7.2 Backward Compatibility

The Orchestrator checks if its consolidated classes are active before removing hooks from the old locations. If the migration is partial (e.g., only `SchemaManager` is deployed), the old OG/canonical code continues to work.

```php
// In Orchestrator::init()
if ( class_exists( __NAMESPACE__ . '\\OpenGraphManager' ) ) {
    // Remove old fragmented OG output
    remove_action( 'wp_head', [ \BD\Frontend\ListSocialMeta::class, 'output_meta_tags' ], 5 );
    // ... remove OpenGraph in src/Social/ ...
}
```

---

## 8. Phased Rollout

### Phase 1: Foundation (Unblocks Article Generator)

**Goal:** Define post meta convention, add JSON-LD output hook, build TitleManager.

- Define `_bd_seo_title`, `_bd_seo_description`, `_bd_schema_json` meta keys
- Build `TitleManager` with `document_title_parts` filter (absorbs ExploreRouter logic)
- Build `MetaDescriptionManager` with `<meta name="description">` output
- Build minimal `SchemaManager` that reads `_bd_schema_json` from post meta and outputs it
- Add `bd_seo_schema_graph` filter for add-on plugins
- Register in `seo-loader.php` alongside existing `SlugMigration`

**Lines of code:** ~400
**Risk:** Low — additive only, no existing behavior changes

### Phase 2: LocalBusiness Schema + Orchestrator

**Goal:** Biggest SEO impact. Every business page gets full JSON-LD.

- Build `ContextDetector` with business/explore/event/list detection
- Build full `SchemaManager` with `LocalBusiness`, `ItemList`, `BreadcrumbList`
- Build `Orchestrator` as central `wp_head` controller
- Build `CanonicalManager` (absorbs `ExploreRouter::output_canonical`)
- Build `OpenGraphManager` (absorbs `ListSocialMeta` + `OpenGraph`)
- Add third-party SEO plugin detection and graceful deferral
- Remove hooks from old locations

**Lines of code:** ~1,200
**Risk:** Medium — replaces existing output. Needs staging QA with before/after comparison of `<head>` output on every page type.

### Phase 3: Internal Linking + Cross-Site

- Build `AutoLinker` (the commented-out stub becomes real)
- Build `RelatedBusinesses` cross-links
- Build `MultisiteCanonical` for network canonical coordination
- Add Event schema enrichment (linked business data in event JSON-LD)
- Add Neighborhood page detection and schema

**Lines of code:** ~800
**Risk:** Medium — AutoLinker modifies `the_content` output. Needs careful testing for edge cases (content inside shortcodes, blocks, etc.)

### Phase 4: Intelligence

- SEO audit dashboard: which business pages are missing data (no image, no hours, no description)
- Schema validation: automated testing against Google's Rich Results Test API
- Internal link report: which pages have the fewest internal links pointing to them
- Explore page coverage: which city × tag intersections exist but have no article

---

## 9. Testing Checklist

### 9.1 Schema Validation

- [ ] Single business page: validate `LocalBusiness` in Google Rich Results Test
- [ ] Business with reviews: validate `AggregateRating` appears
- [ ] Business with hours: validate `openingHoursSpecification` is correct
- [ ] Business with categories: validate schema type maps correctly (Winery, Restaurant, etc.)
- [ ] Explore intersection page: validate `ItemList` with correct `ListItem` entries
- [ ] Explore city page: validate `ItemList` with all businesses
- [ ] Event page linked to business: validate `Event` schema includes `location` with full address
- [ ] Generated article: validate `Article` + `ItemList` from post meta
- [ ] Every page type: validate `BreadcrumbList` trail is correct
- [ ] `@graph` array: validate single `<script>` block, not multiple

### 9.2 Third-Party Plugin Compatibility

- [ ] With Yoast active: no duplicate OG tags, no duplicate meta descriptions, BD schema still outputs
- [ ] With RankMath active: same checks
- [ ] With no SEO plugin: full BD output (title, description, OG, schema)
- [ ] Yoast OG image filter: BD's custom OG image for lists/businesses overrides Yoast default

### 9.3 Multisite

- [ ] Business page on main site: canonical is self
- [ ] Business rendered via `bd/feature` on subsite: canonical points to main site
- [ ] Article on subsite: canonical is self (subsite URL)
- [ ] Explore page on subsite: canonical is self
- [ ] API key for Article Generator accessible from subsites via `get_site_option`

### 9.4 Performance

- [ ] No additional database queries on pages where SEO output is "unknown" context
- [ ] Schema data uses existing cached queries (QueryBuilder's batch cache, transients)
- [ ] AutoLinker builds link map once per request, caches in transient
- [ ] No assets enqueued on frontend (SEO is all `wp_head` output, no JS/CSS)

---

## 10. Open Questions

1. **BD Neighborhood plugin hooks** — Need to confirm what action hooks neighborhood pages fire and what data they make available. The `ContextDetector::is_neighborhood_page()` method needs to know how to detect these pages.

2. **Events Calendar native schema** — TEC outputs its own Event JSON-LD. Should we modify theirs via filter, or output our own alongside it? Recommendation: use TEC's `tribe_json_ld_event_object` filter to enrich rather than duplicate.

3. **Review schema: microdata vs JSON-LD** — The current `reviews-section-seo.php` uses inline microdata. Should we migrate to JSON-LD for consistency? Recommendation: keep microdata for reviews (it's correctly scoped to the review HTML elements) and add JSON-LD `AggregateRating` at the page level in `LocalBusiness` schema. Google handles both formats and merges them.

4. **BD-Core-SEO as separate plugin** — If you prefer keeping this as a separate companion plugin rather than building it into BD Pro's `src/SEO/`, the architecture is the same — just different file locations and a `require_once` dependency check instead of being always-loaded. Let me know your preference.

5. **OG image sizing** — Should we generate specific 1200×630 OG images for business pages, or use the featured image with `og:image:width`/`og:image:height`? The list cover system already generates sized images. Recommendation: use featured image with dimensions for Phase 1; add auto-cropped OG images in a future phase.

---

## 11. Files Changed/Created

### New Files

| File | Purpose | Est. Lines |
|------|---------|-----------|
| `src/SEO/Orchestrator.php` | Central `wp_head` controller | 120 |
| `src/SEO/ContextDetector.php` | Page type detection | 200 |
| `src/SEO/SEOContext.php` | Data object for context | 80 |
| `src/SEO/TitleManager.php` | `<title>` filtering | 100 |
| `src/SEO/MetaDescriptionManager.php` | Meta description output | 80 |
| `src/SEO/OpenGraphManager.php` | OG + Twitter Card | 200 |
| `src/SEO/SchemaManager.php` | All JSON-LD output | 500 |
| `src/SEO/BreadcrumbManager.php` | Breadcrumb schema + HTML | 120 |
| `src/SEO/CanonicalManager.php` | Canonical URL output | 100 |
| `src/SEO/AutoLinker.php` | Internal linking engine | 250 |
| `src/SEO/RelatedBusinesses.php` | Cross-link widget | 150 |
| `src/SEO/MultisiteCanonical.php` | Network canonical rules | 100 |

### Modified Files

| File | Change |
|------|--------|
| `includes/seo-loader.php` | Uncomment all classes, add new class paths |
| `src/Explore/ExploreRouter.php` | Remove `output_canonical()` and `filter_document_title()` (Orchestrator handles) |
| `src/Frontend/ListSocialMeta.php` | Deprecate, remove `wp_head` hook (Orchestrator handles) |
| `src/Social/OpenGraph.php` | Deprecate, remove `wp_head` hook (Orchestrator handles) |

### Total New Code: ~2,000 lines across 12 files
