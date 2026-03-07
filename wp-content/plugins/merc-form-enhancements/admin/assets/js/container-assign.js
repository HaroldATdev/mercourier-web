/**
 * container-assign.js
 *
 * Asigna automáticamente el contenedor correcto al cambiar el distrito:
 *   - Distrito Destino  → Contenedor de ENTREGA  (shipment_container_entrega)
 *   - Distrito Recojo   → Contenedor de RECOJO   (shipment_container_recojo)
 *   - Para tipos express/full_fitment: también asigna a shipment_container
 *
 * Variables globales requeridas (via wp_localize_script):
 *   MercContainerAssign.ajaxurl    (string)
 *   MercContainerAssign.mode       ('add' | 'update')
 *   MercContainerAssign.shipmentId (int, solo en update)
 */
/* global MercContainerAssign, jQuery */
jQuery(document).ready(function ($) {
    'use strict';

    if (typeof MercContainerAssign === 'undefined') {
        console.warn('[MercContainerAssign] Variable no disponible — script no cargado correctamente.');
        return;
    }

    console.log('[MercContainerAssign] Script cargado. Modo:', MercContainerAssign.mode);

    var ajaxurl     = MercContainerAssign.ajaxurl;
    var modoEdicion = MercContainerAssign.mode === 'update';
    var shipmentId  = MercContainerAssign.shipmentId || 0;
    var throttleTimer = null;
    var tipoEnvioGlobal = '';

    /* ── En modo edición: obtener tipo desde BD ── */
    if (modoEdicion && shipmentId) {
        $.ajax({
            url: ajaxurl, type: 'POST', async: false,
            data: { action: 'merc_get_shipment_data', shipment_id: shipmentId },
            success: function (r) {
                if (r.success && r.data) tipoEnvioGlobal = r.data.tipo_envio || '';
            }
        });
    } else {
        tipoEnvioGlobal = $('input[name="tipo_envio"]').val() ||
                          new URLSearchParams(window.location.search).get('type') || '';
    }

    console.log('[MercContainerAssign] tipo_envio detectado:', tipoEnvioGlobal || '(vacío)');

    /* ── Buscar contenedor y asignarlo ── */
    function buscarYAsignar(distrito, mode) {
        // mode: 'entrega' | 'recojo'
        if (!distrito || distrito === '' || distrito === '-- Seleccione uno --') {
            console.log('[MercContainerAssign] Distrito vacío, abortando.');
            return;
        }

        var tipo = tipoEnvioGlobal ||
                   $('input[name="tipo_envio"]').val() ||
                   new URLSearchParams(window.location.search).get('type') || '';

        console.log('[MercContainerAssign] Buscando contenedor para distrito:', distrito, '| modo:', mode, '| tipo:', tipo);

        $.ajax({
            url:     ajaxurl,
            type:    'POST',
            timeout: 10000,
            data: { action: 'merc_buscar_contenedor_por_distrito', distrito: distrito, tipo_envio: tipo },
            success: function (r) {
                console.log('[MercContainerAssign] Respuesta AJAX:', r);
                if (!r.success || !r.data || !r.data.container_id) {
                    console.warn('[MercContainerAssign] No se encontró contenedor para:', distrito);
                    return;
                }

                var cid   = r.data.container_id;
                var cname = r.data.container_name;

                if (mode === 'entrega') {
                    // MERC Emprendedor (normal): shipment_container_entrega
                    // Express/Full Fitment: también shipment_container (único)
                    $('select[name="shipment_container_entrega"], #shipment_container_entrega').val(cid).trigger('change');
                    $('select[name="shipment_container"], #shipment_container').val(cid).trigger('change');
                    console.log('[MercContainerAssign] ✅ Contenedor ENTREGA asignado:', cname, '(ID:', cid, ')');
                } else if (mode === 'recojo') {
                    $('select[name="shipment_container_recojo"], #shipment_container_recojo').val(cid).trigger('change');
                    console.log('[MercContainerAssign] ✅ Contenedor RECOJO asignado:', cname, '(ID:', cid, ')');
                }

                /* Notificación flotante */
                $('.merc-container-notif').remove();
                var label = mode === 'recojo' ? 'RECOJO' : 'ENTREGA';
                var $n = $('<div class="merc-container-notif" style="background:#4CAF50;color:#fff;padding:10px 16px;border-radius:6px;font-weight:bold;position:fixed;top:20px;right:20px;z-index:9999;box-shadow:0 2px 10px rgba(0,0,0,.25);">✅ Contenedor ' + label + ': ' + cname + '</div>');
                $('body').append($n);
                setTimeout(function () { $n.fadeOut(400, function () { $n.remove(); }); }, 4000);
            },
            error: function (xhr, status, err) {
                console.error('[MercContainerAssign] Error AJAX:', status, err);
            }
        });
    }

    /* ── Manejador genérico para cambio de distrito ── */
    function onDistritoDestinoChange() {
        var distrito = $('#wpcargo_distrito_destino').val() ||
                       $('select[name="wpcargo_distrito_destino"]').val() || '';
        clearTimeout(throttleTimer);
        throttleTimer = setTimeout(function () { buscarYAsignar(distrito, 'entrega'); }, 400);
    }

    function onDistritoRecojoChange() {
        var distrito = $('#wpcargo_distrito_recojo').val() ||
                       $('select[name="wpcargo_distrito_recojo"]').val() || '';
        clearTimeout(throttleTimer);
        throttleTimer = setTimeout(function () { buscarYAsignar(distrito, 'recojo'); }, 400);
    }

    /* ── Listeners: cambio nativo y Select2 ── */
    $(document)
        .off('change.caEntrega select2:select.caEntrega', '#wpcargo_distrito_destino, select[name="wpcargo_distrito_destino"]')
        .on('change.caEntrega select2:select.caEntrega',  '#wpcargo_distrito_destino, select[name="wpcargo_distrito_destino"]',
            onDistritoDestinoChange);

    $(document)
        .off('change.caRecojo select2:select.caRecojo', '#wpcargo_distrito_recojo, select[name="wpcargo_distrito_recojo"]')
        .on('change.caRecojo select2:select.caRecojo',  '#wpcargo_distrito_recojo, select[name="wpcargo_distrito_recojo"]',
            onDistritoRecojoChange);

    console.log('[MercContainerAssign] Listeners registrados en distrito_destino y distrito_recojo.');

    /* ── Estado por defecto en modo creación ── */
    setTimeout(function () {
        /* Observaciones opcionales */
        $('label[for*="remarks"], label:contains("Observaciones"), label:contains("Remarks")')
            .find('.required, .text-danger, span:contains("*")').remove()
            .end().css('font-weight', 'normal');
        $('textarea[name="remarks"], input[name="remarks"]').removeAttr('required');

        if (modoEdicion || !tipoEnvioGlobal) return;

        var $sel = $('select[name="status"], select[name="wpcargo_status"]').first();
        if (!$sel.length) $sel = $('select.merc-estado-select').first();
        if (!$sel.length || $sel.val() !== '') return;

        var tipo   = tipoEnvioGlobal.toLowerCase();
        var target = tipo === 'normal' ? 'pendiente' :
                     (tipo === 'express' || tipo === 'full_fitment') ? 'recepcionado' : '';
        if (!target) return;

        $sel.find('option').filter(function () {
            return $(this).text().toLowerCase().indexOf(target) !== -1;
        }).first().each(function () {
            $sel.val($(this).val()).trigger('change');
        });
    }, 1500);
});
