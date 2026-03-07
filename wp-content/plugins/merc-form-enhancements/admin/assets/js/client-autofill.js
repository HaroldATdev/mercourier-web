/**
 * client-autofill.js  v4
 *
 * Rellena los campos del remitente al seleccionar un cliente.
 * Usa polling (detección activa cada 300 ms) + event listeners
 * para garantizar funcionamiento independientemente de Select2/MDB.
 * Versión mejorada con búsqueda dinámica de campos.
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

    console.log('[ClientAutofill] ✓ Iniciado');

    var ultimoClienteId = '';   // Para detectar cambios por polling
    var cargando        = false; // Evitar llamadas simultáneas

    /* ══════════════════════════════════════════════════════════════
     * DIAGNÓSTICO: Encontrar campos en el formulario
     * ══════════════════════════════════════════════════════════════ */

    function diagnosticoFormulario() {
        console.log('[ClientAutofill] === DIAGNÓSTICO DE FORMULARIO ===');
        
        // Select de cliente
        var $cliente = $('#registered_client');
        console.log('[ClientAutofill] Select cliente (#registered_client):', $cliente.length > 0 ? 'ENCONTRADO' : 'NO ENCONTRADO');
        if ($cliente.length) {
            console.log('   └─ Valor actual:', $cliente.val());
        }
        
        // Buscar todos los inputs y selects del formulario
        var camposEncontrados = {
            inputs: [],
            selects: [],
            textareas: []
        };
        
        $('input[type="text"], input[type="email"], input[type="tel"]').each(function() {
            var nombre = $(this).attr('name') || $(this).attr('id') || 'sin-nombre';
            camposEncontrados.inputs.push(nombre);
        });
        
        $('select').each(function() {
            var nombre = $(this).attr('name') || $(this).attr('id') || 'sin-nombre';
            var opciones = $(this).find('option').length;
            camposEncontrados.selects.push(nombre + ' (' + opciones + ' opciones)');
        });
        
        $('textarea').each(function() {
            var nombre = $(this).attr('name') || $(this).attr('id') || 'sin-nombre';
            camposEncontrados.textareas.push(nombre);
        });
        
        console.log('[ClientAutofill] Campos encontrados:');
        console.log('   ├─ Inputs:', camposEncontrados.inputs);
        console.log('   ├─ Selects:', camposEncontrados.selects);
        console.log('   └─ Textareas:', camposEncontrados.textareas);
    }

    // Ejecutar diagnóstico al cargar
    setTimeout(diagnosticoFormulario, 1000);

    /* ══════════════════════════════════════════════════════════════
     * LLENADO DE CAMPOS - Búsqueda inteligente
     * ══════════════════════════════════════════════════════════════ */

    /**
     * Busca un campo por múltiples criterios (name o id)
     */
    function buscarCampo(nombres) {
        if (!Array.isArray(nombres)) nombres = [nombres];
        
        for (var i = 0; i < nombres.length; i++) {
            var selector = '[name="' + nombres[i] + '"], #' + nombres[i];
            var $campo = $(selector);
            if ($campo.length > 0) {
                return $campo;
            }
        }
        return $();
    }

    /**
     * Rellena un <input> / <textarea> y activa el floating label de MDB4.
     */
    function setTexto($el, valor) {
        if (!$el.length || !valor) return false;
        $el.val(valor);
        $el.trigger('focus').trigger('input').trigger('blur').trigger('change');
        // Eventos nativos
        try {
            $el[0].dispatchEvent(new Event('input',  { bubbles: true }));
            $el[0].dispatchEvent(new Event('change', { bubbles: true }));
        } catch (e) { /* no-op */ }
        return true;
    }

    /**
     * Selecciona una opción en un <select> buscando por valor o texto (case-insensitive).
     */
    function setSelect($sel, valor) {
        if (!$sel.length || !valor) return false;
        var vl = valor.toLowerCase().trim();
        var ok = false;

        $sel.find('option').each(function () {
            var ov = String($(this).val()).toLowerCase().trim();
            var ot = $(this).text().toLowerCase().trim();
            
            if (ov === vl || ot === vl || ot.indexOf(vl) !== -1) {
                $sel.val($(this).val());
                ok = true;
                return false; /* break */
            }
        });

        if (!ok) return false;

        // Change nativo
        try { $sel[0].dispatchEvent(new Event('change', { bubbles: true })); } catch (e) { /* no-op */ }
        $sel.trigger('change');
        return true;
    }

    /** Rellena todos los campos del remitente con los datos del cliente. */
    function rellenarRemitente(ud) {
        console.log('[ClientAutofill] ─────── RELLENANDO REMITENTE ───────');
        console.log('[ClientAutofill] Datos recibidos:', ud);

        var camposMap = {
            nombre:    ['wpcargo_shipper_name', 'shipper_name', 'nombre_remitente'],
            telefono:  ['wpcargo_shipper_phone', 'shipper_phone', 'telefono_remitente', 'phone'],
            direccion: ['wpcargo_shipper_address', 'shipper_address', 'direccion_remitente', 'address'],
            email:     ['wpcargo_shipper_email', 'shipper_email', 'email_remitente'],
            empresa:   ['wpcargo_tiendaname', 'tienda', 'empresa', 'company'],
            link_maps: ['link_maps_remitente', 'link_maps', 'google_maps']
        };

        var rellenados = 0;
        var noEncontrados = [];

        for (var key in camposMap) {
            if (ud[key]) {
                var $campo = buscarCampo(camposMap[key]);
                if ($campo.length) {
                    if (setTexto($campo, ud[key])) {
                        console.log('[ClientAutofill]   ✓', key + ':', ud[key]);
                        rellenados++;
                    }
                } else {
                    console.warn('[ClientAutofill]   ✗', key, '- campo NO encontrado. Buscado:', camposMap[key]);
                    noEncontrados.push(key);
                }
            }
        }

        // Distrito (es select)
        if (ud.distrito) {
            var $distrito = buscarCampo(['wpcargo_distrito_recojo', 'distrito_recojo']);
            if ($distrito.length) {
                if (setSelect($distrito, ud.distrito)) {
                    console.log('[ClientAutofill]   ✓ distrito:', ud.distrito);
                    rellenados++;
                } else {
                    console.warn('[ClientAutofill]   ✗ distrito - opción no encontrada en select:', ud.distrito);
                }
            } else {
                console.warn('[ClientAutofill]   ✗ distrito - select NO encontrado');
                noEncontrados.push('distrito');
            }
        }

        console.log('[ClientAutofill] ─────────────────────────────────────');
        console.log('[ClientAutofill] Resumen: ' + rellenados + ' campos rellenados');
        if (noEncontrados.length > 0) {
            console.warn('[ClientAutofill] Campos no encontrados:', noEncontrados.join(', '));
        }
        
        return rellenados;
    }

    /* ══════════════════════════════════════════════════════════════
     * AJAX
     * ══════════════════════════════════════════════════════════════ */

    function cargarDatosCliente(userId) {
        if (!userId || userId === '0' || userId === '' || userId === ultimoClienteId || cargando) return;

        console.log('[ClientAutofill] ───> Cargando datos para userId:', userId);
        cargando = true;

        $.post(ajaxurl, {
            action:  'merc_get_client_data',
            nonce:   nonce,
            user_id: userId
        })
        .done(function (resp) {
            console.log('[ClientAutofill] <─── Respuesta AJAX:', resp);
            if (resp && resp.success && resp.data) {
                ultimoClienteId = userId;
                var numCampos = rellenarRemitente(resp.data);
                if (numCampos > 0) {
                    toast('✅ Datos del remitente cargados (' + numCampos + ' campos)', '#4CAF50');
                } else {
                    toast('⚠️ No se pudieron rellenar los campos', '#f39c12');
                }
            } else {
                console.warn('[ClientAutofill] Respuesta sin datos:', resp);
                toast('⚠️ Sin datos para este cliente', '#f39c12');
            }
        })
        .fail(function (xhr) {
            console.error('[ClientAutofill] ❌ Error AJAX:', xhr.status, xhr.responseText);
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
    $(document).on('change', '#registered_client, select[name="registered_shipper"]', function () {
        var v = $(this).val();
        console.log('[ClientAutofill] [EVENT:change] Cliente seleccionado:', v);
        if (v && v !== '0' && v !== '') cargarDatosCliente(v);
    });

    // Select2 dispara este evento en adición a change
    $(document).on('select2:select', '#registered_client, select[name="registered_shipper"]', function (e) {
        var v = (e.params && e.params.data) ? String(e.params.data.id) : $(this).val();
        console.log('[ClientAutofill] [EVENT:select2:select] Cliente seleccionado:', v);
        if (v && v !== '0' && v !== '') cargarDatosCliente(v);
    });

    // También escuchar click como fallback
    $(document).on('click', '#registered_client, select[name="registered_shipper"]', function () {
        setTimeout(function() {
            var $sel = $('#registered_client, select[name="registered_shipper"]').first();
            var v = $sel.val();
            if (v && v !== '0' && v !== '' && v !== ultimoClienteId) {
                console.log('[ClientAutofill] [EVENT:click] Cliente detectado:', v);
                cargarDatosCliente(v);
            }
        }, 200);
    });

    // Polling: comprueba cada 300 ms si el valor del select cambió
    var pollingIntentando = 0;
    setInterval(function () {
        var $sel = $('#registered_client, select[name="registered_shipper"]').first();
        if (!$sel.length) {
            if (pollingIntentando < 3) {
                pollingIntentando++;
                console.warn('[ClientAutofill] [POLLING] Select de cliente no encontrado (intento ' + pollingIntentando + ')');
            }
            return;
        }
        
        var v = $sel.val();
        if (v && v !== ultimoClienteId && v !== '' && v !== '0') {
            console.log('[ClientAutofill] [POLLING] Cambio detectado, val:', v);
            cargarDatosCliente(v);
        }
    }, 300);

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