# 📋 Análisis Completo: Plugin WPCargo-POD-Addons

## 📌 Resumen Ejecutivo

El plugin **wpcargo-pod-addons** implementa un sistema completo de Proof of Delivery que permite:
- ✅ Captura de firmas digitales
- ✅ Carga de imágenes de entrega
- ✅ Registro de métodos de pago con comprobantes
- ✅ Historial de cambios de estado con observaciones

---

## 🔍 1. FUNCIONES AJAX DEFINIDAS

### 1.1 Gestión de Imágenes de Entrega

#### **`wpcpod_delete_image()`** 
**Ubicación:** `/admin/includes/dashboard.php` línea 186

**Acciones AJAX Registradas:**
```php
add_action('wp_ajax_wpcpod_delete_image', 'wpcpod_delete_image');
add_action('wp_ajax_nopriv_wpcpod_delete_image', 'wpcpod_delete_image');
```

**Parámetros Requeridos:**
- `shipmentID` (integer) - ID del envío
- `attchID` (integer) - ID del attachment a eliminar

**Lógica:**
```
1. Obtiene meta key: wpcargo-pod-image (string con IDs separados por coma)
2. Explota la cadena en array
3. Busca y elimina el attachment ID especificado
4. Re-guarda el array imploded en la meta key
5. Retorna JSON con status y mensaje
```

**Respuesta:**
```json
{
  "status": true|false,
  "message": "Attachment successfully removed in...",
  "shipmentID": 123,
  "attchID": 456
}
```

---

#### **`wpcpod_direct_upload_image()`**
**Ubicación:** `/admin/includes/dashboard.php` línea 210

**Acciones AJAX Registradas:**
```php
add_action('wp_ajax_wpcpod_direct_upload_image', 'wpcpod_direct_upload_image');
add_action('wp_ajax_nopriv_wpcpod_direct_upload_image', 'wpcpod_direct_upload_image');
```

**Parámetros Requeridos:**
- `shipmentID` (integer) - ID del envío
- `nonce` (string) - Verificación de seguridad 'wpcpod_upload_image'
- `files[]` (multipart/form-data) - Archivos a subir

**Validaciones Implementadas:**
```
✓ Nonce válido (wp_verify_nonce)
✓ MIME types permitidos: image/png, image/jpeg, image/gif, image/svg+xml
✓ Tamaño máximo: 10 MB por archivo
✓ Archivo temporal debe existir y ser legible
✓ Timeout aumentado a 300 segundos (@set_time_limit)
```

**Función Auxiliar:**
`wpcpod_handle_single_file_upload($file_array, $valid_mime_types)`
- Valida errores de upload (UPLOAD_ERR_OK)
- Utiliza `wp_handle_upload()` de WordPress
- Crea entrada en biblioteca de medios con `wp_insert_attachment()`
- Genera metadatos con `wp_generate_attachment_metadata()`
- Retorna el ID del attachment creado

**Flujo de Guardado:**
```
1. Procesa cada archivo
2. Crea attachment en biblioteca
3. Obtiene meta actual: wpcargo-pod-image
4. Explota en array y filtra valores vacíos
5. Fusiona con nuevos IDs (array_merge + array_unique)
6. Re-guarda meta key: wpcargo-pod-image
```

**Respuesta de Éxito:**
```json
{
  "success": true,
  "message": "Imágenes subidas correctamente",
  "html": "<div class='gallery-thumb' data-id='...'><div class='single-img'>...</div><span class='delete-attachment'>x</span></div>",
  "count": 2
}
```

---

#### **`wpcpod_save_attachment()`**
**Ubicación:** `/admin/includes/dashboard.php` línea 379

**Acciones AJAX Registradas:**
```php
add_action('wp_ajax_wpcpod_save_attachment', 'wpcpod_save_attachment');
add_action('wp_ajax_nopriv_wpcpod_save_attachment', 'wpcpod_save_attachment');
```

**Parámetros:**
- `shipmentID` (integer)
- `attachments` (array de IDs)

---

