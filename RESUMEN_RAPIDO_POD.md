# 🎯 RESUMEN RÁPIDO: Flujos y Meta Keys

## 📌 QUICK REFERENCE - Meta Keys

```
ALMACENAMIENTO PRINCIPAL:

┌─────────────────────────────────────────────────────────┐
│ POST META (wp_postmeta)                                 │
│ ─────────────────────────────────────────────────────── │
│                                                         │
│  post_id=12345  (Shipment ID)                          │
│  ├─ meta_key: wpcargo-pod-image                        │
│  │  meta_value: "1001,1002,1003"                       │
│  │  (IDs de attachments separados por coma)            │
│  │                                                     │
│  ├─ meta_key: wpcargo-pod-signature                    │
│  │  meta_value: "1005"                                 │
│  │  (ID del attachment de firma)                       │
│  │                                                     │
│  ├─ meta_key: pod_payment_methods                      │
│  │  meta_value: '[{"metodo":"efectivo","monto":150}]' │
│  │  (JSON con métodos de pago)                         │
│  │                                                     │
│  ├─ meta_key: wpcargo_status                           │
│  │  meta_value: "ENTREGADO"                            │
│  │  (Estado actual del envío)                          │
│  │                                                     │
│  └─ meta_key: wpcargo_shipments_update                 │
│     meta_value: a:2:{i:0;a:6:{...}i:1;a:6:{...}}      │
│     (Array serializado con historial de cambios)       │
│                                                         │
└─────────────────────────────────────────────────────────┘
```

---

## 🔄 FLUJOS PRINCIPALES

### 1️⃣ UPLOAD DE IMÁGENES

```
UI (jQuery)
    │
    ├─ Click "ADD IMAGES"
    │
    ├─ Swal.fire() → Cámara | Archivos
    │
    ├─ mercPodSubirImagenes(files)
    │  └─ Valida MIME types, tamaño
    │
    └─► AJAX POST admin-ajax.php
        action: wpcpod_direct_upload_image
        │
        ├─ Parámetros:
        │  ├─ shipmentID = 12345
        │  ├─ nonce = "xyz"
        │  └─ files[] = [archivo1, archivo2, ...]
        │
        └─► SERVER (PHP)
            ├─ wp_verify_nonce()
            ├─ Valida MIME, tamaño, archivo
            ├─ wpcpod_handle_single_file_upload()
            │  ├─ wp_handle_upload()
            │  ├─ wp_insert_attachment()
            │  └─ wp_generate_attachment_metadata()
            │  Retorna: attachment_id
            ├─ get_post_meta('wpcargo-pod-image')
            ├─ array_merge() con nuevos IDs
            ├─ update_post_meta('wpcargo-pod-image', implode(',', ...))
            └─ wp_send_json_success({ html, count })
                │
                └─► UI
                    └─ Mostrar imágenes con botón X
```

**Meta Key Actualizada:**
```
wpcargo-pod-image = "1001,1002,1003"
```

---

### 2️⃣ MÉTODOS DE PAGO

```
UI (jQuery)
    │
    ├─ Click "Agregar método de pago"
    │
    ├─ Append .fila-metodo dinamicamente
    │  ├─ Select método (dropdown)
    │  ├─ Input monto
    │  ├─ Input imagen comprobante
    │  └─ Botón eliminar
    │
    ├─ JavaScript: event listeners
    │  ├─ .pay-method (change) → recalcular()
    │  ├─ .pay-amount (input) → recalcular()
    │  ├─ .pay-image (change) → compressImage()
    │  └─ Dinámicamente actualiza $('#pod_payment_methods')
    │
    ├─ recalcular() FUNCTION:
    │  ├─ Itera .fila-metodo
    │  ├─ Obtiene metodo, monto, archivo
    │  ├─ Si hay imagen: compressImage() → Base64
    │  ├─ Suma total ingresado
    │  ├─ Valida vs. monto esperado
    │  └─ $('#pod_payment_methods').val(JSON.stringify(arr))
    │     JSON: [{metodo, monto, imagen, imagen_nombre}, ...]
    │
    ├─ applyPOSDisplay():
    │  └─ Si hay método POS:
    │     monto_pos = total_base - otros_montos
    │     Recalcula automáticamente
    │
    └─► Form Submit
        └─► SERVER
            └─ wpcargo_pod_signed_load_action()
               ├─ Obtiene $_POST['pod_payment_methods'] (JSON)
               ├─ update_post_meta('pod_payment_methods', ...)
               └─ Guardado completo
```

**Meta Key Guardada:**
```json
pod_payment_methods = '[
  {
    "metodo": "efectivo",
    "monto": 150.00,
    "imagen": "data:image/jpeg;base64,...",
    "imagen_nombre": "comprobante.jpg"
  },
  {
    "metodo": "pos",
    "monto": 50.00
  }
]'
```

---

### 3️⃣ FIRMA DIGITAL

