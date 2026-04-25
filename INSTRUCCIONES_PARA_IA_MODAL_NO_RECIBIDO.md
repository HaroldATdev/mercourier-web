# 🎯 INSTRUCCIONES PARA IA: Implementar Modal "NO RECIBIDO" en WPCargo-POD

## 📋 CONTEXTO DEL PROYECTO

**Proyecto:** MERCourier - Plataforma de Courier basada en WordPress + WPCargo  
**Plugin Objetivo:** `wpcargo-pod-addons` (Proof of Delivery)  
**Objetivo:** Crear un modal especializado para el estado "NO RECIBIDO" que permita:
1. Adjuntar fotos del intento de entrega
2. Agregar observaciones/descripción
3. Procesar métodos de pago (si aplica)
4. Diferenciarse del flujo normal de entrega

---

## 🔍 ESTADO ACTUAL DEL SISTEMA

### A. Sistema de Guardado de Imágenes (Existente)

**Meta Key:** `wpcargo-pod-image`  
**Formato:** String con IDs de attachments separados por coma  
**Ejemplo:** `"1001,1002,1003"`

**Flujo Actual:**
```
jQuery → Validar MIME → AJAX POST (wpcpod_direct_upload_image)
   ↓
wp_handle_upload() → wp_insert_attachment() → wp_generate_attachment_metadata()
   ↓
Obtener meta actual → array_merge() con nuevos IDs
   ↓
update_post_meta('wpcargo-pod-image', implode(',', $ids))
```

**Ubicación del Código:**
- Template: `/wp-content/plugins/wpcargo-pod-addons/templates/wpc-pod-sign.tpl.php` (línea ~60-130)
- AJAX Handler: `/wp-content/plugins/wpcargo-pod-addons/admin/includes/dashboard.php`

---

### B. Sistema de Observaciones (Existente)

**Meta Key:** `wpcargo_shipments_update`  
**Formato:** Array serializado con historial de cambios  
**Estructura:**
```php
$shipment_update = array(
    'date' => '2024-04-24',
    'time' => '14:30:00',
    'updated-by' => $user_id,
    'updated-name' => 'Juan López',
    'status' => 'ENTREGADO',
    'remarks' => 'Observación general del estado',
    // + otros 30+ campos de datos
);
```

**Cómo se guarda:**
- En formulario POD, hay campos dinámicos generados por hooks
- El campo 'remarks' viene de custom fields (plugin `wpcargo-custom-field-addons`)
- Se guarda automáticamente en el array de historial

---

### C. Sistema de Métodos de Pago (Existente)

**Meta Key:** `pod_payment_methods`  
**Formato:** JSON con array de métodos  
**Ejemplo:**
```json
[
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
]
```

**Métodos Disponibles:**
- `efectivo` → Pago a motorizado
- `pago_marca` → Pago a Marca
- `pago_merc` → Pago a MERC (integración con merc-finance)
- `pos` → POS Terminal

**Flujo Actual:**
1. Usuario agrega fila de pago (botón "➕ Agregar método de pago")
2. Selecciona método en dropdown
3. Ingresa monto
4. Sube imagen de comprobante (opcional para efectivo, obligatorio para POS)
5. JavaScript recolecta todo en JSON
6. Form submit → update_post_meta()

---

## 🚀 REQUERIMIENTO: ESTADO "NO RECIBIDO"

El estado **"NO RECIBIDO"** ya existe en el sistema pero está incompleto:

✅ **Ya implementado:**
- Definido en tabla de estados (merc-table-customizer)
- Se puede cambiar desde la tabla administrativa
- Registra observaciones en historial automáticamente

❌ **Falta implementar:**
- Modal visual independiente en formulario POD
- Estructura clara de datos para este estado específico
- Interfaz simplificada (sin firma obligatoria)
- Lógica de integración con merc-finance

---

## 💡 ARQUITECTURA PROPUESTA

### Opción Recomendada: SIMPLE (Usar Sistema Existente)

**Ventaja:** Cero cambios en BD, reutiliza hooks existentes, compatible con todo

#### Estructura de Datos para "NO RECIBIDO"

```php
// Meta keys que se guardan automáticamente:

'wpcargo_status'          = "NO RECIBIDO"

'wpcargo_shipments_update' = serialize([
    'date'           => '2024-04-24',
    'time'           => '14:30:00',
    'updated-by'     => $user_id,
    'status'         => 'NO RECIBIDO',
    'remarks'        => 'Cliente no disponible - Teléfono apagado',
    'razon_devolucion' => 'Cliente no presente',  // Custom field
    'proxima_fecha'  => '2024-04-25',             // Custom field
    'motorizado_id'  => 5,
    'motorizado_name' => 'Juan López'
])

'wpcargo-pod-image'       = "1001,1002,1003"     // Fotos del intento

'pod_payment_methods'     = JSON (si hay cobro)  // Si aplica pago

'wpcargo-pod-signature'   = NULL                 // NO requiere firma
```