### 1.2 Métodos de Pago

#### **`pod_payment_methods` - Campo Hidden (NO es AJAX)**
**Ubicación:** `/templates/wpc-pod-sign.tpl.php` línea 174

**Descripción:**
```html
<input type="hidden" name="pod_payment_methods" id="pod_payment_methods" value="[]">
```

**Estructura JSON Almacenada:**
```json
[
  {
    "metodo": "efectivo|pago_marca|pago_merc|pos",
    "monto": 150.50,
    "imagen": "data:image/jpeg;base64,...[comprimida a 70% quality]",
    "imagen_nombre": "comprobante_pago.jpg"
  },
  {
    "metodo": "pos",
    "monto": 100.00
  }
]
```

**Métodos de Pago Disponibles:**
| Código | Etiqueta | Uso |
|--------|----------|-----|
| `efectivo` | Pago a motorizado | Dinero en efectivo |
| `pago_marca` | Pago a Marca | Pago a nombre de marca |
| `pago_merc` | Pago a MERC | Pago a MERCourier |
| `pos` | POS | Transacción por terminal |

**JavaScript de Control (línea 360-650):**
- Función `compressImage()` - Comprime imágenes a máximo 800x800 con 70% calidad
- Función `recalcular()` - Valida totales y actualiza meta key
- Función `updateSubmitState()` - Habilita/deshabilita botón según balance

**Lógica de POS Especial:**
- Si hay método POS, el monto se ajusta automáticamente
- El monto de POS = Total base - otros montos
- Se recalcula cuando cambian montos

---

### 1.3 Debug (Sin Implementación Actual)

#### **`merc_pod_client_debug()` - NO IMPLEMENTADO**
**Ubicación:** `/templates/wpc-pod-sign.tpl.php` línea 200

**Estado:** Función JavaScript existe pero NO tiene handler AJAX registrado

```javascript
window.sendDebug = function(payload) {
    jQuery.post(AJAXHANDLER_GLOBAL_POD, 
        Object.assign({ action: 'merc_pod_client_debug' }, payload)
    );
};
```

**Nota:** Actualmente no hace nada en el servidor. Sería para debugging del cliente.

---

## 💾 2. META KEYS PARA ALMACENAMIENTO

### Meta Keys Principales

| Meta Key | Descripción | Tipo | Tamaño | Quién Guarda | Cuándo |
|----------|-------------|------|--------|------------|---------|
| `wpcargo-pod-image` | IDs de imágenes POD | String | Variable | `wpcpod_direct_upload_image()` | Upload directo |
| `wpcargo-pod-signature` | ID de firma digital | Integer | 4 bytes | `wpcargo_pod_signed_load_action()` | Submit formulario |
| `pod_payment_methods` | JSON de pagos | JSON String | Variable | Formulario POD | Submit formulario |
| `wpcargo_status` | Estado actual | String | 50 bytes | Múltiples | Cambio de estado |
| `wpcargo_status_anterior` | Estado previo | String | 50 bytes | `merc_actualizar_estado()` | Transición a "LISTO..." |
| `wpcargo_shipments_update` | Historial completo | Serialized Array | Variable | `wpcargo_pod_signed_load_action()` | Cada actualización |
| `wpcargo_driver` | ID motorizado | Integer | 4 bytes | Múltiples | Asignación |

### Estructura de `wpcargo_shipments_update`

```php
// Array serializado, cada elemento es un registro de cambio
$history[] = [
    'date'          => '2024-04-24',        // Formato: Y-m-d
    'time'          => '14:30:45',          // Formato: H:i:s
    'updated-by'    => 1,                   // Usuario ID
    'updated-name'  => 'Juan Pérez',        // Display name
    'status'        => 'ENTREGADO',         // Nuevo estado
    'remarks'       => 'Entregado exitoso', // Observaciones
    // ... + todos los campos de signature_field_list()
];
```

