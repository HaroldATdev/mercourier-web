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

    if (typeof MercClientAutofill === 'undefined') {
        console.warn('[MercClientAutofill] Variable no disponible — script no cargado correctamente.');
        return;
    }

    console.log('[MercClientAutofill] Script cargado.');

    var ajaxurl = MercClientAutofill.ajaxurl;
    var nonce   = MercClientAutofill.nonce;

    /* ── Disparar evento change en un elemento ── */
    function trigger(el) {
        if (!el) return;
        el.dispatchEvent(new Event('change', { bubbles: true }));
        $(el).trigger('change');
    }

    /* ── Rellenar campos del remitente con los datos del cliente ── */
    function rellenarRemitente(ud) {
        console.log('[MercClientAutofill] Rellenando campos con:', ud);

        var map = {
            nombre:    document.querySelector('[name="wpcargo_shipper_name"]'),
            telefono:  document.querySelector('[name="wpcargo_shipper_phone"]'),
            distrito:  document.querySelector('[name="wpcargo_distrito_recojo"]'),
            direccion: document.querySelector('[name="wpcargo_shipper_address"]'),
            email:     document.querySelector('[name="wpcargo_shipper_email"]'),
            empresa:   document.querySelector('[name="wpcargo_tiendaname"]'),
            link_maps: document.querySelector('[name="link_maps_remitente"]')
        };

        /* Campos de texto simples */
        ['nombre', 'telefono', 'direccion', 'email', 'empresa', 'link_maps'].forEach(function (k) {
            if (map[k] && ud[k]) {
                map[k].value = ud[k];
                trigger(map[k]);
                console.log('[MercClientAutofill] ✅ Campo', k, '=', ud[k]);
            }
        });

        /* Distrito es un <select> */
        if (map.distrito && ud.distrito) {
            var opts = map.distrito.options;
            for (var i = 0; i < opts.length; i++) {
                if (opts[i].value === ud.distrito || opts[i].text.trim() === ud.distrito) {
                    map.distrito.selectedIndex = i;
                    trigger(map.distrito);
                    console.log('[MercClientAutofill] ✅ Campo distrito =', ud.distrito);
                    break;
                }
            }
        }
    }

    /* ── Función de fetch AJAX ── */
    function fetchClientData(userId) {
        if (!userId) return;
        console.log('[MercClientAutofill] Obteniendo datos del cliente ID:', userId);

        $.post(
            ajaxurl,
            { action: 'merc_get_client_data', nonce: nonce, user_id: userId },
            function (resp) {
                console.log('[MercClientAutofill] Respuesta AJAX:', resp);
                if (resp && resp.success && resp.data) {
                    rellenarRemitente(resp.data);
                } else {
                    console.warn('[MercClientAutofill] Respuesta incorrecta:', resp);
                }
            }
        ).fail(function (xhr, status, err) {
            console.error('[MercClientAutofill] Error AJAX:', status, err);
        });
    }

    /* ── Listener nativo: change en el select original ── */
    $(document).on('change', '#registered_client', function () {
        fetchClientData($(this).val());
    });

    /* ── Listener Select2: por si el select usa Select2 ── */
    $(document).on('select2:select', '#registered_client', function () {
        fetchClientData($(this).val());
    });

    console.log('[MercClientAutofill] Listeners registrados en #registered_client.');
});
