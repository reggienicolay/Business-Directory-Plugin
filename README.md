# Business Directory

A modern, map-first WordPress business directory plugin with powerful location features and beautiful templates.

![WordPress](https://img.shields.io/badge/WordPress-6.0%2B-blue.svg)
![PHP](https://img.shields.io/badge/PHP-8.0%2B-purple.svg)
![License](https://img.shields.io/badge/license-GPL%20v2%2B-blue.svg)
![Version](https://img.shields.io/badge/version-1.0.0-green.svg)

---

## 🎯 Core Features

**Business Directory** gives you everything you need to create a professional local business directory on your WordPress site:

- ✅ **Business Listings** - Custom post type with full WordPress integration
- ✅ **Categories & Areas** - Organize by business type and location
- ✅ **Interactive Maps** - Leaflet.js integration with custom markers
- ✅ **Location Search** - Find businesses by address, city, or area
- ✅ **Responsive Templates** - Beautiful business detail pages
- ✅ **Admin Interface** - Easy-to-use dashboard for managing listings
- ✅ **CSV Import** - Bulk import businesses from spreadsheets
- ✅ **SEO Friendly** - Clean permalinks and structured data ready
- ✅ **Developer Friendly** - Hooks, filters, and extensible architecture

---

## 🚀 Premium Add-ons

Extend your directory with powerful premium features:

### ⭐ Reviews & Ratings - $49/year
Add a complete review system to your directory:
- 5-star rating system with aggregates
- Photo uploads (up to 3 per review)
- Review moderation queue
- Spam protection with Cloudflare Turnstile
- Email notifications
- Beautiful review cards

**[Learn More →](#)** *(Coming soon)*

---

### 🏢 Business Claims - $49/year
Let business owners claim and manage their listings:
- Claim request form with modal interface
- Proof of ownership upload
- Admin claims dashboard with 24-48h SLA tracking
- Automatic user account creation
- Business owner role assignment
- Email workflow for approvals/rejections

**[Learn More →](#)** *(Coming soon)*

---

### 📝 Frontend Submissions - $49/year
Allow users to submit businesses from your site:
- Beautiful multi-step submission form
- Photo and video uploads
- Hours of operation scheduler
- Automatic geocoding
- Submission moderation queue
- Turnstile spam protection

**[Learn More →](#)** *(Coming soon)*

---

### 🔍 Advanced Filters - $49/year
Add powerful real-time filtering to your directory:
- AJAX-powered instant filtering
- Multi-criteria search (category, area, rating, price)
- Geolocation and radius search
- Distance calculations
- Premium filter UI
- Cache optimization

**[Learn More →](#)** *(Coming soon)*

---

### 🎨 Premium Templates - $49/year
Beautiful, conversion-optimized templates:
- Premium single business template
- Modern directory grid layouts
- Custom archive templates
- Mobile-first responsive design
- Multiple color schemes

**[Learn More →](#)** *(Coming soon)*

---

### 💎 Pro Bundle - $199/year
**Save $46!** Get all premium add-ons plus:
- ✅ All 5 premium add-ons
- ✅ Priority support via tickets
- ✅ Premium documentation & videos
- ✅ All future add-ons included
- ✅ 1 year of updates

**[Get Pro Bundle →](#)** *(Coming soon)*

---

## 📦 Installation

### From WordPress.org *(Coming Soon)*
1. Go to **Plugins → Add New**
2. Search for **"Business Directory"**
3. Click **Install Now**, then **Activate**

### Manual Installation
1. Download the plugin
2. Upload to `/wp-content/plugins/business-directory/`
3. Activate through the **Plugins** menu in WordPress
4. Go to **Settings → Permalinks** and click **Save** to flush rewrites

### Requirements
- WordPress 6.0 or higher
- PHP 8.0 or higher
- MySQL 5.7+ or MariaDB 10.2+

---

## ⚡ Quick Start

### 1. Create Your Directory Page
1. Create a new page (e.g., "Business Directory")
2. Add this shortcode: `[bd_directory]`
3. Publish the page

### 2. Add Your First Business
1. Go to **Directory → Add New Business**
2. Fill in business details
3. Add location information
4. Click **Publish**

### 3. Configure Settings
1. Go to **Directory → Settings**
2. Set up notification emails
3. Configure map defaults
4. Save changes

**That's it!** Your directory is ready to use.

---

## 🎨 Shortcodes

### Main Directory
```
[bd_directory]
```
Displays the complete business directory with map and listings.

### Business List Only
```
[bd_business_list category="restaurants" area="downtown"]
```
Displays a filtered list of businesses.

**Parameters:**
- `category` - Filter by category slug
- `area` - Filter by area slug
- `per_page` - Number of businesses to show (default: 20)
- `show_map` - Show/hide map (true/false)

---

## 🗂️ Organizing Businesses

### Categories
Organize businesses by type:
- Restaurants
- Hotels
- Shopping
- Services
- Entertainment

Go to **Directory → Categories** to manage.

### Areas
Organize businesses by location:
- Downtown
- Uptown
- Suburbs
- Neighborhoods

Go to **Directory → Areas** to manage.

### Tags
Add flexible tags for additional organization:
- "Pet Friendly"
- "Open Late"
- "Free WiFi"
- "Outdoor Seating"

---

## 🛠️ Developer Features

### Hooks & Filters

**Modify business query:**
```php
add_filter('bd_business_query_args', function($args) {
    $args['posts_per_page'] = 50;
    return $args;
});
```

**Customize business card output:**
```php
add_action('bd_before_business_card', function($business_id) {
    echo '<div class="custom-badge">Featured</div>';
});
```

**Add custom fields:**
```php
add_filter('bd_business_meta_fields', function($fields) {
    $fields['parking'] = [
        'label' => 'Parking Available',
        'type' => 'checkbox'
    ];
    return $fields;
});
```

### Template Override
Copy any template from `templates/` to your theme:
```
your-theme/
  business-directory/
    single-business.php
    archive-business.php
```

### REST API
Access businesses via REST API:
```
GET /wp-json/bd/v1/businesses
GET /wp-json/bd/v1/businesses/{id}
GET /wp-json/bd/v1/geocode?address=123+Main+St
```

---

## 📖 Documentation

### For Users
- [Installation Guide](../../wiki/Installation)
- [Adding Businesses](../../wiki/Adding-Businesses)
- [Using Shortcodes](../../wiki/Shortcodes)
- [Organizing Content](../../wiki/Organization)

### For Developers
- [Hooks & Filters Reference](../../wiki/Hooks-and-Filters)
- [Template Customization](../../wiki/Templates)
- [REST API Documentation](../../wiki/REST-API)
- [Creating Add-ons](../../wiki/Creating-Addons)

---

## 💬 Support

### Free Support
- 📖 [Documentation Wiki](../../wiki)
- 🐛 [Report Bugs](../../issues)
- 💡 [Feature Requests](../../discussions)
- 💬 [Community Forum](../../discussions)

### Premium Support
**Available with any paid add-on or Pro Bundle:**
- 🎫 Priority ticket system
- 📧 Direct email support
- 📚 Premium documentation
- 🎥 Video tutorials
- ⚡ Faster response times

---

## 🤝 Contributing

We welcome contributions! Here's how to help:

1. **Report Bugs** - Use [GitHub Issues](../../issues)
2. **Suggest Features** - Start a [Discussion](../../discussions)
3. **Submit Pull Requests** - Follow [WordPress Coding Standards](https://developer.wordpress.org/coding-standards/)
4. **Improve Docs** - Help with [Wiki pages](../../wiki)
5. **Translate** - Contribute translations

### Development Setup
```bash
# Clone repository
git clone https://github.com/reggienicolay/Business-Directory-Plugin.git

# Install dependencies
cd Business-Directory-Plugin
composer install

# Run code standards check
composer phpcs

# Run tests
composer test
```

---

## 📝 Changelog

### Version 1.0.0 - 2025-01-XX
**Initial Release**
- Business listings custom post type
- Categories and areas taxonomies
- Interactive Leaflet maps
- Location search and filtering
- Responsive templates
- CSV import functionality
- Admin interface
- REST API endpoints

---

## 🗺️ Roadmap

### Core Features (Free)
- [ ] WordPress.org submission
- [ ] Advanced map clustering
- [ ] Improved mobile experience
- [ ] Schema.org markup
- [ ] Multi-language support (WPML/Polylang)

### Premium Add-ons (Paid)
- [ ] Reviews & Ratings (Q1 2025)
- [ ] Business Claims (Q1 2025)
- [ ] Frontend Submissions (Q1 2025)
- [ ] Advanced Filters (Q2 2025)
- [ ] Premium Templates (Q2 2025)
- [ ] Analytics Dashboard (Q2 2025)
- [ ] Payment Integration (Q3 2025)

---

## ❓ FAQ

### Is this plugin really free?
Yes! The core Business Directory plugin is 100% free and GPL-licensed. Premium add-ons are optional paid extensions.

### Can I use this on multiple sites?
Yes, the free core plugin can be used on unlimited sites. Premium add-ons require individual licenses per site (or use Agency Bundle for unlimited sites).

### Do I need all the add-ons?
No! Only purchase the add-ons you need. Start with the free core and add premium features as your directory grows.

### Will the free version always be free?
Yes. The core plugin will always be free and GPL-licensed. We're committed to the WordPress open-source ecosystem.

### Can I request features?
Absolutely! Use our [Discussions](../../discussions) page for feature requests. Popular requests may be added to the core or developed as add-ons.

### Is support included?
Community support is available for free users via GitHub. Premium add-ons include priority ticket support.

### Can I build add-ons myself?
Yes! The plugin is developer-friendly with extensive hooks and filters. See our [developer documentation](../../wiki) for creating custom add-ons.

---

## 📄 License

**GNU General Public License v2 or later**

This program is free software; you can redistribute it and/or modify it under the terms of the GNU General Public License as published by the Free Software Foundation; either version 2 of the License, or (at your option) any later version.

This program is distributed in the hope that it will be useful, but WITHOUT ANY WARRANTY; without even the implied warranty of MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the GNU General Public License for more details.

**[Read the full GPL v2 license →](https://www.gnu.org/licenses/gpl-2.0.html)**

---

## 👤 Author

**Reggie Nicolay**  
🌐 Website: [https://narrpr.com](https://narrpr.com)  
💻 GitHub: [@reggienicolay](https://github.com/reggienicolay)

---

## 🙏 Credits

Built with:
- [Leaflet.js](https://leafletjs.com/) - Open-source interactive maps
- [Leaflet.markercluster](https://github.com/Leaflet/Leaflet.markercluster) - Marker clustering
- WordPress - The world's best CMS

---

## ⭐ Show Your Support

If you find Business Directory useful:
- ⭐ **Star this repository**
- 🐦 **Share on social media**
- 💬 **Write a review** (when on WordPress.org)
- 💰 **Consider premium add-ons** to support development

---

**Ready to create an amazing business directory?** 

[Download Now](../../releases) | [View Demo](#) | [Documentation](../../wiki) | [Get Premium Add-ons](#)

---

*Business Directory - Making local business discovery easy since 2025* 🚀