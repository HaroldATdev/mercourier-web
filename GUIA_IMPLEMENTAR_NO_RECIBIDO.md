# 🚀 GUÍA: Implementar Estado Especial "NO RECIBIDO"

## 📋 Contexto Actual

El estado **"NO RECIBIDO"** ya existe en el sistema:
- ✅ Definido en merc-table-customizer (`class-table-ui.php`)
- ✅ Se puede cambiar desde la tabla
- ✅ Registra observaciones en historial
- ❌ Falta integración visual en formulario POD
- ❌ No tiene estructura de datos especializada

---

## 💡 Propuesta: Estructura Recomendada

### Opción A: SIMPLE (Usar Sistema Existente - RECOMENDADO)

**Ventaja:** Requiere CERO modificaciones, usa hooks existentes

```php
// En wpcargo_pod_signed_load_action() - dashboard.php
// Detectar si el estado es "NO RECIBIDO"

if ( $shipment_status === 'NO RECIBIDO' ) {
    // Ya se guarda automáticamente en:
    // 1. wpcargo_status = "NO RECIBIDO"
    // 2. wpcargo_shipments_update[0]['status'] = "NO RECIBIDO"
    // 3. wpcargo_shipments_update[0]['remarks'] = observaciones (del modal)
    // 4. wpcargo-pod-image = IDs de intentos fallidos
    
    // NO HAY CAMBIOS NECESARIOS
    // El sistema ya maneja todo correctamente
}
```

**Qué pasa automáticamente:**
```
Usuario selecciona "NO RECIBIDO" en formulario POD
    ↓
submit formulario
    ↓
wpcargo_pod_signed_load_action() procesa
    ↓
Guarda automáticamente:
├─ wpcargo_status = "NO RECIBIDO"
├─ wpcargo_shipments_update[0].status = "NO RECIBIDO"
├─ wpcargo_shipments_update[0].remarks = observaciones
└─ wpcargo-pod-image = IDs de imágenes del intento
```

**Para Habilitar en POD:**
Solo agregar "NO RECIBIDO" al select de estados en `wpc-pod-sign.tpl.php`:

```php
// Línea ~100 en wpc-pod-sign.tpl.php
<select name="status" class="form-control">
    <option value="ENTREGADO">ENTREGADO</option>
    <option value="NO RECIBIDO">NO RECIBIDO</option>
    <option value="REPROGRAMADO">REPROGRAMADO</option>
    <option value="ANULADO">ANULADO</option>
</select>
```

---

### Opción B: INTERMEDIA (Datos Especializados Opcionales)

**Para capturar info adicional:**

```php
// Agregar al hook do_action('wpcargo_extra_pod_saving', ...)

add_action('wpcargo_extra_pod_saving', function($shipment_id, $form_data) {
    $shipment_status = wpcpod_find_metakey($form_data, 'status');
    
    if ($shipment_status && $shipment_status['value'] === 'NO RECIBIDO') {
        // Datos adicionales para NO RECIBIDO
        $razon_no_recibido = wpcpod_find_metakey($form_data, 'razon_no_recibido');
        $proxima_fecha = wpcpod_find_metakey($form_data, 'proxima_fecha_entrega');
        
        if ($razon_no_recibido) {
            update_post_meta($shipment_id, 'wpcargo_no_recibido_razon', 
                sanitize_text_field($razon_no_recibido['value']));
        }
        
        if ($proxima_fecha) {
            update_post_meta($shipment_id, 'wpcargo_no_recibido_proxima_fecha', 
                sanitize_text_field($proxima_fecha['value']));
        }
        
        // Contador de intentos
        $intentos_actual = (int)get_post_meta($shipment_id, 'wpcargo_no_recibido_intentos', true);
        update_post_meta($shipment_id, 'wpcargo_no_recibido_intentos', $intentos_actual + 1);
    }
}, 10, 2);
```

**Meta Keys Agregadas:**
```
wpcargo_no_recibido_razon = "Cliente no disponible"
wpcargo_no_recibido_proxima_fecha = "2024-04-25"
wpcargo_no_recibido_intentos = 2
```

---

