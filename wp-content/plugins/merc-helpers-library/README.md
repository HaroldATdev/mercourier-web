# MERCourier Helpers Library

**Centralized reusable helper functions for MERCourier**

This plugin provides a comprehensive library of helper functions organized by category, making them easily reusable across other plugins and the main codebase.

## Features

- ✅ Date/Time helpers
- ✅ Shipment/Envío helpers  
- ✅ User/Usuario helpers
- ✅ Financial/Financiero helpers
- ✅ Easy to extend with new categories

## Organization

### Date Helpers (`helpers-date.php`)
- `merc_get_today()` - Get today's date in Y-m-d format
- `merc_get_tomorrow_formatted()` - Get tomorrow in d/m/Y format (skip Sundays)
- `merc_get_time_limits($tipo)` - Get time limits by shipment type
- `merc_get_current_time()` - Get current time in HH:MM format
- `merc_is_today($date)` - Check if date is today
- `merc_convert_date_to_iso($date)` - Convert d/m/Y to Y-m-d
- `merc_convert_date_from_iso($date)` - Convert Y-m-d to d/m/Y

### Shipment Helpers (`helpers-shipment.php`)
- `merc_count_envios_del_tipo_hoy($client_id, $tipo)` - Count today's shipments by type
- `merc_get_motorizado_activo($shipment_id)` - Get active driver
- `merc_get_shipment_status($shipment_id)` - Get shipment status
- `merc_get_shipment_cost($shipment_id)` - Get shipment cost
- `merc_get_shipment_pickup_date($shipment_id)` - Get pickup date
- `merc_pickup_date_is_today($shipment_id)` - Check if pickup is today
- `merc_get_shipment_tracking($shipment_id)` - Get tracking number
- `merc_normalize_tipo_envio($tipo)` - Normalize shipment type

### User Helpers (`helpers-user.php`)
- `merc_get_user_phone($user_id)` - Get user phone
- `merc_get_user_full_name($user_id)` - Get user full name
- `merc_get_user_address($user_id)` - Get user address
- `merc_get_user_district($user_id)` - Get user district
- `merc_get_user_company($user_id)` - Get user company
- `merc_get_user_email($user_id)` - Get user email
- `merc_user_has_role($user_id, $role)` - Check user role
- `merc_is_client_user($user_id)` - Check if client
- `merc_is_driver_user($user_id)` - Check if driver
- `merc_get_all_drivers()` - Get all drivers
- `merc_get_driver_name($driver_id)` - Get driver name

### Financial Helpers (`helpers-financial.php`)
- `merc_format_amount($amount)` - Format amount for display
- `merc_normalize_amount($amount)` - Normalize amount text to float
- `merc_get_shipment_revenue($shipment_id)` - Get shipment revenue
- `merc_get_user_total_debt($user_id)` - Get user total debt
- `merc_get_user_total_liquidations($user_id)` - Get total liquidations
- `merc_get_user_today_revenue($user_id)` - Get today's revenue
- `merc_is_shipment_paid($shipment_id)` - Check if shipment is paid
- `merc_mark_shipment_as_paid($shipment_id, $liquidation_id)` - Mark as paid
- `merc_parse_currency($currency)` - Parse currency string
- `merc_format_currency($amount, $symbol)` - Format as currency

## Usage

All functions are available globally after plugin activation:

```php
// Date helpers
$today = merc_get_today();
$limits = merc_get_time_limits('normal');

// Shipment helpers
$count = merc_count_envios_del_tipo_hoy($client_id, 'express');
$driver_id = merc_get_motorizado_activo($shipment_id);

// User helpers
$full_name = merc_get_user_full_name($user_id);
$phone = merc_get_user_phone($user_id);
$is_client = merc_is_client_user($user_id);

// Financial helpers
$formatted = merc_format_currency(150.50);
$debt = merc_get_user_total_debt($user_id);
```

## Extending

To add new helper functions:

1. Create a new file in `includes/` with naming pattern `helpers-{category}.php`
2. Add require statement in `plugin.php`
3. Implement functions following the naming convention: `merc_{function_name}()`

## Dependencies

- WordPress 5.0+
- PHP 7.2+

## Version

- v1.0.0 - Initial release

## Location

`wp-content/plugins/merc-helpers-library/`
