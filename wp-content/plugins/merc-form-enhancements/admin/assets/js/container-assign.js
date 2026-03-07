/**
 * container-assign.js  v3
 *
 * Asigna automáticamente el contenedor al cambiar el distrito.
 * Usa polling (detección activa cada 400 ms) + event listeners
 * para garantizar funcionamiento independientemente de MDB/Select2.
 *
 * - Distrito Destino → Contenedor ENTREGA (normal) o único (express/full_fitment)
 * - Distrito Recojo  → Contenedor RECOJO  (solo tipo 'normal')
 */
/* global MercContainerAssign, jQuery */
jQuery(document).ready(function ($) {
    'use strict';

    if (typeof MercContainerAssign === 'undefined') {
        console.warn('[ContainerAssign] MercContainerAssign no está definido.');
        return;
    }

    var ajaxurl     = MercContainerAssign.ajaxurl;
    var modoEdicion = MercContainerAssign.mode === 'update';
    var shipmentId  = MercContainerAssign.shipmentId || 0;

    console.log('[ContainerAssign] Iniciado ✓ Modo:', MercContainerAssign.mode);

    /* ══════════════════════════════════════════════════════════════
     * TIPO DE ENVÍO
     * ══════════════════════════════════════════════════════════════ */

    var tipoEnvioGlobal = '';

    /** Obtiene el tipo de envío desde la URL, campos hidden o AJAX (modo edición). */
    function getTipoEnvio() {
        // 1. Hidden input inyectado por tipo-envio-saver.js
        var el = document.querySelector('input[name="tipo_envio"]');
        if (el && el.value) return el.value;

        // 2. URL query string (?type=...)
        var urlTipo = new URLSearchParams(window.location.search).get('type');
        if (urlTipo) return urlTipo;

        return '';
    }

    if (modoEdicion && shipmentId) {
        // Modo edición: obtener tipo desde la BD
        $.ajax({
            url: ajaxurl, type: 'POST', async: false,
            data: { action: 'merc_get_shipment_data', shipment_id: shipmentId },
            success: function (r) {
                if (r && r.success && r.data && r.data.tipo_envio) {
                    tipoEnvioGlobal = r.data.tipo_envio;
                }
            }
        });
    } else {
        tipoEnvioGlobal = getTipoEnvio();
    }

    // Si no se pudo obtener aún, reintentarlo cuando el hidden input esté disponible
    if (!tipoEnvioGlobal) {
        var intentosTipo = 0;
        var ivTipo = setInterval(function () {
            intentosTipo++;
            tipoEnvioGlobal = getTipoEnvio();
            if (tipoEnvioGlobal || intentosTipo >= 10) {
                clearInterval(ivTipo);
                console.log('[ContainerAssign] tipoEnvio detectado:', tipoEnvioGlobal);
            }
        }, 300);
    } else {
        console.log('[ContainerAssign] tipoEnvio:', tipoEnvioGlobal);
    }

    /* ══════════════════════════════════════════════════════════════
     * NOMBRE DEL SELECT DE CONTENEDOR
     * ══════════════════════════════════════════════════════════════
     *
     *  tipo 'normal'          → destino: shipment_container_entrega
     *                           recojo:  shipment_container_recojo
     *  express / full_fitment → destino: shipment_container (único)
     *                           recojo:  no aplica (null)
     * ══════════════════════════════════════════════════════════════ */

    function getSelectName(campo) {
        var tipo = (tipoEnvioGlobal || '').toLowerCase();
        if (tipo === 'normal') {
            return campo === 'destino' ? 'shipment_container_entrega' : 'shipment_container_recojo';
        }
        return campo === 'destino' ? 'shipment_container' : null;
    }

    /* ══════════════════════════════════════════════════════════════
     * ACTUALIZAR SELECT MDB
     * ══════════════════════════════════════════════════════════════ */

    /**
     * Asigna containerId en el select MDB y refresca el widget visual.
     * MDB4 oculta el <select> nativo y crea una UI personalizada.
     * Hay que llamar materialSelect() para que el widget se actualice.
     */
    function setearSelectContenedor($sel, containerId) {
        if (!$sel.length) return false;

        // Verificar que la opción exista
        if (!$sel.find('option[value="' + containerId + '"]').length) {
            console.warn('[ContainerAssign] Opción no encontrada en select para containerId:', containerId);
            return false;
        }

        $sel.val(containerId);
        $sel.trigger('change');

        // Refrescar widget MDB (método real: $.fn.materialSelect)
        if (typeof $.fn.materialSelect === 'function') {
            try { $sel.materialSelect('destroy'); } catch (e) { /* no-op */ }
            try { $sel.materialSelect(); }         catch (e) { /* no-op */ }
            console.log('[ContainerAssign] materialSelect() llamado OK');
        } else {
            console.warn('[ContainerAssign] $.fn.materialSelect no disponible, usando solo .val()');
        }

        return true;
    }

    /* ══════════════════════════════════════════════════════════════
     * AJAX: BUSCAR Y ASIGNAR CONTENEDOR
     * ══════════════════════════════════════════════════════════════ */

    var enCurso = {}; // { destino: bool, recojo: bool }

    function buscarYAsignar(distrito, campo) {
        if (!distrito || /^--/.test(distrito.trim())) return;
        if (enCurso[campo]) return;

        var tipo = tipoEnvioGlobal || getTipoEnvio();
        var selectName = getSelectName(campo);
        if (!selectName) {
            console.log('[ContainerAssign] Campo "' + campo + '" no aplica para tipo:', tipo);
            return;
        }

        var $sel = $('select[name="' + selectName + '"]');
        if (!$sel.length) {
            console.warn('[ContainerAssign] Select no encontrado:', selectName);
            return;
        }

        console.log('[ContainerAssign] Buscando contenedor | distrito:', distrito, '| campo:', campo, '| tipo:', tipo);
        enCurso[campo] = true;

        $.ajax({
            url: ajaxurl, type: 'POST', timeout: 10000,
            data: {
                action:     'merc_buscar_contenedor_por_distrito',
                distrito:   distrito,
                tipo_envio: tipo
            },
            success: function (r) {
                console.log('[ContainerAssign] Respuesta AJAX:', r);
                if (!r || !r.success || !r.data || !r.data.container_id) {
                    toast('⚠️ Sin contenedor para: ' + distrito, '#f39c12');
                    return;
                }
                var ok = setearSelectContenedor($sel, r.data.container_id);
                if (ok) {
                    toast('✅ Contenedor ' + (campo === 'destino' ? 'ENTREGA' : 'RECOJO') +
                          ': ' + r.data.container_name, '#4CAF50');
                } else {
                    toast('⚠️ Contenedor no listado: ' + r.data.container_name, '#f39c12');
                }
            },
            error: function (xhr) {
                console.error('[ContainerAssign] Error AJAX:', xhr.status);
                toast('❌ Error al buscar contenedor', '#e74c3c');
            },
            complete: function () {
                enCurso[campo] = false;
            }
        });
    }

    /* ══════════════════════════════════════════════════════════════
     * DETECCIÓN DE CAMBIO — Event listeners + Polling
     * ══════════════════════════════════════════════════════════════ */

    var lastDestino = '';
    var lastRecojo  = '';
    var tDest = null;
    var tRecojo = null;

    function onDestinoChange(val) {
        if (!val || val === lastDestino) return;
        lastDestino = val;
        clearTimeout(tDest);
        tDest = setTimeout(function () { buscarYAsignar(val, 'destino'); }, 400);
    }

    function onRecojoChange(val) {
        if (!val || val === lastRecojo) return;
        lastRecojo = val;
        clearTimeout(tRecojo);
        tRecojo = setTimeout(function () { buscarYAsignar(val, 'recojo'); }, 400);
    }

    // Event listeners
    $(document).on('change', 'select[name="wpcargo_distrito_destino"]', function () {
        console.log('[ContainerAssign] change event destino:', $(this).val());
        onDestinoChange($(this).val());
    });
    $(document).on('change', 'select[name="wpcargo_distrito_recojo"]', function () {
        console.log('[ContainerAssign] change event recojo:', $(this).val());
        onRecojoChange($(this).val());
    });

    // Polling como respaldo (captura cambios aunque los eventos no lleguen)
    setInterval(function () {
        var $dest = $('select[name="wpcargo_distrito_destino"]');
        if ($dest.length) onDestinoChange($dest.val());

        var $rec = $('select[name="wpcargo_distrito_recojo"]');
        if ($rec.length) onRecojoChange($rec.val());
    }, 400);

    /* ══════════════════════════════════════════════════════════════
     * ESTADO POR DEFECTO Y OBSERVACIONES OPCIONALES
     * ══════════════════════════════════════════════════════════════ */

    setTimeout(function () {
        // Quitar obligatoriedad de observaciones
        $('label[for*="remarks"], label:contains("Observaciones"), label:contains("Remarks")')
            .find('.required, .text-danger, span:contains("*")').remove()
            .end().css('font-weight', 'normal');
        $('textarea[name="remarks"], input[name="remarks"]').removeAttr('required');

        if (MercContainerAssign.mode !== 'add') return;
        var tipo = (tipoEnvioGlobal || getTipoEnvio()).toLowerCase();
        if (!tipo) return;

        var $sel = $('select[name="status"], select[name="wpcargo_status"], select.merc-estado-select').first();
        if (!$sel.length || $sel.val() !== '') return;

        var target = tipo === 'normal'      ? 'pendiente'     :
                    (tipo === 'express' || tipo === 'full_fitment') ? 'recepcionado' : '';
        if (!target) return;

        $sel.find('option').filter(function () {
            return $(this).text().toLowerCase().indexOf(target) !== -1;
        }).first().each(function () {
            $sel.val($(this).val()).trigger('change');
        });
    }, 1500);

    /* ══════════════════════════════════════════════════════════════
     * TOAST
     * ══════════════════════════════════════════════════════════════ */

    function toast(msg, color) {
        $('.merc-container-notif').remove();
        $('<div class="merc-container-notif">' + msg + '</div>').css({
            background: color, color: '#fff', padding: '10px 18px',
            borderRadius: '6px', fontWeight: 'bold', position: 'fixed',
            top: '20px', right: '20px', zIndex: 9999,
            boxShadow: '0 2px 8px rgba(0,0,0,.3)', fontSize: '14px'
        }).appendTo('body');
        setTimeout(function () {
            $('.merc-container-notif').fadeOut(400, function () { $(this).remove(); });
        }, 4000);
    }
});