**Acceso:**
```php
// Obtener historial
$historial = maybe_unserialize(get_post_meta($shipment_id, 'wpcargo_shipments_update', true));

// Obtener último cambio (índice 0)
$ultimo_cambio = !empty($historial) ? $historial[0] : [];

// Guardar nuevo cambio al inicio
array_unshift($historial, $nuevo_registro);
update_post_meta($shipment_id, 'wpcargo_shipments_update', $historial);
```

---

## 📊 3. FLUJO COMPLETO DE GUARDADO

### 3.1 Flujo de Guardado de Imágenes

```
┌─────────────────────────────────────┐
│ Usuario hace click en "ADD IMAGES"  │
└────────────┬────────────────────────┘
             │
             ▼
┌─────────────────────────────────────┐
│ Swal.fire() muestra opciones        │
│ - Tomar foto (cámara)               │
│ - Subir imagen (archivos)           │
└────────────┬────────────────────────┘
             │
    ┌────────┴────────┐
    │                 │
    ▼                 ▼
Cámara         Selector archivos
    │                 │
    └────────┬────────┘
             │
             ▼
┌─────────────────────────────────────┐
│ mercPodSubirImagenes(files)         │
│ - Valida extensiones (MIME types)   │
│ - Prepara FormData                  │
└────────────┬────────────────────────┘
             │
             ▼
┌─────────────────────────────────────┐
│ AJAX POST wpcpod_direct_upload_image│
│ Parámetros:                         │
│ - shipmentID                        │
│ - nonce                             │
│ - files[]                           │
└────────────┬────────────────────────┘
             │
             ▼ (server)
┌─────────────────────────────────────┐
│ wpcpod_handle_single_file_upload()  │
│ Para cada archivo:                  │
│ 1. Valida MIME y tamaño             │
│ 2. wp_handle_upload()               │
│ 3. wp_insert_attachment()           │
│ 4. wp_generate_attachment_metadata()│
│ Retorna: attachment_id              │
└────────────┬────────────────────────┘
             │
             ▼
┌─────────────────────────────────────┐
│ Actualizar Meta Key:                │
│ wpcargo-pod-image                   │
│                                     │
│ Viejo: "123,456"                    │
│ Nuevo: "123,456,789"                │
│ (array_unique + array_merge)        │
└────────────┬────────────────────────┘
             │
             ▼
┌─────────────────────────────────────┐
│ Respuesta JSON Success              │
│ - html con previsualizaciones       │
│ - count de imágenes nuevas          │
└────────────┬────────────────────────┘
             │
             ▼ (client)
┌─────────────────────────────────────┐
│ Mostrar imágenes en #wpcargo-pod-img│
│ Con botón "x" para eliminar c/una   │
└─────────────────────────────────────┘
```

### 3.2 Flujo de Métodos de Pago

```
┌──────────────────────────────────────┐
│ Mostrar campo "Total a recibir"      │
│ según modo de pago                   │
└────────────┬─────────────────────────┘
             │
             ▼
┌──────────────────────────────────────┐
│ Usuario hace click "Agregar método"  │
│ (+) Agregar método de pago           │
└────────────┬─────────────────────────┘
             │
             ▼
┌──────────────────────────────────────┐
│ Se agrega .fila-metodo dinámicamente │
│ - Selector de método (dropdown)      │
│ - Input monto                        │
│ - Input imagen comprobante           │
│ - Botón eliminar                     │
└────────────┬─────────────────────────┘
             │
             ▼
┌──────────────────────────────────────┐
│ Usuario completa datos               │
│ - Selecciona método                  │
│ - Ingresa monto                      │
│ - Sube comprobante                   │
└────────────┬─────────────────────────┘
             │
             ▼
┌──────────────────────────────────────┐
│ JavaScript recalcular()              │
│ - Suma totales                       │
│ - Valida vs. monto esperado          │
│ - Comprime imagen a Base64           │
│ - Actualiza $('#pod_payment_methods')│
└────────────┬─────────────────────────┘
             │
             ▼
┌──────────────────────────────────────┐
│ Mostrar:                             │
│ - Total ingresado                    │
│ - Diferencia (si falta)              │
│ - Estado del botón "Update"          │
└────────────┬─────────────────────────┘
             │
             ▼
┌──────────────────────────────────────┐
│ Usuario hace click "Update"          │
│ Se ejecuta validación final          │
│ - Total debe coincidir               │
│ - Comprimir imágenes nuevamente      │
│ - Armar array JSON final             │
└────────────┬─────────────────────────┘
             │
             ▼
┌──────────────────────────────────────┐
│ HTMLFormElement.prototype.submit()   │
│ POST del formulario completo         │
└────────────┬─────────────────────────┘
             │
             ▼ (server)
┌──────────────────────────────────────┐
│ wpcargo_pod_signed_load_action()     │
│ Actualiza meta key:                  │
│ pod_payment_methods = JSON_STRING    │
│                                      │
│ También procesa:                     │
│ - wpcargo-pod-signature              │
│ - wpcargo_status                     │
│ - wpcargo_shipments_update (historial)
│ - Campos custom                      │
└─────────────────────────────────────┘
```