### Opción C: COMPLETA (Estructura Personalizada)

**Para máximo control y datos especializados:**

```php
// Crear meta key JSON especializada
$no_recibido_data = [
    'status' => 'NO RECIBIDO',
    'intentos' => 2,
    'razones' => [
        'Cliente no disponible',
        'Teléfono apagado'
    ],
    'imagenes_intento' => [1004, 1005],
    'proxima_cita' => '2024-04-25 10:00:00',
    'motorizado_asignado' => 'Juan López',
    'motorizado_id' => 5,
    'observaciones' => 'Sin contacto al cliente después de 2 intentos',
    'no_cobrar' => true,
    'reenvio_automatico' => false
];

update_post_meta($shipment_id, 'wpcargo_no_recibido_datos', $no_recibido_data);
```

**Ventaja:** Datos centralizados en una meta key JSON

---

## 🎯 IMPLEMENTACIÓN PASO A PASO

### PASO 1: Habilitar "NO RECIBIDO" en Formulario POD

**Archivo:** `/wp-content/plugins/wpcargo-pod-addons/templates/wpc-pod-sign.tpl.php`

**Buscar:** Sección de selector de estado (alrededor de línea 75-95)

**Cambio:**
```php
// ANTES (probablemente en hook wpcpod_after_status_container)
<?php foreach( $signature_fields as $metakey => $fieldinfo ): ?>
    <!-- Mostrar solo campos configurados -->
<?php endforeach; ?>

// DESPUÉS - Agregar selector de estado:
<div class="col-md-6 mb-4">
    <p>
        <label><?php _e('Order Status', 'wpcargo-pod'); ?></label><br/>
        <select name="status" id="pod-status-select" class="form-control">
            <option value="">-- Select Status --</option>
            <option value="ENTREGADO">ENTREGADO</option>
            <option value="NO RECIBIDO">NO RECIBIDO</option>
            <option value="REPROGRAMADO">REPROGRAMADO</option>
            <option value="ANULADO">ANULADO</option>
        </select>
    </p>
</div>

<!-- Si selecciona NO RECIBIDO, mostrar campos adicionales -->
<div id="no-recibido-fields" style="display:none;" class="col-md-12">
    <label>Razón de no recepción:</label>
    <select name="razon_no_recibido" class="form-control">
        <option value="">-- Seleccionar razón --</option>
        <option value="Cliente no disponible">Cliente no disponible</option>
        <option value="Teléfono apagado">Teléfono apagado</option>
        <option value="Dirección incorrecta">Dirección incorrecta</option>
        <option value="Rechazó el envío">Rechazó el envío</option>
        <option value="Puerta cerrada">Puerta cerrada</option>
    </select>
    
    <label style="margin-top:10px;">Próxima fecha de entrega:</label>
    <input type="date" name="proxima_fecha_entrega" class="form-control">
</div>

<script>
$(document).ready(function() {
    $('#pod-status-select').change(function() {
        if ($(this).val() === 'NO RECIBIDO') {
            $('#no-recibido-fields').show();
        } else {
            $('#no-recibido-fields').hide();
        }
    });
});
</script>
```

---

### PASO 2: Procesar Datos Especiales en PHP

**Archivo:** `/wp-content/plugins/wpcargo-pod-addons/admin/includes/dashboard.php`

**Ubicación:** En función `wpcargo_pod_signed_load_action()` alrededor de línea 150

