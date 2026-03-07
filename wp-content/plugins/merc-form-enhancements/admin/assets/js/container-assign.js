/**
 * container-assign.js
 *
 * Asigna automáticamente el contenedor correcto al cambiar el distrito de recojo/destino
 * en el formulario de creación/edición de envíos.
 *
 * Variables globales requeridas (via wp_localize_script):
 *   MercContainerAssign.ajaxurl  (string)
 *   MercContainerAssign.mode     ('add' | 'update')
 *   MercContainerAssign.shipmentId (int, solo en update)
 */
/* global MercContainerAssign, jQuery */
jQuery(document).ready(function ($) {
    'use strict';

    if (typeof MercContainerAssign === 'undefined') return;

    var ajaxurl           = MercContainerAssign.ajaxurl;
    var modoEdicion       = MercContainerAssign.mode === 'update';
    var shipmentId        = MercContainerAssign.shipmentId || 0;

    var procesandoContenedor = false;
    var tipoEnvioGlobal      = '';
    var estadoActualGlobal   = '';

    /* ── Obtener tipo_envio y estado en modo edición de forma síncrona ── */
    if (modoEdicion && shipmentId) {
        $.ajax({
            url:   ajaxurl,
            type:  'POST',
            async: false,
            data:  { action: 'merc_get_shipment_data', shipment_id: shipmentId },
            success: function (response) {
                if (response.success && response.data) {
                    tipoEnvioGlobal    = response.data.tipo_envio    || '';
                    estadoActualGlobal = response.data.estado_actual || '';
                }
            }
        });
    } else {
        tipoEnvioGlobal = $('input[name="tipo_envio"]').val() ||
                          new URLSearchParams(window.location.search).get('type') || '';
    }

    /* ── Helper: obtener tipo_envio desde múltiples fuentes ── */
    function obtenerTipoEnvio() {
        if (tipoEnvioGlobal) return tipoEnvioGlobal;
        var tipo = $('input[name="tipo_envio"]').val();
        if (tipo) return tipo;
        return new URLSearchParams(window.location.search).get('type') || '';
    }

    /* ── Buscar contenedor por distrito vía AJAX ── */
    function buscarContenedorPorDistrito(forzar, selectTarget) {
        selectTarget = selectTarget || null;
        if (!forzar && procesandoContenedor) return;

        var tipoEnvio    = obtenerTipoEnvio();
        var distrito     = '';
        var tipoDistrito = '';

        if (selectTarget === 'recojo') {
            distrito     = $('select[name="wpcargo_distrito_recojo"]').val();
            tipoDistrito = 'recojo';
        } else if (selectTarget === 'entrega') {
            distrito     = $('select[name="wpcargo_distrito_destino"]').val();
            tipoDistrito = 'destino';
        } else {
            if (tipoEnvio.toLowerCase() === 'express') {
                distrito     = $('select[name="wpcargo_distrito_destino"]').val();
                tipoDistrito = 'destino';
            } else if (tipoEnvio.toLowerCase() === 'normal') {
                distrito     = $('select[name="wpcargo_distrito_recojo"]').val();
                tipoDistrito = 'recojo';
            } else if (tipoEnvio.toLowerCase() === 'full_fitment') {
                distrito     = $('select[name="wpcargo_distrito_destino"]').val();
                tipoDistrito = 'destino';
            } else {
                return;
            }
        }

        if (!distrito || distrito === '' || distrito === '-- Seleccione uno --') return;

        procesandoContenedor = true;

        $.ajax({
            url:     ajaxurl,
            type:    'POST',
            timeout: 10000,
            data: {
                action:     'merc_buscar_contenedor_por_distrito',
                distrito:   distrito,
                tipo_envio: tipoEnvio
            },
            success: function (response) {
                procesandoContenedor = false;
                if (!response.success || !response.data.container_id) {
                    if (new URLSearchParams(window.location.search).get('wpcfe') === 'add') {
                        $('select[name="shipment_container"]').val('').trigger('change');
                    }
                    return;
                }

                $('.merc-container-asignado').remove();

                var tEnvio = response.data.tipo_envio || obtenerTipoEnvio();
                var esMerc = tEnvio.toLowerCase() === 'normal';
                var cid    = response.data.container_id;
                var cname  = response.data.container_name;
                var msg    = '';

                if (selectTarget === 'recojo') {
                    $('select[name="shipment_container_recojo"]').val(cid).trigger('change');
                    msg = '✅ Contenedor RECOJO actualizado por distrito ' + tipoDistrito + ': ' + cname;
                } else if (selectTarget === 'entrega') {
                    $('select[name="shipment_container_entrega"]').val(cid).trigger('change');
                    msg = '✅ Contenedor ENTREGA actualizado por distrito ' + tipoDistrito + ': ' + cname;
                } else {
                    var $cs = $('select[name="shipment_container"]');
                    $cs.val(cid).trigger('change');
                    try { $cs[0].dispatchEvent(new Event('change', { bubbles: true })); } catch(e) {}

                    if (esMerc) {
                        $('select[name="shipment_container_recojo"]').val(cid).trigger('change');
                        $('select[name="shipment_container_entrega"]').val(cid).trigger('change');
                    }
                    var ext = esMerc ? ' (Recojo + Entrega)' : '';
                    msg = '✅ Contenedor actualizado' + ext + ' por distrito de ' + tipoDistrito + ': ' + cname;
                }

                var $notif = $('<div class="merc-container-asignado" style="background:#4CAF50;color:#fff;padding:10px;border-radius:4px;font-weight:bold;position:fixed;top:20px;right:20px;z-index:9999;box-shadow:0 2px 10px rgba(0,0,0,.2);">' + msg + '</div>');
                $('body').append($notif);
                setTimeout(function () { $notif.fadeOut(function () { $notif.remove(); }); }, 4000);
            },
            error: function () {
                procesandoContenedor = false;
            }
        });
    }

    /* ── Throttle ── */
    var throttleTimer = null;

    /* ── Listener: distrito DESTINO ── */
    $(document).off('change.containerAssignment', 'select[name="wpcargo_distrito_destino"]')
               .on('change.containerAssignment',  'select[name="wpcargo_distrito_destino"]', function () {
        var tipo = (obtenerTipoEnvio() || '').toLowerCase().trim();
        clearTimeout(throttleTimer);
        if (tipo === 'express' || tipo === 'full_fitment') {
            throttleTimer = setTimeout(function () { buscarContenedorPorDistrito(true); }, 500);
        } else if (tipo === 'normal') {
            throttleTimer = setTimeout(function () { buscarContenedorPorDistrito(true, 'entrega'); }, 500);
        }
    });

    /* ── Listener: distrito RECOJO ── */
    $(document).off('change.containerAssignment', 'select[name="wpcargo_distrito_recojo"]')
               .on('change.containerAssignment',  'select[name="wpcargo_distrito_recojo"]', function () {
        var tipo = (obtenerTipoEnvio() || '').toLowerCase().trim();
        clearTimeout(throttleTimer);
        if (tipo === 'normal') {
            throttleTimer = setTimeout(function () { buscarContenedorPorDistrito(true, 'recojo'); }, 500);
        } else if (tipo === 'express' || tipo === 'full_fitment') {
            throttleTimer = setTimeout(function () { buscarContenedorPorDistrito(true); }, 500);
        }
    });

    /* ── Estado por defecto y observaciones opcionales (solo en creación) ── */
    setTimeout(function () {
        var urlParams    = new URLSearchParams(window.location.search);
        var modoCreacion = urlParams.get('wpcfe') === 'add';

        var $estadoSelect = $('select[name="status"]').length
            ? $('select[name="status"]')
            : ($('select[name="wpcargo_status"]').length ? $('select[name="wpcargo_status"]') : $('select.merc-estado-select'));

        if (modoCreacion && $estadoSelect.length && $estadoSelect.val() === '') {
            var tipo = obtenerTipoEnvio();
            if (tipo.toLowerCase() === 'normal') {
                $estadoSelect.find('option').filter(function () {
                    return $(this).text().toLowerCase().indexOf('pendiente') !== -1 ||
                           $(this).text().toLowerCase().indexOf('pending') !== -1;
                }).first().each(function () {
                    $estadoSelect.val($(this).val()).trigger('change');
                });
            } else if (tipo.toLowerCase() === 'express' || tipo.toLowerCase() === 'full_fitment') {
                $estadoSelect.find('option').filter(function () {
                    return $(this).text().toUpperCase().indexOf('RECEPCIONADO') !== -1;
                }).first().each(function () {
                    $estadoSelect.val($(this).val()).trigger('change');
                });
            }
        }

        /* Observaciones opcionales */
        $('label[for*="remarks"], label:contains("Observaciones"), label:contains("Remarks")')
            .find('.required, .text-danger, span:contains("*")').remove()
            .end().css('font-weight', 'normal');
        $('textarea[name="remarks"], input[name="remarks"]').removeAttr('required');
    }, 1500);
});
