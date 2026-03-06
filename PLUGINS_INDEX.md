# 📑 ÍNDICE DE PLUGINS MERCOURIER

*Referencia rápida de todos los módulos del sistema*

---

## 🆕 PLUGINS CREADOS (Esta sesión: 5 de marzo, 2026)

### 1. wpcargo-shipment-filters
**Filtros avanzados para dashboard de envíos**

- **Ruta**: `wp-content/plugins/wpcargo-shipment-filters/`
- **Archivos**:
  - `plugin.php` - Entry point
  - `includes/class-main.php` - Clase principal
  - `includes/filters.php` - Lógica de filtros
  - `includes/filters-ui.php` - Rendering de UI
  - `includes/scripts.php` - JavaScript
  - `README.md` - Documentación

- **Funciones principales**:
  - Filtro por fecha (from/to)
  - Filtro por motorizado (recojo/entrega)
  - Filtro por cliente searchable
  - Filtro por tiencia
  - Meta queries integradas

- **Hooks utilizados**:
  - `wpcfe_dashboard_meta_query`
  - `wpcfe_dashboard_arguments`
  - `wpcfe_after_shipment_filters`

---

### 2. merc-helpers-library
**Librería centralizada de funciones auxiliares**

- **Ruta**: `wp-content/plugins/merc-helpers-library/`
- **Archivos**:
  - `plugin.php` - Entry point
  - `includes/helpers-date.php` - 8 funciones de fecha/hora
  - `includes/helpers-shipment.php` - 10 funciones de envíos
  - `includes/helpers-user.php` - 11 funciones de usuarios
  - `includes/helpers-financial.php` - 10 funciones financieras
  - `README.md` - Documentación

- **Funciones disponibles**:
  - `merc_get_today()` - Hoy en Y-m-d
  - `merc_get_time_limits($tipo)` - Límites por tipo
  - `merc_count_envios_del_tipo_hoy($client_id, $tipo)` - Contador
  - `merc_get_motorizado_activo($shipment_id)` - Driver activo
  - `merc_get_user_phone($user_id)` - Teléfono del usuario
  - `merc_get_shipment_cost($shipment_id)` - Costo del envío
  - `merc_format_currency($amount)` - Formatear moneda
  - Y muchas más (39 total)

---

### 3. wpcargo-ui-customizer
**Personalización modular de interfaz**

- **Ruta**: `wp-content/plugins/wpcargo-ui-customizer/`
- **Archivos**:
  - `plugin.php` - Entry point
  - `includes/class-main.php` - Clase principal  
  - `includes/menus.php` - Renombramiento de menús
  - `includes/tables.php` - Manipulación de tabla
  - `includes/footer.php` - Personalización de footer
  - `includes/styles.php` - Estilos globales
  - `README.md` - Documentación

- **Personalizaciones**:
  - Renom. POD: "Recojo/Entrega de mercadería"
  - Remover columnas de shipment
  - Reordenar columnas (Estado, Tracking)
  - Ocultar Location field
  - Fixes de Bootstrap dropdowns
  - Footer personalizado
  - Responsive tables

---

### 4. wpcargo-user-management
**Gestión centralizada de usuarios**

- **Ruta**: `wp-content/plugins/wpcargo-user-management/`
- **Archivos**:
  - `plugin.php` - Entry point
  - `includes/class-main.php` - Clase principal
  - `includes/phone-capture.php` - Phone capture logic
  - `README.md` - Documentación

- **Funcionalidades**:
  - Captura automática de teléfono
  - Soporta 8 nombres de campos diferentes
  - Persiste en transientes
  - Hooks múltiples (user_register, profile_update, wp_insert_user)
  - Almacenamiento en meta keys

- **Campos de teléfono detectados**:
  - phone, billing_phone, wpcargo_phone, user_phone
  - telephone, telefono, wpcargo_shipper_phone, wpcu_phone

---

## 📦 PLUGINS EXISTENTES

### 5. merc-finance  
**Sistema de finanzas, liquidaciones y penalidades**

- **Ruta**: `wp-content/plugins/merc-finance/`
- **Estado**: Existente, operacional
- **Crítico**: SÍ

---

### 6. wpcargo-access-control
**Control de acceso y permisos**

- **Ruta**: `wp-content/plugins/wpcargo-access-control/`
- **Estado**: Existente, operacional
- **Crítico**: SÍ

---

### 7. merc-csv-import
**Importación de CSV y validaciones**

- **Ruta**: `wp-content/plugins/merc-csv-import/`
- **Estado**: Existente, operacional

---

