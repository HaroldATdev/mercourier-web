/**
 * container-assign.js
 *
 * Asigna automáticamente el contenedor correcto al cambiar el distrito:
 *   - Distrito Destino  → Contenedor de ENTREGA  (normal) o contenedor único (express/full_fitment)
 *   - Distrito Recojo   → Contenedor de RECOJO   (solo en tipo 'normal')
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

    var ajaxurl     = MercContainerAssign.ajaxurl;
    var modoEdicion = MercContainerAssign.mode === 'update';
    var shipmentId  = MercContainerAssign.shipmentId || 0;

    var tipoEnvioGlobal    = '';
    var estadoActualGlobal = '';
    var throttleDestino    = null;
    var throttleRecojo     = null;

    /* ── En modo edición: obtener tipo y estado desde la BD ── */
    if (modoEdicion && shipmentId) {
        $.ajax({
            url:   ajaxurl,
            type:  'POST',
            async: false,
            data:  { action: 'merc_get_shipment_data', shipment_id: shipmentId },
            success: function (r) {
                if (r && r.success && r.data) {
                    tipoEnvioGlobal    = r.data.tipo_envio    || '';
                    estadoActualGlobal = r.data.estado_actual || '';
                }
            }
        });
    } else {
        /* En modo creación: leer desde hidden input (inyectado por tipo-envio-saver.js)
           o desde la URL (?type=...) como fallback */
        tipoEnvioGlobal = (function () {
            var el = document.querySelector('input[name="tipo_envio"]');
            if (el && el.value) return el.value;
            return new URLSearchParams(window.location.search).get('type') || '';
        }());
    }

    /* ── Determinar el select de contenedor según tipo y campo ────────
     *
     *  tipo 'normal' (emprendedor):
     *    destino → shipment_container_entrega
     *    recojo  → shipment_container_recojo
     *
     *  express / full_fitment / otros:
     *    destino → shipment_container  (único)
     *    recojo  → no aplica (null)
     * ─────────────────────────────────────────────────────────────── */
    function getSelectName(campo) {
        var tipo = (tipoEnvioGlobal || '').toLowerCase();
        if (tipo === 'normal') {
            return campo === 'destino' ? 'shipment_container_entrega' : 'shipment_container_recojo';
        }
        return campo === 'destino' ? 'shipment_container' : null;
    }

    /* ── Actualizar un MDB <select> programáticamente ─────────────────
     *
     *  MDB envuelve el <select> nativo con su propia UI personalizada.
     *  Para que la UI visible se actualice hay que:
     *    1. Fijar el valor en el <select> nativo con .val()
     *    2. Dispara change (algunos builds de MDB escuchan esto)
     *    3. Llamar .materialSelect() para re-renderizar el widget
     * ─────────────────────────────────────────────────────────────── */
    function setearSelectMDB($sel, containerId) {
        if (!$sel.length) return false;

        /* Verificar que la opción exista antes de asignar */
        if (!$sel.find('option[value="' + containerId + '"]').length) {
            return false;
        }

        $sel.val(containerId);
        $sel.trigger('change');

        /* Refrescar widget MDB (jQuery plugin: $.fn.materialSelect) */
        if (typeof $.fn.materialSelect === 'function') {
            try {
                $sel.materialSelect('destroy');
            } catch (e) { /* no-op si ya fue destruido */ }
            try {
                $sel.materialSelect();
            } catch (e) { /* no-op */ }
        }

        return true;
    }

    /* ── Toast de notificación ── */
    function mostrarToast(msg, color) {
        $('.merc-container-notif').remove();
        var $n = $('<div class="merc-container-notif">' + msg + '</div>').css({
            background:   color,
            color:        '#fff',
            padding:      '10px 18px',
            borderRadius: '6px',
            fontWeight:   'bold',
            position:     'fixed',
            top:          '20px',
            right:        '20px',
            zIndex:       9999,
            boxShadow:    '0 2px 10px rgba(0,0,0,.3)',
            fontSize:     '14px'
        });
        $('body').append($n);
        setTimeout(function () { $n.fadeOut(400, function () { $n.remove(); }); }, 4000);
    }

    /* ── Buscar contenedor por distrito y asignarlo al select correcto ── */
    function buscarYAsignar(distrito, campo) {
        if (!distrito || /^\s*--/.test(distrito)) return;

        var selectName = getSelectName(campo);
        if (!selectName) return; /* recojo en express → no aplica */

        var $sel = $('select[name="' + selectName + '"]');
        if (!$sel.length) return;

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
                if (!r || !r.success || !r.data || !r.data.container_id) {
                    mostrarToast('⚠️ Sin contenedor para: ' + distrito, '#f39c12');
                    return;
                }

                var ok = setearSelectMDB($sel, r.data.container_id);

                if (ok) {
                    var label = (campo === 'destino') ? 'ENTREGA' : 'RECOJO';
                    mostrarToast('✅ Contenedor ' + label + ': ' + r.data.container_name, '#4CAF50');
                } else {
                    mostrarToast('⚠️ Contenedor no listado: ' + r.data.container_name, '#f39c12');
                }
            },
            error: function () {
                mostrarToast('❌ Error al buscar contenedor', '#e74c3c');
            }
        });
    }

    /* ── Listener: Distrito de DESTINO ── */
    $(document).off('change.caEntrega', 'select[name="wpcargo_distrito_destino"]')
               .on('change.caEntrega',  'select[name="wpcargo_distrito_destino"]', function () {
        var distrito = $(this).val();
        clearTimeout(throttleDestino);
        throttleDestino = setTimeout(function () {
            buscarYAsignar(distrito, 'destino');
        }, 400);
    });

    /* ── Listener: Distrito de RECOJO ── */
    $(document).off('change.caRecojo', 'select[name="wpcargo_distrito_recojo"]')
               .on('change.caRecojo',  'select[name="wpcargo_distrito_recojo"]', function () {
        var distrito = $(this).val();
        clearTimeout(throttleRecojo);
        throttleRecojo = setTimeout(function () {
            buscarYAsignar(distrito, 'recojo');
        }, 400);
    });

    /* ── Estado por defecto y observaciones opcionales (solo modo creación) ── */
    setTimeout(function () {
        $('label[for*="remarks"], label:contains("Observaciones"), label:contains("Remarks")')
            .find('.required, .text-danger, span:contains("*")').remove()
            .end().css('font-weight', 'normal');
        $('textarea[name="remarks"], input[name="remarks"]').removeAttr('required');

        if (MercContainerAssign.mode !== 'add' || !tipoEnvioGlobal) return;

        var $sel = $('select[name="status"], select[name="wpcargo_status"]').first();
        if (!$sel.length) $sel = $('select.merc-estado-select').first();
        if (!$sel.length || $sel.val() !== '') return;

        var tipo   = tipoEnvioGlobal.toLowerCase();
        var target = (tipo === 'normal')      ? 'pendiente'     :
                     (tipo === 'express' || tipo === 'full_fitment') ? 'recepcionado' : '';
        if (!target) return;

        $sel.find('option').filter(function () {
            return $(this).text().toLowerCase().indexOf(target) !== -1;
        }).first().each(function () {
            $sel.val($(this).val()).trigger('change');
        });
    }, 1500);
});
