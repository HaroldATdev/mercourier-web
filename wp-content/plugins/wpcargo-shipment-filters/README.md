# WPCargo Shipment Filters

**Advanced shipment filtering system for WPCargo**

## Features

- ✅ Date range filtering (from/to shipment dates)
- ✅ Motorizado (driver) filters for pickup and delivery
- ✅ Client searchable filter
- ✅ Tienda (store) filter with dynamic options
- ✅ Auto-apply today's date by default
- ✅ Meta query integration with WPCargo dashboard

## Structure

```
wpcargo-shipment-filters/
├── plugin.php                 # Main plugin file
├── includes/
│   ├── class-main.php        # Main plugin class
│   ├── filters.php           # Filter logic and meta queries
│   ├── filters-ui.php        # Filter UI rendering
│   ├── scripts.php           # JavaScript handlers
│   └── config.php            # Configuration (future)
└── README.md
```

## Hooks Used

### Filters
- `wpcfe_dashboard_meta_query` - Meta query manipulation
- `wpcfe_dashboard_arguments` - WP_Query arguments modification

### Actions
- `wpcfe_after_shipment_filters` - Render filter UI
- `wp_head` - Inline styles
- `admin_footer` - Admin scripts
- `wp_footer` - Frontend scripts

## Usage

1. Activate the plugin from WordPress admin
2. All filters will automatically appear in the shipment dashboard
3. Filters are automatically applied when navigating

## Configuration

The plugin uses default WPCargo hooks for:
- Date formatting (Y-m-d to d/m/Y conversion)
- Meta key detection (wpcargo_pickup_date_picker, etc.)
- Driver role detection (wpcargo_driver)

## Dependencies

- WordPress 5.0+
- WPCargo plugin active
- WPCargo Dashboard available

## Compatibility

- WPCargo 1.0+
- WordPress 5.0 - 6.x
- PHP 7.2+

## Version

- v1.0.0 - Initial release