---

## 🛠️ GUÍA DE IMPLEMENTACIÓN (PASO A PASO)

### PASO 1: Habilitar Campo de Razón/Observaciones

**Archivo:** `/wp-content/plugins/wpcargo-pod-addons/templates/wpc-pod-sign.tpl.php`

**Ubicación:** Alrededor de línea 100-120 (dentro del `<form>` POD)

**Código a Agregar:**
```php
<?php
// Después del selector de estado, agregar campos específicos para "NO RECIBIDO"
$shipment_status = isset($_POST['status']) ? $_POST['status'] : 
                   get_post_meta($get_sid, 'wpcargo_status', true);
?>

<!-- CAMPOS DINÁMICOS PARA NO RECIBIDO -->
<div id="no-recibido-section" style="display: none;" class="col-md-12 mb-4">
    <div style="background: #fff3cd; padding: 15px; border-radius: 5px; border-left: 4px solid #ff6b6b;">
        <h5>⚠️ Información del Intento No Recibido</h5>
        
        <div class="form-group">
            <label for="razon-devolucion"><strong>Razón por la que no recibió</strong></label>
            <select id="razon-devolucion" name="razon_devolucion" class="form-control">
                <option value="">-- Seleccionar razón --</option>
                <option value="cliente_no_disponible">Cliente no disponible</option>
                <option value="direccion_incorrecta">Dirección incorrecta</option>
                <option value="cliente_no_presente">Cliente no presente</option>
                <option value="rechazo_cliente">Cliente rechazó el paquete</option>
                <option value="paquete_dañado">Paquete dañado</option>
                <option value="otra">Otra razón</option>
            </select>
        </div>

        <div class="form-group">
            <label for="proxima-fecha"><strong>Próxima fecha de entrega propuesta</strong></label>
            <input type="date" id="proxima-fecha" name="proxima_fecha" class="form-control">
        </div>

        <div class="form-group">
            <label for="observaciones-no-recibido"><strong>Observaciones detalladas</strong></label>
            <textarea id="observaciones-no-recibido" name="remarks" class="form-control" 
                      rows="4" placeholder="Describe qué pasó en el intento de entrega..."></textarea>
        </div>
    </div>
</div>

<script>
jQuery(document).ready(function($) {
    // Mostrar/ocultar sección según estado seleccionado
    $('select[name="status"]').on('change', function() {
        const status = $(this).val();
        if (status === 'NO RECIBIDO') {
            $('#no-recibido-section').slideDown();
            // Ocultar firma obligatoria
            $('#pod-pop-up .signature-required').hide();
        } else {
            $('#no-recibido-section').slideUp();
            $('#pod-pop-up .signature-required').show();
        }
    }).trigger('change');
});
</script>
```

---

### PASO 2: Modificar Validación de Firma

**Archivo:** `/wp-content/plugins/wpcargo-pod-addons/templates/wpc-pod-sign.tpl.php`

**Ubicación:** Alrededor de línea 200-220 (al final del formulario)

**Cambio:**
```php
// ANTES:
<input type="submit" class="delivered-btn btn btn-success" 
       name="submit" value="Update" disabled>

// DESPUÉS:
<input type="submit" class="delivered-btn btn btn-success" 
       id="submit-pod" name="submit" value="Update" disabled>

<script>
jQuery(document).ready(function($) {
    // Validar según estado
    function validatePODForm() {
        const status = $('select[name="status"]').val();
        const signaturaID = $('#__pod_signature').val();
        const hasImages = $('#wpcargo-pod-images .gallery-thumb').length > 0;
        
        if (status === 'NO RECIBIDO') {
            // Para NO RECIBIDO: requiere observación y al menos 1 foto (opcional mejora)
            const hasObservations = $('#observaciones-no-recibido').val().trim().length > 0;
            return hasObservations; // Foto opcional para NO RECIBIDO
        } else {
            // Para ENTREGADO: requiere firma obligatoria
            return signaturaID && signaturaID.length > 0;
        }
    }
    
    $('input[name="status"], #observaciones-no-recibido, #__pod_signature').on('change input', function() {
        $('#submit-pod').prop('disabled', !validatePODForm());
    }).trigger('change');
});
</script>
```

---

### PASO 3: Procesar Datos en Backend

**Archivo:** `/wp-content/plugins/wpcargo-pod-addons/admin/includes/dashboard.php`

**Ubicación:** Función `wpcargo_pod_signed_load_action()` (alrededor de línea 150-200)

