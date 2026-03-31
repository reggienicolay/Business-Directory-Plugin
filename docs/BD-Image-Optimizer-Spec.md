# BD Image Optimizer — Feature Specification

**Version:** 1.0.0
**Author:** Reggie / Claude
**Date:** March 28, 2026
**Status:** Draft
**Plugin:** Business Directory Pro (`BD\` namespace)

---

## 1. Problem Statement

Users upload photos to business listings through three entry points: the admin gallery (Media Library picker), the front-end review form, and the business owner edit-listing form. These photos arrive in every condition imaginable — 12MP iPhone shots at 4032×3024 and 6MB, Android photos with embedded GPS coordinates, PNGs exported from screenshot tools at 3x the necessary resolution, and the occasional WebP from a Chrome save-as.

Today, WordPress generates its default thumbnail sizes (`thumbnail`, `medium`, `medium_large`, `large`) and stores the original. But the immersive detail page, the gallery lightbox, the search result cards, and the hero section all have specific layout requirements that don't align with WordPress defaults. The result:

- **Oversized images served to users.** The hero loads a `full` size image (often 4000px+) when the layout only needs 1600px wide. The lightbox serves the same `full` when 1400px would be pixel-perfect. Mobile devices download desktop-sized images.
- **No WebP delivery.** The `CoverManager` already converts cover images to WebP at 80% quality, but gallery photos and review photos skip this entirely. WebP typically saves 25–35% over JPEG at equivalent quality.
- **EXIF data leaks.** User phone photos contain GPS coordinates, device serial numbers, and timestamps. The immersive template serves these unstripped to every visitor.
- **No responsive `srcset`.** The `bdBusinessPhotos` JavaScript array passes a single `full` URL per photo. The lightbox, hero background, and gallery grid all use this one size regardless of viewport.
- **Inconsistent quality.** Cover images get WebP treatment; everything else doesn't. There's no unified pipeline.

### What Success Looks Like

A visitor opens a business detail page on their phone. The hero loads a 900px-wide WebP in under 200ms from Varnish. They tap "See all photos" — the gallery grid shows crisp 400×300 thumbnails, each under 30KB. They tap a photo — the lightbox loads a 1400px WebP, sharp on retina, under 80KB. No EXIF. No layout shift. No wasted bytes. The page scores 95+ on Lighthouse performance.

---

## 2. Current Architecture (What Exists)

### 2.1 Upload Entry Points

| Entry Point | Handler | Location | Current Processing |
|---|---|---|---|
| Admin gallery | WordPress Media Library picker → `bd_gallery_photos[]` hidden inputs | `MetaBoxes.php` | WordPress default sizes only |
| Review photos | `SubmitReviewController::handle_photo_uploads()` | `src/REST/SubmitReviewController.php` | MIME validation, `wp_generate_attachment_metadata()`, max 3 photos, 5MB limit |
| Edit listing (front-end) | Media Library frame → `bd-photo-item` grid | `assets/js/edit-listing.js` | WordPress default sizes only |
| Cover images | `CoverManager::upload_cover()` → `process_cover_image()` | `src/Lists/CoverManager.php` | WebP conversion via GD at 80%, custom upload dir `/bd-covers/`, EXIF stripped implicitly by GD re-encode |
| CSV import | `Importer\CSV::set_featured_image_from_url()` | `src/Importer/CSV.php` | `media_handle_sideload()`, WordPress defaults only |

### 2.2 Rendering / Consumption Points

| Context | Template / JS | Current Image Source | Ideal Size |
|---|---|---|---|
| Hero background | `immersive.php` → `bd_get_business_image($id, 'full')` | Original full-size URL | 1600×900 |
| Gallery grid (detail page) | Not yet implemented (photos listed inline) | `medium` (300×300) | 400×300 hard crop |
| Lightbox | `business-detail-immersive.js` → `bdBusinessPhotos[].url` | `full` | 1400×940 soft crop |
| Search result cards | Various templates | `thumbnail` or `medium` | 600×400 hard crop |
| Review photos inline | `review-form.js` lightbox fallback | `full` minus `-150x150` suffix hack | 800×600 soft crop |
| Social sharing / OG image | Not implemented for gallery photos | N/A | 1200×630 hard crop |
| REST API `/bd/v1/search` | JSON response | `wp_get_attachment_image_url($id, 'medium')` | 600×400 |

### 2.3 Existing WebP Pattern (CoverManager)

The `CoverManager::process_cover_image()` method is the reference implementation:

```
1. Get attached file path
2. Check function_exists('imagewebp')
3. Load via imagecreatefromjpeg() or imagecreatefrompng()
4. Preserve PNG alpha (imagepalettetotruecolor + imagesavealpha)
5. Save WebP sibling at 80% quality
6. Store path in post meta: _bd_webp_file
```

This pattern works but has limitations: it only converts the original/cropped file, not the generated thumbnail sizes. And it stores a single WebP path rather than a per-size mapping.

### 2.4 Meta Convention

| Meta Key | Used By | Value |
|---|---|---|
| `_bd_webp_file` | CoverManager | Absolute file path of WebP version |
| `_bd_list_cover` | CoverManager | List ID reference |
| `_bd_cover_type` | CoverManager | `cropped` or `original` |
| `_bd_custom_video_thumb` | CoverManager | Boolean flag |
| `bd_photos` | Gallery | Array of attachment IDs |

---

## 3. Solution Design

### 3.1 Architecture Overview

A single `BD\Media\ImageOptimizer` class hooks into WordPress's attachment lifecycle to process every image that enters the Media Library through any entry point. It operates as a post-processing pipeline triggered by the `wp_generate_attachment_metadata` filter — the same hook WordPress uses after generating its own thumbnail sizes. This means admin uploads, REST uploads, CSV sideloads, and review photo uploads all get processed identically without modifying any existing upload handler.

```
User uploads photo
        │
        ▼
WordPress core: wp_handle_upload()
        │
        ▼
WordPress core: wp_generate_attachment_metadata()
  ├── Generates default sizes (thumbnail, medium, large, etc.)
  ├── Generates BD custom sizes (bd-hero, bd-gallery, bd-lightbox, etc.)
  │
  ▼ (filter hook)
BD\Media\ImageOptimizer::on_generate_metadata( $metadata, $attachment_id )
  ├── 1. Strip EXIF from original (re-encode via GD)
  ├── 2. Generate WebP for original + every registered size
  ├── 3. Store WebP manifest in post meta (_bd_webp_sizes)
  └── Return $metadata (unmodified — WebP files are siblings, not WP sizes)
        │
        ▼
Template rendering via bd_picture() helper
  ├── Checks _bd_webp_sizes meta for requested size
  ├── Outputs <picture> with WebP <source> + JPEG/PNG <img> fallback
  └── Includes srcset/sizes for responsive delivery
```

### 3.2 Custom Image Sizes

These sizes are tuned to the actual layout dimensions across the immersive template, search cards, and gallery views. All dimensions account for 2x retina density where it matters.

| Size Name | Dimensions | Crop | Purpose | Notes |
|---|---|---|---|---|
| `bd-hero` | 1600×900 | Hard (center) | Hero background on detail page | 16:9, covers full-width hero at 1x; retina covered by browser downscaling from larger |
| `bd-card` | 600×400 | Hard (center) | Search result cards, explore grid | 3:2, used in card layouts across search and explore |
| `bd-gallery-thumb` | 400×300 | Hard (center) | Gallery grid on detail page | 4:3, small file size for grid of 10 photos |
| `bd-lightbox` | 1400×1050 | Soft (proportional) | Lightbox overlay | 4:3 max, soft crop preserves portrait photos; capped width prevents oversized loads |
| `bd-review` | 800×600 | Soft (proportional) | Inline review photos | 4:3 max, used in review display and review photo lightbox |
| `bd-og` | 1200×630 | Hard (center) | Open Graph / social sharing | Facebook/Twitter recommended ratio |

**Registration** happens in `ImageOptimizer::register_sizes()` called on `after_setup_theme` at priority 20 (after Kadence theme registers its own sizes):

```php
add_image_size( 'bd-hero', 1600, 900, true );
add_image_size( 'bd-card', 600, 400, true );
add_image_size( 'bd-gallery-thumb', 400, 300, true );
add_image_size( 'bd-lightbox', 1400, 1050, false );
add_image_size( 'bd-review', 800, 600, false );
add_image_size( 'bd-og', 1200, 630, true );
```

### 3.3 WebP Generation Strategy

**Per-size WebP siblings.** For each WordPress-generated size (including our custom sizes), create a `.webp` file alongside the original. For a file `uploads/2026/03/restaurant-photo-600x400.jpg`, generate `uploads/2026/03/restaurant-photo-600x400.webp`.

**Quality settings by purpose:**

| Size | WebP Quality | Rationale |
|---|---|---|
| `bd-hero` | 82 | Large display area, quality matters; still 30%+ smaller than JPEG |
| `bd-card` | 78 | Small display, aggressive compression unnoticeable |
| `bd-gallery-thumb` | 75 | Tiny display, maximize savings |
| `bd-lightbox` | 85 | Full-screen viewing, quality is paramount |
| `bd-review` | 78 | Medium display, balance quality/size |
| `bd-og` | 80 | Social platforms re-compress anyway |
| Original | 82 | Fallback; rarely served directly |

**Meta storage.** Instead of the current single `_bd_webp_file` path, store a structured manifest:

```php
// Post meta key: _bd_webp_sizes
[
    'full'              => '/abs/path/to/restaurant-photo.webp',
    'bd-hero'           => '/abs/path/to/restaurant-photo-1600x900.webp',
    'bd-card'           => '/abs/path/to/restaurant-photo-600x400.webp',
    'bd-gallery-thumb'  => '/abs/path/to/restaurant-photo-400x300.webp',
    'bd-lightbox'       => '/abs/path/to/restaurant-photo-1400x1050.webp',
    'bd-review'         => '/abs/path/to/restaurant-photo-800x600.webp',
    'bd-og'             => '/abs/path/to/restaurant-photo-1200x630.webp',
]
```

**Backward compatibility.** Continue writing `_bd_webp_file` for the `full` size so existing `CoverManager` code doesn't break. The new `_bd_webp_sizes` is additive.

### 3.4 EXIF Stripping

GD's `imagecreatefromjpeg()` → `imagejpeg()` round-trip inherently strips EXIF. But we need to do this to the *original* stored file, not just the WebP derivatives. The pipeline:

1. After `wp_generate_attachment_metadata` fires, load the original file.
2. If JPEG: `imagecreatefromjpeg()` → `imagejpeg($image, $original_path, 92)` (high quality, near-lossless re-encode). This strips EXIF including GPS, device info, timestamps.
3. If PNG: No EXIF concern (PNG doesn't carry EXIF in practice).
4. If already WebP: `imagewebp()` round-trip strips any XMP/EXIF.

**Quality note:** The JPEG re-encode at quality 92 introduces minimal generational loss (imperceptible to humans) while guaranteeing EXIF removal. This is the same approach used by WordPress.com, Facebook, and most image-hosting platforms.

### 3.5 Memory Safety

Large uploads (8MP+ phones) can exhaust PHP memory during GD processing. The pipeline must:

1. Call `wp_raise_memory_limit('image')` before processing (already used in `CoverManager`).
2. Process sizes sequentially, calling `imagedestroy()` after each conversion.
3. Skip WebP generation for any size where GD allocation fails (log error, continue to next size).
4. Set a dimension ceiling: if the original exceeds 4096px on either axis, downscale it before processing custom sizes. This prevents memory exhaustion from 12MP+ originals.

### 3.6 Template Helper: `bd_picture()`

A global helper function that generates `<picture>` markup with WebP source and responsive attributes.

**Signature:**

```php
function bd_picture(
    int $attachment_id,
    string $size = 'bd-card',
    array $attrs = []
): string
```

**Output example:**

```html
<picture>
    <source
        type="image/webp"
        srcset="photo-600x400.webp 600w, photo-1400x1050.webp 1400w"
        sizes="(max-width: 768px) 100vw, 600px"
    >
    <img
        src="photo-600x400.jpg"
        srcset="photo-600x400.jpg 600w, photo-1400x1050.jpg 1400w"
        sizes="(max-width: 768px) 100vw, 600px"
        alt="Downtown Livermore tasting room"
        width="600"
        height="400"
        loading="lazy"
        decoding="async"
        class="bd-gallery-img"
    >
</picture>
```

**Key behaviors:**

- Always includes `width` and `height` attributes to prevent Cumulative Layout Shift (CLS).
- `loading="lazy"` on all images except the hero (first visible image gets `loading="eager"`).
- `decoding="async"` to avoid blocking the main thread.
- Falls back gracefully: if no WebP exists, outputs a standard `<img>` with `srcset`.
- Accepts `$attrs` array for overriding `class`, `alt`, `loading`, `sizes`, and adding `data-*` attributes.

### 3.7 Lightbox Integration Update

The `bdBusinessPhotos` JavaScript array currently passes a single `url` per photo. Update to include multiple sizes:

```javascript
window.bdBusinessPhotos = [
    {
        "id": 1234,
        "alt": "Tasting room interior",
        "sizes": {
            "thumb":    { "url": "photo-400x300.webp", "w": 400, "h": 300 },
            "lightbox": { "url": "photo-1400x1050.webp", "w": 1400, "h": 1050 },
            "full":     { "url": "photo-1400x1050.jpg", "w": 1400, "h": 1050 }
        }
    }
]
```

The lightbox JS loads the `lightbox` size by default. On devices with `window.innerWidth < 768`, it loads `thumb` first as a placeholder, then upgrades to `lightbox` — progressive loading that feels instant on mobile.

**Backward compatibility:** If `sizes` key is absent (old data), fall back to `url` key (current behavior).

### 3.8 Hero Background Update

The immersive hero currently uses:

```php
$hero_image_url = bd_get_business_image( $business_id, 'full' )['url'];
```

Update to use `bd-hero` size with WebP preference:

```php
$hero = bd_get_optimized_business_image( $business_id, 'bd-hero' );
```

The hero `<section>` switches from inline `background-image` to a `<picture>` element with `object-fit: cover`, which enables proper WebP delivery and `srcset` — something CSS `background-image` cannot do without JavaScript.

```html
<section class="bd-business-hero bd-immersive-hero">
    <picture class="bd-hero-bg">
        <source type="image/webp"
            srcset="hero-1600x900.webp 1600w, hero-800x450.webp 800w"
            sizes="100vw">
        <img src="hero-1600x900.jpg"
            srcset="hero-1600x900.jpg 1600w, hero-800x450.jpg 800w"
            sizes="100vw"
            alt=""
            loading="eager"
            decoding="async"
            fetchpriority="high"
            class="bd-hero-bg-img">
    </picture>
    <!-- ... hero content overlay ... -->
</section>
```

CSS:

```css
.bd-hero-bg { position: absolute; inset: 0; overflow: hidden; }
.bd-hero-bg-img { width: 100%; height: 100%; object-fit: cover; }
```

This replaces the current `background-image` approach with a semantically correct, optimized image element. The hero image gets `loading="eager"` and `fetchpriority="high"` since it's the LCP (Largest Contentful Paint) element.

---

## 4. File Structure

```
src/Media/
    ImageOptimizer.php       ← Core class: hooks, size registration, WebP generation, EXIF strip
    ImageHelper.php          ← Template helpers: bd_picture(), bd_get_optimized_business_image()

includes/
    image-helper-functions.php   ← Global function wrappers (loaded alongside placeholder-image-helper.php)
```

**Namespace:** `BD\Media` (PSR-4, consistent with project convention).

**Autoloading:** Already handled by the existing PSR-4 autoloader in BD plugin; `BD\Media\ImageOptimizer` maps to `src/Media/ImageOptimizer.php`.

---

## 5. Implementation Phases

### Phase 1: Core Pipeline (Backend)

**Goal:** Every new upload gets custom sizes + WebP + EXIF stripped. No template changes yet.

**Files to create:**
- `src/Media/ImageOptimizer.php`
- `src/Media/ImageHelper.php`
- `includes/image-helper-functions.php`

**Hooks to register:**
- `after_setup_theme` → `ImageOptimizer::register_sizes()`
- `wp_generate_attachment_metadata` → `ImageOptimizer::on_generate_metadata()`
- `delete_attachment` → `ImageOptimizer::cleanup_webp_files()` (delete WebP siblings when attachment is trashed)

**Validation:** Upload a test JPEG via admin gallery, confirm:
- All 6 BD sizes generated in `uploads/` directory
- WebP siblings exist for each size
- `_bd_webp_sizes` meta contains correct paths
- Original JPEG has no EXIF data (verify with `exiftool` or PHP `exif_read_data`)
- `_bd_webp_file` still written for backward compat

**No breaking changes.** All existing templates continue to work because they use `wp_get_attachment_image_url()` which returns JPEG/PNG URLs. WebP files sit alongside as siblings, unused until Phase 2.

### Phase 2: Template Integration (Frontend)

**Goal:** Detail page, gallery, lightbox, and search cards serve WebP via `<picture>` elements.

**Files to modify:**
- `templates/single-business/immersive.php` — hero background → `<picture>`, gallery grid → `bd_picture()`
- `assets/js/business-detail-immersive.js` — lightbox uses multi-size `bdBusinessPhotos`
- `assets/js/business-detail.js` — standard lightbox updated
- `includes/placeholder-image-helper.php` — `bd_get_business_image()` gains WebP awareness

**CSS changes:**
- `assets/css/business-detail-immersive.css` — hero background → `object-fit: cover` on `<img>`

**Validation:**
- Chrome DevTools Network tab: all gallery images served as WebP
- Safari: falls back to JPEG (Safari 14+ supports WebP, but older versions get fallback)
- Lighthouse: no "Serve images in next-gen formats" warning
- Mobile: hero image < 100KB, gallery thumbs < 30KB each

### Phase 3: Backfill & WP-CLI Command

**Goal:** Process existing uploads that predate the optimizer.

**WP-CLI command:**

```bash
wp bd media optimize --batch-size=50 --dry-run
wp bd media optimize --batch-size=50
wp bd media optimize --only=webp         # Skip size regeneration, just add WebP
wp bd media optimize --only=exif         # Only strip EXIF from originals
```

**Behavior:**
1. Query all attachments that are referenced by `bd_photos` meta or are post thumbnails of `bd_business` posts.
2. For each, check if `_bd_webp_sizes` meta exists and is complete.
3. If missing sizes: call `wp_generate_attachment_metadata()` to regenerate (picks up new BD sizes).
4. Run `ImageOptimizer::on_generate_metadata()` to generate WebP + strip EXIF.
5. Progress bar, batch processing, memory-safe (unload each image after processing).

**Estimated scope:** For a site with ~2,000 business listings averaging 3 photos each = ~6,000 attachments. At ~2 seconds per attachment (GD processing), backfill completes in ~3.5 hours. Batching with `--batch-size=50` keeps memory stable.

### Phase 4: REST API & Subsite Delivery

**Goal:** The unified `/bd/v1/search` endpoint returns optimized image URLs. Subsites pull WebP-aware image data.

**Changes to REST response:**

```json
{
    "image": {
        "card": "https://lovetrivalley.com/.../photo-600x400.webp",
        "card_fallback": "https://lovetrivalley.com/.../photo-600x400.jpg",
        "hero": "https://lovetrivalley.com/.../photo-1600x900.webp",
        "thumb": "https://lovetrivalley.com/.../photo-400x300.webp"
    }
}
```

Subsites use `card` URL for search results, with `card_fallback` for `<picture>` fallback. This keeps all image processing on the main site while subsites serve optimized sizes.

---

## 6. Performance Targets

| Metric | Current (Estimated) | Target | How |
|---|---|---|---|
| Hero image file size | 800KB–2MB (full JPEG) | < 120KB | `bd-hero` 1600×900 WebP at q82 |
| Gallery thumb file size | 80–200KB (medium JPEG) | < 30KB | `bd-gallery-thumb` 400×300 WebP at q75 |
| Lightbox image file size | 800KB–2MB (full JPEG) | < 100KB | `bd-lightbox` 1400×1050 WebP at q85 |
| Detail page total image weight (10 photos) | 4–8MB | < 500KB | Lazy loading + right-sized + WebP |
| Largest Contentful Paint (hero) | 2.5–4s | < 1.5s | Right-sized hero + `fetchpriority="high"` + Varnish |
| Cumulative Layout Shift | 0.1–0.3 | < 0.05 | `width`/`height` on all `<img>` elements |
| EXIF data exposed | GPS, device info in every photo | Zero EXIF in any served image | GD re-encode on upload |

---

## 7. Security Considerations

- **EXIF stripping is a privacy requirement,** not just a performance optimization. User-uploaded photos from phones contain GPS coordinates accurate to ~3 meters. For a business directory, this is less sensitive (business addresses are public), but review photos taken "on location" could reveal a reviewer's home or workplace if uploaded from their camera roll.
- **WebP generation runs server-side only.** No client-side JavaScript processing that could be manipulated.
- **File validation unchanged.** The existing MIME validation in `SubmitReviewController` (finfo + getimagesize + mime_content_type triple-check) and `CoverManager` (finfo + embedded code scan) remains the security gate. `ImageOptimizer` runs *after* validation, on files already accepted into the Media Library.
- **WebP file cleanup on delete.** The `delete_attachment` hook must remove all WebP siblings to prevent orphaned files accumulating on disk.
- **No new user-facing endpoints.** `ImageOptimizer` is purely a server-side processing hook.

---

## 8. Edge Cases & Failure Modes

| Scenario | Handling |
|---|---|
| GD `imagewebp` not available (unlikely on Cloudways PHP 8.2) | Skip WebP generation entirely; log once on activation; all templates fall back to JPEG/PNG via `<picture>` fallback |
| Upload is already WebP | Skip WebP conversion for that size (copy as-is); still generate JPEG/PNG sizes for `<img>` fallback |
| Image smaller than target size (e.g., 300×200 uploaded, `bd-hero` wants 1600×900) | WordPress skips generating sizes larger than the original; WebP generated only for sizes that were created |
| PHP memory exhaustion during processing | `try/catch` around each size conversion; log failure; continue to next size; partial WebP manifest stored |
| Concurrent uploads (bulk admin import) | Each `wp_generate_attachment_metadata` call is independent; no shared state; safe for concurrent processing |
| Animated GIF uploaded | Skip WebP conversion (GD can't handle animated WebP); serve original GIF |
| HEIC/HEIF upload (iPhone) | Already in `CoverManager::ALLOWED_MIME_TYPES`; requires ImageMagick for conversion (GD can't read HEIC); if ImageMagick unavailable, reject with helpful error |
| Cloudways Varnish caching stale images after re-processing | Backfill command should output list of URLs to purge; manual Varnish purge via Cloudways panel or `wp bd media purge-cache` |

---

## 9. Compatibility & Constraints

- **WordPress 6.8+** — relies on `wp_generate_attachment_metadata` filter (stable since WP 2.1).
- **PHP 8.2** — GD extension with WebP support required (standard on Cloudways).
- **Kadence theme** — no theme modifications required; all changes are in plugin templates and helpers.
- **Redis object cache** — `_bd_webp_sizes` meta cached automatically by WordPress object cache layer.
- **Varnish/Breeze** — WebP files served directly by nginx/Varnish; no PHP involvement for cached pages.
- **Multisite** — `ImageOptimizer` registers sizes on the main site only (subsites pull via REST API). The `after_setup_theme` hook includes a `is_main_site()` guard for size registration, but WebP processing runs on any site in the network where uploads occur.
- **Disk space** — WebP files add ~15–25% to total media storage (WebP is smaller than JPEG, but you're storing both). For 6,000 images with 6 sizes each = ~36,000 WebP files. At ~50KB average = ~1.7GB additional storage. Cloudways SSD handles this easily.

---

## 10. Testing Checklist

**Phase 1 — Backend:**
- [ ] Upload JPEG via admin gallery → 6 BD sizes + WebP for each + EXIF stripped
- [ ] Upload PNG via admin gallery → BD sizes + WebP with alpha preserved
- [ ] Upload WebP via admin gallery → BD sizes generated as WebP (no double conversion)
- [ ] Upload via review form (front-end) → same processing as admin
- [ ] Upload via edit-listing form (front-end) → same processing as admin
- [ ] Upload oversized image (12MP) → processed without memory exhaustion
- [ ] Upload undersized image (200×150) → no upscaling, partial size manifest
- [ ] Delete attachment → all WebP siblings removed from disk
- [ ] `_bd_webp_sizes` meta correct for all generated sizes
- [ ] `_bd_webp_file` still written for backward compat with CoverManager
- [ ] PHPCS passes on all new files (WordPress-Extra ruleset)
- [ ] No multisite table creation on subsites

**Phase 2 — Frontend:**
- [ ] Hero renders as `<picture>` with WebP source
- [ ] Gallery grid uses `bd-gallery-thumb` WebP
- [ ] Lightbox loads `bd-lightbox` WebP
- [ ] Safari/Firefox/Chrome all display images correctly
- [ ] Missing WebP gracefully falls back to JPEG
- [ ] All `<img>` elements have `width` and `height` attributes
- [ ] Hero image has `loading="eager"` and `fetchpriority="high"`
- [ ] Below-fold images have `loading="lazy"`
- [ ] No CLS on page load (Lighthouse CLS < 0.05)
- [ ] Lightbox backward compat: old `bdBusinessPhotos` format still works

**Phase 3 — Backfill:**
- [ ] `wp bd media optimize --dry-run` reports count without modifying files
- [ ] `wp bd media optimize --batch-size=10` processes 10 and stops
- [ ] Memory stays stable across 500+ attachments
- [ ] Progress bar accurate
- [ ] Idempotent: running twice doesn't duplicate files or corrupt meta

**Phase 4 — REST API:**
- [ ] `/bd/v1/search` returns WebP URLs in `image` object
- [ ] Subsites render cards with WebP images from main site
- [ ] Fallback URLs present for non-WebP browsers

---

## 11. Open Questions

1. **Should we also generate AVIF?** AVIF offers ~20% better compression than WebP, but encoding is 10–50x slower and PHP GD support is limited. Recommendation: defer to a future phase; WebP gets us 90% of the benefit with proven tooling.

2. **Maximum original dimensions.** Should we downscale originals larger than 4096px to cap storage and processing time? WordPress 5.3+ has `big_image_size_threshold` (default 2560px) which already creates a `-scaled` version. We could rely on this or set our own threshold.

3. **Google Places fallback photos.** The photo integration strategy (deferred) includes Google Places API photos as a dynamic fallback. Should these be cached locally and run through the optimizer, or served directly from Google's CDN? Recommendation: cache locally to ensure consistent WebP delivery and avoid Google API rate limits on page loads.

4. **CDN consideration.** If Love TriValley moves to Cloudflare (or similar) in the future, their image optimization (Polish, WebP auto-conversion) would overlap with this pipeline. The `<picture>` markup would still be valuable for `srcset`/`sizes`, but WebP generation could be offloaded. Design the `bd_picture()` helper with a filter hook so CDN integration can bypass local WebP lookup.
