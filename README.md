# Rocket.net Reseller with FluentCart

A WordPress plugin that enables selling Rocket.net hosting through FluentCart with on-demand site creation and management.

**Version:** 0.9
**Author:** WolfDevs - https://wolfdevs.com
**Requires:** WordPress 6.0+, PHP 7.4+, FluentCart 1.2.2+

## Features

- ✅ Sell Rocket.net hosting through FluentCart
- ✅ On-demand site creation (sites not created until customer requests)
- ✅ Quantity-based site allocation (qty × sites per product)
- ✅ Customer dashboard for site management
- ✅ Embedded Rocket.net control panel
- ✅ Subscription management integration
- ✅ Secure API token encryption
- ✅ Single product per order validation
- ✅ Comprehensive admin tools
- ✅ Manual allocation creation and editing
- ✅ Allocation deletion with site cleanup
- ✅ Customizable page URLs for customer dashboard
- ✅ Cache-busted CSS/JS assets

## Installation

1. Upload the `rocket-fluentcart-plugin` folder to `/wp-content/plugins/`
2. Activate the plugin through the 'Plugins' menu in WordPress
3. Configure Rocket.net API credentials in **FluentCart > Rocket Settings**
4. Create hosting products with Rocket configuration

## Requirements

- WordPress 6.0 or higher
- PHP 7.4 or higher
- FluentCart 1.2.2 or higher
- Sodium PHP extension (for secure token encryption)
- Rocket.net API credentials

## Configuration

### 1. API Credentials

Navigate to **FluentCart > Rocket Settings** and enter:

- Rocket.net Email
- Rocket.net Password
- Click "Test Connection" to verify

### 2. Product Setup

Edit a FluentCart product and:

1. Enable "Rocket.net Hosting for this product"
2. Set "Number of Sites Per Product" (e.g., 5)
3. Configure optional settings:
   - Disk Space (MB)
   - Bandwidth (MB)
   - Monthly Visitors
   - WordPress Plugins to Auto-Install

**Note:** Sites allocated = Sites Per Product × Order Quantity

### 3. Page URLs (Optional)

Configure customer-facing page URLs in **FluentCart > Rocket Settings**:

- **My Sites URL** - Where customers manage their sites (default: /my-sites/)
- **Hosting Plans URL** - Where customers can purchase hosting (default: /hosting-plans/)

These URLs support both absolute and relative paths.

### 4. Branding (Optional)

Customize colors in **FluentCart > Rocket Settings**:

- Primary Color
- Secondary Color
- Accent Color
- Background Color
- Text Color
- Link Color
- Button Color
- Button Text Color

## Usage

### For Administrators

**View Allocations:**
- Navigate to **FluentCart > Site Allocations**
- View stats, filter by status, search by order/customer ID
- Click "View Sites" to see details

**Create Manual Allocations:**
- Click "Create Allocation" button at top of Site Allocations page
- Fill in customer, order, product, total sites, and status
- Useful for custom arrangements or migrations

**Edit Allocations:**
- Click "Edit" button on any allocation row
- Modify total sites, used sites, or status
- Orders dropdown auto-filters by selected customer

**Delete Allocations:**
- Click "Delete" button on any allocation
- Confirmation required (irreversible action)
- Deletes allocation and all associated sites

**Monitor Orders:**
- Order confirmation emails include site allocation info
- Order details page shows allocation status

### For Customers

**Purchase Hosting:**
1. Add hosting product to cart
2. Complete checkout and payment
3. Receive allocation (e.g., "10 sites allocated")

**Create Sites:**
1. Visit "My Sites" dashboard (via shortcode or link)
2. Click "Create New Site"
3. Fill in details:
   - Site Name
   - Domain
   - Server Location
   - Admin Email
4. Click "Create Site"
5. Save admin credentials shown

**Manage Sites:**
- Click "Manage" button on any site
- Opens Rocket.net control panel in new window
- Temporary access token generated (400s TTL)

## Shortcodes

### [rocket_my_sites]

Displays customer's hosting dashboard with allocations and sites.

**Usage:**
```
[rocket_my_sites]
```

**PHP Function:**
```php
<?php echo rocket_display_my_sites(); ?>
```

## Workflow

1. **Customer Orders**
   - Adds hosting product (qty 2 × 5 sites = 10 total)
   - Checkout validates only 1 product per order
   - Payment completes

2. **Allocation Created**
   - Database record created (10 sites, 0 used)
   - Status: active
   - Order email includes allocation info

3. **Customer Creates Sites**
   - Views "My Sites" dashboard
   - Sees "10 sites available, 0 used"
   - Creates site #1 via form
   - Now shows "10 sites available, 1 used"

4. **Subscription Cancelled**
   - Allocation status → cancelled
   - All sites status → suspended
   - Customer cannot access/manage sites

