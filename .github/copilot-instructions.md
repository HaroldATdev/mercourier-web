# MERCourier Web Copilot Instructions

## Architecture
- WordPress-based courier service platform built on WPCargo plugin for shipment tracking and management.
- **Core Components**: Blocksy theme (active), WPCargo suite plugins, custom MERC plugins (merc-table-customizer, merc-finance, merc-returns, etc.).
- **Data Flow**: Shipments created via frontend forms, processed through WPCargo, tracked by clients/drivers; payments handled via merc-finance.
- **Service Boundaries**: Frontend (client panels), Admin (management), Driver (routes/POD), Warehouse (receiving/containers).

## Workflows
- **Maintenance Fixes**: Use Python scripts in root for code fixes:
  - `find_bom.py`: Scan PHP files for UTF-8 BOM (run after edits to wp-content/plugins/themes).
  - `fix_all.py`: Apply CSS/JS patches to merc-table-customizer (e.g., checkbox styling, event handlers).
  - `fix_checkbox.py`, `fix_accordion.py`, etc.: Targeted fixes for UI issues.
- **Development**: Edit custom plugins in wp-content/plugins/merc-*, test in staging, deploy via FTP or deploy.php.
- **Debugging**: Check BOM in files first; use WP_DEBUG in wp-config.php.

## Conventions
- **WordPress Standards**: Use actions/filters/hooks; avoid direct DB queries (use WP_Query, $wpdb prepared).
- **Custom Plugins**: Follow WPCargo patterns; classes in admin/classes/, functions in includes/.
- **UI Patterns**: Custom tables use .merc-tienda-card-table class; checkbox sync via updateGlobalCheckboxState() (see merc-table-customizer).
- **File Encoding**: Ensure no BOM in PHP files; run find_bom.py post-edit.
- **Example**: For shipment forms, extend WPCargo with custom fields via wpcargo-custom-field-addons, using conditional logic.

## Key Files/Directories
- `wp-config.php`: DB/constant config.
- `wp-content/themes/blocksy/`: Main theme (overrides in blocksy-child/).
- `wp-content/plugins/wpcargo/`: Core shipment plugin.
- `wp-content/plugins/merc-table-customizer/`: Custom table UI (example: admin/classes/class-shipment-table.php).
- `wp-content/plugins/wpcargo-custom-field-addons/`: Form builder (see CLAUDE.md for details).