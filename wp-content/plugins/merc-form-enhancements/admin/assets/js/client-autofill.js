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

    /* ── Rellenar un campo de texto y notificar a MDB floating labels ──
     *
     *  MDB4 hace flotar la etiqueta al detectar el evento 'input' en el campo.
     *  Disparamos tanto el evento nativo como el de jQuery para cubrir
     *  cualquier versión de MDB/jQuery que esté escuchando.
     * ───────────────────────────────────────────────────────────────── */
    function setearCampoTexto($el, valor) {
        if (!$el.length || !valor) return;
        $el.val(valor);
        /* Evento nativo (para MDB4 que usa addEventListener internamente) */
        $el[0].dispatchEvent(new Event('input',  { bubbles: true }));
        $el[0].dispatchEvent(new Event('change', { bubbles: true }));
        /* Evento jQuery (para listeners con $.on) */
        $el.trigger('input').trigger('change');
    }

    /* ── Actualizar un <select> por valor o texto (insensible a mayúsculas) ── */
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
                return false; /* break */
            }
        });

        if (!encontrado) return false;

        /* Disparar change – nativo + jQuery para que container-assign.js lo detecte */
        $sel[0].dispatchEvent(new Event('change', { bubbles: true }));
        $sel.trigger('change');

        return true;
    }

    /* ── Rellenar todos los campos del remitente ── */
    function rellenarRemitente(ud) {
        /* Campos de texto */
        setearCampoTexto($('[name="wpcargo_shipper_name"]'),    ud.nombre);
        setearCampoTexto($('[name="wpcargo_shipper_phone"]'),   ud.telefono);
        setearCampoTexto($('[name="wpcargo_shipper_address"]'), ud.direccion);
        setearCampoTexto($('[name="wpcargo_shipper_email"]'),   ud.email);
        setearCampoTexto($('[name="wpcargo_tiendaname"]'),      ud.empresa);
        setearCampoTexto($('[name="link_maps_remitente"]'),     ud.link_maps);

        /* Distrito remitente: es un <select> nativo (no MDB) */
        if (ud.distrito) {
            setearSelect($('[name="wpcargo_distrito_recojo"]'), ud.distrito);
        }
    }

    /* ── Toast de notificación ── */
    function mostrarToast(msg, color) {
        $('.merc-client-toast').remove();
        var $t = $('<div class="merc-client-toast">' + msg + '</div>').css({
            background:   color,
            color:        '#fff',
            padding:      '10px 18px',
            borderRadius: '6px',
            fontWeight:   'bold',
            position:     'fixed',
            top:          '70px',
            right:        '20px',
            zIndex:       9999,
            boxShadow:    '0 2px 10px rgba(0,0,0,.3)',
            fontSize:     '14px'
        });
        $('body').append($t);
        setTimeout(function () { $t.fadeOut(400, function () { $t.remove(); }); }, 4000);
    }

    /* ── Listener: selección de cliente ── */
    $(document).on('change', '#registered_client', function () {
        var userId = $(this).val();
        if (!userId) return;

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
        });
    });
});
