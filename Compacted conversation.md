Compacted conversation


 

Searched codebase for "pod shipment image upload photo attachment wpcargo", 18 results




 

Searched codebase for "merc finance payment method cargo costo estado shipment", 24 results




 


 

Searched for text `historial|popup|modal` (`**/wp-content/plugins/merc-table-customizer/**/*.php`), 20 results



Basándome en mi análisis, te proporciono las indicaciones técnicas para que otra IA pueda implementar la nueva estructura:

---

## 📋 Instrucciones para Implementar Modal de "No Recibido"

### **1. Lógica Actual de Imágenes (POD)**

**Ubicación:** dashboard.php

| Función | Descripción |
|---------|-------------|
| `wpcpod_direct_upload_image()` | Sube imágenes via AJAX (líneas 223-285) |
| `wpcpod_delete_image()` | Elimina imágenes (líneas 170-197) |
| Meta key | `wpcargo-pod-image` (guarda IDs separados por coma) |

**Cómo funciona:**
```php
// Guardar imágenes - dashboard.php línea 264
$saved_images = get_post_meta($shipmentID, 'wpcargo-pod-image', true);
$explode_images = !empty($saved_images) ? explode(',', $saved_images) : array();
$set_attachments = array_unique(array_merge($uploaded_ids, array_filter($explode_images)));
update_post_meta($shipmentID, 'wpcargo-pod-image', implode(',', $set_attachments));
```

---

### **2. Lógica de Métodos de Pago (conectado a Finanzas)**

**Ubicación:** wpc-pod-sign.tpl.php (líneas 72-160)

| Campo Meta | Descripción |
|------------|-------------|
| `pod_payment_methods` | JSON con métodos de pago [{metodo, monto, imagen}] |
| `wpcargo_costo_envio` | Costo del envío |
| `wpcargo_total_cobrar` | Total a cobrar |
| `wpcargo_cobrado_por_motorizado` | Monto cobrado por motorizado |

**Métodos disponibles:**
- `efectivo` → Pago a motorizado
- `pago_marca` → Pago a Marca  
- `pago_merc` → Pago a MERC
- `pos` → POS

---

### **3. Hook de Finanzas para "No Recibido"**

**Ubicación:** hooks.php (líneas 1-30)

```php
add_action('updated_post_meta', function($meta_id, $post_id, $meta_key, $meta_value) {
    if ($meta_key !== 'wpcargo_status') return;
    if ($meta_value !== 'No recibido') return;
    
    // Crea cargo automático de S/. 5.00
    merc_crear_cargo_no_recibido($post_id, $customer_id, 5.00);
}, 10, 4);
```

---

### **4. Estructura Sugerida para el Nuevo Modal**

#### **A) Campos requeridos:**

| Campo | Tipo | Meta Key | Notas |
|-------|------|----------|-------|
| Fotos | File input | `merc_no_recibido_fotos` | Similar a POD |
| Observaciones | Textarea | `merc_no_recibido_observaciones` | Texto libre |
| Método de pago | Select | `merc_no_recibido_metodo` | efectivo/pago_marca/pago_merc/pos |
| Monto | Number | `merc_no_recibido_monto` | Decimal |
| Comprobante | File | `merc_no_recibido_comprobante` | Imagen |

#### **B) Dónde agregar el modal:**

1. **Para historial de envíos (admin):**
   - Buscar en `merc-table-customizer/admin/classes/class-table-ui.php`
   - Donde se renderiza el popup de historial (buscar `historial` o `select`)

2. **Para lista de entrega con estado "No recibido":**
   - En `merc-table-customizer/admin/classes/class-shipment-table.php`
   - Agregar botón/acción cuando `status === 'No recibido'`

#### **C) AJAX handler sugerido:**

```php
// Nuevo endpoint AJAX
add_action('wp_ajax_merc_save_no_recibido', 'merc_save_no_recibido_data');
add_action('wp_ajax_nopriv_merc_save_no_recibido', 'merc_save_no_recibido_data');

function merc_save_no_recibido_data() {
    $shipment_id = intval($_POST['shipment_id']);
    $observaciones = sanitize_textarea_field($_POST['observaciones']);
    $metodo = sanitize_text_field($_POST['metodo']);
    $monto = floatval($_POST['monto']);
    
    // Guardar observaciones
    update_post_meta($shipment_id, 'merc_no_recibido_observaciones', $observaciones);
    
    // Guardar método de pago (JSON)
    $pago_data = [
        'metodo' => $metodo,
        'monto' => $monto,
        'fecha' => current_time('Y-m-d H:i:s'),
        'user_id' => get_current_user_id()
    ];
    update_post_meta($shipment_id, 'merc_no_recibido_pago', json_encode($pago_data));
    
    // Guardar imágenes (usar lógica existente de POD)
    // ... código similar a wpcpod_direct_upload_image
    
    wp_send_json_success(['message' => 'Datos guardados correctamente']);
}
```

---

### **5. Archivos clave a modificar:**

| Archivo | Acción |
|---------|--------|
| `merc-table-customizer/admin/classes/class-table-ui.php` | Agregar modal HTML |
| `merc-table-customizer/admin/classes/class-shipment-table.php` | Agregar trigger para "No recibido" |
| Nuevo archivo o functions.php de merc-table-customizer | AJAX handler |
| `merc-finance/includes/hooks.php` | Conectar con sistema de finanzas |

---

¿Necesitas que profundice en algún punto específico o que genere código de ejemplo para alguno de estos componentes?You've used 60% of your session rate limit. Your session rate limit will reset on April 24 at 6:50 PM. [Learn More](https://aka.ms/github-copilot-rate-limit-error)