**Agregar antes de `update_post_meta('wpcargo_shipments_update', ...)`:**

```php
// Al procesar el formulario POD
if (isset($_POST['submit']) && $_POST['submit'] === 'Update') {
    
    $shipment_status = isset($_POST['status']) ? sanitize_text_field($_POST['status']) : '';
    
    // Si es NO RECIBIDO, guardar datos específicos
    if ($shipment_status === 'NO RECIBIDO') {
        
        $razon_no_recibido = isset($_POST['razon_devolucion']) 
            ? sanitize_text_field($_POST['razon_devolucion']) 
            : '';
        
        $proxima_fecha = isset($_POST['proxima_fecha']) 
            ? sanitize_text_field($_POST['proxima_fecha']) 
            : '';
        
        // Guardar datos específicos
        if ($razon_no_recibido) {
            update_post_meta($shipment_id, 'wpcargo_no_recibido_razon', $razon_no_recibido);
        }
        
        if ($proxima_fecha) {
            update_post_meta($shipment_id, 'wpcargo_no_recibido_proxima_fecha', $proxima_fecha);
        }
        
        // Incrementar contador de intentos
        $intentos = (int) get_post_meta($shipment_id, 'wpcargo_no_recibido_intentos', true);
        update_post_meta($shipment_id, 'wpcargo_no_recibido_intentos', $intentos + 1);
        
        // El historial se guarda automáticamente con remarks
        // El flujo normal se encarga de eso
    }
}
```

---

### PASO 4: Integración con Métodos de Pago (OPCIONAL)

Si deseas que "NO RECIBIDO" también permita cobro:

**En `wpc-pod-sign.tpl.php` (línea ~180):**

```php
<?php
// Si es NO RECIBIDO, permitir opcionalmente cobro si hay un "precio de intento fallido"
$es_no_recibido = $shipment_status === 'NO RECIBIDO';
$permite_cobro = !$es_no_recibido; // Por defecto NO se cobra en NO RECIBIDO

// Pero si quieres permitir cobro condicional:
if ($es_no_recibido) {
    $intenta_reintento_pago = get_post_meta($shipment_id, 'wpcargo_cobrar_reintento', true);
    $permite_cobro = ($intenta_reintento_pago === 'yes');
}
?>

<?php if ($permite_cobro): ?>
    <!-- Mostrar sección de pagos -->
    <div id="payment-area">
        <!-- ... código de métodos de pago ... -->
    </div>
<?php else: ?>
    <input type="hidden" name="pod_payment_methods" value="[]">
<?php endif; ?>
```

---

## 📊 FLUJOS COMPLETAMENTE DOCUMENTADOS

### Flujo 1: Upload de Imágenes (Existente - Reutilizar)

```
jQuery: Click "ADD IMAGES"
    ↓
Swal.fire: ¿Cámara o Archivo?
    ↓
mercPodSubirImagenes()
    ├─ Validar MIME types
    ├─ AJAX: action=wpcpod_direct_upload_image
    │   ├─ Servidor: wp_verify_nonce()
    │   ├─ wp_handle_upload()
    │   ├─ wp_insert_attachment()
    │   └─ get/update_post_meta('wpcargo-pod-image')
    │
    └─ UI: Mostrar imágenes con x delete

GUARDADO: meta_key='wpcargo-pod-image', meta_value='1001,1002,1003'
```

### Flujo 2: Guardar Observaciones (Nuevo para NO RECIBIDO)

```
Usuario completa campos en #no-recibido-section
    ├─ razon_devolucion (select)
    ├─ proxima_fecha (date)
    └─ remarks (textarea)
    
Form submit
    ↓
Backend: wpcargo_pod_signed_load_action()
    ├─ update_post_meta('wpcargo_no_recibido_razon', $valor)
    ├─ update_post_meta('wpcargo_no_recibido_proxima_fecha', $valor)
    ├─ update_post_meta('wpcargo_no_recibido_intentos', $intentos + 1)
    └─ [Automático] update_post_meta('wpcargo_shipments_update', $historial)
       └─ El historial incluye remarks automáticamente
```

### Flujo 3: Métodos de Pago (Existente - Reutilizar)

```
"NO RECIBIDO" + permite_cobro = true
    ↓
UI: Mostrar sección de pagos
    ├─ Agregar método (efectivo, pago_marca, pago_merc, pos)
    ├─ Subir comprobante
    └─ Validar monto = total esperado
    
Form submit
    ↓
update_post_meta('pod_payment_methods', $json)

GUARDADO: meta_key='pod_payment_methods'
```

---

## 🔌 HOOKS Y EXTENSIONES

### Hooks Existentes a Reutilizar