### 3.3 Flujo de Firma

```
┌──────────────────────────────────────┐
│ Modal de firma se abre               │
│ (datos del envío)                    │
└────────────┬─────────────────────────┘
             │
             ▼
┌──────────────────────────────────────┐
│ Usuario dibuja firma en canvas       │
│ SignaturePad.js                      │
└────────────┬─────────────────────────┘
             │
             ▼
┌──────────────────────────────────────┐
│ Sistema captura firma                │
│ Genera attachment (imagen)           │
│ Obtiene attachment_id                │
└────────────┬─────────────────────────┘
             │
             ▼ (en #__pod_signature)
┌──────────────────────────────────────┐
│ Guarda attachment_id en input hidden │
│ value = "attachment_id"              │
└────────────┬─────────────────────────┘
             │
             ▼ (al submit)
┌──────────────────────────────────────┐
│ wpcargo_pod_signed_load_action()     │
│ Actualiza meta key:                  │
│ wpcargo-pod-signature = attachment_id│
│ (update_post_meta)                   │
└──────────────────────────────────────┘
```

---

## 🎯 4. HOOKS DE INTEGRACIÓN

### Hooks en wpcargo-pod-addons

**Antes/Después del Popup:**
```php
do_action( 'wpcpod_before_sign_popup_form' );
do_action( 'wpcpod_after_sign_popup_form' );
```

**Estructura del Popup:**
```php
do_action( 'wpcpod_before_popup_header' );
do_action( 'wpcpod_after_popup_header', $get_sid );
do_action( 'wpcpod_before_upload_container', $get_sid );
do_action( 'wpcpod_after_upload_container', $get_sid );
do_action( 'wpcpod_before_status_container', $get_sid );
do_action( 'wpcpod_after_status_container', $get_sid );
```

**Guardado de Datos:**
```php
// Antes de guardar meta keys
do_action( 'wpcargo_extra_pod_saving', $shipment_id, $form_data );

// Después de guardar estado (notificaciones)
do_action( 'wpcargo_extra_send_email_notification', $shipment_id, $shipment_status );
do_action( 'wpc_add_sms_notification', $shipment_id );
```

**Filtros:**
```php
// Filtrar lista de campos de firma
apply_filters( 'wpcpod_signature_field_list', $history_fields );

// Filtrar historial antes de guardar
apply_filters( 'wpcargo_pod_current_history', $pod_history );

// Filtrar estado de firma
apply_filters( 'pod_table_header_sign_label', __('Sign', 'wpcargo-pod') );
apply_filters( 'pod_table_header_signed_label', __('Signed', 'wpcargo-pod') );
apply_filters( 'pod_modal_title', __('Proof of Delivery', 'wpcargo-pod') );
```

---

## 🔗 5. INTEGRACIÓN CON OTROS PLUGINS

### 5.1 Integración con merc-table-customizer

