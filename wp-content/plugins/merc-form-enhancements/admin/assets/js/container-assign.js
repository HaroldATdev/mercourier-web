/**
 * container-assign.js
 *
 * Asigna automáticamente el contenedor correcto al cambiar el distrito:
 *   - Distrito Destino  → Contenedor de ENTREGA  (normal) o contenedor único (express/full_fitment)
 *   - Distrito Recojo   → Contenedor de RECOJO   (solo en tipo 'normal')
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

    /* ── Determinar nombre del select de contenedor según tipo y campo ──
     *
     *  tipo 'normal' (emprendedor):
     *    - destino → shipment_container_entrega
     *    - recojo  → shipment_container_recojo
     *
     *  tipo 'express' / 'full_fitment':
     *    - destino → shipment_container  (select único)
     *    - recojo  → no aplica (null)
     * ── */
    function getSelectName(campo) {
        var tipo = (tipoEnvioGlobal || '').toLowerCase();

        if (tipo === 'normal') {
            return campo === 'destino' ? 'shipment_container_entrega' : 'shipment_container_recojo';
        }

        // express, full_fitment y cualquier otro: un único select de contenedor
        return campo === 'destino' ? 'shipment_container' : null;
    }

    /* ── Asignar valor en un <select> verificando que la opción exista ── */
    function setearSelect($sel, containerId, containerName) {
        if (!$sel.length) return false;

        // Verificar que la opción con ese ID exista en el select
        var $opt = $sel.find('option[value="' + containerId + '"]');
        if (!$opt.length) {
            console.warn('[MercContainer] Opción no encontrada en select:', containerName, '(ID ' + containerId + ')');
            return false;
        }

        $sel.val(containerId);

        // Trigger estándar jQuery
        $sel.trigger('change');

        // MDB Material Design Bootstrap
        if (typeof $sel.material_select === 'function') {
            $sel.material_select();
        }

        // Select2
        if ($sel.hasClass('select2-hidden-accessible')) {
            try { $sel.trigger('change.select2'); } catch (e) { /* noop */ }
        }

        return true;
    }

    /* ── Toast de notificación ── */
    function mostrarToast(msg, color) {
        $('.merc-container-notif').remove();
        var $n = $('<div class="merc-container-notif">' + msg + '</div>').css({
            background:   color,
            color:        '#fff',
            padding:      '10px 16px',
            borderRadius: '6px',
            fontWeight:   'bold',
            position:     'fixed',
            top:          '20px',
            right:        '20px',
            zIndex:       9999,
            boxShadow:    '0 2px 10px rgba(0,0,0,.25)',
            fontSize:     '14px'
        });
        $('body').append($n);
        setTimeout(function () { $n.fadeOut(400, function () { $n.remove(); }); }, 4000);
    }

    /* ── Buscar contenedor por distrito y asignarlo ── */
    function buscarYAsignar(distrito, campo) {
        if (!distrito || distrito === '-- Seleccione uno --' || distrito === '-- Select One --') return;

        var selectName = getSelectName(campo);
        if (!selectName) return; // No aplica (ej: recojo en express)

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
                if (!r.success || !r.data || !r.data.container_id) {
                    mostrarToast('⚠️ Sin contenedor para: ' + distrito, '#f39c12');
                    return;
                }

                var ok = setearSelect($sel, r.data.container_id, r.data.container_name);
                if (ok) {
                    var label = campo === 'destino' ? 'ENTREGA' : 'RECOJO';
                    mostrarToast('✅ Contenedor ' + label + ': ' + r.data.container_name, '#4CAF50');
                } else {
                    mostrarToast('⚠️ Contenedor no disponible en el listado: ' + r.data.container_name, '#f39c12');
                }
            },
            error: function () {
                mostrarToast('❌ Error al buscar contenedor', '#e74c3c');
            }
        });
    }

    /* ── Listener: Distrito de DESTINO → Contenedor de entrega/único ── */
    $(document).off('change.caEntrega', 'select[name="wpcargo_distrito_destino"]')
               .on('change.caEntrega',  'select[name="wpcargo_distrito_destino"]', function () {
        var distrito = $(this).val();
        clearTimeout(throttleDestino);
        throttleDestino = setTimeout(function () {
            buscarYAsignar(distrito, 'destino');
        }, 400);
    });

    /* ── Listener: Distrito de RECOJO → Contenedor de recojo (solo 'normal') ── */
    $(document).off('change.caRecojo', 'select[name="wpcargo_distrito_recojo"]')
               .on('change.caRecojo',  'select[name="wpcargo_distrito_recojo"]', function () {
        var distrito = $(this).val();
        clearTimeout(throttleRecojo);
        throttleRecojo = setTimeout(function () {
            buscarYAsignar(distrito, 'recojo');
        }, 400);
    });

    /* ── Estado por defecto y observaciones opcionales en modo creación ── */
    setTimeout(function () {
        // Hacer observaciones opcionales
        $('label[for*="remarks"], label:contains("Observaciones"), label:contains("Remarks")')
            .find('.required, .text-danger, span:contains("*")').remove()
            .end().css('font-weight', 'normal');
        $('textarea[name="remarks"], input[name="remarks"]').removeAttr('required');

        if (MercContainerAssign.mode !== 'add' || !tipoEnvioGlobal) return;

        var $sel = $('select[name="status"], select[name="wpcargo_status"]').first();
        if (!$sel.length) $sel = $('select.merc-estado-select').first();
        if (!$sel.length || $sel.val() !== '') return;

        var tipo   = tipoEnvioGlobal.toLowerCase();
        var target = (tipo === 'normal')      ? 'pendiente'    :
                     (tipo === 'express' || tipo === 'full_fitment') ? 'recepcionado' : '';
        if (!target) return;

        $sel.find('option').filter(function () {
            return $(this).text().toLowerCase().indexOf(target) !== -1;
        }).first().each(function () {
            $sel.val($(this).val()).trigger('change');
        });
    }, 1500);
});