```
UI (Modal)
    │
    ├─ Canvas aparece
    ├─ Usuario dibuja firma
    ├─ SignaturePad.js captura
    │
    ├─ Sistema genera attachment
    │  ├─ Convierte a imagen
    │  ├─ Sube a biblioteca
    │  └─ Obtiene attachment_id (ej: 1005)
    │
    ├─ Guarda en input hidden
    │  └─ $('#__pod_signature').val(1005)
    │
    └─► Form Submit
        └─► SERVER
            └─ wpcargo_pod_signed_load_action()
               ├─ $signature_id = $_POST['__pod_signature']
               └─ update_post_meta('wpcargo-pod-signature', $signature_id)
```

**Meta Key Guardada:**
```
wpcargo-pod-signature = 1005
```

---

### 4️⃣ ESTADO + HISTORIAL

```
Form Submit (completo)
    │
    └─► SERVER
        └─ wpcargo_pod_signed_load_action()
           │
           ├─ Obtiene dato: shipment_status (del formulario)
           │
           ├─ Construye pod_history = {
           │    date: current_time('Y-m-d'),
           │    time: current_time('H:i:s'),
           │    updated-by: $current_user->ID,
           │    updated-name: $current_user->display_name,
           │    status: $shipment_status,
           │    remarks: ...,
           │    ... + otros campos de firma
           │  }
           │
           ├─ apply_filters('wpcargo_pod_current_history', ...)
           │
           ├─ get_post_meta('wpcargo_shipments_update')
           │  └─ Convierte de serialized a array
           │
           ├─ array_unshift($history, $pod_history)
           │  └─ Nuevo registro al INICIO del array
           │
           ├─ update_post_meta('wpcargo_shipments_update', $history)
           │
           └─ update_post_meta('wpcargo_status', $shipment_status)
```

**Meta Keys Guardadas:**
```
wpcargo_status = "ENTREGADO"

wpcargo_shipments_update = a:3:{
  i:0;a:6:{           // Registro más reciente (index 0)
    s:4:"date";s:10:"2024-04-24";
    s:4:"time";s:8:"14:30:45";
    s:10:"updated-by";i:5;
    s:12:"updated-name";s:10:"Juan López";
    s:6:"status";s:10:"ENTREGADO";
    s:7:"remarks";s:20:"Entregado al cliente";
  }
  i:1;a:6:{           // Registro anterior
    ...
  }
}
```

---

## 🎯 CASOS DE USO

### Caso 1: Envío Exitoso + Pago en Efectivo

```
Estado: EN RUTA → ENTREGADO
Meta Keys After:
├─ wpcargo-pod-image: "1001,1002"
├─ wpcargo-pod-signature: 1003
├─ pod_payment_methods: '[{"metodo":"efectivo","monto":200}]'
├─ wpcargo_status: "ENTREGADO"
└─ wpcargo_shipments_update: [
    { status: "ENTREGADO", date, time, remarks },
    { status: "EN RUTA", date, time, remarks }
  ]
```

### Caso 2: No Recibido sin Firma

```
Estado: EN RUTA → NO RECIBIDO
Meta Keys After:
├─ wpcargo-pod-image: "1004,1005,1006"  # Intentos fallidos
├─ wpcargo-pod-signature: ""             # Vacío (sin firma)
├─ pod_payment_methods: "[]"             # Sin pago
├─ wpcargo_status: "NO RECIBIDO"
└─ wpcargo_shipments_update: [
    {
      status: "NO RECIBIDO",
      remarks: "Cliente no disponible. Teléfono apagado.",
      date, time
    },
    { status: "EN RUTA", ... }
  ]
```

### Caso 3: Múltiples Métodos de Pago

```
Estado: EN RUTA → ENTREGADO
Meta Keys After:
├─ wpcargo-pod-image: "1001,1002,1003"
├─ wpcargo-pod-signature: 1004
├─ pod_payment_methods: '[
    {"metodo":"efectivo","monto":100},
    {"metodo":"pos","monto":75},
    {"metodo":"pago_merc","monto":25}
  ]'
├─ wpcargo_status: "ENTREGADO"
└─ Historial: [...]
```

---

## 🔌 AJAX ACTIONS DISPONIBLES

### En wpcargo-pod-addons

```
POST /wp-admin/admin-ajax.php

├─ action=wpcpod_direct_upload_image (nopriv)
│  └─ Parámetros: shipmentID, nonce, files[]
│     Retorna: success, html, count
│
├─ action=wpcpod_delete_image (nopriv)
│  └─ Parámetros: shipmentID, attchID
│     Retorna: status, message
│
└─ action=wpcpod_save_attachment (nopriv)
   └─ Parámetros: shipmentID, attachments[]
      Retorna: HTML
```

### En merc-table-customizer

```
POST /wp-admin/admin-ajax.php

├─ action=merc_actualizar_estado_rapido (require auth)
│  └─ Parámetros: shipment_id, nuevo_estado, observaciones, nonce
│     Retorna: message, nuevo_estado, observaciones
│     Actualiza: wpcargo_status, wpcargo_shipments_update
│
└─ action=merc_pod_client_debug (NO IMPLEMENTADO)
   └─ Propuesto pero sin handler actual
```

---

## 🛠️ PARA NUEVA ESTRUCTURA "NO RECIBIDO"

### Opción 1: Usar estructura existente (RECOMENDADO)

