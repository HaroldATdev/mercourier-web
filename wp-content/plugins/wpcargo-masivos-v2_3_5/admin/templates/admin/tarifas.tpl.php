<?php if ( ! defined('ABSPATH') ) exit; ?>
<div class="wrap">
    <h1>💰 Tarifas por Distrito y Tipo de Servicio</h1>
    <p class="description">
        Define el costo de envío por distrito y tipo de servicio. Al crear envíos masivos,
        el <strong>Costo Servicio</strong> se autocompleta con este valor (editable).
    </p>

    <?php if ( empty($distritos) ): ?>
        <div class="notice notice-warning inline"><p>No se encontraron distritos en WPCargo. Asegúrate de tener el addon de Custom Fields activado.</p></div>
    <?php else: ?>

        <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>">
            <?php wp_nonce_field('wcmas_config_nonce'); ?>
            <input type="hidden" name="action" value="wcmas_guardar_tarifas">
            <input type="hidden" name="redirect_to" value="tarifas">

            <div style="background:#fff;border:1px solid #dee2e6;border-radius:6px;padding:20px;margin-top:16px;max-width:900px;">
                <div style="overflow-x:auto">
                    <table class="widefat fixed striped" style="font-size:12px;min-width:500px">
                        <thead>
                        <tr style="background:#2271b1;color:#fff">
                            <th style="width:220px;color:#fff;padding:8px 10px">Distrito</th>
                            <?php foreach($tipos_servicio as $label => $val): ?>
                                <th style="text-align:center;color:#fff;padding:8px 10px">
                                    <?php echo esc_html($label); ?>
                                    <small style="display:block;opacity:.8;font-weight:normal"><?php echo esc_html($val); ?></small>
                                </th>
                            <?php endforeach; ?>
                        </tr>
                        </thead>
                        <tbody>
                        <?php foreach($distritos as $distrito): ?>
                            <tr>
                                <td style="padding:4px 10px;font-weight:600"><?php echo esc_html($distrito); ?></td>
                                <?php foreach($tipos_servicio as $label => $tipo_val): ?>
                                    <td style="padding:4px 8px;text-align:center">
                                        <input
                                                type="number"
                                                step="0.50"
                                                min="0"
                                                name="tarifas[<?php echo esc_attr($distrito); ?>][<?php echo esc_attr($tipo_val); ?>]"
                                                value="<?php echo esc_attr($tarifas[$distrito][$tipo_val] ?? ''); ?>"
                                                placeholder="0.00"
                                                style="width:80px;text-align:center;padding:3px 5px;font-size:12px"
                                        >
                                    </td>
                                <?php endforeach; ?>
                            </tr>
                        <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>

                <p class="submit" style="margin-top:16px">
                    <button type="submit" class="button button-primary">💾 Guardar Tarifas</button>
                    <span class="description" style="margin-left:12px">Deja en blanco los distritos sin tarifa definida.</span>
                    &nbsp;
                    <button type="button" class="button button-secondary" style="margin-left:8px"
                            onclick="if(confirm('¿Restaurar las tarifas por defecto de Mercourier?')) wcmasRestaurarTarifas()">
                        🔄 Restaurar tarifas por defecto
                    </button>
                    <script>
                        function wcmasRestaurarTarifas() {
                            fetch(ajaxurl, {
                                method: 'POST',
                                credentials: 'same-origin',
                                headers: {'Content-Type':'application/x-www-form-urlencoded'},
                                body: 'action=wcmas_restaurar_tarifas_default&nonce=<?php echo wp_create_nonce("wcmas_procesar_nonce"); ?>'
                            }).then(r=>r.json()).then(function(r){
                                if(r.success) { alert('Tarifas restauradas. Recarga la página.'); location.reload(); }
                                else alert('Error: ' + (r.data&&r.data.msg||'desconocido'));
                            });
                        }
                    </script>
                </p>
            </div>
        </form>

    <?php endif; ?>
</div>
