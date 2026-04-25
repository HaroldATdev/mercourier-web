<script>
    // Variable global para almacenar datos de shipments
    let shipmentsWhatsAppData = {};
    
    // Variable global para almacenar las entregas
    let deliveries = [];
    
    // Variable global para almacenar estados disponibles
    let availableStatuses = [];
    
    // Función para obtener la fecha de hoy en formato Y-m-d
    function getTodayDate() {
        const today = new Date();
        const year = today.getFullYear();
        const month = String(today.getMonth() + 1).padStart(2, '0');
        const day = String(today.getDate()).padStart(2, '0');
        return `${day}/${month}/${year}`;
    }

    // Normalizar varias representaciones de fecha a 'dd/mm/YYYY'
    function normalizeToDMY(dateStr) {
        if (!dateStr) return '';
        dateStr = String(dateStr).trim();
        dateStr = dateStr.replace(/-/g, '/');

        var m = dateStr.match(/^\s*(\d{4})\/(\d{1,2})\/(\d{1,2})\s*$/);
        if (m) {
            var y = m[1], mo = String(m[2]).padStart(2,'0'), d = String(m[3]).padStart(2,'0');
            return d + '/' + mo + '/' + y;
        }

        m = dateStr.match(/^\s*(\d{1,2})\/(\d{1,2})\/(\d{4})\s*$/);
        if (m) {
            var d2 = String(m[1]).padStart(2,'0'), mo2 = String(m[2]).padStart(2,'0'), y2 = m[3];
            return d2 + '/' + mo2 + '/' + y2;
        }

        return dateStr;
    }
    
    // Función para obtener datos de un shipment específico
    async function getShipmentData(postId) {
        return new Promise((resolve, reject) => {
            jQuery.ajax({
                type: "POST",
                url: "<?php echo admin_url('admin-ajax.php'); ?>",
                data: {
                    action: 'wpcpod_get_single_shipment_data',
                    post_id: postId
                },
                success: function(response) {
                    if (response.success) {
                        resolve(response.data);
                    } else {
                        reject(response.data);
                    }
                },
                error: function(xhr, status, error) {
                    reject('Error de conexión: ' + error);
                }
            });
        });
    }
    
    // Función para extraer coordenadas de un enlace de Google Maps
    function extractCoordinatesFromUrl(url) {
        if (!url) return null;
        
        const patterns = [
            /@(-?\d+\.?\d*),(-?\d+\.?\d*)/,
            /q=(-?\d+\.?\d*),(-?\d+\.?\d*)/,
            /ll=(-?\d+\.?\d*),(-?\d+\.?\d*)/,
            /query=(-?\d+\.?\d*),(-?\d+\.?\d*)/
        ];
        
        for (let pattern of patterns) {
            const match = url.match(pattern);
            if (match) {
                return {
                    lat: parseFloat(match[1]),
                    lng: parseFloat(match[2])
                };
            }
        }
        return null;
    }
    
    // Función para limpiar y formatear teléfono
    function formatPhoneNumber(phone) {
        if (!phone) return '';
        
        let cleaned = phone.replace(/[^0-9]/g, '');
        
        if (cleaned && !cleaned.startsWith('51')) {
            cleaned = '51' + cleaned;
        }
        
        return cleaned;
    }
    
    // Función para generar enlaces de navegación
    function generateNavigationLinks(shipmentNumber) {
        return `
            <div class="shipment-actions" style="margin-top: 8px;">
                <button onclick="toggleActions('actions-${shipmentNumber}')" 
                   type="button"
                   style="display: block; background-color: #6c757d; color: white; 
                          padding: 8px 12px; border: none; border-radius: 4px; 
                          font-size: 12px; cursor: pointer; width: 100%; margin-bottom: 8px;">
                    ⚡ Mostrar Acciones
                </button>
                <div id="actions-${shipmentNumber}" style="display: none; border-top: 1px solid #e0e0e0; padding-top: 8px;">
                    <div style="display: flex; flex-direction: column; gap: 6px;">
                        <button onclick="openGoogleMaps('${shipmentNumber}')" 
                           type="button"
                           style="display: block; background-color: #4285f4; color: white; 
                                  padding: 8px 12px; border: none; border-radius: 4px; 
                                  font-size: 12px; text-align: center; font-weight: 500; 
                                  cursor: pointer; width: 100%;">
                            📍 Ver ubicación en Google Maps
                        </button>
                        
                        <button onclick="sendWhatsAppMessage('${shipmentNumber}')" 
                           type="button"
                           style="display: block; background-color: #25D366; color: white; 
                                  padding: 8px 12px; border: none; border-radius: 4px; 
                                  font-size: 12px; text-align: center; font-weight: 500; 
                                  cursor: pointer; width: 100%;">
                            💬 Contactar por WhatsApp
                        </button>
                        
                        <button onclick="sendSupportMessage('${shipmentNumber}')" 
                           type="button"
                           style="display: block; background-color: #dc3545; color: white; 
                                  padding: 8px 12px; border: none; border-radius: 4px; 
                                  font-size: 12px; text-align: center; font-weight: 500; 
                                  cursor: pointer; width: 100%;">
                            🆘 Solicitar soporte a Marca
                        </button>
                    </div>
                </div>
            </div>
        `;
    }
    
    // Función para abrir Google Maps con el link correcto
    async function openGoogleMaps(shipmentNumber) {
        try {
            const delivery = deliveries.find(d => d.number === shipmentNumber);
            
            if (!delivery || !delivery.id) {
                alert('❌ No se encontró el pedido.');
                return;
            }
            
            const data = await getShipmentData(delivery.id);
            
            if (data.link_maps && data.link_maps.trim() !== '') {
                window.open(data.link_maps, '_blank');
            } else {
                alert('❌ No hay link de Google Maps disponible para este pedido.');
            }
            
        } catch (error) {
            alert('❌ Error al obtener el link de Google Maps');
        }
    }
    
    // Función para alternar visibilidad de acciones
    function toggleActions(elementId) {
        const element = document.getElementById(elementId);
        if (element) {
            element.style.display = element.style.display === 'none' ? 'block' : 'none';
        }
    }
    
    // Función para solicitar soporte a la marca
    async function sendSupportMessage(shipmentNumber) {
        jQuery('body').append('<div id="whatsapp-loader" style="position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(0,0,0,0.5); z-index: 9999; display: flex; align-items: center; justify-content: center;"><div style="background: white; padding: 20px; border-radius: 8px; text-align: center;"><div style="font-size: 18px; margin-bottom: 10px;">⏳ Obteniendo datos...</div></div></div>');
        
        try {
            const delivery = deliveries.find(d => d.number === shipmentNumber);
            
            if (!delivery || !delivery.id) {
                jQuery('#whatsapp-loader').remove();
                alert('❌ No se encontró el ID del pedido.');
                return;
            }
            
            const data = await getShipmentData(delivery.id);
            
            jQuery('#whatsapp-loader').remove();
            
            const shipperPhone = formatPhoneNumber(data.shipper_phone);
            
            if (!shipperPhone) {
                alert('❌ No se encontró el teléfono de la marca para este pedido.');
                return;
            }
            
            let mensaje = `¡Hola! Te saluda👋🏼 *${data.motorizado_name}* motorizado🏍️ de MERCourier, tengo una entrega🎁 de tu clienta:\n\n`;
            mensaje += `📦 *Datos de la entrega:*\n`;
            mensaje += `- Número de pedido: *${shipmentNumber}*\n`;
            mensaje += `- Destinatario: *${data.receiver_name || 'No especificado'}*\n`;
            mensaje += `- Dirección: *${data.receiver_address}*\n`;
            mensaje += `- Teléfono: *${formatPhoneNumber(data.receiver_phone)}*\n`;
            if (data.monto > 0) {
                mensaje += `- Monto: *S/. ${data.monto.toFixed(2)}*\n`;
            }
            mensaje += `\nNo me contesta 📞 me podría apoyar con la comunicación🗣️, para que su pedido🛂 se entregado💪🏻 correctamente.\n\n`;
            mensaje += `¡Gracias! Por su apoyo, quedo atento a su respuesta. 😊🤝`;
            
            const mensajeCodificado = encodeURIComponent(mensaje);
            const whatsappUrl = `https://wa.me/${shipperPhone}?text=${mensajeCodificado}`;
            
            window.open(whatsappUrl, '_blank');
            
        } catch (error) {
            jQuery('#whatsapp-loader').remove();
            alert('❌ Error: ' + error);
        }
    }
    
    // Función para enviar mensaje de WhatsApp al cliente
    async function sendWhatsAppMessage(shipmentNumber) {
        jQuery('body').append('<div id="whatsapp-loader" style="position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(0,0,0,0.5); z-index: 9999; display: flex; align-items: center; justify-content: center;"><div style="background: white; padding: 20px; border-radius: 8px; text-align: center;"><div style="font-size: 18px; margin-bottom: 10px;">⏳ Obteniendo datos...</div></div></div>');
        
        try {
            const delivery = deliveries.find(d => d.number === shipmentNumber);
            
            if (!delivery || !delivery.id) {
                jQuery('#whatsapp-loader').remove();
                alert('❌ No se encontró el ID del pedido.');
                return;
            }
            
            const data = await getShipmentData(delivery.id);
            
            jQuery('#whatsapp-loader').remove();
            
            const receiverPhone = formatPhoneNumber(data.receiver_phone);
            
            if (!receiverPhone) {
                alert('❌ No se encontró el teléfono del cliente para este pedido.');
                return;
            }
            
            let mensaje = `¡Hola! Te saluda👋🏼 *${data.motorizado_name}* motorizado🏍️ de MERCourier, tengo una entrega🎁 para ud de parte de la marca *${data.tienda_name}*, me podría confirmar la recepción de su pedido🛂 en la *${data.receiver_address}*.\n\n`;
            mensaje += `⏳ Te notificaremos de 10 a 15 min antes de llegar. El horario de reparto es de 2:30 a 7:30 pm\n\n`;
            
            if (data.monto > 0) {
                mensaje += `💰 Tipo de Pago: YAPE o efectivo (monto exacto S/. ${data.monto.toFixed(2)}).\n\n`;
            } else {
                mensaje += `💰 Tipo de Pago: YAPE o efectivo (monto exacto). Si no tiene ningún cobro omita el aviso 🤓\n\n`;
            }
            
            mensaje += `¡Gracias! Por tu atención`;
            
            const mensajeCodificado = encodeURIComponent(mensaje);
            const whatsappUrl = `https://wa.me/${receiverPhone}?text=${mensajeCodificado}`;
            
            window.open(whatsappUrl, '_blank');
            
        } catch (error) {
            jQuery('#whatsapp-loader').remove();
            alert('❌ Error: ' + error);
        }
    }
    
    // Función para calcular distancia entre dos puntos (fórmula de Haversine)
    function calculateDistance(lat1, lon1, lat2, lon2) {
        const R = 6371;
        const dLat = (lat2 - lat1) * Math.PI / 180;
        const dLon = (lon2 - lon1) * Math.PI / 180;
        const a = Math.sin(dLat/2) * Math.sin(dLat/2) +
                  Math.cos(lat1 * Math.PI / 180) * Math.cos(lat2 * Math.PI / 180) *
                  Math.sin(dLon/2) * Math.sin(dLon/2);
        const c = 2 * Math.atan2(Math.sqrt(a), Math.sqrt(1-a));
        return R * c;
    }
    
    // Función para obtener estados permitidos según el estado actual
    function getPermittedStatuses(currentStatus, allStatuses) {
        const estadosMotorizadoInicial = ['PENDIENTE', 'RECOGIDO', 'NO RECOGIDO'];
        const estadosMotorizadoDespuesBase = ['EN RUTA', 'NO CONTESTA', 'NO RECIBIDO', 'ENTREGADO', 'REPROGRAMADO', 'ANULADO'];
        const estadosAvanzados = ['EN BASE MERCOURIER', 'RECEPCIONADO', 'LISTO PARA SALIR', 'NO CONTESTA', 'EN RUTA', 'NO RECIBIDO', 'ENTREGADO', 'REPROGRAMADO', 'ANULADO'];
        
        if (!allStatuses) {
            return [];
        }
        const currentStatusUpper = (currentStatus || '').toUpperCase();
        let permitidos = [];
        
        const esEstadoAvanzado = estadosAvanzados.some(function(estado) {
            return currentStatusUpper.includes(estado);
        });
        
        if (esEstadoAvanzado) {
            permitidos = allStatuses.filter(function(status) {
                const statusUpper = status.toUpperCase();
                return estadosMotorizadoDespuesBase.some(function(permitido) {
                    return statusUpper.includes(permitido) || permitido.includes(statusUpper);
                });
            });
        } else {
            permitidos = allStatuses.filter(function(status) {
                const statusUpper = status.toUpperCase();
                return estadosMotorizadoInicial.some(function(permitido) {
                    return statusUpper.includes(permitido) || permitido.includes(statusUpper);
                });
            });
        }
        
        permitidos = permitidos.filter(function(opt) {
            return opt.toUpperCase().trim() !== 'LISTO PARA SALIR';
        });
        
        return permitidos;
    }
    
    // Función para actualizar estado de una entrega
    async function updateDeliveryStatus(shipmentId, newStatus) {
        const nonceField = document.getElementById('wpcpod-nonce-field');
        const nonce = nonceField ? nonceField.value : null;
        
        if (!nonce) {
            alert('❌ Error: No se pudo obtener el token de seguridad. Recarga la página');
            console.error('Nonce no encontrado en el elemento HTML');
            return;
        }
        
        if (newStatus === 'ENTREGADO') {
            showSignatureModalForStatus(shipmentId, newStatus, nonce);
        } else if (newStatus === 'NO RECIBIDO') {
            showNoRecibidoModal(shipmentId, newStatus, nonce);
        } else {
            showStatusConfirmation(shipmentId, newStatus, nonce);
        }
    }
    
    // Función para enviar actualización de estado
    async function sendStatusUpdate(shipmentId, newStatus, nonce, formData = null) {
        try {
            const data = {
                action: 'wpcpod_update_delivery_status',
                shipment_id: shipmentId,
                status: newStatus,
                nonce: nonce
            };
            
            if (formData && Array.isArray(formData)) {
                data.formData = JSON.stringify(formData);
                formData.forEach(field => {
                    if (field.name === '__pod_signature') {
                        data.signature = field.value;
                    }
                    if (field.name === 'pod_payment_methods') {
                        data.pod_payment_methods = field.value;
                    }
                    if (field.name === 'wpcargo_total_cobrar') {
                        data.wpcargo_total_cobrar = field.value;
                    }
                    if (field.name === 'remarks') {
                        data.remarks = field.value;
                    }
                });
            }
            
            const response = await jQuery.ajax({
                type: "POST",
                url: "<?php echo admin_url('admin-ajax.php'); ?>",
                data: data
            });
            
            if (response.success) {
                Swal.fire({
                    title: '✅ Éxito',
                    text: response.data.message,
                    icon: 'success',
                    confirmButtonColor: '#3498db',
                    timer: 2000
                });
            } else {
                Swal.fire({
                    title: '❌ Error',
                    text: response.data.message || 'Error al actualizar el estado',
                    icon: 'error',
                    confirmButtonColor: '#e74c3c'
                });
            }
        } catch (error) {
            Swal.fire({
                title: '❌ Error de conexión',
                text: 'No se pudo actualizar el estado. ' + (error.statusText || error.responseText || 'Error desconocido'),
                icon: 'error',
                confirmButtonColor: '#e74c3c'
            });
        }
    }
    
    // Función para mostrar modal de firma reutilizando el modal existente
    function showSignatureModalForStatus(shipmentId, newStatus, nonce) {
        try {
            const $modal = jQuery('#wpc_pod_signature-modal');
            
            if ($modal.length === 0) {
                alert('Error: Modal de firma no encontrado en la página');
                return;
            }
            
            jQuery.ajax({
                type: "POST",
                data: {
                    action: 'show_signaturepad',
                    sid: shipmentId
                },
                url: "<?php echo admin_url('admin-ajax.php'); ?>",
                beforeSend: function() {
                    jQuery('body').append('<div class="wpcargo-loading">Cargando formulario de firma...</div>');
                },
                success: function(response) {
                    try {
                        jQuery('body .wpcargo-loading').remove();
                        
                        const $modalBody = jQuery('#wpc_pod_signature-modal .modal-body');
                        $modalBody.html(response);
                        
                        jQuery('#wpc_pod_signature-modal').modal('show');
                        
                        jQuery('#wpc_pod_signature-modal #wpc_pod_signature-form').off('submit').on('submit', function(e) {
                            e.preventDefault();
                            const formData = jQuery(this).serializeArray();
                            sendStatusUpdate(shipmentId, newStatus, nonce, formData);
                            jQuery('#wpc_pod_signature-modal').modal('hide');
                        });
                        
                    } catch(e) {
                        alert('Error al mostrar modal: ' + e.message);
                    }
                },
                error: function(xhr, status, error) {
                    jQuery('body .wpcargo-loading').remove();
                    alert('❌ Error al cargar el formulario de firma: ' + error);
                }
            });
        } catch(e) {
            console.error('❌ Error general:', e.message);
        }
    }

    function showNoRecibidoModal(shipmentId, newStatus, nonce) {
        getShipmentData(shipmentId).then(function(data) {
            const amountToReceive = parseFloat(data.monto || data.total || data.wpcargo_total_cobrar || data.monto_cobrar || 0) || 0;
            const modalId = 'wpc_pod_no_recibido_modal';
            let $modal = jQuery('#' + modalId);

            if ($modal.length === 0) {
                const modalHtml = `
                <div class="modal fade" id="${modalId}" tabindex="-1" role="dialog" aria-labelledby="noRecibidoModalLabel" aria-hidden="true">
                    <div class="modal-dialog modal-lg modal-dialog-centered" role="document">
                        <div class="modal-content">
                            <div class="modal-header">
                                <h5 class="modal-title" id="noRecibidoModalLabel">NO RECIBIDO - Intento fallido</h5>
                                <button type="button" class="close" data-dismiss="modal" aria-label="Cerrar">
                                    <span aria-hidden="true">&times;</span>
                                </button>
                            </div>
                            <div class="modal-body" style="max-height:70vh; overflow-y:auto;">
                                <form id="wpc_pod_no_recibido_form">
                                    <input type="hidden" name="__pod_id" value="${shipmentId}">
                                    <input type="hidden" name="status" value="${newStatus}">
                                    <input type="hidden" name="pod_payment_methods" id="nr-pod_payment_methods" value="[]">
                                    <input type="hidden" name="wpcargo_total_cobrar" id="nr-wpcargo_total_cobrar" value="${amountToReceive.toFixed(2)}">
                                    <div style="margin-bottom:20px;">
                                        <p style="font-weight:bold; margin-bottom:5px;">Total a recibir</p>
                                        <div style="font-size:22px; font-weight:700; color:#333; margin-bottom:10px;">S/. ${amountToReceive.toFixed(2)}</div>
                                        <div id="nr-amount-status" style="font-size:14px; color:#555;">Se podrá actualizar aunque el pago quede incompleto.</div>
                                    </div>
                                    <div style="border:1px solid #e2e2e2; border-radius:8px; padding:16px; margin-bottom:20px; background:#f7f7f7;">
                                        <h6 style="margin-top:0; margin-bottom:12px;">1. Fotos del intento (opcional)</h6>
                                        <button type="button" id="nr-pod-img-btn" class="btn btn-success" style="margin-bottom:10px;">➕ Añadir imagen</button>
                                        <input type="file" id="nr-pod-file-input" multiple accept="image/*" style="display:none;">
                                        <input type="file" id="nr-pod-camera-input" accept="image/*" capture="camera" style="display:none;">
                                        <div id="nr-pod-images" style="display:flex; flex-wrap:wrap; gap:10px;"> </div>
                                    </div>
                                    <div style="border:1px solid #e2e2e2; border-radius:8px; padding:16px; margin-bottom:20px; background:#f7f7f7;">
                                        <h6 style="margin-top:0; margin-bottom:12px;">2. Observaciones</h6>
                                        <textarea id="nr-remarks" name="remarks" class="form-control" rows="4" placeholder="Ingrese observaciones adicionales sobre este intento..." style="width:100%; resize:vertical;"></textarea>
                                    </div>
                                    <div style="border:1px solid #e2e2e2; border-radius:8px; padding:16px; margin-bottom:20px; background:#f7f7f7;">
                                        <h6 style="margin-top:0; margin-bottom:12px;">3. Métodos de pago (opcional)</h6>
                                        <div id="nr-payment-methods-list"></div>
                                        <button type="button" id="nr-add-method" class="btn btn-primary" style="margin-top:10px;">➕ Agregar método de pago</button>
                                        <div style="margin-top:14px; font-size:14px; color:#333;">
                                            <strong>Total ingresado: S/. <span id="nr-total-ingresado">0.00</span></strong><br>
                                            <span id="nr-missing-amount" style="color:#d9534f; font-weight:600;">Falta S/. ${amountToReceive.toFixed(2)}</span>
                                        </div>
                                        <div id="nr-payment-note" style="margin-top:8px; font-size:13px; color:#555;">El botón se mantendrá habilitado. El pago puede quedar incompleto.</div>
                                    </div>
                                    <div style="display:flex; justify-content:flex-end; gap:10px;">
                                        <button type="button" class="btn btn-secondary" data-dismiss="modal">Cancelar</button>
                                        <button type="submit" id="nr-submit-button" class="btn btn-success">Actualizar y cambiar estado</button>
                                    </div>
                                </form>
                            </div>
                        </div>
                    </div>
                </div>
                `;
                jQuery('body').append(modalHtml);
                $modal = jQuery('#' + modalId);
                bindNoRecibidoModalEvents(shipmentId, nonce, amountToReceive);
            } else {
                jQuery('#nr-wpcargo_total_cobrar').val(amountToReceive.toFixed(2));
                bindNoRecibidoModalEvents(shipmentId, nonce, amountToReceive);
            }

            $modal.modal('show');
        }).catch(function(error) {
            Swal.fire({
                title: '❌ Error',
                text: 'No se pudo cargar los datos del pedido para NO RECIBIDO. ' + error,
                icon: 'error',
                confirmButtonColor: '#e74c3c'
            });
        });
    }

    function bindNoRecibidoModalEvents(shipmentId, nonce, amountToReceive) {
        const $modal = jQuery('#wpc_pod_no_recibido_modal');
        if (!$modal.length) return;

        const $form = $modal.find('#wpc_pod_no_recibido_form');
        const $imagesContainer = $modal.find('#nr-pod-images');
        const $totalIngresado = $modal.find('#nr-total-ingresado');
        const $missingAmount = $modal.find('#nr-missing-amount');
        const $podPaymentMethods = $modal.find('#nr-pod_payment_methods');
        const $wpcargoTotalCobrar = $modal.find('#nr-wpcargo_total_cobrar');

        function formatAmount(value) {
            return parseFloat(value || 0).toFixed(2);
        }

        function updatePaymentSummary() {
            let total = 0;
            $modal.find('.nr-fila-metodo').each(function() {
                const montoStr = jQuery(this).find('.nr-pay-amount').val().trim();
                const monto = parseFloat(montoStr.replace(/[^0-9.]/g, '')) || 0;
                total += monto;
            });
            $totalIngresado.text(formatAmount(total));
            const faltante = parseFloat(amountToReceive) - total;
            if (faltante <= 0) {
                $missingAmount.text(`No falta monto (${formatAmount(Math.abs(faltante))} de más)`).css('color', '#28a745');
            } else {
                $missingAmount.text(`Falta S/. ${formatAmount(faltante)}`).css('color', '#d9534f');
            }
            updatePaymentMethodsInput();
        }

        function updatePaymentMethodsInput() {
            const arr = [];
            $modal.find('.nr-fila-metodo').each(function() {
                const $fila = jQuery(this);
                const metodo = $fila.find('.nr-pay-method').val().trim();
                const monto = parseFloat($fila.find('.nr-pay-amount').val().trim().replace(/[^0-9.]/g, '')) || 0;
                const imagen = $fila.data('imageBase64') || '';
                const imagen_nombre = $fila.data('imageName') || '';
                if (!metodo) return;
                const item = { metodo: metodo, monto: monto };
                if (imagen) {
                    item.imagen = imagen;
                    item.imagen_nombre = imagen_nombre;
                }
                arr.push(item);
            });
            $podPaymentMethods.val(JSON.stringify(arr));
        }

        function createMethodRow() {
            const row = jQuery(`
                <div class="nr-fila-metodo" style="border:1px solid #ccc;padding:12px;margin-bottom:10px;border-radius:5px;position:relative;">
                    <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:10px;">
                        <strong style="font-size:14px;">Método de pago</strong>
                        <button type="button" class="btn btn-sm btn-danger nr-remove-method" style="padding:4px 8px;">Eliminar</button>
                    </div>
                    <div style="margin-bottom:10px; position:relative;">
                        <button type="button" class="btn btn-light nr-select-method" style="width:100%; text-align:left;">Seleccionar método</button>
                        <div class="nr-method-options" style="display:none; position:absolute; top:44px; left:0; width:100%; background:#fff; border:1px solid #ddd; border-radius:4px; z-index:2500;">
                            <div class="nr-method-option" data-value="efectivo" style="padding:10px; cursor:pointer;">Pago a motorizado</div>
                            <div class="nr-method-option" data-value="pago_marca" style="padding:10px; cursor:pointer;">Pago a Marca</div>
                            <div class="nr-method-option" data-value="pago_merc" style="padding:10px; cursor:pointer;">Pago a MERC</div>
                            <div class="nr-method-option" data-value="pos" style="padding:10px; cursor:pointer;">POS</div>
                        </div>
                    </div>
                    <input type="hidden" class="nr-pay-method" value="">
                    <div style="margin-bottom:10px;">
                        <label><strong>Monto</strong></label>
                        <input type="text" class="form-control nr-pay-amount" placeholder="0.00" inputmode="decimal" style="width:100%;">
                    </div>
                    <div style="margin-bottom:10px;">
                        <label><strong>Imagen del comprobante</strong></label>
                        <input type="file" class="form-control nr-pay-image" accept="image/*" style="width:100%;">
                        <div class="nr-image-preview" style="margin-top:8px;"></div>
                    </div>
                </div>
            `);
            return row;
        }

        function readFileAsDataURL(file) {
            return new Promise((resolve, reject) => {
                const reader = new FileReader();
                reader.onload = function(event) {
                    resolve(event.target.result);
                };
                reader.onerror = reject;
                reader.readAsDataURL(file);
            });
        }

        function addPaymentMethodRow() {
            const $row = createMethodRow();
            $modal.find('#nr-payment-methods-list').append($row);
            updatePaymentSummary();
        }

        $modal.find('#nr-pod-img-btn').off('click').on('click', function(e) {
            e.preventDefault();
            const swalAvailable = typeof window.Swal !== 'undefined' || typeof window.swal !== 'undefined';
            const SwalLib = window.Swal || window.swal;
            if (!swalAvailable || !SwalLib || typeof SwalLib.fire !== 'function') {
                $modal.find('#nr-pod-file-input').click();
                return;
            }
            SwalLib.fire({
                title: 'Agregar imagen',
                text: '¿Cómo deseas agregar la imagen?',
                icon: 'question',
                showCancelButton: true,
                confirmButtonText: '📷 Tomar foto',
                cancelButtonText: '🖼️ Subir imagen',
                cancelButtonColor: '#3085d6',
                confirmButtonColor: '#28a745',
                reverseButtons: false
            }).then(function(result) {
                if (result.isConfirmed) {
                    $modal.find('#nr-pod-camera-input').val('').click();
                } else if (result.dismiss === SwalLib.DismissReason.cancel) {
                    $modal.find('#nr-pod-file-input').val('').click();
                }
            });
        });

        function uploadNoRecibidoImages(files) {
            if (!files || !files.length) return;
            const formData = new FormData();
            let valid = 0;
            const validTypes = ['image/png','image/jpeg','image/jpg','image/gif','image/svg+xml'];
            for (let i=0;i<files.length;i++) {
                const file = files[i];
                if (validTypes.includes(file.type)) {
                    formData.append('files[]', file);
                    valid++;
                }
            }
            if (valid === 0) {
                Swal.fire({ title:'❌ Error', text:'Selecciona imágenes válidas', icon:'error', confirmButtonColor:'#e74c3c' });
                return;
            }
            formData.append('action','wpcpod_direct_upload_image');
            formData.append('shipmentID', shipmentId);
            formData.append('nonce', nonce);
            const $button = $modal.find('#nr-pod-img-btn');
            const originalText = $button.text();
            $button.prop('disabled', true).text('⏳ Subiendo...');
            jQuery.ajax({
                type:'POST',
                url:'<?php echo admin_url('admin-ajax.php'); ?>',
                data: formData,
                processData:false,
                contentType:false,
                timeout: 120000,
                success:function(response){
                    $button.prop('disabled', false).text(originalText);
                    if (response.success) {
                        $modal.find('#nr-pod-images').html(response.html);
                    } else {
                        Swal.fire({ title:'❌ Error', text: response.message || 'Error al subir imágenes', icon:'error', confirmButtonColor:'#e74c3c' });
                    }
                },
                error:function(xhr,status,error){
                    $button.prop('disabled', false).text(originalText);
                    Swal.fire({ title:'❌ Error', text: 'Error de conexión: ' + error, icon:'error', confirmButtonColor:'#e74c3c' });
                }
            });
        }

        $modal.find('#nr-pod-file-input').off('change').on('change', function() {
            uploadNoRecibidoImages(this.files);
            jQuery(this).val('');
        });
        $modal.find('#nr-pod-camera-input').off('change').on('change', function() {
            uploadNoRecibidoImages(this.files);
            jQuery(this).val('');
        });

        $modal.off('click', '.delete-attachment').on('click', '.delete-attachment', function(){
            const $thumb = jQuery(this).closest('.gallery-thumb');
            const attchID = $thumb.data('id');
            if (!attchID) return;
            $thumb.addClass('d-none');
            jQuery.ajax({
                type:'POST',
                url:'<?php echo admin_url('admin-ajax.php'); ?>',
                data:{ action:'wpcpod_delete_image', shipmentID: shipmentId, attchID: attchID },
                success:function(response){
                    if (response.status) {
                        $thumb.remove();
                    } else {
                        $thumb.removeClass('d-none');
                        Swal.fire({ title:'❌ Error', text: response.message || 'No se pudo eliminar la imagen', icon:'error', confirmButtonColor:'#e74c3c' });
                    }
                },
                error:function(){
                    $thumb.removeClass('d-none');
                    Swal.fire({ title:'❌ Error', text:'No se pudo eliminar la imagen. Intenta de nuevo.', icon:'error', confirmButtonColor:'#e74c3c' });
                }
            });
        });

        $modal.off('click', '.nr-select-method').on('click', '.nr-select-method', function(e){
            e.preventDefault();
            const $button = jQuery(this);
            const $options = $button.siblings('.nr-method-options');
            $options.toggle();
        });

        $modal.off('click', '.nr-method-option').on('click', '.nr-method-option', function(){
            const $option = jQuery(this);
            const metodo = $option.data('value');
            const texto = $option.text();
            const $fila = $option.closest('.nr-fila-metodo');
            $fila.find('.nr-select-method').text(texto);
            $fila.find('.nr-pay-method').val(metodo);
            $fila.find('.nr-method-options').hide();
            updatePaymentSummary();
        });

        $modal.off('click', '.nr-remove-method').on('click', '.nr-remove-method', function(){
            jQuery(this).closest('.nr-fila-metodo').remove();
            updatePaymentSummary();
        });

        $modal.off('input', '.nr-pay-amount').on('input', '.nr-pay-amount', function(){
            updatePaymentSummary();
        });

        $modal.off('change', '.nr-pay-image').on('change', '.nr-pay-image', function(){
            const file = this.files[0];
            const $fila = jQuery(this).closest('.nr-fila-metodo');
            const $preview = $fila.find('.nr-image-preview');
            if (!file) {
                $fila.removeData('imageBase64').removeData('imageName');
                $preview.html('');
                updatePaymentSummary();
                return;
            }
            readFileAsDataURL(file).then(function(base64){
                $fila.data('imageBase64', base64);
                $fila.data('imageName', file.name || 'comprobante.jpg');
                $preview.html(`<img src="${base64}" style="max-width:140px; max-height:140px; border-radius:6px; border:1px solid #ccc; margin-top:6px;"><button type="button" class="btn btn-sm btn-danger nr-remove-image" style="display:block; margin-top:8px;">Eliminar</button>`);
                updatePaymentSummary();
            });
        });

        $modal.off('click', '.nr-remove-image').on('click', '.nr-remove-image', function(){
            const $fila = jQuery(this).closest('.nr-fila-metodo');
            $fila.find('.nr-pay-image').val('');
            $fila.removeData('imageBase64').removeData('imageName');
            $fila.find('.nr-image-preview').html('');
            updatePaymentSummary();
        });

        $modal.find('#nr-add-method').off('click').on('click', function() {
            addPaymentMethodRow();
        });

        $form.off('submit').on('submit', function(e) {
            e.preventDefault();
            updatePaymentSummary();
            const formData = jQuery(this).serializeArray();
            sendStatusUpdate(shipmentId, newStatus, nonce, formData);
            $modal.modal('hide');
        });

        addPaymentMethodRow();
        updatePaymentSummary();
    }

    // Función para mostrar confirmación de estado (sin firma)
    function showStatusConfirmation(shipmentId, newStatus, nonce) {
        Swal.fire({
            title: '⚠️ Confirmar cambio de estado',
            text: `¿Deseas cambiar el estado a "${newStatus}"?`,
            icon: 'warning',
            showCancelButton: true,
            confirmButtonColor: '#3498db',
            cancelButtonColor: '#6c757d',
            confirmButtonText: 'Sí, cambiar',
            cancelButtonText: 'Cancelar',
            allowOutsideClick: false,
            allowEscapeKey: false
        }).then(async (result) => {
            if (result.isConfirmed) {
                await sendStatusUpdate(shipmentId, newStatus, nonce);
            }
        });
    }
    
    function initPODRouteMap() {
        $('#wpcpod-route-planner #wpcpod-route-map').hide();
        
        const today = getTodayDate();
        
        jQuery.ajax({
            type: "POST",
            url: "<?php echo admin_url('admin-ajax.php'); ?>",
            data: {
                action: 'wpcpod_get_all_possible_statuses'
            },
            success: function(response) {
                if (response.success) {
                    availableStatuses = response.data;
                }
            }
        });
        
        jQuery.ajax({
            type:"POST",
            data:{
                action  : 'wpcpod_generate_route_address',
                filter_date: today
            },
            url : "<?php echo admin_url( 'admin-ajax.php' ); ?>",
            success:function(response){
                if( response.status == 'success'){
                    displayShipmentsList(response.origin, response.waypoints, response.shipments, response.poo);
                }else{
                    $('#wpcpod-route-planner #wpcpod-route-map').remove();
                    $('#wpcpod-route-planner #wpcpod-route-loader').remove();
                    $('#wpcpod-route-planner #route-planner-content').append('<div class="my-4 alert alert-info text-center">'+response.message+'</div>')
                }              
            }
        });
    }
    
    function displayShipmentsList(origin, waypoints, shipments, poo) {
        const summaryPanel = document.getElementById("directions-panel");
        
        deliveries = [];
        
        const today = getTodayDate();
        
        if (shipments && shipments.length > 0) {
            shipments.forEach((shipment, index) => {
                const shipmentDateRaw = shipment['pickup_date'] || shipment['shipping_date'] || '';
                const shipmentDate = normalizeToDMY(shipmentDateRaw);

                if (shipmentDate && shipmentDate !== today) {
                    return;
                }
                
                let address = shipment['address'] || 'Dirección no disponible';
                let linkMaps = shipment['link_maps'] || shipment['address'] || '';
                let lat = shipment['lat'] || null;
                let lng = shipment['lng'] || null;
                
                if ((!lat || !lng) && address) {
                    const coords = extractCoordinatesFromUrl(address);
                    if (coords) {
                        lat = coords.lat;
                        lng = coords.lng;
                    }
                }
                
                let distance = 0;
                if (origin && origin.lat && origin.lng && lat && lng) {
                    distance = calculateDistance(origin.lat, origin.lng, lat, lng);
                }
                
                deliveries.push({
                    id: shipment['id'] || null,
                    number: shipment['number'] || 'N/A',
                    receiver_name: shipment['receiver_name'] || '',
                    address: address,
                    link_maps: linkMaps,
                    info: shipment['info'] || {},
                    lat: lat,
                    lng: lng,
                    distance: distance,
                    pickup_date: shipmentDate,
                    status: shipment['status'] || 'N/A'
                });
            });
        }
        
        deliveries.sort((a, b) => a.distance - b.distance);
        
        let listHTML = '<div style="font-family: Arial, sans-serif; max-width: 900px; margin: 0 auto;">';
        listHTML += `<h3 style="margin-bottom: 20px; color: #333; text-align: center; padding: 15px; background: #f8f9fa; border-radius: 8px;">📋 Lista de Entregas - ${today}</h3>`;
        
        if (deliveries.length === 0) {
            listHTML += '<div class="alert alert-warning" style="padding: 15px; background: #fff3cd; border: 1px solid #ffc107; border-radius: 5px; text-align: center;">⚠️ No se encontraron pedidos para entregar hoy</div>';
        } else {
            deliveries.forEach((delivery, index) => {
                const permittedStatuses = getPermittedStatuses(delivery.status, availableStatuses);
                
                let statusOptions = '';
                if (delivery.status) {
                    statusOptions += `<option value="${delivery.status}" selected>${delivery.status}</option>`;
                }
                if (permittedStatuses && permittedStatuses.length > 0) {
                    permittedStatuses.forEach(status => {
                        if (status !== delivery.status) {
                            statusOptions += `<option value="${status}">${status}</option>`;
                        }
                    });
                }

                // URL de tracking: mismo formato confirmado del dashboard
                const trackingUrl = `<?php echo home_url('/'); ?>dashboard/?wpcfe=track&num=${delivery.number}`;

                // Nombre del destinatario con fallback al número
                const receiverDisplay = delivery.receiver_name
                    ? delivery.receiver_name
                    : `Pedido ${delivery.number}`;

                listHTML += `
                    <div style="border: 2px solid #dee2e6; border-radius: 8px; padding: 15px; margin-bottom: 15px; background: white; box-shadow: 0 2px 4px rgba(0,0,0,0.1);">
                        
                        <!-- ═══ HEADER: nombre + select estado ═══ -->
                        <div style="display: flex; justify-content: space-between; align-items: flex-start; margin-bottom: 10px; padding-bottom: 10px; border-bottom: 1px solid #e9ecef; gap: 10px;">
                            
                            <!-- Nombre del destinatario + badge número de seguimiento -->
                            <div style="display: flex; flex-direction: column; gap: 5px;">
                                <span style="font-weight: bold; font-size: 17px; color: #007bff;">
                                    ${index + 1}. ${receiverDisplay}
                                </span>
                                <!-- NUEVO: Badge clickeable con número de seguimiento -->
                                <a href="${trackingUrl}"
                                   target="_blank"
                                   title="Ver hoja de tracking en WPCargo"
                                   style="display: inline-flex; align-items: center; gap: 5px;
                                          background: #343a40; color: #ffffff;
                                          padding: 4px 11px; border-radius: 20px;
                                          font-size: 12px; font-weight: bold;
                                          text-decoration: none; width: fit-content;">
                                    🔍 ${delivery.number}
                                </a>
                            </div>

                            <!-- Select de estado -->
                            <select onchange="updateDeliveryStatus(${delivery.id}, this.value)" 
                               style="background: #fff; color: #333; padding: 6px 10px; border: 1px solid #dee2e6; border-radius: 4px; font-size: 12px; font-weight: bold; cursor: pointer; min-width: 120px; flex-shrink: 0;">
                                ${statusOptions}
                            </select>
                        </div>

                        <!-- Dirección -->
                        <div style="font-size: 14px; color: #555; margin-bottom: 8px;">
                            <strong>📍 Dirección:</strong> ${delivery.address}
                        </div>
                `;
                
                if (delivery.info && Object.keys(delivery.info).length > 0) {
                    for (const [key, value] of Object.entries(delivery.info)) {
                        if (value) {
                            listHTML += `<div style="font-size: 13px; color: #666; margin-bottom: 4px;">${value}</div>`;
                        }
                    }
                }
                
                listHTML += generateNavigationLinks(delivery.number);
                listHTML += '</div>';
            });
        }
        
        listHTML += '</div>';
        summaryPanel.innerHTML = listHTML;
        
        $('#wpcpod-route-planner #wpcpod-route-loader').remove();
    }
    
    jQuery(document).ready(function(){
        initPODRouteMap();
    });
</script>
