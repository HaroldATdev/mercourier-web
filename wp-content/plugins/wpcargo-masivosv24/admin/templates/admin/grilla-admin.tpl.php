<?php if ( ! defined('ABSPATH') ) exit; ?>
<div class="wrap">
<h1><span class="dashicons dashicons-grid-view" style="font-size:24px;vertical-align:middle;margin-right:6px"></span>Carga Masiva — Vista Administrador</h1>
<hr class="wp-header-end">

<div style="background:#fff3cd;border:1px solid #ffc107;border-radius:4px;padding:10px 14px;margin-bottom:16px;font-size:13px">
    <span class="dashicons dashicons-info" style="color:#856404;vertical-align:middle"></span>
    El remitente se define en cada fila con la columna <strong>Remitente</strong>. El tracking se genera automáticamente.
</div>

<!-- Incrustar la misma grilla pero en contexto admin -->
<?php
// Reusar el template de grilla con flag de admin
$es_admin = true;
$page_url = '';
$historial = \WCMAS_Historial::obtener(5, 0, 0); // todos los usuarios para admin
wcmas_tpl('frontend/grilla.tpl.php', compact('columnas','todas','usuarios','nonce','filas_init','es_admin','page_url','historial'));
?>
</div>
