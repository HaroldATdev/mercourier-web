/**
 * client-autofill.js
 *
 * Al seleccionar un cliente del select #registered_client en el formulario de
 * creación de envíos, obtiene los datos del cliente vía AJAX y rellena
 * automáticamente los campos del remitente.
 *
 * Variables globales (via wp_localize_script):
 *   MercClientAutofill.ajaxurl  (string)
 *   MercClientAutofill.nonce    (string)
 */
/* global MercClientAutofill, jQuery */
jQuery(document).ready(function ($) {
    'use strict';

    if (typeof MercClientAutofill === 'undefined') return;

    var ajaxurl = MercClientAutofill.ajaxurl;
    var nonce   = MercClientAutofill.nonce;

    /* ── Actualizar un <select> por valor o texto (compatible MDB / Select2 / nativo) ── */
    function setearSelect($sel, valor) {
        if (!$sel.length || !valor) return false;

        var vlower = valor.toLowerCase();
        var encontrado = false;

        $sel.find('option').each(function () {
            var ov = $(this).val();
            var ot = $(this).text().trim();
            if (ov === valor || ov.toLowerCase() === vlower ||
                ot === valor || ot.toLowerCase() === vlower) {
                $sel.val(ov);
                encontrado = true;
                return false; // break
            }
        });

        if (!encontrado) return false;

        // Trigger estándar
        $sel.trigger('change');

        // MDB Material Design Bootstrap
        if (typeof $sel.material_select === 'function') {
            $sel.material_select();
        }

        // Select2
        if ($sel.hasClass('select2-hidden-accessible')) {
            try { $sel.trigger('change.select2'); } catch (e) { /* noop */ }
        }

        // Evento nativo para que otros listeners (container-assign.js) lo detecten
        if ($sel[0]) {
            $sel[0].dispatchEvent(new Event('change', { bubbles: true }));
        }

        return true;
    }

    /* ── Rellenar campos de texto del remitente ── */
    function rellenarRemitente(ud) {
        var camposTexto = {
            nombre:    '[name="wpcargo_shipper_name"]',
            telefono:  '[name="wpcargo_shipper_phone"]',
            direccion: '[name="wpcargo_shipper_address"]',
            email:     '[name="wpcargo_shipper_email"]',
            empresa:   '[name="wpcargo_tiendaname"]',
            link_maps: '[name="link_maps_remitente"]'
        };

        $.each(camposTexto, function (k, selector) {
            if (!ud[k]) return;
            var $el = $(selector);
            if (!$el.length) return;
            $el.val(ud[k]);
            // Disparar ambos eventos para compatibilidad con React/Vue y listeners nativos
            $el[0].dispatchEvent(new Event('input',  { bubbles: true }));
            $el[0].dispatchEvent(new Event('change', { bubbles: true }));
            $el.trigger('change');
        });

        // Distrito remitente: es un <select> — buscar por valor o texto
        if (ud.distrito) {
            var $distr = $('[name="wpcargo_distrito_recojo"]');
            setearSelect($distr, ud.distrito);
        }
    }

    /* ── Toast de notificación ── */
    function mostrarToast(msg, color) {
        $('.merc-client-toast').remove();
        var $t = $('<div class="merc-client-toast">' + msg + '</div>').css({
            background:    color,
            color:         '#fff',
            padding:       '10px 16px',
            borderRadius:  '6px',
            fontWeight:    'bold',
            position:      'fixed',
            top:           '70px',
            right:         '20px',
            zIndex:        9999,
            boxShadow:     '0 2px 10px rgba(0,0,0,.25)',
            fontSize:      '14px'
        });
        $('body').append($t);
        setTimeout(function () { $t.fadeOut(400, function () { $t.remove(); }); }, 4000);
    }

    /* ── Listener delegado: cambio de cliente ── */
    $(document).on('change', '#registered_client', function () {
        var userId = $(this).val();
        if (!userId) return;

        var $select = $(this);
        // Deshabilitar temporalmente para evitar doble-click
        $select.prop('disabled', true);

        $.post(
            ajaxurl,
            { action: 'merc_get_client_data', nonce: nonce, user_id: userId }
        )
        .done(function (resp) {
            if (resp && resp.success && resp.data) {
                rellenarRemitente(resp.data);
                mostrarToast('✅ Datos del remitente cargados', '#4CAF50');
            } else {
                mostrarToast('⚠️ No se encontraron datos para este cliente', '#f39c12');
            }
        })
        .fail(function () {
            mostrarToast('❌ Error al cargar datos del cliente', '#e74c3c');
        })
        .always(function () {
            $select.prop('disabled', false);
        });
    });
});
