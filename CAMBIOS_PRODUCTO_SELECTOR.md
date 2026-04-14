# 📋 Resumen de Cambios - Selector de Productos por Cliente

## 🎯 Objetivo
Mostrar SOLO los productos asignados al cliente seleccionado al crear/editar un envío MERC Full Fitment.

---

## ✅ Cambios Realizados

### 1. **Backend PHP** - `class-shipment-form-fields.php`

#### Cambio 1: Lógica de filtrado inicial (líneas ~480-520)
**Antes:** Mostraba TODOS los productos si era admin sin shipper_id
**Después:** Muestra selector VACÍO hasta que el admin seleccione un cliente

```php
// IMPORTANTE: Si no hay cliente definido (admin en creación), mostrar lista VACÍA
// La lista se llenará cuando el usuario seleccione un cliente vía AJAX
$mostrar_todos = ( $cliente_id === null );

if ( $mostrar_todos ) {
    error_log("📦 [MERC_FORM] Sin cliente definido - mostrando selector VACÍO");
    $productos_disponibles = [];
} else {
    // Obtener productos filtrados por cliente...
}
```

#### Cambio 2: AJAX Handler `ajax_reload_productos()` (líneas ~1351-1405)
**Funcionalidad:** Recibe `shipper_id` y retorna HTML con productos filtrados

```php
public function ajax_reload_productos() {
    check_ajax_referer( 'merc_form_nonce', 'nonce' );
    
    $shipper_id = isset( $_POST['shipper_id'] ) ? intval( $_POST['shipper_id'] ) : 0;
    
    // Filtrar por: _merc_producto_cliente_asignado == shipper_id
    // Retorna HTML con <option> para cada producto
}
```

**Registrado como:** `wp_ajax_merc_reload_productos` (línea 1412)

---

### 2. **Frontend JavaScript** - `client-autofill.js`

#### Cambio 1: Integración con `cargarDatosCliente()` (línea ~261)
**Antes:** Solo rellenaba datos del cliente (nombre, teléfono, etc.)
**Después:** También recarga productos del cliente seleccionado

```javascript
// Dentro de cargarDatosCliente() .done():
var numCampos = rellenarRemitente(resp.data);
// 🔄 RECARGAR PRODUCTOS DEL CLIENTE
recargarProductosCliente(userId);
```

#### Cambio 2: Nueva función `recargarProductosCliente()` (líneas ~294-354)
**Funcionalidad:** Realiza AJAX POST a `merc_reload_productos` con:
- `shipment_id`: ID del envío (0 en creación)
- `shipper_id`: ID del cliente seleccionado
- `nonce`: Token de seguridad

**Resultado:** Actualiza todos los selectores `select[name="merc_producto_id[]"]` con opciones filtradas

---

### 3. **Backend PHP** - `class-client-autofill.php`

#### Cambio: Nonce unificado
**Antes:** `merc_get_client_data` (para obtener datos del cliente)
**Después:** `merc_form_nonce` (único para todo el formulario)

```php
$nonce = wp_create_nonce( 'merc_form_nonce' );  // Línea 53
check_ajax_referer( 'merc_form_nonce', 'nonce' ); // Línea 66
```

---

## 🔄 Flujo de Funcionamiento

```
1. Admin abre formulario de crear envío MERC Full Fitment
   ↓
   Selector de productos: [vacío ▼] ← sin cliente definido

2. Admin selecciona cliente "ITDEV"
   ↓
   cargarDatosCliente(userId) ejecuta:
     - AJAX: obtiene datos del cliente (nombre, teléfono, etc.)
     - rellenarRemitente() rellena los campos del remitente
     - recargarProductosCliente(userId) ejecuta AJAX para productos

3. Backend recibe AJAX en wp_ajax_merc_reload_productos
   ↓
   Filtra productos por: _merc_producto_cliente_asignado = shipper_id
   Retorna HTML con opciones de productos

4. JavaScript actualiza selector
   ↓
   Selector de productos: [Selecciona un producto ▼]
                          [Producto A - Stock: 5]
                          [Producto B - Stock: 3]
                          [Producto C - Stock: 2]
   
   (Solo productos de ITDEV)

5. Admin selecciona producto y guarda envío ✅
```

---

## 🛡️ Seguridad

- ✅ Nonce verificado en ambos handlers AJAX
- ✅ Validación de permisos en PHP
- ✅ Sanitización de inputs con `intval()`
- ✅ Meta key `_merc_producto_cliente_asignado` protegida

---

## 📊 Validación

**Errores de sintaxis:**
```bash
✅ PHP: No syntax errors
✅ JavaScript: No syntax errors
```

**Logging:**
- Backend: `error_log()` para debugging en `wp-content/debug.log`
- Frontend: `console.log()` con prefijo `[ProductReload]` para debugging en DevTools

---

## 🧪 Testing

**Antes de enviar a producción:**

1. Abrir formulario de crear envío Full Fitment
   - ✓ Selector productos debe estar VACÍO

2. Seleccionar cliente
   - ✓ Datos se rellenan (nombre, teléfono, etc.)
   - ✓ Selector se llena con productos del cliente
   - ✓ Console muestra logs `[ProductReload]` sin errores

3. Cambiar a otro cliente
   - ✓ Selector se actualiza con productos del nuevo cliente

4. En edición de envío existente
   - ✓ Productos ya asignados aparecen en selector
   - ✓ Cambiar cliente también actualiza selector dinámicamente

---

## 📝 Notas Técnicas

- **Meta key:** `_merc_producto_cliente_asignado` (almacena ID del cliente propietario)
- **Tipo de post:** `merc_producto`
- **Selector HTML:** `select[name="merc_producto_id[]"]`
- **Stock function:** `merc_get_product_stock()` (si existe)
- **AJAX action:** `merc_reload_productos`
- **Nonce field:** `merc_form_nonce`

---

## ✨ Mejoras Implementadas

1. ✅ Selector VACÍO en carga (no confunde al usuario)
2. ✅ Carga automática de productos cuando selecciona cliente
3. ✅ Solo muestra productos del cliente específico
4. ✅ Actualización dinámica sin reload de página
5. ✅ Logging completo para debugging
6. ✅ Notificaciones toast al usuario

