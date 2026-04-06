# Event Image Manager — Documentation

**Component:** `BD\Integrations\EventsCalendar\EventImageManager`
**File:** `src/Integrations/EventsCalendar/EventImageManager.php`
**Since:** 0.2.0
**Status:** IMPLEMENTED

---

## 1. Overview

EventImageManager auto-prunes featured images from expired events to prevent storage bloat. It only deletes images flagged as imported by the BD Event Aggregator — never touches user-uploaded images.

It also hooks into ImageOptimizer to skip BD-specific custom sizes on event images (events only need thumbnail, medium, large — not bd-hero, bd-lightbox, etc.).

### Key Features

- **Auto-prune:** Deletes imported event images 30 days after the event ends
- **Safety boundary:** Only images with `_bd_event_imported_image` meta get pruned
- **Shared image awareness:** Detaches shared images from expired events without deleting the file
- **Image deduplication:** Works with the aggregator's source URL dedup to share one attachment across recurring events
- **Image optimization:** Event images are fully optimized by ImageOptimizer (EXIF stripping, WebP generation) but skip BD custom sizes (bd-hero, bd-card, etc.) that events never use
- **Size filtering:** Uses `intermediate_image_sizes_advanced` filter with a context flag pattern to remove BD custom sizes during event image sideload

---

## 2. How It Works

### 2.1 Cron Schedule

- **Hook:** `bd_prune_expired_event_images`
- **Frequency:** Daily at 2 AM local time (DST-safe via `wp_timezone_string()`)
- **Batch size:** 50 events per tick, self-reschedules if more remain
- **Kill switch:** `bd_event_image_pruning_enabled` option (default: true)
- **Filterable:** `bd_event_image_pruning_enabled` filter

### 2.2 Pruning Logic

For each expired event (end_date + retention_days < now):

1. Get `_thumbnail_id` (featured image)
2. Skip if no thumbnail (race condition or already removed)
3. Verify it has `_bd_event_imported_image` meta flag — skip if not (user-uploaded)
4. Check if any OTHER post uses this attachment as `_thumbnail_id`:
   - **If shared:** Detach from this event (`delete_post_thumbnail`) but keep the file
   - **If not shared:** Delete the attachment file (`wp_delete_attachment($id, true)`)
5. Mark event with `_bd_image_pruned` meta to prevent re-processing
6. Log results and update stats

### 2.3 Image Deduplication (Event Aggregator Side)

The aggregator stores `_bd_event_source_url` meta on each imported attachment. Before downloading a new image, it checks if an attachment with the same source URL already exists. If found, it reuses the existing attachment via `set_post_thumbnail()` — no duplicate download.

**Example:** A recurring event series (5 dates, same image URL) creates 1 attachment shared by 5 events. As events expire, the pruner detaches each one. The file is only deleted when the last event referencing it expires.

### 2.4 Future Event Cutoff

The aggregator skips events more than 16 months in the future to prevent importing placeholder or far-off events. This is enforced in `BDEA_Event_Importer::import()` before event creation.

### 2.5 Image Optimization on Import

Event images are fully processed by ImageOptimizer (EXIF stripping + WebP generation for all standard sizes). However, BD custom sizes (bd-hero, bd-card, bd-gallery-thumb, bd-lightbox, bd-review, bd-og) are stripped during sideload since event templates never use them.

**How it works (context flag pattern):**

1. The aggregator calls `EventImageManager::begin_event_import()` before `media_handle_sideload()` / `media_sideload_image()`
2. During sub-size generation, WordPress fires `intermediate_image_sizes_advanced`
3. `EventImageManager::limit_event_image_sizes()` checks the `$importing_event_image` flag and removes BD custom sizes
4. The aggregator calls `EventImageManager::end_event_import()` after sideload returns

**Why a context flag instead of meta:** The `intermediate_image_sizes_advanced` filter fires *during* `media_handle_sideload()`, before `_bd_event_imported_image` meta is set. The context flag solves this timing issue.

**Sizes kept for events:** thumbnail, medium, medium_large, large, 1536x1536, 2048x2048 (WordPress defaults)
**Sizes removed for events:** bd-hero, bd-card, bd-gallery-thumb, bd-lightbox, bd-review, bd-og

---

## 3. Constants & Configuration

### Constants (EventImageManager.php)

| Constant | Value | Description |
|---|---|---|
| `CRON_HOOK` | `bd_prune_expired_event_images` | Cron action hook name |
| `IMPORTED_META_KEY` | `_bd_event_imported_image` | Flag on imported attachments |
| `PRUNED_META_KEY` | `_bd_image_pruned` | Flag on processed events |
| `DEFAULT_RETENTION` | `30` | Days after event end date |
| `PRUNE_BATCH_SIZE` | `50` | Events processed per cron tick |