```php
// Antes de procesar formulario
do_action('wpcpod_before_sign_popup_form');

// Después de header del popup
do_action('wpcpod_after_popup_header', $shipment_id);

// Después de cargar el formulario completo
do_action('wpcpod_after_sign_popup_form');

// Después de guardar (implementar si falta):
do_action('wpcargo_pod_no_recibido_saved', $shipment_id, $razon, $proxima_fecha);
```

### Hook a Crear (OPCIONAL)

```php
// En dashboard.php, después de procesar NO RECIBIDO:
do_action('wpcargo_pod_no_recibido_saved', [
    'shipment_id'  => $shipment_id,
    'razon'        => $razon_no_recibido,
    'proxima_fecha' => $proxima_fecha,
    'intentos'     => $intentos + 1,
    'images'       => $images_ids,
    'motorizado'   => $current_user->ID
]);

// Permitiría que otros plugins (ej: merc-finance) reaccionen
```

---

## 📁 ARCHIVOS A MODIFICAR

| Archivo | Línea | Cambio | Tipo |
|---------|-------|--------|------|
| `wpc-pod-sign.tpl.php` | 100-120 | Agregar div #no-recibido-section | HTML + JavaScript |
| `wpc-pod-sign.tpl.php` | 180 | Mostrar/ocultar sección pagos | Condicional |
| `wpc-pod-sign.tpl.php` | 200-220 | Validar según estado | JavaScript |
| `dashboard.php` | 170-200 | Procesar datos NO RECIBIDO | Backend |
| `wpc-pod-sign-header.tpl.php` | - | Actualizar título para NO RECIBIDO (OPCIONAL) | HTML |

---

## 🧪 TESTING CHECKLIST

- [ ] Cambiar estado a "NO RECIBIDO" muestra campos especiales
- [ ] Cambiar estado a otro oculta campos especiales
- [ ] Razón de devolución se guarda en meta key
- [ ] Próxima fecha se guarda en meta key
- [ ] Observaciones aparecen en historial
- [ ] Contador de intentos incrementa en cada NO RECIBIDO
- [ ] Fotos se suben normalmente (reutiliza flujo existente)
- [ ] Form submit solo activa con observación (para NO RECIBIDO)
- [ ] Métodos de pago ocultos por defecto en NO RECIBIDO
- [ ] Sin requerimiento de firma para NO RECIBIDO
- [ ] Historial muestra fecha, hora, motorizado, estado y remarks

---

## 📚 REFERENCIAS

**Documentos Generados (Ver en raíz del proyecto):**
- `ANALISIS_WPCARGO_POD.md` → Análisis técnico completo (445+ líneas)
- `RESUMEN_RAPIDO_POD.md` → Quick reference de meta keys y flujos
- `GUIA_IMPLEMENTAR_NO_RECIBIDO.md` → Guía con 3 opciones arquitectónicas

**Rutas Clave:**
```
/wp-content/plugins/wpcargo-pod-addons/
├── templates/
│   ├── wpc-pod-sign.tpl.php          (modificar)
│   ├── wpc-pod-sign-header.tpl.php   (opcional)
│   └── wpc-pod-results.tpl.php       (para mostrar resultados)
├── admin/
│   └── includes/
│       └── dashboard.php              (modificar backend)
└── classes/
    └── wpc-pod-function-ajax.php      (reutilizar AJAX existente)
```

---

## 💬 NOTAS IMPORTANTES

1. **No modificar estructura de BD:** Todo se guarda en post meta, sin cambios en tablas
2. **Reutilizar AJAX existente:** Las funciones para imágenes ya están implementadas
3. **Observaciones automáticas:** El historial se actualiza automáticamente con remarks
4. **Métodos de pago opcionales:** NO RECIBIDO puede no tener cobro (defecto)
5. **Firma NO obligatoria:** Validar que solo ENTREGADO requiere firma, NO RECIBIDO no

---

## 🎯 PRÓXIMOS PASOS DESPUÉS DE IMPLEMENTACIÓN

1. **Integración con merc-finance:** 
   - Hook `wpcargo_pod_no_recibido_saved` → Crear registro de intento fallido
   - Decidir si carga pendiente de cobro o anula

2. **Reporte de NO RECIBIDOS:**
   - Crear vista de "Envíos pendientes" por motorizado
   - Mostrar próximas fechas propuestas

3. **Automatización:**
   - Si intento #3 falla → Cambiar a ANULADO automáticamente
   - Notificar a cliente sobre próxima fecha

4. **Dashboard:**
   - Widget de "Entregas incompletas" en admin

---

**Documento Generado:** 2024-04-24  
**Para pasar a:** Otra IA o desarrollador  
**Tipo:** Especificaciones Técnicas + Código Listo