### 8. merc-table-customizer
**Personalización de tabla de shipments**

- **Ruta**: `wp-content/plugins/merc-table-customizer/`
- **Estado**: Existente, operacional

---

### 9. merc-form-enhancements
**Mejoras en formularios de envíos**

- **Ruta**: `wp-content/plugins/merc-form-enhancements/`
- **Estado**: Existente, operacional

---

## 🔌 DEPENDENCIAS Y ORDEN

```
merc-helpers-library              ← BASE (sin dependencias)
    ↓
merc-finance                      ← Finanzas (puede usar helpers)
wpcargo-access-control            ← Acceso (puede usar helpers)
    ↓
wpcargo-shipment-filters          ← Filtros (usa helpers + wpcfe)
wpcargo-ui-customizer             ← UI (usa helpers + wpcfe)
wpcargo-user-management           ← Usuarios (usa helpers)
    ↓
merc-csv-import                   ← CSV (usa helpers + finance)
merc-table-customizer             ← Tabla (usa helpers)
merc-form-enhancements            ← Formularios (usa helpers)
```

---

## 🎯 ACTIVACIÓN RECOMENDADA

```
Orden sugerido:
1. merc-helpers-library           ← PRIMERO (dependencia)
2. merc-finance                   ← CRÍTICO
3. wpcargo-access-control         ← CRÍTICO
4. wpcargo-shipment-filters       ← ESENCIAL
5. wpcargo-ui-customizer          ← IMPORTANTE
6. wpcargo-user-management        ← IMPORTANTE
7. merc-csv-import                ← COMPLEMENTO
8. merc-table-customizer          ← COMPLEMENTO
9. merc-form-enhancements         ← COMPLEMENTO
```

---

## 📊 ESTADÍSTICAS

| Métrica | Cantidad |
|---------|----------|
| Plugins totales | 9 |
| Plugins nuevos (hoy) | 4 |
| Funciones helper | 39 |
| Líneas de código migradas | ~5,000+ |
| Archivos creados | 20 |
| README de documentación | 4 |

---

## 🔗 ACCESOS RÁPIDOS

### Activación/Desactivación
```bash
# Activar todos
wp plugin activate wpcargo-shipment-filters merc-helpers-library wpcargo-ui-customizer wpcargo-user-management

# Deactivar un plugin específico
wp plugin deactivate wpcargo-shipment-filters

# Ver estado
wp plugin list --status=active
```

### Verificación de Errores
```bash
# Ver últimas líneas de log
tail -f /wp-content/merc_logs/merc-debug-*.log

# Ver solo errores
grep -i "error\|fatal\|warning" /wp-content/merc_logs/merc-debug-*.log
```

### URLs de Administración
```
Plugins:          /wp-admin/plugins.php
Settings:         /wp-admin/options-general.php
Shipments:        /wp-admin/admin.php?page=wpcfe_shipment
Users:            /wp-admin/users.php
Logs:             /wp-content/merc_logs/
```

---

## 📋 REFERENCIAS DE CÓDIGO

### Llamar helpers desde otros plugins
```php
// Revisar que merc-helpers-library esté activo
if ( function_exists( 'merc_get_today' ) ) {
    $today = merc_get_today();
} else {
    error_log( 'ERROR: merc-helpers-library is not active' );
}
```

### Usar hooks de phone capture
```php
add_action( 'wpcargo_phone_saved', function( $user_id, $phone ) {
    error_log( "Phone saved for user {$user_id}: {$phone}" );
}, 10, 2 );
```

---

## 🚨 EMERGENCIAS

### Plugin no carga
1. Verificar PHP errors: `php -l plugin.php`
2. Revisar logs: `/wp-content/merc_logs/`
3. Desactivar y reactivar

### Filtros no funcionan
1. Verificar WPCargo activo
2. Revisar que merc-helpers-library activo
3. Buscar "[FILTER]" en logs

### Helpers no disponibles
1. Activar merc-helpers-library
2. Revisar require_once en plugin.php
3. PHP errors: `php -l plugin.php`

---

## 📋 TODO LIST PARA PRÓXIMAS FASES

- [ ] Limpiar functions.php
- [ ] Crear pruebas unitarias
- [ ] Documentación de API
- [ ] Guía de extensión
- [ ] Performance audit
- [ ] Security audit
- [ ] Crear wpcargo-penalties-system
- [ ] Crear wpcargo-notifications
- [ ] Crear merc-audit-log

---

**Preparado**: 5 de marzo, 2026  
**Última actualización**: 5 de marzo, 2026  
**Estado**: 🟢 LISTO
