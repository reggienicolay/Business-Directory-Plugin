# Business Directory Pro

A modern, map-first local business directory plugin for WordPress with geolocation, reviews, gamification, and multi-city support.

![WordPress](https://img.shields.io/badge/WordPress-6.0%2B-blue)
![PHP](https://img.shields.io/badge/PHP-8.0%2B-purple)
![License](https://img.shields.io/badge/License-GPL%20v2-green)
![Version](https://img.shields.io/badge/Version-0.1.3-orange)

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

- WordPress 6.0 or higher
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
├── includes/             # Feature loaders
├── languages/            # Translation files
├── src/
│   ├── Admin/            # Admin interfaces & menus
│   ├── API/              # REST API endpoints
│   ├── Auth/             # Authentication & login system
│   ├── BusinessTools/    # Owner dashboard & tools
│   ├── DB/               # Database tables & migrations
│   ├── Forms/            # Form handlers
│   ├── Frontend/         # Frontend rendering & shortcodes
│   ├── Gamification/     # Points, badges, ranks, leaderboards
│   ├── Importer/         # CSV/bulk import tools
│   ├── Install/          # Installation & setup
│   ├── Integrations/     # Third-party integrations (TEC, etc.)
│   ├── Lists/            # User lists & collections
│   ├── Moderation/       # Content moderation queues
│   ├── Notifications/    # Email & notification system
│   ├── PostTypes/        # Custom post types
│   ├── REST/             # REST controllers
│   ├── Roles/            # User roles & capabilities
│   ├── Search/           # Search & filtering engine
│   ├── Security/         # Rate limiting, CAPTCHA
│   ├── Social/           # Social sharing & Open Graph
│   ├── Taxonomies/       # Categories, areas, tags
│   ├── Utils/            # Helper utilities & cache
│   └── Plugin.php        # Main plugin class
├── templates/            # Template files
├── tests/                # PHPUnit tests
├── vendor/               # Composer dependencies
└── business-directory.php  # Main plugin file
```

## Database Tables

The plugin creates the following custom tables:

- `bd_locations` - Business coordinates and address data
- `bd_reviews` - User reviews and ratings
- `bd_submissions` - Pending business submissions
- `bd_claim_requests` - Business claim requests
- `bd_change_requests` - Edit requests from owners
- `bd_user_reputation` - Gamification points and ranks
- `bd_user_activity` - Activity log for points
- `bd_badge_awards` - Earned badges
- `bd_lists` - User-created lists
- `bd_list_items` - Businesses in lists
- `bd_list_collaborators` - List collaboration
- `bd_list_follows` - List subscriptions

## REST API

Base URL: `/wp-json/bd/v1/`

| Endpoint | Method | Description |
|----------|--------|-------------|
| `/businesses` | GET | Search/filter businesses |
| `/businesses/{id}` | GET | Get single business |
| `/reviews` | POST | Submit a review |
| `/claim` | POST | Submit a claim request |
| `/lists` | GET/POST | User lists |
| `/geocode` | GET | Geocode an address |

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

- **Email:** support@example.com
- **Documentation:** [docs.example.com](https://docs.example.com)

## Credits

- **Author:** Reggie Nicolay
- **Website:** [narrpr.com](https://narrpr.com)
- **Maps:** [Leaflet](https://leafletjs.com/) with [OpenStreetMap](https://www.openstreetmap.org/)
- **Icons:** [Font Awesome](https://fontawesome.com/)

---

© 2025 Reggie Nicolay. All rights reserved.