**DIRECTA:**
- ✅ `wpcargo-pod-image` se lee en [class-table-ui.php:1449](https://repo)
- ✅ Estados POD se usan en visualización de tabla
- ✅ "NO RECIBIDO" está registrado como estado válido

**VÍA META KEYS:**
- ✅ `wpcargo_status` - es el punto de sincronización principal
- ✅ `wpcargo_shipments_update` - historial de cambios
- ✅ Observaciones se guardan en `remarks` del historial

### 5.2 Integración con merc-finance

**Hallazgo:** NO HAY INTEGRACIÓN DIRECTA en wpcargo-pod-addons

**Qué podría conectar:**
- Meta key `pod_payment_methods` (JSON con métodos y montos)
- Meta key `wpcargo_status` (para filtrar envíos pagados)
- Meta key `wpcargo_total_cobrar` (monto total a cobrar)

**Recomendación:**
Para integrar con merc-finance, se necesita:
1. Hook AJAX para procesar métodos de pago
2. Validar métodos disponibles según configuración de caja
3. Sincronizar movimientos financieros con cambio de estado

---

## 🚀 6. ESTADO "NO RECIBIDO" - ESTRUCTURA EXISTENTE

### En merc-table-customizer

**Línea 381 (class-table-ui.php):**
```javascript
const estadosMotorizadoDespuesBase = [
    'EN RUTA', 
    'NO CONTESTA', 
    'NO RECIBIDO',  // ← YA PRESENTE
    'ENTREGADO', 
    'REPROGRAMADO', 
    'ANULADO'
];
```

**Línea 737 (class-table-ui.php):**
```javascript
const estadosAvanzados = [
    'EN BASE MERCOURIER',
    'RECEPCIONADO',
    'LISTO PARA SALIR',
    'NO CONTESTA',
    'EN RUTA',
    'NO RECIBIDO',  // ← YA PRESENTE
    'ENTREGADO',
    'REPROGRAMADO',
    'ANULADO'
];
```

### Cómo se usa actualmente

1. **En tabla:** Se puede cambiar estado a "NO RECIBIDO" manualmente
2. **Con observaciones:** Se abre modal para agregar observaciones
3. **Historial:** Se registra en `wpcargo_shipments_update`
4. **Email:** Se envía notificación

---

## 📈 7. FLUJO COMPLETO: ENVÍO CON ESTADO "NO RECIBIDO"

```
┌────────────────────────────────────────────────┐
│ 1. MOTORIZADO INTENTA ENTREGA (EN RUTA)       │
│    - Abre modal POD                            │
│    - Puede cambiar estado en selector          │
│    - O usar tabla merc-table-customizer        │
└───────────────┬────────────────────────────────┘
                │
                ▼
┌────────────────────────────────────────────────┐
│ 2. SELECCIONA "NO RECIBIDO"                   │
│    - Se abre modal de confirmación             │
│    - Pide observaciones (opcional)             │
└───────────────┬────────────────────────────────┘
                │
                ▼
┌────────────────────────────────────────────────┐
│ 3. INGRESA OBSERVACIONES                       │
│    - "Cliente no disponible"                   │
│    - "Teléfono apagado"                        │
│    - Etc.                                      │
└───────────────┬────────────────────────────────┘
                │
                ▼
┌────────────────────────────────────────────────┐
│ 4. CONFIRMA CAMBIO DE ESTADO                   │
│    AJAX: merc_actualizar_estado_rapido         │
│    POST /wp-admin/admin-ajax.php               │
│    Datos:                                      │
│    - shipment_id = 123                         │
│    - nuevo_estado = "NO RECIBIDO"              │
│    - observaciones = "..."                     │
│    - nonce = "..."                             │
└───────────────┬────────────────────────────────┘
                │
                ▼ (server - merc-table-customizer)
┌────────────────────────────────────────────────┐
│ 5. PROCESA EN ajax_actualizar_estado()         │
│    - Valida nonce                              │
│    - Obtiene estado anterior                   │
│    - Guarda en wpcargo_status_anterior         │
│    - Actualiza wpcargo_status = "NO RECIBIDO"  │
│    - Llama merc_sync_service_cost_by_status()  │
│    - Arma registro de historial:               │
│      {                                         │
│        status: "NO RECIBIDO",                  │
│        date: "2024-04-24",                     │
│        time: "14:30:00",                       │
│        updated-name: "Juan López",             │
│        remarks: "Cliente no disponible"        │
│      }                                         │
│    - Inserta al inicio del array               │
│    - Guarda en wpcargo_shipments_update        │
└───────────────┬────────────────────────────────┘
                │
                ▼
┌────────────────────────────────────────────────┐
│ 6. RESPUESTA EXITOSA                           │
│    JSON:                                       │
│    {                                           │
│      message: "Estado actualizado correc.",    │
│      nuevo_estado: "NO RECIBIDO",              │
│      observaciones: "Cliente no disponible"    │
│    }                                           │
└────────────────────────────────────────────────┘
```

---

## 📱 8. ESTRUCTURA ACTUAL DE DATOS POR ENVÍO

### Ejemplo real de meta keys para envío con "NO RECIBIDO"

```php
// Envío ID: 12345

// Meta keys:
'wpcargo_status' = 'NO RECIBIDO'
'wpcargo_status_anterior' = 'EN RUTA'

'wpcargo_driver' = 5  // Motorizado ID

'wpcargo-pod-image' = '1001,1002,1003'  // 3 imágenes de intento

'wpcargo-pod-signature' = 0  // Sin firma (no se entregó)

'pod_payment_methods' = '[]'  // Sin pagos registrados

'wpcargo_shipments_update' = [
    0 => [
        'date' => '2024-04-24',
        'time' => '14:30:45',
        'status' => 'NO RECIBIDO',
        'updated-by' => 5,
        'updated-name' => 'Juan López',
        'remarks' => 'Cliente no disponible. Teléfono apagado.'
    ],
    1 => [
        'date' => '2024-04-24',
        'time' => '10:15:30',
        'status' => 'EN RUTA',
        'updated-by' => 5,
        'updated-name' => 'Juan López',
        'remarks' => 'Iniciando entrega'
    ],
    // ... más histórico
]

'wpcargo_costo_producto' = 150.00
'wpcargo_costo_envio' = 50.00
'wpcargo_total_cobrar' = 0.00  // NO COBRAR este envío

'payment_wpcargo_mode_field' = 'NO COBRAR'
```

---

## 🎨 9. INTERFAZ VISUAL

### Modal de Firma POD

```
╔════════════════════════════════════════════╗
║        PROOF OF DELIVERY                   ║
╠════════════════════════════════════════════╣
║                                            ║
║  📋 Shipment: #12345                       ║
║  🚚 Driver: Juan López                     ║
║  📍 Recipient: María García                ║
║                                            ║
║  ┌──────────────────────────────────────┐  ║
║  │  FIRMA (Canvas)                      │  ║
║  │  [Canvas para firmar]                │  ║
║  └──────────────────────────────────────┘  ║
║                                            ║
║  📸 ADD IMAGES  [Swal2 opciones]          ║
║  ┌──────────────────────────────────────┐  ║
║  │ [Imagen 1] [Imagen 2] [Imagen 3]     │  ║
║  │    (x)         (x)        (x)         │  ║
║  └──────────────────────────────────────┘  ║
║                                            ║
║  💰 Total a recibir: S/. 200.00           ║
║                                            ║
║  📋 Métodos de Pago                        ║
║  ┌──────────────────────────────────────┐  ║
║  │ Método: [Pago a motorizado ▼]        │  ║
║  │ Monto:  [150.50]                     │  ║
║  │ Comprobante: [Subir imagen]          │  ║
║  │ [Preview]                            │  ║
║  │ [Eliminar]                           │  ║
║  │                                      │  ║
║  │ Método: [POS ▼]                      │  ║
║  │ Monto:  [49.50]                      │  ║
║  │ [Eliminar]                           │  ║
║  └──────────────────────────────────────┘  ║
║  ➕ Agregar método de pago                 ║
║                                            ║
║  Total ingresado: S/. 200.00               ║
║                                            ║
║  Estado: [ENTREGADO ▼]                     ║
║                                            ║
║  ┌────────────────┐   ┌──────────────────┐ ║
║  │ ✓ Update       │   │ Cancel           │ ║
║  └────────────────┘   └──────────────────┘ ║
╚════════════════════════════════════════════╝
```

---

## 🔐 10. CONSIDERACIONES DE SEGURIDAD

### Validaciones Implementadas

✅ **NONCE Validation**
```php
wp_verify_nonce($_POST['nonce'], 'wpcpod_upload_image')
check_ajax_referer('merc_actualizar_estado', 'nonce')
```

✅ **Sanitización**
```php
sanitize_text_field()      // Para textos simples
sanitize_textarea_field()  // Para campos largos
intval()                   // Para números
```

✅ **Validación de Archivos**
```php
file_exists()              // Verifica que archivo existe
is_readable()              // Verifica permiso de lectura
in_array($mime_type)       // Whitelist de MIME types
filesize < 10MB            // Límite de tamaño
```

✅ **Validación de Envío**
```php
get_post() 
check post_type === 'wpcargo_shipment'
```

---

## ✅ 11. RESUMEN: DÓNDE SE GUARDAN LOS DATOS

### Durante la Firma (POD Signature)

| Datos | Meta Key | Tipo | Método |
|-------|----------|------|--------|
| Imágenes de entrega | `wpcargo-pod-image` | String (IDs CSV) | AJAX `wpcpod_direct_upload_image` |
| Firma digital | `wpcargo-pod-signature` | Integer (attachment ID) | Form submit + AJAX |
| Métodos de pago | `pod_payment_methods` | JSON String | Form submit |
| Nuevo estado | `wpcargo_status` | String | Form submit |
| Historial | `wpcargo_shipments_update` | Serialized Array | Form submit |

### Cómo se comunican con AJAX

```
Cliente (JS)
    ↓
[AJAX POST /wp-admin/admin-ajax.php]
    ↓
    action=wpcpod_direct_upload_image
    shipmentID=123
    nonce=xyz123
    files[]=image.jpg
    ↓
Server (PHP)
    ↓
[wp_handle_upload()]
[wp_insert_attachment()]
[update_post_meta(id, 'wpcargo-pod-image', ...)]
    ↓
[JSON Response]
    ↓
Cliente (JS)
    ↓
[Update UI con imágenes]
```

---

## 🎯 12. PARA IMPLEMENTAR "NO RECIBIDO" ESPECIAL

### Estructura Recomendada

```php
// 1. Crear meta key especial si necesita datos diferentes
'wpcargo_no_recibido_data' = [
    'intentos' => 2,
    'razon' => 'Cliente no disponible',
    'imagenes_intento' => [1001, 1002],
    'fecha_proximo_intento' => '2024-04-25'
]

// 2. O usar campo remarks del historial (actual)
'wpcargo_shipments_update[0].remarks' = 'NO RECIBIDO - Cliente no disponible'

// 3. Meta keys involucradas:
'wpcargo_status' = 'NO RECIBIDO'
'wpcargo_status_anterior' = 'EN RUTA'
'wpcargo-pod-image' = '1001,1002'  // Imágenes del intento
'pod_payment_methods' = '[]'  // Sin pago
'wpcargo_shipments_update' = [
    { status: 'NO RECIBIDO', remarks: '...' },
    { status: 'EN RUTA', remarks: '...' }
]
```

---

## 📞 Contacto y Soporte

Para preguntas sobre:
- **Imágenes POD:** `wpcpod_direct_upload_image()` en `/admin/includes/dashboard.php`
- **Métodos de Pago:** `pod_payment_methods` en `/templates/wpc-pod-sign.tpl.php`
- **Estados:** `merc_actualizar_estado()` en merc-table-customizer

---

**Análisis generado:** 2024-04-24  
**Plugin:** wpcargo-pod-addons v5.0.0  
**Base WordPress:** WPCargo Suite
