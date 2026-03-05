# WPCargo Access Control

**Versión:** 1.0.0  
**Requiere:** WordPress 5.0+, PHP 7.4+

## 📋 Descripción

Plugin de gestión centralizada de permisos y acceso para WPCargo. Permite restringir acceso a páginas específicas por email/rol, filtrar menús del sidebar y controlar desbloqueos manuales de clientes.

Mueve toda la lógica de control de acceso desde `functions.php` a un plugin modular, independiente y reutilizable.

---

## ✨ Características

### 1. **Matriz de Permisos por Email**
- Define qué páginas puede acceder cada usuario
- Basado en email del usuario
- Fácilmente configurable y extensible

### 2. **Restricción de Acceso por Ruta**
- Control en `template_redirect` - redirecciona usuarios sin permiso
- Siempre permite: wp-admin, wp-json, assets estáticos
- Administradores siempre tienen acceso

### 3. **Filtrado de Menús Sidebar**
- `wpcfe_after_sidebar_menu_items` - filtra items individuales
- `wpcfe_after_sidebar_menus` - filtra grupos de menús
- CSS adicional para ocultar items que no se pueden filtrar

### 4. **Sistema de Desbloqueo Manual**
- Toggle diario para desbloquear todos los clientes
- Control desde WP-Admin: **Herramientas > Skip Blocks Recojo**
- Aplica `merc_desbloqueado_manualmente_fecha` a todos los clientes
- Automático: el estado se resetea después de medianoche

### 5. **Super Administas Especiales**
- Email `mercourier2019@gmail.com` tiene permisos totales
- Email `davidmorilloacuna@gmail.com` puede acceder al admin
- El resto requiere acceso a `/wp-admin` bloqueado

---

## 🚀 Instalación

1. **Subir plugin**
   ```bash
   wp-content/plugins/wpcargo-access-control/
   ```

2. **Activar en WP-Admin**
   ```
   Plugins > Plugins instalados > Activar "WPCargo Access Control"
   ```

3. **Verificar en logs**
   ```
   wp-content/merc_logs/merc-debug-YYYY-MM-DD.log
   # Verá: ✅ WPCargo Access Control activated
   ```

---

## ⚙️ Configuración

### Definir Permisos por Email

**Opción 1: Via Base de Datos**
```php
// En functions.php de tu theme de child
add_filter('wpcac_permissions_matrix', function($matrix) {
    $matrix['nuevousuario@example.com'] = array(
        '/dashboard/',
        '/dashboard/?wpcfe=add',
        '/receiving/',
    );
    return $matrix;
});
```

**Opción 2: Via Función**
```php
// Agregar permisos
wpcac_add_user_permissions('user@example.com', array(
    '/dashboard/',
    '/containers/',
));

// Remover permisos
wpcac_remove_user_permissions('user@example.com');

// Obtener permisos
$paths = wpcac_get_user_permissions('user@example.com');
```

### Rutas Disponibles

```php
'/dashboard/'                           // Panel principal
'/dashboard/?wpcfe=add'                 // Crear envío
'/dashboard/?wpcfe=add&type=normal'     // Crear envío normal
'/dashboard/?wpcfe=add&type=express'    // Crear envío express
'/dashboard/?wpcfe=add&type=full_fitment' // Crear envío full fitment
'/containers/'                          // Contenedores
'/receiving/'                           // Recepción
'/import-export/?type=import'           // Importación CSV
'/almacen-de-productos/'                // Almacén de productos
'/devoluciones/'                        // Devoluciones
'/panel-admin/'                         // Panel de administrador
'/wpcumanage-users/'                    // Gestión de usuarios
'/wpcpod-report-order/'                 // Reporte de órdenes POD
```

---

## 🎮 Uso: Desbloqueo Manual

### Vía WP-Admin
```
Herramientas > Skip Blocks Recojo
```
- Click en **🔓 DESBLOQUEAR** para permitir todos los clientes hoy
- Click en **🔒 BLOQUEAR** para re-aplicar restricciones
- Ver log de cambios reciente

### Vía URL (admin only)
```
https://tudominio.com/wp-admin/tools.php?page=merc-skip-blocks&merc_toggle_skip_today=enable
https://tudominio.com/wp-admin/tools.php?page=merc-skip-blocks&merc_toggle_skip_today=disable
```

