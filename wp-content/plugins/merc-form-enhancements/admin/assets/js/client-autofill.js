/**
 * client-autofill.js  v3
 *
 * Rellena los campos del remitente al seleccionar un cliente.
 * Usa polling (detección activa cada 400 ms) + event listeners
 * para garantizar funcionamiento independientemente de Select2/MDB.
 */
/* global MercClientAutofill, jQuery */
jQuery(document).ready(function ($) {
    'use strict';

    if (typeof MercClientAutofill === 'undefined') {
        console.warn('[ClientAutofill] MercClientAutofill no está definido. ¿El script fue encolado?');
        return;
    }

    var ajaxurl = MercClientAutofill.ajaxurl;
    var nonce   = MercClientAutofill.nonce;

    console.log('[ClientAutofill] Iniciado ✓');

    var ultimoClienteId = '';   // Para detectar cambios por polling
    var cargando        = false; // Evitar llamadas simultáneas

    /* ══════════════════════════════════════════════════════════════
     * LLENADO DE CAMPOS
     * ══════════════════════════════════════════════════════════════ */

    /**
     * Rellena un <input> / <textarea> y activa el floating label de MDB4.
     * MDB4 hace flotar la etiqueta al detectar el evento 'focus'+'input'+'blur'.
     */
    function setTexto($el, valor) {
        if (!$el.length || !valor) return;
        $el.val(valor);
        $el.trigger('focus').trigger('input').trigger('blur').trigger('change');
        // Eventos nativos para listeners que no usan jQuery
        try {
            $el[0].dispatchEvent(new Event('input',  { bubbles: true }));
            $el[0].dispatchEvent(new Event('change', { bubbles: true }));
        } catch (e) { /* no-op */ }
    }

    /**
     * Selecciona una opción en un <select> buscando por valor o texto (case-insensitive).
     * Dispara change nativo + jQuery para activar cualquier listener downstream.
     */
    function setSelect($sel, valor) {
        if (!$sel.length || !valor) return false;
        var vl = valor.toLowerCase();
        var ok = false;

        $sel.find('option').each(function () {
            var ov = String($(this).val());
            var ot = $(this).text().trim();
            if (ov === valor || ov.toLowerCase() === vl ||
                ot === valor || ot.toLowerCase() === vl) {
                $sel.val(ov);
                ok = true;
                return false; /* break */
            }
        });

        if (!ok) return false;

        // Change nativo (para que container-assign.js lo detecte via polling)
        try { $sel[0].dispatchEvent(new Event('change', { bubbles: true })); } catch (e) { /* no-op */ }
        $sel.trigger('change');
        return true;
    }

    /** Rellena todos los campos del remitente con los datos del cliente. */
    function rellenarRemitente(ud) {
        console.log('[ClientAutofill] Rellenando campos con:', ud);

        setTexto($('[name="wpcargo_shipper_name"]'),    ud.nombre);
        setTexto($('[name="wpcargo_shipper_phone"]'),   ud.telefono);
        setTexto($('[name="wpcargo_shipper_address"]'), ud.direccion);
        setTexto($('[name="wpcargo_shipper_email"]'),   ud.email);
        setTexto($('[name="wpcargo_tiendaname"]'),      ud.empresa);
        setTexto($('[name="link_maps_remitente"]'),     ud.link_maps);

        if (ud.distrito) {
            setSelect($('[name="wpcargo_distrito_recojo"]'), ud.distrito);
        }
    }

    /* ══════════════════════════════════════════════════════════════
     * AJAX
     * ══════════════════════════════════════════════════════════════ */

    function cargarDatosCliente(userId) {
        if (!userId || userId === ultimoClienteId || cargando) return;

        console.log('[ClientAutofill] Cargando datos para userId:', userId);
        cargando = true;

        $.post(ajaxurl, {
            action:  'merc_get_client_data',
            nonce:   nonce,
            user_id: userId
        })
        .done(function (resp) {
            console.log('[ClientAutofill] Respuesta AJAX:', resp);
            if (resp && resp.success && resp.data) {
                ultimoClienteId = userId;
                rellenarRemitente(resp.data);
                toast('✅ Datos del remitente cargados', '#4CAF50');
            } else {
                console.warn('[ClientAutofill] Respuesta sin datos:', resp);
                toast('⚠️ Sin datos para este cliente', '#f39c12');
            }
        })
        .fail(function (xhr) {
            console.error('[ClientAutofill] Error AJAX:', xhr.status, xhr.responseText);
            toast('❌ Error al cargar datos del cliente', '#e74c3c');
        })
        .always(function () {
            cargando = false;
        });
    }

    /* ══════════════════════════════════════════════════════════════
     * DETECCIÓN DE CAMBIO — Event listeners + Polling como respaldo
     * ══════════════════════════════════════════════════════════════ */

    // Listener estándar change (nativo y jQuery)
    $(document).on('change', '#registered_client', function () {
        var v = $(this).val();
        console.log('[ClientAutofill] change event en #registered_client, val:', v);
        if (v) cargarDatosCliente(v);
    });

    // Select2 dispara este evento en adición a change
    $(document).on('select2:select', '#registered_client', function (e) {
        var v = (e.params && e.params.data) ? String(e.params.data.id) : $(this).val();
        console.log('[ClientAutofill] select2:select en #registered_client, val:', v);
        if (v) cargarDatosCliente(v);
    });

    // Polling: comprueba cada 400 ms si el valor del select cambió
    setInterval(function () {
        var $sel = $('#registered_client');
        if (!$sel.length) return;
        var v = $sel.val();
        if (v && v !== ultimoClienteId) {
            console.log('[ClientAutofill] Cambio detectado por polling, val:', v);
            cargarDatosCliente(v);
        }
    }, 400);

    /* ══════════════════════════════════════════════════════════════
     * TOAST
     * ══════════════════════════════════════════════════════════════ */

    function toast(msg, color) {
        $('.merc-client-toast').remove();
        $('<div class="merc-client-toast">' + msg + '</div>').css({
            background: color, color: '#fff', padding: '10px 18px',
            borderRadius: '6px', fontWeight: 'bold', position: 'fixed',
            top: '70px', right: '20px', zIndex: 9999,
            boxShadow: '0 2px 8px rgba(0,0,0,.3)', fontSize: '14px'
        }).appendTo('body');
        setTimeout(function () {
            $('.merc-client-toast').fadeOut(400, function () { $(this).remove(); });
        }, 4000);
    }
});