### Options

| Option | Default | Description |
|---|---|---|
| `bd_event_image_pruning_enabled` | `true` | Kill switch |
| `bd_event_image_prune_stats` | `[]` | Prune run statistics |

### Filters

| Filter | Args | Description |
|---|---|---|
| `bd_event_image_pruning_enabled` | `bool $enabled` | Override kill switch |
| `bd_event_image_retention_days` | `int $days` | Override retention period |

### Meta Keys (on attachments)

| Key | Set By | Description |
|---|---|---|
| `_bd_event_imported_image` | Event Aggregator | Safety flag — only flagged images get pruned |
| `_bd_event_source_url` | Event Aggregator | Source URL for deduplication |

---

## 4. Monitoring & Verification

### Check prune stats

```bash
wp option get bd_event_image_prune_stats --format=json
```

Returns:
```json
{
  "total_pruned": 23,
  "total_bytes_freed": 49545216,
  "last_run": "2026-04-04T02:00:12+00:00",
  "last_run_count": 5,
  "last_run_bytes": 2457600
}
```

### Check if cron is scheduled

```bash
wp cron event list | grep bd_prune
```

Should show `bd_prune_expired_event_images` with a next run time around 2 AM local.

### Force a prune run

```bash
wp cron event run bd_prune_expired_event_images
```

Triggers immediately — useful for testing or clearing a backlog.

### Check PHP error log

When the pruner runs, it writes a summary:
```
[BD EventImageManager] Pruned 5 images from 8 expired events. Freed 12.3 MB. Retention: 30 days. Duration: 0.45s.
```

### Check imported image count

```bash
wp post list --post_type=attachment --meta_key=_bd_event_imported_image --meta_value=1 --format=count
```

### Check shared images (images used by multiple events)

```sql
SELECT meta_value AS attachment_id, COUNT(*) AS use_count
FROM wp_postmeta
WHERE meta_key = '_thumbnail_id'
AND meta_value IN (
  SELECT post_id FROM wp_postmeta WHERE meta_key = '_bd_event_imported_image'
)
GROUP BY meta_value
HAVING use_count > 1;
```

---

## 5. Production Setup (Cloudways)

For reliable daily pruning on production:

1. **Disable WP-Cron** — Add to `wp-config.php`:
   ```php
   define( 'DISABLE_WP_CRON', true );
   ```

2. **Set up server cron** — In Cloudways cron manager, add:
   ```
   0 2 * * * cd /home/master/applications/YOUR_APP/public_html && wp cron event run --due-now --quiet
   ```
   This fires at 2 AM server time and runs all due WP-Cron events including the image pruner.

3. **Verify** — After 24 hours, check stats:
   ```bash
   wp option get bd_event_image_prune_stats --format=json
   ```

---

## 6. Edge Cases

### Shared Images (Recurring Events)
When multiple events share one attachment (via dedup), the pruner detaches expired events one at a time. The file is only deleted when the reference count drops to zero.

### Manually-Uploaded Images
Images uploaded through the WordPress editor do NOT have `_bd_event_imported_image` meta. The pruner always skips them. This is the hard safety boundary.

### Trashed Events
The query uses `post_status => 'any'`, so trashed events get their images pruned too. No reason to keep images for deleted events.

### Events Without End Date
TEC defaults `_EventEndDate` to start date + 1 hour. The pruner's date comparison still works correctly.

### Stale Source URL Meta
If an attachment is deleted manually but `_bd_event_source_url` meta lingers, the aggregator's `find_existing_attachment()` method detects the stale reference (via `get_post_type()` check), cleans it up, and falls through to a fresh download.

---

## 7. Activation / Deactivation

### On BD Pro Activation
`EventImageManager::schedule_pruning()` — schedules the daily cron at 2 AM local time.

### On BD Pro Deactivation
`EventImageManager::cleanup_on_deactivation()` — clears the cron hook.

### On Uninstall
Does NOT delete imported images — that would be destructive. Only cleans up options.

---

## 8. Related Files

| File | Plugin | Role |
|---|---|---|
| `src/Integrations/EventsCalendar/EventImageManager.php` | BD Pro | Pruning cron, size optimization |
| `src/Integrations/EventsCalendar/EventsCalendarIntegration.php` | BD Pro | Loads EventImageManager via `init()` |
| `includes/class-event-importer.php` | BD Event Aggregator | Sets `_bd_event_imported_image` + `_bd_event_source_url` meta, dedup logic |
| `src/Media/ImageOptimizer.php` | BD Pro | WebP generation, custom sizes (skipped for events via filter) |
