# Changelog

## [0.1.0] - 2025-01-XX

### Added
- Plugin foundation with PSR-4 autoloading
- Custom post type bd_business
- Taxonomies: categories, areas, tags
- Custom database tables
- bd_manager role
- Data access classes
- PHPUnit test suite
- GitHub Actions CI/CD

## [0.2.0] - Sprint 1 Week 2

### Added
- Business metaboxes for custom fields (phone, website, hours, social)
- Location map with Leaflet for coordinate selection
- CSV bulk importer with admin page
- REST API endpoint /bd/v1/businesses
- Frontend directory shortcode [bd_directory]
- Map/list view toggle
- Distance calculation from user location

### Technical
- Admin map integration with Leaflet
- Haversine distance formula
- CSV parsing with term creation
- Frontend asset enqueuing system

## [0.3.0] - Sprint 2 Week 1

### Added
- Public business submission form with spam protection
- Review system with 5-star ratings
- Photo uploads for reviews (up to 3 images)
- Cloudflare Turnstile CAPTCHA integration
- Rate limiting for submissions and reviews
- Email notifications to admins
- Moderation queues for submissions and reviews
- Settings page for Turnstile keys and notification emails
- Aggregate rating calculation
- REST API endpoints for submissions and reviews

### Technical
- New database tables: bd_submissions
- Updated bd_reviews table with photo_ids and email fields
- Form validation and sanitization
- File upload handling for review photos
- Transient-based rate limiting
