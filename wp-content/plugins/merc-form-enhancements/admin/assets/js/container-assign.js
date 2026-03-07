/**
 * container-assign.js
 *
 * Asigna automáticamente el contenedor correcto al cambiar el distrito:
 *   - Distrito Destino  → Contenedor de ENTREGA
 *   - Distrito Recojo   → Contenedor de RECOJO
 *
 * También establece el estado por defecto y hace opcionales las observaciones.
 *
 * Variables globales requeridas (via wp_localize_script):
 *   MercContainerAssign.ajaxurl    (string)
 *   MercContainerAssign.mode       ('add' | 'update')
 *   MercContainerAssign.shipmentId (int, solo en update)
 */
/* global MercContainerAssign, jQuery */
jQuery(document).ready(function ($) {
    'use strict';

    if (typeof MercContainerAssign === 'undefined') return;

    var ajaxurl    = MercContainerAssign.ajaxurl;
    var modoEdicion = MercContainerAssign.mode === 'update';
    var shipmentId  = MercContainerAssign.shipmentId || 0;

    var estadoActualGlobal  = '';
    var tipoEnvioGlobal     = '';
    var throttleTimer       = null;

    /* ── En modo edición obtener tipo y estado desde la BD ── */
    if (modoEdicion && shipmentId) {
        $.ajax({
            url:   ajaxurl,
            type:  'POST',
            async: false,
            data:  { action: 'merc_get_shipment_data', shipment_id: shipmentId },
            success: function (r) {
                if (r.success && r.data) {
                    tipoEnvioGlobal    = r.data.tipo_envio    || '';
                    estadoActualGlobal = r.data.estado_actual || '';
                }
            }
        });
    } else {
        tipoEnvioGlobal = $('input[name="tipo_envio"]').val() ||
                          new URLSearchParams(window.location.search).get('type') || '';
    }

    /* ── Buscar contenedor por distrito y asignarlo a un select específico ── */
    function buscarYAsignar(distrito, selectName, labelTipo) {
        if (!distrito || distrito === '-- Seleccione uno --') return;

        $.ajax({
            url:     ajaxurl,
            type:    'POST',
            timeout: 10000,
            data: {
                action:     'merc_buscar_contenedor_por_distrito',
                distrito:   distrito,
                tipo_envio: tipoEnvioGlobal
            },
            success: function (r) {
                if (!r.success || !r.data || !r.data.container_id) return;

                var cid   = r.data.container_id;
                var cname = r.data.container_name;

                $('select[name="' + selectName + '"]').val(cid).trigger('change');

                // Notificación flotante temporal
                $('.merc-container-notif').remove();
                var $n = $('<div class="merc-container-notif" style="background:#4CAF50;color:#fff;padding:10px 16px;border-radius:6px;font-weight:bold;position:fixed;top:20px;right:20px;z-index:9999;box-shadow:0 2px 10px rgba(0,0,0,.25);">✅ Contenedor ' + labelTipo + ': ' + cname + '</div>');
                $('body').append($n);
                setTimeout(function () { $n.fadeOut(400, function () { $n.remove(); }); }, 4000);
            }
        });
    }

    /* ── Listener: Distrito de DESTINO → Contenedor de ENTREGA ── */
    $(document).off('change.caEntrega', 'select[name="wpcargo_distrito_destino"]')
               .on('change.caEntrega',  'select[name="wpcargo_distrito_destino"]', function () {
        var distrito = $(this).val();
        clearTimeout(throttleTimer);
        throttleTimer = setTimeout(function () {
            buscarYAsignar(distrito, 'shipment_container_entrega', 'ENTREGA');
        }, 400);
    });

    /* ── Listener: Distrito de RECOJO → Contenedor de RECOJO ── */
    $(document).off('change.caRecojo', 'select[name="wpcargo_distrito_recojo"]')
               .on('change.caRecojo',  'select[name="wpcargo_distrito_recojo"]', function () {
        var distrito = $(this).val();
        clearTimeout(throttleTimer);
        throttleTimer = setTimeout(function () {
            buscarYAsignar(distrito, 'shipment_container_recojo', 'RECOJO');
        }, 400);
    });

    /* ── Estado por defecto en modo creación ── */
    setTimeout(function () {
        var modoCreacion = MercContainerAssign.mode === 'add';

        // Observaciones opcionales
        $('label[for*="remarks"], label:contains("Observaciones"), label:contains("Remarks")')
            .find('.required, .text-danger, span:contains("*")').remove()
            .end().css('font-weight', 'normal');
        $('textarea[name="remarks"], input[name="remarks"]').removeAttr('required');

        if (!modoCreacion || !tipoEnvioGlobal) return;

        var $sel = $('select[name="status"], select[name="wpcargo_status"]').first();
        if (!$sel.length) $sel = $('select.merc-estado-select').first();
        if (!$sel.length || $sel.val() !== '') return;

        var tipo = tipoEnvioGlobal.toLowerCase();
        var target = (tipo === 'normal') ? 'pendiente' :
                     (tipo === 'express' || tipo === 'full_fitment') ? 'recepcionado' : '';
        if (!target) return;

        $sel.find('option').filter(function () {
            return $(this).text().toLowerCase().indexOf(target) !== -1;
        }).first().each(function () {
            $sel.val($(this).val()).trigger('change');
        });
    }, 1500);
});
