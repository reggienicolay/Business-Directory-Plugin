# Event Image Manager — Technical Specification

**Component:** `BD\Integrations\EventsCalendar\EventImageManager`
**File:** `src/Integrations/EventsCalendar/EventImageManager.php`
**Version:** 1.5.0
**Status:** SPEC — Do not implement until reviewed and approved.

---

## 1. Problem Statement

BD Event Aggregator imports events from Eventbrite, iCal, Google Calendar, Livermore Arts, and Alameda County Fair. Each import can sideload a featured image via `media_handle_sideload()`. WordPress then generates every registered image size (thumbnail, medium, medium_large, large, 1536x1536, 2048x2048, plus any theme/plugin sizes). Over months, this creates significant bloat:

- **Storage:** A single 3000×2000 source image can produce 8–12 derivative files totaling 5–10 MB. At 50 events/month, that's 250–500 MB/year of images for events nobody will ever view again.
- **Database:** Each attachment creates a row in `wp_posts`, `wp_postmeta` (5–8 rows for sizes, alt, file path), and potentially `wp_term_relationships`.
- **Backup size:** Inflated backups slow down Cloudways snapshot/restore cycles.

### Goals

1. Optimize images at import time so they're smaller from day one.
2. Auto-delete imported event images N days after the event ends.
3. Never touch images that a user uploaded manually.
4. Keep images looking sharp on the single event page header and in grid/list cards.

---

## 2. Image Size Audit — What TEC & Our Templates Actually Use

Before stripping sizes, we need to know what's consumed where:

| Context | Template / Shortcode | Image Size Used | Min Dimensions Needed |
|---|---|---|---|
| TEC single event hero | TEC's `single-event.php` block template | `full` (falls back to `large`) | 1200×630 ideal (OG share size) |
| City events grid card | `CityEventsShortcode::render_grid_v2()` | `medium` (via `get_the_post_thumbnail_url($id, 'medium')`) | 300×300 |
| City events list card | `CityEventsShortcode::render_list_v2()` | `medium` (same pattern) | 300×200 |
| REST API `/events/city/` | `EventsCalendarIntegration::rest_get_city_events()` | `medium` (via `get_the_post_thumbnail_url($id, 'medium')`) | 300×300 |
| Business page event chip | `immersive.php` hero | No image — text only | N/A |
| OG / social sharing | Theme or SEO plugin | `full` or `large` | 1200×630 |

### Sizes to KEEP for event images

- **`large`** — WordPress default 1024×1024. Used by TEC single event page and OG tags. This is the hero image.
- **`medium`** — WordPress default 300×300. Used by all our grid/list card templates.
- **`thumbnail`** — WordPress default 150×150. Used by admin columns, widgets, fallbacks.

### Sizes to STRIP for event images

Everything else: `medium_large` (768px), `1536x1536`, `2048x2048`, and any Kadence/theme-registered sizes. These are never referenced in any event template.

**Estimated savings:** Stripping 5–7 unused sizes saves ~40–60% of per-image disk usage.

---

## 3. Architecture

### 3.1 Class Location & Namespace

```
src/Integrations/EventsCalendar/EventImageManager.php
```

Namespace: `BD\Integrations\EventsCalendar`

Loaded from `EventsCalendarIntegration::init()` — same pattern as BusinessLinker, CityEventsShortcode, VenueSyncer:

```php
require_once __DIR__ . '/EventImageManager.php';
EventImageManager::init();
```

### 3.2 Constants

```php
const CRON_HOOK          = 'bd_prune_expired_event_images';
const IMPORTED_META_KEY  = '_bd_event_imported_image';  // Flag on attachment
const SOURCE_META_KEY    = '_bd_event_image_source';    // 'eventbrite', 'ical', etc.
const DEFAULT_RETENTION  = 30;                          // Days after event end
const MAX_IMPORT_WIDTH   = 1200;                        // Cap source image width
const MAX_IMPORT_HEIGHT  = 800;                         // Cap source image height
const PRUNE_BATCH_SIZE   = 50;                          // Events per cron tick
const WEBP_QUALITY       = 82;                          // WebP compression quality
```

