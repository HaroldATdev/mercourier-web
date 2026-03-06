# WPCargo UI Customizer

**Modular UI customization for WPCargo - Menu renaming, column management, field visibility**

## Features

- ✅ Rename driver menu items (Pickup/Delivery routes)
- ✅ Remove unwanted shipment table columns
- ✅ Rearrange table columns
- ✅ Hide location field from forms
- ✅ Hide financial sidebar badge
- ✅ Fix Bootstrap dropdown issues
- ✅ Custom footer text
- ✅ Responsive table styling

## Structure

```
wpcargo-ui-customizer/
├── plugin.php              # Main plugin file
├── includes/
│   ├── class-main.php     # Main plugin class
│   ├── menus.php          # Menu customizations
│   ├── tables.php         # Table customizations
│   ├── footer.php         # Footer customizations
│   └── styles.php         # Styles and scripts
└── README.md
```

## Customizations

### Menu Renaming (for Drivers)
- "wpcpod-pickup-route" → "Recojo de mercadería"
- "wpcpod-route" → "Entrega de mercadería"

### Table Column Management
- Removes Type column
- Removes Shipper/Receiver column
- Removes Container column
- Reorders Status after "Cambio de Producto"
- Reorders Tracking after "Motorizado Entrega"

### Field Visibility
- Hides Location field from shipment forms
- Hides financial sidebar badge
- Hides Location meta fields

### Responsive Design
- Makes delivery tables horizontally scrollable
- Maintains Bootstrap dropdown functionality
- Ensures body overflow is always visible

## How It Works

1. **Menus Module** - Filters sidebar menu items and renames for drivers
2. **Tables Module** - Removes actions and uses JavaScript to rearrange columns
3. **Footer Module** - Replaces default footer credit with custom text
4. **Styles Module** - Injects CSS and JavaScript for UI fixes and enhancements

## Hooks Used

- `wpcfe_after_sidebar_menus` - Rename menu items
- `init` / `plugins_loaded` - Remove table columns
- `wp_footer` - JavaScript manipulation
- `wpcfe_footer_credits` - Custom footer text
- `wp_head` - Inline styles

## Dependencies

- WordPress 5.0+
- WPCargo plugin active
- jQuery (for table manipulation)

## Version

- v1.0.0 - Initial release

## Location

`wp-content/plugins/wpcargo-ui-customizer/`
