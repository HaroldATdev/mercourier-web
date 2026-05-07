jQuery(document).ready(function($) {

    var urlParams = new URLSearchParams(window.location.search);
    var isBulk = window.location.pathname.indexOf('importacion-masiva') !== -1 ||
                 window.location.pathname.indexOf('bulk-import') !== -1 ||
                 window.location.pathname.indexOf('envios-masivos') !== -1;

    if (isBulk) { return; }

    var tipoEnvio = urlParams.get('type') || 'normal';

    /**
     * Calcula la próxima fecha disponible (YYYY-MM-DD) dado el estado de bloqueos.
     */
    function calcularProximaFecha(bloquear_hoy, fechas_bloqueadas, domingos_desbloqueados) {
        var date = new Date();
        date.setHours(0, 0, 0, 0);
        if (bloquear_hoy) {
            date.setDate(date.getDate() + 1);
        }

        for (var i = 0; i < 60; i++) {
            var year  = date.getFullYear();
            var month = ('0' + (date.getMonth() + 1)).slice(-2);
            var day   = ('0' + date.getDate()).slice(-2);
            var ds    = year + '-' + month + '-' + day;

            if (date.getDay() === 0) {
                if ((domingos_desbloqueados || []).indexOf(ds) === -1) {
                    date.setDate(date.getDate() + 1);
                    continue;
                }
            }

            if ((fechas_bloqueadas || []).indexOf(ds) !== -1) {
                date.setDate(date.getDate() + 1);
                continue;
            }

            return ds;
        }
        return '';
    }

    console.log('[MERC] Iniciando calendar-block.js, tipoEnvio:', tipoEnvio);

    $.post(mercBloqueos.ajax_url, {
        action: 'merc_bloqueo_info',
        tipo: tipoEnvio
    }).done(function(res) {
        if (!res.success) {
            console.error('[MERC] Error en AJAX merc_bloqueo_info:', res);
            return;
        }

        var data = res.data;
        console.log('[MERC] Bloqueo Info recibido:', data);

        // ── Bloqueo total de cuenta ──────────────────────────────────────────────
        function aplicarBloqueoTotal() {
            var forms = $('#wpcfe-add-shipment-form, .wpcargo-frontend-bulk-import, .wpcargo-bulk-import');
            if (forms.length > 0) {
                forms.hide();
                if ($('.merc-bloqueo-total-alert').length === 0) {
                    var el = document.createElement('div');
                    el.className = 'alert alert-danger merc-bloqueo-total-alert';
                    el.style.cssText = 'margin-top:20px;font-size:16px;padding:20px;background:#fee2e2;border:1px solid #ef4444;color:#b91c1c;border-radius:8px;';
                    el.textContent = 'Tu cuenta se encuentra bloqueada para realizar envíos. Por favor comunícate con administración.';
                    forms.first()[0].parentNode.insertBefore(el, forms.first()[0]);
                }
                return true;
            }
            return false;
        }

        if (data.bloqueo_total) {
            if (!aplicarBloqueoTotal()) {
                var totalInterval = setInterval(function() {
                    if (aplicarBloqueoTotal()) clearInterval(totalInterval);
                }, 500);
            }
            return;
        }

        // ── Variables de estado ──────────────────────────────────────────────────
        var domingos_desbloqueados = data.domingos_desbloqueados || [];
        var fechas_bloqueadas      = data.fechas_bloqueadas || [];
        var isAdmin                = (data._debug_reason === 'is_admin');

        var hoy      = new Date();
        var todayIso = hoy.getFullYear() + '-' + ('0' + (hoy.getMonth() + 1)).slice(-2) + '-' + ('0' + hoy.getDate()).slice(-2);

        // Admin: siempre hoy. Cliente: calcular próxima fecha hábil.
        var proximaFecha = isAdmin
            ? todayIso
            : calcularProximaFecha(data.bloquear_hoy, fechas_bloqueadas, domingos_desbloqueados);

        // Para Flatpickr: minDate
        var todayObj = new Date();
        todayObj.setHours(0, 0, 0, 0);
        var minDate = isAdmin
            ? null
            : (data.bloquear_hoy ? new Date(todayObj.getTime() + 86400000) : 'today');

        // Función de disable para Flatpickr
        var disableConfig = [function(date) {
            var y  = date.getFullYear();
            var m  = ('0' + (date.getMonth() + 1)).slice(-2);
            var d  = ('0' + date.getDate()).slice(-2);
            var ds = y + '-' + m + '-' + d;
            if (date.getDay() === 0) {
                return domingos_desbloqueados.indexOf(ds) === -1;
            }
            return fechas_bloqueadas.indexOf(ds) !== -1;
        }];

        // ── Aviso de fecha cambiada ──────────────────────────────────────────────
        function mostrarAvisoFechaCambiada() {
            if (document.getElementById('merc-aviso-fecha-cambiada')) return;
            var avisoEl = document.createElement('div');
            avisoEl.id = 'merc-aviso-fecha-cambiada';
            avisoEl.style.cssText = 'position:fixed;top:20px;left:50%;transform:translateX(-50%);z-index:999999;background-color:#ef4444;color:white;padding:15px 25px;border-radius:8px;box-shadow:0 10px 25px rgba(239,68,68,0.4);max-width:90%;width:500px;text-align:center;font-size:15px;line-height:1.4;transition:opacity 0.5s ease-in-out;';
            avisoEl.innerHTML = '⚠️ <strong>ATENCIÓN:</strong> La fecha ha cambiado para el día de mañana (o el próximo día hábil) por estar fuera de horario. Su pedido no será agendado para hoy. Si requiere que se gestione hoy, comuníquese con nosotros de inmediato.';
            document.body.appendChild(avisoEl);
            setTimeout(function() {
                avisoEl.style.opacity = '0';
                setTimeout(function() {
                    if (avisoEl.parentNode) avisoEl.parentNode.removeChild(avisoEl);
                }, 500);
            }, 10000);
        }

        // ── Campos obligatorios + validación al enviar ───────────────────────────
        function aplicarCamposObligatorios() {
            var $form = $('#wpcfe-add-shipment-form');
            if ($form.length === 0) return false;

            $form.find('input, select, textarea')
                .not('[type="hidden"],[type="button"],[type="submit"],[type="checkbox"],[type="radio"],[readonly]')
                .not('[name="wpcargo_comments"],[name="wpcargo_comments[]"]')
                .attr('required', 'required');

            if (!$form.data('merc-submit-bound')) {
                $form.data('merc-submit-bound', true);
                $form.on('submit', function(e) {
                    var fi = $form.find('input[name="wpcargo_pickup_date_picker"],input[name="calendarenvio"]').first();
                    if (!fi.val() || !fi.val().trim()) {
                        e.preventDefault();
                        fi.addClass('is-invalid');
                        if (!document.getElementById('merc-fecha-error-msg')) {
                            var errEl = document.createElement('div');
                            errEl.id = 'merc-fecha-error-msg';
                            errEl.className = 'invalid-feedback';
                            errEl.style.cssText = 'display:block;color:#dc3545;font-size:13px;margin-top:4px;';
                            errEl.textContent = 'La fecha de envío es obligatoria.';
                            fi[0].parentNode.insertBefore(errEl, fi[0].nextSibling);
                        }
                        fi[0].scrollIntoView({ behavior: 'smooth', block: 'center' });
                        return false;
                    }
                });
            }
            return true;
        }

        // ── Función de disable para Pickadate ────────────────────────────────────
        function disableConfigPickadate() {
            var conf = [1]; // domingo deshabilitado por defecto
            fechas_bloqueadas.forEach(function(f) {
                var p = f.split('-');
                conf.push([parseInt(p[0]), parseInt(p[1]) - 1, parseInt(p[2])]);
            });
            domingos_desbloqueados.forEach(function(f) {
                var p = f.split('-');
                conf.push([parseInt(p[0]), parseInt(p[1]) - 1, parseInt(p[2]), 'inverted']);
            });
            return conf;
        }

        // ── Lógica principal: buscar inputs y aplicar reglas ─────────────────────
        function buscarYAplicarReglas() {
            var inputs = $('input[name="wpcargo_pickup_date_picker"],input[name="calendarenvio"],.wpcfe-datepicker,.wpccf-datepicker');
            if (inputs.length === 0) return false;

            console.log('[MERC] Inputs encontrados:', inputs.length, '| isAdmin:', isAdmin, '| proximaFecha:', proximaFecha);

            inputs.each(function() {
                var input    = $(this);
                var currentVal = input.val() || '';
                var fp       = input[0]._flatpickr || (typeof input[0].flatpickr === 'object' ? input[0].flatpickr : null);

                if (fp) {
                    // ── Flatpickr ──
                    console.log('[MERC] Flatpickr detectado, valor actual:', currentVal);

                    if (isAdmin) {
                        fp.set('disable', []);
                        fp.set('minDate', null);
                        // Admin: autocompletar con hoy si el campo está vacío
                        if (!currentVal) {
                            fp.setDate(proximaFecha, true);
                        }
                    } else {
                        // Cliente: aplicar restricciones
                        fp.set('disable', disableConfig);
                        fp.set('minDate', minDate);
                        // Autocompletar solo si el campo está vacío
                        if (!currentVal) {
                            fp.setDate(proximaFecha, true);
                            if (proximaFecha !== todayIso) {
                                mostrarAvisoFechaCambiada();
                            }
                        }
                    }
                    input.attr('required', 'required');

                } else {
                    // ── Pickadate fallback ──
                    var picker = input.pickadate ? input.pickadate('picker') : null;
                    if (picker) {
                        console.log('[MERC] Pickadate detectado, valor actual:', currentVal);

                        if (isAdmin) {
                            picker.set('min', false);
                            picker.set('disable', false);
                            if (!currentVal) {
                                var pA = proximaFecha.split('-');
                                picker.set('select', new Date(pA[0], pA[1] - 1, pA[2]));
                            }
                        } else {
                            picker.set('disable', false);
                            picker.set('disable', disableConfigPickadate());
                            picker.set('min', data.bloquear_hoy ? 1 : true);
                            if (!currentVal) {
                                var p = proximaFecha.split('-');
                                picker.set('select', new Date(p[0], p[1] - 1, p[2]));
                                if (proximaFecha !== todayIso) {
                                    mostrarAvisoFechaCambiada();
                                }
                            }
                        }
                        input.attr('required', 'required');

                    } else {
                        console.log('[MERC] No se encontró instancia de calendario (ni Flatpickr ni Pickadate).');
                    }
                }
            });

            aplicarCamposObligatorios();
            return true;
        }

        // ── Ejecutar (con polling si el form carga tarde) ────────────────────────
        if (!buscarYAplicarReglas()) {
            var att = 0;
            var poll = setInterval(function() {
                att++;
                if (buscarYAplicarReglas() || att > 20) clearInterval(poll);
            }, 500);
        }
    });
});