## Database Schema

### wp_fc_rocket_allocations

Tracks site allocations per order.

| Column | Type | Description |
|--------|------|-------------|
| id | bigint | Primary key |
| customer_id | bigint | FluentCart customer ID |
| order_id | bigint | FluentCart order ID |
| product_id | bigint | FluentCart product ID |
| total_sites | int | Total sites allocated |
| used_sites | int | Sites created |
| status | varchar | active/cancelled |
| created_at | datetime | Creation timestamp |
| updated_at | datetime | Update timestamp |

### wp_fc_rocket_sites

Tracks individual sites.

| Column | Type | Description |
|--------|------|-------------|
| id | bigint | Primary key |
| allocation_id | bigint | FK to allocations |
| order_id | bigint | For easy tracking |
| customer_id | bigint | For easy queries |
| site_name | varchar | Site friendly name |
| rocket_site_id | varchar | Rocket.net site ID |
| rocket_site_data | longtext | JSON site data |
| status | varchar | active/suspended |
| created_at | datetime | Creation timestamp |
| deleted_at | datetime | Soft delete timestamp |

## Hooks & Filters

### Actions

**rfc_allocation_created**
```php
do_action('rfc_allocation_created', $allocation_id, $order_id, $customer_id);
```

**rfc_allocation_cancelled**
```php
do_action('rfc_allocation_cancelled', $allocation_id, $order_id);
```

**rfc_site_created**
```php
do_action('rfc_site_created', $site_id, $allocation_id, $rocket_result);
```

### Filters

**rfc_create_site_data**
```php
apply_filters('rfc_create_site_data', $body, $site_data);
```

## FluentCart Integration

### Events Hooked

**fluent_cart/order_paid**
- Creates allocation record
- Stores order metadata
- No sites created yet (on-demand only)

**fluent_cart/subscription_canceled**
- Updates allocation status to 'cancelled'
- Updates all sites status to 'suspended'
- Prevents customer access

**fluent_cart/before_checkout_process**
- Validates only 1 Rocket product per order
- Throws exception if multiple found

## API Integration

### Rocket.net API Endpoints Used

- `POST /v1/login` - Authentication
- `POST /v1/partner/sites` - Create site
- `DELETE /v1/sites/{id}` - Delete site (unused currently)
- `POST /v1/sites/{id}/access_token` - Generate control panel token
- `GET /v1/locations` - Get available server locations

### Security

- JWT tokens encrypted with libsodium
- Keypair-based encryption/decryption
- Auto token refresh on 401
- Nonce verification on AJAX
- Capability checks on all admin pages

## Troubleshooting

### Connection Test Fails

1. Verify credentials are correct
2. Check Rocket.net API status
3. Enable WP_DEBUG to see detailed logs
4. Check error_log for API responses

### Site Creation Fails

1. Check Rocket.net account has available resources
2. Verify domain is valid and not already used
3. Check PHP error logs
4. Enable debug logging

### Subscription Not Suspending Sites

1. Verify FluentCart subscription hooks are firing
2. Check allocation status in Site Allocations page
3. Review error logs

## Debug Logging

Enable WordPress debug logging:

```php
// wp-config.php
define('WP_DEBUG', true);
define('WP_DEBUG_LOG', true);
define('WP_DEBUG_DISPLAY', false);
```

Logs are written to `wp-content/debug.log`

## Support

For issues, feature requests, or questions:

- **Plugin Author:** WolfDevs
- **Website:** https://wolfdevs.com
- **Email:** support@wolfdevs.com

## Changelog

### Version 0.9 (2024-11-20)

**New Features:**
- Manual allocation creation and editing
- Allocation deletion with site cleanup
- Customizable page URLs (My Sites, Hosting Plans)
- Cache-busted CSS/JS assets with filemtime
- Dynamic FluentCart database column detection
- Order filtering by customer in allocation modal

**Improvements:**
- Better modal UI with proper button styling
- Improved AJAX error handling
- Enhanced validation for allocation forms
- Removed unused template files

**Bug Fixes:**
- Fixed database column errors for FluentCart tables
- Fixed modal JavaScript not loading on page load
- Fixed Cancel button display in allocation modal

### Version 0.1 (2024-10-19)

- Initial release
- Core functionality: allocations, site creation, subscription handling
- Admin: Settings, Product metabox, Allocations page
- Frontend: Customer dashboard, Site creation
- API: Rocket.net integration with secure encryption
- FluentCart: Order paid, Subscription cancelled hooks

## License

GPL v2 or later

## Credits

- Developed by WolfDevs
- Powered by Rocket.net API
- Integrated with FluentCart

---

**Note:** This plugin requires active Rocket.net and FluentCart accounts. Rocket.net hosting resources are subject to your Rocket.net plan limits.