**Agregar código:**
```php
// Después de guardar wpcargo_status (línea ~145)
update_post_meta($shipment_id, 'wpcargo_status', $shipment_status);

// AGREGAR ESTO:
// ============================================
// Procesar datos especiales para NO RECIBIDO
// ============================================
if ( $shipment_status === 'NO RECIBIDO' ) {
    
    // Obtener razón de no recepción si existe
    $razon_no_recibido = wpcpod_find_metakey($form_data, 'razon_no_recibido');
    if ($razon_no_recibido && !empty($razon_no_recibido['value'])) {
        update_post_meta(
            $shipment_id,
            'wpcargo_no_recibido_razon',
            sanitize_text_field($razon_no_recibido['value'])
        );
    }
    
    // Obtener próxima fecha de entrega
    $proxima_fecha = wpcpod_find_metakey($form_data, 'proxima_fecha_entrega');
    if ($proxima_fecha && !empty($proxima_fecha['value'])) {
        update_post_meta(
            $shipment_id,
            'wpcargo_no_recibido_proxima_fecha',
            sanitize_text_field($proxima_fecha['value'])
        );
    }
    
    // Incrementar contador de intentos fallidos
    $intentos_actual = (int) get_post_meta($shipment_id, 'wpcargo_no_recibido_intentos', true);
    $intentos_nuevo = $intentos_actual + 1;
    update_post_meta($shipment_id, 'wpcargo_no_recibido_intentos', $intentos_nuevo);
    
    // Si llegó a 3 intentos, marcar para revisión
    if ($intentos_nuevo >= 3) {
        update_post_meta($shipment_id, 'wpcargo_no_recibido_requiere_revision', 1);
        
        // Notificar a administrador
        do_action('wpcargo_no_recibido_revision_requerida', $shipment_id, $intentos_nuevo);
    }
}
```

---

### PASO 3: Mostrar Datos en Admin (Opcional)

**Archivo:** Crear archivo `/wp-content/plugins/wpcargo-pod-addons/admin/templates/wpc-pod-no-recibido-box.php`

```php
<?php
if (!defined('ABSPATH')) exit;

$shipment_id = get_the_ID();
$razon = get_post_meta($shipment_id, 'wpcargo_no_recibido_razon', true);
$proxima_fecha = get_post_meta($shipment_id, 'wpcargo_no_recibido_proxima_fecha', true);
$intentos = get_post_meta($shipment_id, 'wpcargo_no_recibido_intentos', true);
$requiere_revision = get_post_meta($shipment_id, 'wpcargo_no_recibido_requiere_revision', true);

if (!$razon && !$proxima_fecha) {
    return;
}
?>

<div style="padding: 15px; background: #fff3cd; border-left: 4px solid #ffc107; border-radius: 4px; margin: 15px 0;">
    <h3 style="margin-top: 0; color: #856404;">⚠️ NO RECIBIDO - Detalles</h3>
    
    <?php if ($intentos): ?>
        <p><strong>Intentos Fallidos:</strong> <?php echo $intentos; ?>/3</p>
    <?php endif; ?>
    
    <?php if ($razon): ?>
        <p><strong>Razón:</strong> <?php echo esc_html($razon); ?></p>
    <?php endif; ?>
    
    <?php if ($proxima_fecha): ?>
        <p><strong>Próxima Entrega:</strong> <?php echo esc_html($proxima_fecha); ?></p>
    <?php endif; ?>
    
    <?php if ($requiere_revision): ?>
        <p style="color: #d9534f; font-weight: bold;">
            ⚠️ Requiere revisión - 3 intentos fallidos
        </p>
    <?php endif; ?>
</div>

<?php
```

**Registrar Meta Box en admin:**
```php
add_action('add_meta_boxes', function() {
    add_meta_box(
        'wpc-pod-no-recibido-box',
        'Detalles No Recibido',
        function($post) {
            include(WPCARGO_POD_PATH . 'admin/templates/wpc-pod-no-recibido-box.php');
        },
        'wpcargo_shipment',
        'normal',
        'high'
    );
});
```

---

## 📊 ESTRUCTURA DE DATOS RESULTANTE

### Ejemplo Completo: Envío NO RECIBIDO