### Vía Código
```php
// Desbloquear todos los clientes hoy
wpcac_apply_skip_to_all_clients(true);

// Bloquear nuevamente
wpcac_apply_skip_to_all_clients(false);

// Verificar estado
if (wpcac_is_bypass_enabled_today()) {
    echo "Desbloqueo en efecto";
}
```

---

## 🔍 Funciones Públicas

### Gestión de Permisos

```php
/**
 * Obtener permisos de usuario
 */
wpcac_get_user_permissions($email);
// Retorna: array de rutas permitidas

/**
 * Verificar si usuario actual puede acceder a ruta
 */
wpcac_current_user_can_access($path);
// Retorna: bool

/**
 * Obtener rutas permitidas para usuario actual
 */
wpcac_get_current_user_allowed_paths();
// Retorna: array de rutas

/**
 * Agregar permisos
 */
wpcac_add_user_permissions($email, $paths);

/**
 * Remover permisos
 */
wpcac_remove_user_permissions($email);
```

### Desbloqueo Manual

```php
/**
 * Aplicar/remover desbloqueo para todos los clientes
 */
wpcac_apply_skip_to_all_clients($enable);
// $enable: bool (true = desbloquear, false = bloquear)

/**
 * Verificar si desbloqueo está activo hoy
 */
wpcac_is_bypass_enabled_today();
// Retorna: bool

/**
 * Obtener fecha actual
 */
wpcac_get_today();
// Retorna: string (Y-m-d format)

/**
 * Obtener estado de bypass
 */
wpcac_get_bypass_status();
// Retorna: bool
```

---

## 📚 Hooks Available

### Filters

```php
/**
 * Modificar matriz de permisos
 */
apply_filters('wpcac_permissions_matrix', $matrix);

/**
 * Modificar rutas permitidas de usuario
 */
apply_filters('wpcac_user_paths', $paths, $email);
```

### Actions

```php
/**
 * Cuando se actualizan permisos
 */
do_action('wpcac_permissions_updated', $email, $paths);

/**
 * Cuando se remueven permisos
 */
do_action('wpcac_permissions_removed', $email);

/**
 * Cuando cambia estado de desbloqueo
 */
do_action('wpcac_skip_status_changed', $enable, $today, $count);
```

---

## 🗑️ Desinstalación

El plugin limpia automáticamente:
- ✅ Opciones almacenadas (`wpcac_permissions_matrix`, `merc_skip_blocks_today`)
- ✅ User meta de desbloqueo manual
- ✅ Flush de rewrite rules

---

## 📝 Logs

Todos los eventos se registran en:
```
wp-content/merc_logs/merc-debug-YYYY-MM-DD.log
```

Búsqueda de eventos:
```bash
grep "wpcac" wp-content/merc_logs/merc-debug-2026-03-05.log
```

---

## 🔄 Rutas + Extensiones Previstas

Este es el primer plugin de la arquitectura modular. Próximos plugins:

1. **wpcargo-shipment-filters** - Filtros avanzados
2. **merc-penalties-system** - Sistema de sanciones
3. **merc-csv-import-pro** - Importación mejorada
4. **wpcargo-form-enhancements** - Mejoras de formulario

---

## 🐛 Troubleshooting

### Usuarios redireccionados a home
- ✅ Verificar que email está en matriz de permisos
- ✅ Verificar que rutas no tienen `/` final extra
- ✅ Revisar logs: `grep "template_redirect" merc-debug-*.log`

### Menú items no se ocultan
- ✅ El CSS se aplica en `wp_head`
- ✅ Si persiste, usar DevTools para inspeccionar
- ✅ Puede haber selectores CSS conflictivos

### Desbloqueo no funciona
- ✅ Verificar permisos del usuario (debe ser `administrator`)
- ✅ Revisar `wp_nonce_field` en formulario
- ✅ Chequear logs para errores de nonce

---

## 📞 Soporte

Para reportar problemas o sugerencias:
- Email: info@mercourier.com
- GitHub: (futura integración)

---

**© 2026 Mercourier - Todos los derechos reservados**