```php
// En wpcargo_shipments_update[0]:
$pod_history = array(
    'status' => 'NO RECIBIDO',
    'date' => '2024-04-24',
    'time' => '14:30:00',
    'updated-name' => 'Juan López',
    'remarks' => 'NO RECIBIDO - Cliente no disponible',
    'intentos_fallidos' => 2,
    'proxima_fecha' => '2024-04-25'
);
```

### Opción 2: Meta key especializada

```php
// Agregar cuando estado = "NO RECIBIDO"
'wpcargo_no_recibido_intentos' = 2
'wpcargo_no_recibido_proxima_fecha' = '2024-04-25'
'wpcargo_no_recibido_razon' = 'Cliente no disponible'
'wpcargo_no_recibido_imagenes' = '1004,1005'
```

### Opción 3: Extender pod_payment_methods

```json
{
  "estado_especial": "NO_RECIBIDO",
  "razones": ["Cliente no disponible", "Puerta cerrada"],
  "imagenes_intento": [1004, 1005],
  "proxima_cita": "2024-04-25 10:00",
  "intentos": 2,
  "no_cobrar": true
}
```

---

## 📊 DIAGRAMA: FLUJO GLOBAL DE DATOS

```
┌─────────────────────────────────────────────────────────────┐
│                        FORMULARIO POD                        │
│  (Modal con firma, imágenes, métodos de pago, estado)       │
└──────────────────────────┬──────────────────────────────────┘
                           │
                    jQuery - Form Submit
                           │
        ┌──────────────────┼──────────────────┐
        │                  │                  │
        ▼                  ▼                  ▼
    ┌────────┐       ┌───────────┐      ┌──────────┐
    │ Firma  │       │ Imágenes  │      │ Métodos  │
    │(AJAX)  │       │ (AJAX)    │      │  Pago    │
    └──┬─────┘       └─────┬─────┘      └──┬───────┘
       │                   │                │
       └───────────────────┼────────────────┘
                           │
        ┌──────────────────▼──────────────────┐
        │   Form Submit (POST)                │
        │   wpcargo_pod_signed_load_action()  │
        └──────────────────┬──────────────────┘
                           │
        ┌──────────────────▼──────────────────┐
        │      Validación & Sanitización      │
        │  - Nonce check                      │
        │  - Sanitize text/textarea           │
        │  - Validar IDs                      │
        └──────────────────┬──────────────────┘
                           │
    ┌──────────────────────┼──────────────────────┐
    │                      │                      │
    ▼                      ▼                      ▼
┌──────────┐       ┌────────────────┐      ┌────────────┐
│ Guardar  │       │ Actualizar     │      │Registrar   │
│  Firma   │       │  Estado +      │      │ Historial  │
│ (Meta)   │       │  Imágenes      │      │  (Array)   │
│          │       │  (Meta)        │      │            │
└──┬───────┘       └────┬───────────┘      └─────┬──────┘
   │                    │                        │
   └────────────────────┼────────────────────────┘
                        │
         ┌──────────────▼──────────────────┐
         │  Base de Datos (WordPress)     │
         │  wp_postmeta                   │
         │  ├─ wpcargo-pod-signature      │
         │  ├─ wpcargo-pod-image          │
         │  ├─ pod_payment_methods        │
         │  ├─ wpcargo_status             │
         │  └─ wpcargo_shipments_update   │
         └────────────────────────────────┘
                        │
         ┌──────────────▼──────────────────┐
         │  Hooks & Notificaciones        │
         │  - Email notification          │
         │  - SMS notification            │
         │  - Filtros personalizados      │
         └────────────────────────────────┘
```

---

## ✨ TIPS IMPORTANTES

1. **IMÁGENES:** Son attachment IDs en WordPress, accesibles via `wp_get_attachment_url()`
2. **JSON:** `pod_payment_methods` es JSON puro, usar `json_decode()` para procesar
3. **HISTORIAL:** Array de cambios, usar `maybe_unserialize()` para recuperar
4. **ESTADO:** El campo `wpcargo_status` es el punto de sincronización principal
5. **OBSERVACIONES:** Se guardan en `remarks` del historial, NO en meta key separada
6. **NO RECIBIDO:** Ya existe en merc-table-customizer, solo falta integración visual en POD

---

## 🔗 FICHEROS CLAVE

```
├─ wpcargo-pod-addons/
│  ├─ wpcargo-pod.php (LOADER)
│  ├─ admin/includes/dashboard.php (FUNCIONES AJAX)
│  ├─ admin/includes/functions.php (FUNCIONES HELPERS)
│  ├─ templates/wpc-pod-sign.tpl.php (FORMULARIO + JS)
│  └─ classes/wpc-pod-function-ajax.php (CLASES AJAX)
│
└─ merc-table-customizer/
   ├─ admin/classes/class-table-ui.php (ESTADOS UI)
   ├─ admin/classes/class-table-ajax.php (AJAX HANDLERS)
   └─ admin/classes/class-shipment-table.php (TABLA)
```

---

**Generado:** 2024-04-24  
**Versión:** 1.0  
**Plugin:** wpcargo-pod-addons v5.0.0
