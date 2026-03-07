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
(function ($) {
    'use strict';

    if (typeof MercClientAutofill === 'undefined') return;

    var ajaxurl = MercClientAutofill.ajaxurl;
    var nonce   = MercClientAutofill.nonce;

    /* ── Rellenar campos del remitente con datos del cliente ── */
    function rellenarRemitente(ud) {
        var map = {
            nombre:    document.querySelector('[name="wpcargo_shipper_name"]'),
            telefono:  document.querySelector('[name="wpcargo_shipper_phone"]'),
            distrito:  document.querySelector('[name="wpcargo_distrito_recojo"]'),
            direccion: document.querySelector('[name="wpcargo_shipper_address"]'),
            email:     document.querySelector('[name="wpcargo_shipper_email"]'),
            empresa:   document.querySelector('[name="wpcargo_tiendaname"]'),
            link_maps: document.querySelector('[name="link_maps_remitente"]')
        };

        ['nombre', 'telefono', 'direccion', 'email', 'empresa', 'link_maps'].forEach(function (k) {
            if (map[k] && ud[k]) {
                map[k].value = ud[k];
                trigger(map[k]);
            }
        });

        // Distrito es un <select>
        if (map.distrito && ud.distrito) {
            var opts = map.distrito.options;
            for (var i = 0; i < opts.length; i++) {
                if (opts[i].value === ud.distrito || opts[i].text.trim() === ud.distrito) {
                    map.distrito.selectedIndex = i;
                    trigger(map.distrito);
                    break;
                }
            }
        }
    }

    function trigger(el) {
        if (!el) return;
        el.dispatchEvent(new Event('change', { bubbles: true }));
        $(el).trigger('change');
    }

    /* ── Reaccionar al cambio del select de cliente ── */
    $(document).on('change', '#registered_client', function () {
        var userId = $(this).val();
        if (!userId) return;

        $.post(ajaxurl, {
            action:  'merc_get_client_data',
            nonce:   nonce,
            user_id: userId
        }, function (resp) {
            if (resp && resp.success && resp.data) {
                rellenarRemitente(resp.data);
            }
        });
    });

}(jQuery));