### 3.3 Option Keys

```php
'bd_event_image_retention_days'  => 30      // Admin-configurable
'bd_event_image_pruning_enabled' => true    // Kill switch
'bd_event_image_last_prune'      => ''      // ISO timestamp of last run
'bd_event_image_prune_stats'     => []      // { total_pruned, total_bytes_freed, last_run }
```

---

## 4. Feature 1: Optimize on Ingest

### 4.1 Hook Point

The Event Aggregator calls `media_handle_sideload()` to import images. We hook into two filters that fire during that process:

```php
// 1. Cap dimensions BEFORE WordPress generates sub-sizes
add_filter( 'wp_handle_sideload', [ __CLASS__, 'cap_imported_dimensions' ] );

// 2. Strip unnecessary sizes for event images only
add_filter( 'intermediate_image_sizes_advanced', [ __CLASS__, 'limit_event_image_sizes' ], 10, 3 );
```

**Problem:** `intermediate_image_sizes_advanced` fires for ALL uploads, not just event images. We need a way to know we're currently inside an event image import.

### 4.2 Context Flag Pattern

```php
private static bool $importing_event_image = false;

public static function begin_event_import(): void {
    self::$importing_event_image = true;
}

public static function end_event_import(): void {
    self::$importing_event_image = false;
}
```

The Event Aggregator calls `begin_event_import()` before `media_handle_sideload()` and `end_event_import()` after. The filter checks this flag:

```php
public static function limit_event_image_sizes( $sizes, $image_meta, $attachment_id ) {
    if ( ! self::$importing_event_image ) {
        return $sizes;
    }

    $keep = [ 'thumbnail', 'medium', 'large' ];
    return array_intersect_key( $sizes, array_flip( $keep ) );
}
```