```
Post ID: 12345 (Shipment)
└─ POST META:
   ├─ wpcargo_status
   │  └─ "NO RECIBIDO"
   │
   ├─ wpcargo_status_anterior
   │  └─ "EN RUTA"
   │
   ├─ wpcargo-pod-image
   │  └─ "1001,1002"  (Imágenes de intentos fallidos)
   │
   ├─ wpcargo-pod-signature
   │  └─ ""  (Vacío, no se entregó)
   │
   ├─ pod_payment_methods
   │  └─ "[]"  (Sin pagos)
   │
   ├─ wpcargo_no_recibido_razon
   │  └─ "Cliente no disponible"
   │
   ├─ wpcargo_no_recibido_proxima_fecha
   │  └─ "2024-04-25"
   │
   ├─ wpcargo_no_recibido_intentos
   │  └─ 2
   │
   ├─ wpcargo_no_recibido_requiere_revision
   │  └─ 0  (Si intentos < 3)
   │
   └─ wpcargo_shipments_update
      └─ a:2:{
        i:0;a:6:{
          s:4:"date";s:10:"2024-04-24";
          s:4:"time";s:8:"14:35:00";
          s:10:"updated-by";i:5;
          s:12:"updated-name";s:10:"Juan López";
          s:6:"status";s:11:"NO RECIBIDO";
          s:7:"remarks";s:30:"Cliente no disponible al intento";
        }
        i:1;a:6:{...}  // Historial previo
```

---

## 🔄 FLUJO FINAL: USUARIO → BD

```
MOTORIZADO EN APP:
    │
    1️⃣ Modal POD abierto
        ├─ Intenta entrega → toma fotos
        ├─ Selecciona estado: "NO RECIBIDO"
        ├─ Se muestran campos adicionales:
        │  ├─ Razón (dropdown)
        │  └─ Próxima fecha (date picker)
        │
    2️⃣ Completa datos
        ├─ Razón: "Cliente no disponible"
        ├─ Próxima: "2024-04-25"
        │
    3️⃣ Click "Update"
        └─ Form submit (POST)
            │
            ▼
SERVIDOR (PHP):
    │
    1️⃣ wpcargo_pod_signed_load_action() procesa
        │
    2️⃣ Guarda automáticamente:
        ├─ wpcargo_status = "NO RECIBIDO"
        ├─ wpcargo_shipments_update[0]:
        │  ├─ status = "NO RECIBIDO"
        │  ├─ remarks = "..."
        │  ├─ date = "2024-04-24"
        │  └─ updated-by = 5
        │
    3️⃣ Hook: wpcargo_extra_pod_saving()
        ├─ Obtiene razon_no_recibido
        ├─ Obtiene proxima_fecha_entrega
        ├─ Guarda:
        │  ├─ wpcargo_no_recibido_razon
        │  ├─ wpcargo_no_recibido_proxima_fecha
        │  ├─ wpcargo_no_recibido_intentos += 1
        │  └─ Si intentos >= 3:
        │     wpcargo_no_recibido_requiere_revision = 1
        │
    4️⃣ Notificaciones
        ├─ Email a cliente
        ├─ SMS (opcional)
        └─ Hook: wpcargo_no_recibido_revision_requerida()
            │
RESULTADO EN BD:
    │
    7 meta keys nuevas + estado actualizado
```

---

## 🧪 TESTING CHECKLIST

- [ ] Formulario POD muestra selector de estado
- [ ] Al seleccionar "NO RECIBIDO", aparecen campos adicionales
- [ ] Datos se guardan en meta keys correctas
- [ ] Historial registra cambio de estado
- [ ] Contador de intentos incrementa
- [ ] Alerta aparece en admin cuando intentos >= 3
- [ ] Tabla merc-table-customizer refleja estado
- [ ] Email de notificación se envía
- [ ] Próxima fecha aparece en detalles del envío

---

## 🚀 DEPLOYMENT

1. **Backup:** DB y archivos del plugin POD
2. **Develop:** En entorno de staging
3. **Test:** Casos mencionados en checklist
4. **Deploy:** A producción en horario de bajo tráfico
5. **Monitor:** Error logs durante 24 horas

---

## ✅ RECOMENDACIÓN FINAL

**USAR OPCIÓN A (SIMPLE)** porque:
- ✅ Cero cambios en BD schema
- ✅ Usa hooks existentes
- ✅ Compatible con merc-table-customizer
- ✅ Fácil de revertir
- ✅ Observaciones se guardan en historial

**Si necesita info adicional:** Usar Opción B (meta keys opcionales)

**Solo Opción C si:** Requiere control muy específico sobre NO RECIBIDO

---

**Documento generado:** 2024-04-24  
**Versión:** 1.0  
**Estado:** Listo para implementar
