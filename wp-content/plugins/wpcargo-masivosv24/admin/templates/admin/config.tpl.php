<?php if ( ! defined('ABSPATH') ) exit; ?>
<div class="wrap">
<h1>Envíos Masivos — Configuración</h1>
<hr class="wp-header-end">
<div class="postbox" style="max-width:600px;margin-top:16px">
    <div class="postbox-header"><h2 class="hndle">General</h2></div>
    <div class="inside">
    <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>">
        <?php wp_nonce_field('wcmas_config_nonce'); ?>
        <input type="hidden" name="action" value="wcmas_guardar_config">
        <table class="form-table" role="presentation">
            <tr>
                <th><label for="tracking_prefix">Prefijo del tracking</label></th>
                <td>
                    <input id="tracking_prefix" name="tracking_prefix" type="text" class="small-text"
                           value="<?php echo esc_attr($tracking_prefix); ?>" maxlength="5" style="text-transform:uppercase">
                    <p class="description">Ej: <code>LISTO</code> → <code>LISTO-095006</code>. Solo aplica si WPCargo no genera el tracking.</p>
                </td>
            </tr>
            <tr>
                <th><label for="filas_default">Filas iniciales</label></th>
                <td>
                    <input id="filas_default" name="filas_default" type="number" class="small-text"
                           value="<?php echo esc_attr($filas_default); ?>" min="5" max="200">
                    <p class="description">Filas vacías que verá el cliente al abrir la grilla. El cliente puede añadir más.</p>
                </td>
            </tr>
        </table>
        <p class="submit"><button type="submit" class="button button-primary">Guardar</button></p>
    </form>
    </div>
</div>
</div>