**Alternative approach (if we can't modify the aggregator call sites):** Hook into `add_attachment` and check if the parent post (`post_parent`) is a `tribe_events` post. Less clean but works retroactively. Downside: sizes are already generated by then, so we'd need to delete them after the fact — wasteful.

**Decision: Use the context flag.** It's explicit, zero overhead, and we control the aggregator code.

### 4.3 Dimension Capping

Before WordPress processes the upload, resize the source if it exceeds 1200×800:

```php
public static function maybe_downscale_source( int $attachment_id ): void {
    if ( ! self::$importing_event_image ) {
        return;
    }

    $file = get_attached_file( $attachment_id );
    $editor = wp_get_image_editor( $file );

    if ( is_wp_error( $editor ) ) {
        return;
    }

    $size = $editor->get_size();

    if ( $size['width'] > self::MAX_IMPORT_WIDTH || $size['height'] > self::MAX_IMPORT_HEIGHT ) {
        $editor->resize( self::MAX_IMPORT_WIDTH, self::MAX_IMPORT_HEIGHT );
        $editor->save( $file );

        // Update attachment metadata to reflect new dimensions
        wp_update_attachment_metadata( $attachment_id, wp_generate_attachment_metadata( $attachment_id, $file ) );
    }
}
```

**Hook:** `add_attachment` (fires after file is in media library but before sub-sizes are generated? No — sub-sizes are generated inside `media_handle_sideload` before `add_attachment` fires.)

**Correction:** Sub-size generation happens inside `wp_generate_attachment_metadata()` which is called by `media_handle_sideload()`. So we need to intervene BEFORE that. The correct hook is `wp_handle_sideload` (fires after the file is moved to uploads but before metadata generation):

```php
add_filter( 'wp_handle_sideload', [ __CLASS__, 'cap_imported_dimensions' ] );

public static function cap_imported_dimensions( $file_data ) {
    if ( ! self::$importing_event_image ) {
        return $file_data;
    }

    $editor = wp_get_image_editor( $file_data['file'] );
    if ( is_wp_error( $editor ) ) {
        return $file_data;
    }

    $size = $editor->get_size();
    if ( $size['width'] > self::MAX_IMPORT_WIDTH || $size['height'] > self::MAX_IMPORT_HEIGHT ) {
        $editor->resize( self::MAX_IMPORT_WIDTH, self::MAX_IMPORT_HEIGHT );
        $editor->save( $file_data['file'] );
    }

    return $file_data;
}
```

### 4.4 WebP Conversion

If the server supports WebP (check `wp_image_editor_supports(['mime_type' => 'image/webp'])`), convert the source to WebP before WordPress generates sub-sizes. This means all derivative sizes are also WebP.

**Consideration:** WordPress 5.8+ has native WebP support via `image_editor_output_format` filter. WordPress 6.1+ can generate WebP sub-sizes automatically. Since we're on WordPress 6.8.3, we can use the native filter:

```php
add_filter( 'image_editor_output_format', [ __CLASS__, 'prefer_webp' ] );

public static function prefer_webp( $formats ) {
    if ( ! self::$importing_event_image ) {
        return $formats;
    }

    if ( wp_image_editor_supports( [ 'mime_type' => 'image/webp' ] ) ) {
        $formats['image/jpeg'] = 'image/webp';
        $formats['image/png']  = 'image/webp';
    }

    return $formats;
}
```

### 4.5 Meta Flagging

After `media_handle_sideload()` returns the attachment ID, flag it:

```php
update_post_meta( $attachment_id, self::IMPORTED_META_KEY, true );
update_post_meta( $attachment_id, self::SOURCE_META_KEY, $source ); // 'eventbrite', 'ical', etc.
update_post_meta( $attachment_id, '_bd_event_id', $event_id );      // Link back to event
```

This flag is the safety net for pruning — only flagged images get deleted.

---

## 5. Feature 2: Auto-Prune Expired Event Images

### 5.1 Cron Schedule

**Timing:** 3:00 AM server time (Pacific). Low-traffic window, well before morning traffic ramp.

```php
public static function schedule_pruning(): void {
    if ( wp_next_scheduled( self::CRON_HOOK ) ) {
        return;
    }

    // Schedule for 3 AM tomorrow, then daily
    $tomorrow_3am = strtotime( 'tomorrow 03:00:00' );
    wp_schedule_event( $tomorrow_3am, 'daily', self::CRON_HOOK );
}
```

**Why `strtotime('tomorrow 03:00:00')` works:** WordPress stores cron timestamps in UTC internally but `strtotime()` uses the PHP default timezone. On Cloudways, PHP timezone is set to match WordPress (`America/Los_Angeles`), so this resolves to 3 AM Pacific → ~10:00 or 11:00 AM UTC depending on DST. If the server timezone differs from WordPress, we should use:

```php
$tz = new \DateTimeZone( wp_timezone_string() );
$dt = new \DateTime( 'tomorrow 03:00:00', $tz );
$tomorrow_3am = $dt->getTimestamp();
```

This is the safer approach. Use it.

### 5.2 Pruning Logic

```
For each expired event (end_date + retention_days < now):
    1. Get _thumbnail_id (featured image)
    2. Verify it has our IMPORTED_META_KEY flag
    3. If flagged:
        a. wp_delete_attachment( $thumb_id, true )  // true = force delete, skip trash
        b. delete_post_thumbnail( $event_id )        // Clean up post meta reference
        c. Log: event_id, attachment_id, file_size freed
    4. If NOT flagged: skip (user-uploaded image, don't touch)
```

### 5.3 Batch Processing

Process `PRUNE_BATCH_SIZE` (50) events per cron run. If more remain, schedule an immediate follow-up:

```php
if ( count( $expired_events ) >= self::PRUNE_BATCH_SIZE ) {
    // More to process — schedule immediate follow-up
    wp_schedule_single_event( time() + 30, self::CRON_HOOK );
}
```

This prevents timeouts on sites with thousands of expired events while still clearing the backlog within hours.

### 5.4 The Query

```php
$cutoff = gmdate( 'Y-m-d H:i:s', strtotime( "-{$retention_days} days" ) );

$expired_event_ids = get_posts( [
    'post_type'      => 'tribe_events',
    'post_status'    => 'any',             // Include draft, trash, etc.
    'fields'         => 'ids',
    'posts_per_page' => self::PRUNE_BATCH_SIZE,
    'meta_query'     => [
        [
            'key'     => '_EventEndDate',  // TEC's end date meta key
            'value'   => $cutoff,
            'compare' => '<',
            'type'    => 'DATETIME',
        ],
    ],
    'meta_key'       => '_thumbnail_id',   // Only events WITH a featured image
    'orderby'        => 'meta_value',
    'order'          => 'ASC',             // Oldest first
] );
```

**Edge case: recurring events.** TEC Pro stores recurring events as individual posts, each with their own `_EventEndDate`. The query naturally handles this — each occurrence is pruned independently based on its own end date.

**Edge case: events with no end date.** Some iCal events may not have an explicit end date. TEC defaults `_EventEndDate` to the start date + 1 hour. The query still works.

**Edge case: trashed events.** `post_status => 'any'` catches trashed events. Their images should be pruned too — no reason to keep images for deleted events.

### 5.5 What Happens to the Event Page After Pruning

The event post itself is NOT deleted — only the featured image. After pruning:

- **Single event page:** TEC's template shows no hero image. The event content, venue, date/time all remain. This is acceptable for a 30+ day old event that's already passed.
- **City events grid/list:** These shortcodes filter by `start_date >= now`, so expired events don't appear in listings. The missing image is irrelevant.
- **REST API:** Same — the endpoint filters by upcoming events only.
- **Search results:** If someone finds an old event via search, they see the event without an image. Acceptable.

**Optional enhancement (Phase 2):** Set a generic "Past Event" placeholder image instead of leaving it blank. Low priority.

---

## 6. Edge Cases & Safety Rails

### 6.1 Never Delete User-Uploaded Images

The `IMPORTED_META_KEY` flag is the hard boundary. An image only gets this flag when imported through our aggregator with `begin_event_import()` active. If a user manually uploads a featured image via the WordPress editor, it will NOT have this flag, and the pruner will skip it.

**Additional safety:** Before deleting, verify the attachment's `post_parent` matches the event ID. This prevents edge cases where an attachment ID was reused or reassigned.

### 6.2 Shared Images

What if two events share the same featured image (attachment ID)? This shouldn't happen with imports (each sideload creates a new attachment), but defensive check:

```php
// Check if any OTHER post uses this attachment as featured image
$other_uses = $wpdb->get_var( $wpdb->prepare(
    "SELECT COUNT(*) FROM {$wpdb->postmeta}
     WHERE meta_key = '_thumbnail_id'
     AND meta_value = %d
     AND post_id != %d",
    $thumb_id,
    $event_id
) );

if ( $other_uses > 0 ) {
    // Image is shared — detach but don't delete the file
    delete_post_thumbnail( $event_id );
    return; // Skip file deletion
}
```

### 6.3 Multisite Guards

This class runs on the main site only (where TEC and the aggregator live). The city subsites pull events via REST API and never store event images locally. No multisite guard needed beyond what `EventsCalendarIntegration::init()` already provides.

### 6.4 Kill Switch

The admin setting `bd_event_image_pruning_enabled` must be checked at the top of the cron callback:

```php
if ( ! get_option( 'bd_event_image_pruning_enabled', true ) ) {
    return;
}
```

### 6.5 Dry Run Mode

An option `bd_event_image_prune_dry_run` (default: false) logs what WOULD be deleted without actually deleting. Useful for first deployment:

```php
if ( get_option( 'bd_event_image_prune_dry_run', false ) ) {
    error_log( sprintf(
        '[BD EventImageManager] DRY RUN: Would delete attachment %d (%s) for event %d',
        $thumb_id,
        get_attached_file( $thumb_id ),
        $event_id
    ) );
    continue;
}
```

### 6.6 Idempotency

Running the pruner multiple times is safe:

- If the image is already deleted, `get_post_thumbnail_id()` returns `0` or `false` — skip.
- `wp_delete_attachment()` on a non-existent attachment returns `null` — no error.
- The query filters by events that still have `_thumbnail_id` meta, so already-pruned events are excluded.

---

## 7. Admin Settings UI

Add a section to the existing Integrations settings (rendered by `IntegrationsManager::render_settings_section()`):

### Fields

| Setting | Type | Default | Description |
|---|---|---|---|
| Enable image pruning | Checkbox | On | Kill switch for the cron job |
| Retention period | Number input | 30 | Days after event end date before image is deleted |
| Dry run mode | Checkbox | Off | Log-only mode for testing |
| Optimize on import | Checkbox | On | Enable dimension capping + size stripping |
| Convert to WebP | Checkbox | On | Convert imported images to WebP format |

### Stats Display (read-only)

- Last prune run: `{date}` — pruned `{N}` images, freed `{X} MB`
- Total images pruned to date: `{N}`
- Total space freed to date: `{X} MB`
- Next scheduled run: `{date/time}`

---

## 8. Logging & Observability

Each prune run logs a summary to `error_log`:

```
[BD EventImageManager] Pruned 23 images from 23 expired events. Freed 47.2 MB. 
Oldest event: 2025-12-15. Retention: 30 days. Duration: 1.3s.
```

Individual deletions logged only in dry-run mode or when `WP_DEBUG` is true.

Stats stored in `bd_event_image_prune_stats` option for admin display:

```php
[
    'total_pruned'       => 847,
    'total_bytes_freed'  => 1782432000,  // bytes
    'last_run'           => '2026-03-28T03:00:12-07:00',
    'last_run_count'     => 23,
    'last_run_bytes'     => 49545216,
]
```

---

## 9. Activation / Deactivation

### On Plugin Activation

```php
EventImageManager::schedule_pruning();
```

Called from the existing `register_activation_hook` in `business-directory.php`. Must respect `Installer::should_create_tables()` — only schedule on main site.

### On Plugin Deactivation

```php
wp_clear_scheduled_hook( EventImageManager::CRON_HOOK );
```

### On Uninstall

```php
delete_option( 'bd_event_image_retention_days' );
delete_option( 'bd_event_image_pruning_enabled' );
delete_option( 'bd_event_image_prune_dry_run' );
delete_option( 'bd_event_image_last_prune' );
delete_option( 'bd_event_image_prune_stats' );
// Note: Do NOT delete imported images on uninstall — that's destructive
```

---

## 10. Integration Points — Changes Required in Other Files

### 10.1 `EventsCalendarIntegration.php`

Add to `init()`:

```php
require_once __DIR__ . '/EventImageManager.php';
EventImageManager::init();
```

### 10.2 Event Aggregator Import Methods

Wherever the aggregator calls `media_handle_sideload()` for an event image, wrap with:

```php
EventImageManager::begin_event_import();

$attachment_id = media_handle_sideload( $file_array, $event_id, $title );

EventImageManager::end_event_import();

if ( ! is_wp_error( $attachment_id ) ) {
    // Flag the attachment
    update_post_meta( $attachment_id, EventImageManager::IMPORTED_META_KEY, true );
    update_post_meta( $attachment_id, EventImageManager::SOURCE_META_KEY, 'eventbrite' );
}
```

**Files to audit and modify:** All import handlers in BD Event Aggregator plugin that sideload images. This is the aggregator plugin's responsibility, not EventImageManager's — we just provide the API.

### 10.3 `business-directory.php`

Add to activation hook:

```php
\BD\Integrations\EventsCalendar\EventImageManager::schedule_pruning();
```

Add to deactivation hook:

```php
wp_clear_scheduled_hook( \BD\Integrations\EventsCalendar\EventImageManager::CRON_HOOK );
```

### 10.4 `IntegrationsManager.php`

Add settings fields for the image manager (retention days, kill switch, dry run) to the Events Calendar integration settings array.

---

## 11. Rollout Plan

### Phase 1: Dry Run (Week 1)

1. Deploy `EventImageManager.php` with pruning enabled but `dry_run = true`.
2. Wrap aggregator sideload calls with `begin/end_event_import()` + meta flags.
3. Let it run for a week. Review logs to verify:
   - Only imported images are targeted (no user uploads).
   - Correct events are identified as expired.
   - Byte counts are reasonable.

### Phase 2: Live Pruning (Week 2)

1. Disable dry run.
2. Monitor first real prune run at 3 AM.
3. Spot-check a few pruned events — confirm event page still renders, just without hero image.
4. Check disk usage delta on Cloudways.

### Phase 3: Optimize on Import (Week 2–3)

1. Enable size stripping (`limit_event_image_sizes`).
2. Enable dimension capping (`cap_imported_dimensions`).
3. Enable WebP conversion if server supports it.
4. Run a test import batch and verify:
   - Only `thumbnail`, `medium`, `large` sizes generated.
   - Source capped at 1200×800.
   - Single event page hero still looks crisp.
   - Grid cards still look sharp.

### Phase 4: Backfill Cleanup (Optional, Week 4)

One-time WP-CLI command to prune images from events that already expired before the system was deployed:

```bash
wp eval 'BD\Integrations\EventsCalendar\EventImageManager::backfill_prune();'
```

This would query ALL expired events (not just flagged ones) but still respect the `IMPORTED_META_KEY` check. Only images that were imported by the aggregator get deleted.

---

## 12. Testing Checklist

- [ ] Import an event with a 4000×3000 image → source capped to 1200×800
- [ ] Imported image only has thumbnail, medium, large sizes → no medium_large, 1536, 2048
- [ ] Imported image has `_bd_event_imported_image` meta flag
- [ ] Manually-uploaded event image does NOT have the meta flag
- [ ] Single event page renders hero from `large` size → looks crisp at 1024px wide
- [ ] City events grid card renders from `medium` size → sharp at 300×300
- [ ] Cron fires at ~3 AM Pacific
- [ ] Dry run mode logs but does not delete
- [ ] Pruner skips events within retention period
- [ ] Pruner skips images without `_bd_event_imported_image` flag
- [ ] Pruner deletes flagged images from expired events
- [ ] Pruner handles shared images (detach but don't delete file)
- [ ] Event page still renders after image is pruned (just no hero)
- [ ] Stats option updated after each prune run
- [ ] Kill switch stops pruning immediately
- [ ] Plugin deactivation clears the cron hook
- [ ] Runs idempotently — multiple executions don't cause errors
- [ ] No errors on subsites (multisite guard)

---

## 13. Open Questions

1. **Should we also prune the event post itself?** Current answer: No. Events have SEO value even after they pass (Google indexes them, backlinks exist). Only the image is pruned. Revisit if DB row bloat becomes a concern.

2. **Should we keep a low-quality placeholder after pruning?** Could replace the deleted image with a generic "Past Event" image. Adds complexity for marginal UX benefit on pages that get near-zero traffic. Defer to Phase 2.

3. **Retention period per source?** Eventbrite events might warrant longer retention (higher quality images) vs. iCal imports (often low-res). Currently using a single global retention period. Revisit if needed.

4. **Should we notify admins before first prune?** A one-time admin notice saying "Event Image Manager will begin pruning images on {date}. Review settings." Could prevent surprise. Nice to have.
