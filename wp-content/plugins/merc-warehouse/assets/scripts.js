document.addEventListener('DOMContentLoaded', function () {
    console.log('Merc Warehouse cargado');

    // Si mercAlmacenData no esta disponible, intentamos sin nonce
    const nonce = (typeof window.mercAlmacenData !== 'undefined') ? window.mercAlmacenData.nonce : '';
    const isAdmin = (typeof window.mercAlmacenData !== 'undefined') ? window.mercAlmacenData.isAdmin : false;

    // Obtener URL AJAX
    let ajaxUrl = (typeof window.mercAlmacenData !== 'undefined' && window.mercAlmacenData.ajaxUrl) ? window.mercAlmacenData.ajaxUrl : '';
    if (!ajaxUrl) {
        ajaxUrl = (typeof ajaxurl !== 'undefined') ? ajaxurl : '/wp-admin/admin-ajax.php';
    }

    console.log('Configuracion:', { nonce: !!nonce, isAdmin, ajaxUrl });

    // Funcion para cargar productos
    function cargarProductos() {
        console.log('Iniciando carga de productos...');

        // Obtener el ID real del cliente/remitente
        const shipperIdInput = document.getElementById('shipper_id');

        // Preparar datos para el AJAX
        const formData = new URLSearchParams({
            action: 'merc_almacen_get_productos',

            // Enviar ID numerico del cliente
            cliente_id: shipperIdInput ? shipperIdInput.value : ''
        });
        // Anadir nonce si esta disponible
        if (nonce) {
            formData.append('nonce', nonce);
        }

        // Cargar datos del almacen
        fetch(ajaxUrl, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/x-www-form-urlencoded',
            },
            body: formData.toString()
        })
            .then(r => {
                console.log('Respuesta HTTP:', r.status);
                return r.json();
            })
            .then(res => {
                console.log('Respuesta AJAX:', res);
                if (res.success && res.data && res.data.productos) {
                    console.log('Productos cargados:', res.data.productos.length);
                    renderProducts(res.data.productos);
                    updateStats(res.data.productos);
                } else {
                    console.warn('Error en respuesta:', res);
                    const tabla = document.getElementById('almacen-tabla');
                    if (tabla) {
                        const errorMsg = (res.data && res.data.message) ? res.data.message : 'Error al cargar productos';
                        tabla.innerHTML = `<p style="padding:20px;color:#e74c3c;">${errorMsg}</p>`;
                    }
                }
            })
            .catch(error => {
                console.error('Error en AJAX:', error);
                const tabla = document.getElementById('almacen-tabla');
                if (tabla) {
                    tabla.innerHTML = '<p style="padding:20px;color:#e74c3c;">Error de conexion: ' + error.message + '</p>';
                }
            });
    }

    function renderProducts(productos) {
        const container = document.getElementById('almacen-tabla');
        if (!container) {
            console.error('Contenedor almacen-tabla no encontrado');
            return;
        }

        console.log('Renderizando productos:', productos.length);

        if (!productos || productos.length === 0) {
            container.innerHTML = '<div style="padding:40px;text-align:center;color:#7f8c8d;"><p>ðŸ“¦ No hay productos en el almacen</p></div>';
            return;
        }

        // Agrupar productos por cliente (billing_company o cliente_nombre)
        const grupos = {};
        productos.forEach(p => {
            let cliente = '';
            if (p.billing_company && p.billing_company.trim() !== '') {
                cliente = p.billing_company.trim();
            } else if (p.cliente_nombre && p.cliente_nombre.trim() !== '') {
                cliente = p.cliente_nombre.trim();
            } else {
                cliente = 'Sin Cliente';
            }
            if (!grupos[cliente]) grupos[cliente] = [];
            grupos[cliente].push(p);
        });

        let html = '';
        const clientesOrdenados = Object.keys(grupos).sort();

        clientesOrdenados.forEach(cliente => {
            const prods = grupos[cliente];
            const totalCliente = prods.reduce((a, p) => a + (parseInt(p.cantidad) || 0), 0);
            const grupoId = 'grupo-' + cliente.replace(/[^a-z0-9]/gi, '_');

            html += `<div class="grupo-cliente" style="margin-bottom: 15px; border-radius: 8px; overflow: hidden; box-shadow: 0 2px 8px rgba(0,0,0,0.1);">`;
            html += `<button class="grupo-cliente-header" data-grupo="${grupoId}" style="display: flex; align-items: center; gap: 14px; padding: 16px 20px; background: linear-gradient(90deg, #2c3e50 0%, #34495e 100%); color: white; cursor: pointer; user-select: none; font-size: 16px; font-weight: 700; border: none; width: 100%; text-align: left; transition: background 0.3s;">`;
            html += `&#128100; ${cliente} <span style="font-size: 13px; font-weight: 400; opacity: 0.85; margin-left: auto;">${prods.length} producto(s) &middot; ${totalCliente} unidades</span>`;
            html += `<span class="grupo-cliente-chevron" style="font-size: 14px; margin-left: auto; transition: transform 0.25s; transform: rotate(-90deg);">&#9660;</span>`;
            html += `</button>`;
            html += `<div class="grupo-cliente-body" id="${grupoId}" style="background: white; display: none;">`;

            // Tabla de productos del grupo
            html += '<table style="width: 100%; border-collapse: collapse;">';
            html += '<thead><tr style="background: #f8f9fa; border-bottom: 2px solid #dee2e6;">';
            html += '<th style="padding: 12px 12px; text-align: center; font-weight: 600; font-size: 13px; color: #495057; width:52px;">Foto</th>';
            html += '<th style="padding: 12px 20px; text-align: left; font-weight: 600; font-size: 13px; color: #495057;">Producto</th>';
            html += '<th style="padding: 12px 20px; text-align: center; font-weight: 600; font-size: 13px; color: #495057;">Stock</th>';
            html += '<th style="padding: 12px 20px; text-align: left; font-weight: 600; font-size: 13px; color: #495057;">Tipo + Medida</th>';
            html += '<th style="padding: 12px 20px; text-align: left; font-weight: 600; font-size: 13px; color: #495057;">Creado</th>';
            html += '<th style="padding: 12px 20px; text-align: left; font-weight: 600; font-size: 13px; color: #495057;">Modificado</th>';
            html += '<th style="padding: 12px 20px; text-align: center; font-weight: 600; font-size: 13px; color: #495057;">Estado</th>';
            html += '<th style="padding: 12px 20px; text-align: center; font-weight: 600; font-size: 13px; color: #495057;">Motorizado</th>';
            if (isAdmin) {
                html += '<th style="padding: 12px 20px; text-align: center; font-weight: 600; font-size: 13px; color: #495057;">Acciones</th>';
            }
            html += '</tr></thead>';
            html += '<tbody>';

            prods.forEach((prod, idx) => {
                const estadoText = prod.estado === 'asignado' ? 'Asignado' : (prod.estado === 'entregado' ? 'Entregado' : 'Sin Asignar');
                const bgcolor = idx % 2 === 0 ? 'white' : '#f9f9f9';

                // Tipo + valor de medida combinados
                let tipoMedidaLabel = '-';
                if (prod.tipo_medida && prod.tipo_medida !== '') {
                    const tipoCapital = prod.tipo_medida.charAt(0).toUpperCase() + prod.tipo_medida.slice(1);
                    tipoMedidaLabel = prod.valor_medida && prod.valor_medida !== ''
                        ? `${tipoCapital}: ${prod.valor_medida}`
                        : tipoCapital;
                } else if (prod.valor_medida && prod.valor_medida !== '') {
                    tipoMedidaLabel = prod.valor_medida;
                }

                const motorizado = prod.motorizado && prod.motorizado !== '-' ? prod.motorizado : '-';
                const fotoHtml = prod.foto_url
                    ? `<img src="${prod.foto_url}" style="width:40px;height:40px;object-fit:cover;border-radius:5px;display:block;margin:auto;" />`
                    : `<span style="display:block;text-align:center;font-size:22px;color:#ccc;" title="Sin foto">[foto]</span>`;

                html += `<tr style="background: ${bgcolor}; border-bottom: 1px solid #ecf0f1;">`;
                html += `<td style="padding: 8px 12px; text-align:center; vertical-align:middle;">${fotoHtml}</td>`;
                html += `<td style="padding: 12px 20px;"><strong>${prod.nombre || 'Sin nombre'}</strong></td>`;
                html += `<td style="padding: 12px 20px; text-align: center;"><button class="btn-ver-cantidad" data-product-id="${prod.id}" data-product-name="${prod.nombre}" style="background: none; border: none; cursor: pointer; color: #1976d2; font-weight: 700; font-size: 14px; padding: 4px 10px; border-radius: 4px; transition: background-color 0.3s;" title="Ver unidades">${prod.cantidad || 0} uds.</button></td>`;
                html += `<td style="padding: 12px 20px; font-size: 13px; color: #7f8c8d;">${tipoMedidaLabel}</td>`;
                html += `<td style="padding: 12px 20px; font-size: 13px; color: #7f8c8d;">${prod.fecha_creacion || '-'}</td>`;
                html += `<td style="padding: 12px 20px; font-size: 13px; color: #7f8c8d;">${prod.fecha_modificacion || '-'}</td>`;
                html += `<td style="padding: 12px 20px; text-align: center;"><span style="display: inline-block; padding: 6px 12px; background: #e9ecef; color: #495057; border-radius: 6px; font-size: 12px; font-weight: 600;">${estadoText}</span></td>`;
                html += `<td style="padding: 12px 20px; text-align: center; color: #2c3e50; font-weight: 600;">${motorizado}</td>`;
                if (isAdmin) {
                    html += `<td style="padding: 8px 12px; text-align: center;">`;
                    html += `<div style="display:flex;flex-wrap:wrap;gap:5px;justify-content:center;">`;
                    html += `<button class="btn-edit" onclick="window.editarProducto(${prod.id})" style="background: #f39c12; color: white; border: none; padding: 5px 10px; border-radius: 4px; cursor: pointer; font-weight: 600; font-size: 12px;">Editar</button>`;
                    html += `<button class="btn-ingreso" data-product-id="${prod.id}" data-product-name="${prod.nombre}" style="background: #27ae60; color: white; border: none; padding: 5px 10px; border-radius: 4px; cursor: pointer; font-weight: 600; font-size: 12px;">+ Ingreso</button>`;
                    html += `<button class="btn-egreso" data-product-id="${prod.id}" data-product-name="${prod.nombre}" data-stock="${prod.cantidad || 0}" style="background: #e74c3c; color: white; border: none; padding: 5px 10px; border-radius: 4px; cursor: pointer; font-weight: 600; font-size: 12px;">- Egreso</button>`;
                    html += `<button class="btn-delete" onclick="window.eliminarProducto(${prod.id}, '${prod.nombre.replace(/'/g, "\\'")}', ${parseInt(prod.cantidad) || 0})" style="background: #95a5a6; color: white; border: none; padding: 5px 10px; border-radius: 4px; cursor: pointer; font-weight: 600; font-size: 12px;">Eliminar</button>`;
                    html += `</div></td>`;
                }
            });

            html += '</tbody>';
            html += '</table>';
            html += '</div>';
            html += '</div>';
        });

        container.innerHTML = html;

        // Event listeners para botones de cantidad (ver envios)
        document.querySelectorAll('.btn-ver-cantidad').forEach(btn => {
            btn.addEventListener('click', function (e) {
                e.preventDefault();
                const productId = this.dataset.productId;
                const productName = this.dataset.productName;
                window.openUnitsModal(productId, productName);
            });
        });

        // Agregar listeners para colapsar/expandir grupos
        document.querySelectorAll('.grupo-cliente-header').forEach(btn => {
            btn.addEventListener('click', function () {
                const grupoId = this.getAttribute('data-grupo');
                const body = document.getElementById(grupoId);
                const chevron = this.querySelector('.grupo-cliente-chevron');

                if (body.style.display === 'none') {
                    body.style.display = 'block';
                    chevron.style.transform = 'rotate(0deg)';
                } else {
                    body.style.display = 'none';
                    chevron.style.transform = 'rotate(-90deg)';
                }
            });
        });

        // Listeners botones Ingreso
        document.querySelectorAll('.btn-ingreso').forEach(btn => {
            btn.addEventListener('click', function () {
                abrirModalIngreso(this.dataset.productId, this.dataset.productName);
            });
        });

        // Listeners botones Egreso
        document.querySelectorAll('.btn-egreso').forEach(btn => {
            btn.addEventListener('click', function () {
                abrirModalEgreso(this.dataset.productId, this.dataset.productName, parseInt(this.dataset.stock) || 0);
            });
        });
    }

    function updateStats(productos) {
        console.log('Actualizando estadisticas...');

        // Contar usuarios unicos
        const usuarios = new Set();
        productos.forEach(p => {
            if (p.cliente_asignado) usuarios.add(p.cliente_asignado);
        });

        // Contar totales
        const total = productos.reduce((acc, p) => acc + (parseInt(p.cantidad) || 0), 0);
        const asignados = productos.reduce((acc, p) => acc + (p.estado === 'asignado' ? (parseInt(p.cantidad) || 0) : 0), 0);
        const entregados = productos.reduce((acc, p) => acc + (p.estado === 'entregado' ? (parseInt(p.cantidad) || 0) : 0), 0);

        const statUsuarios = document.getElementById('stat-usuarios');
        const statTotal = document.getElementById('stat-total');
        const statAsignados = document.getElementById('stat-asignados');
        const statEntregados = document.getElementById('stat-entregados');

        if (statUsuarios) statUsuarios.textContent = usuarios.size;
        if (statTotal) statTotal.textContent = total;
        if (statAsignados) statAsignados.textContent = asignados;
        if (statEntregados) statEntregados.textContent = entregados;

        console.log('Estadisticas actualizadas:', { usuarios: usuarios.size, total, asignados, entregados });
    }
    // -----------------------------------------------------------------------
    // MODAL: INGRESO DE MERCADERIA
    // -----------------------------------------------------------------------
    function abrirModalIngreso(productId, productName) {
        console.log('Abriendo modal ingreso para:', productId, productName);
        Swal.fire({
            title: 'Registrar Ingreso',
            html: '<p style="color:#555;margin-bottom:12px;">Producto: <strong>' + productName + '</strong></p>' +
                '<div style="text-align:left;margin-bottom:10px;"><label style="font-weight:600;font-size:13px;display:block;margin-bottom:4px;">Cantidad a ingresar *</label>' +
                '<input id="swal-cantidad-ingreso" type="number" min="1" value="1" class="swal2-input" style="width:100%;margin:0;" /></div>' +
                '<div style="text-align:left;"><label style="font-weight:600;font-size:13px;display:block;margin-bottom:4px;">Notas (opcional)</label>' +
                '<textarea id="swal-notas-ingreso" class="swal2-textarea" placeholder="Observaciones..." style="width:100%;margin:0;height:70px;"></textarea></div>',
            showCancelButton: true,
            confirmButtonColor: '#27ae60',
            cancelButtonColor: '#95a5a6',
            confirmButtonText: 'Registrar Ingreso',
            cancelButtonText: 'Cancelar',
            preConfirm: function () {
                var c = parseInt(document.getElementById('swal-cantidad-ingreso').value);
                if (!c || c < 1) { Swal.showValidationMessage('La cantidad debe ser mayor a 0'); return false; }
                return { cantidad: c, notas: document.getElementById('swal-notas-ingreso').value };
            }
        }).then(function (result) {
            if (!result.isConfirmed) return;
            fetch(ajaxUrl, {
                method: 'POST',
                headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                body: new URLSearchParams({ action: 'merc_registrar_ingreso', product_id: productId, cantidad: result.value.cantidad, notas: result.value.notas, nonce: nonce })
            }).then(function (r) { return r.json(); }).then(function (res) {
                if (res.success) {
                    Swal.fire({ icon: 'success', title: 'Ingreso registrado', text: res.data.message, confirmButtonColor: '#27ae60' }).then(function () { cargarProductos(); });
                } else {
                    Swal.fire({ icon: 'error', title: 'Error', text: (res.data && res.data.message) || 'Error desconocido', confirmButtonColor: '#e74c3c' });
                }
            });
        });
    }

    // -----------------------------------------------------------------------
    // MODAL: EGRESO DE MERCADERIA
    // -----------------------------------------------------------------------
    function abrirModalEgreso(productId, productName, stockActual) {
        console.log('Abriendo modal egreso para:', productId, productName, stockActual);
        Swal.fire({
            title: 'Registrar Egreso',
            html: '<p style="color:#555;margin-bottom:12px;">Producto: <strong>' + productName + '</strong> &mdash; Stock disponible: <strong>' + stockActual + '</strong></p>' +
                '<div style="text-align:left;margin-bottom:10px;"><label style="font-weight:600;font-size:13px;display:block;margin-bottom:4px;">Cantidad a egresar *</label>' +
                '<input id="swal-cantidad-egreso" type="number" min="1" max="' + stockActual + '" value="1" class="swal2-input" style="width:100%;margin:0;" /></div>' +
                '<div style="text-align:left;margin-bottom:10px;"><label style="font-weight:600;font-size:13px;display:block;margin-bottom:4px;">Motivo *</label>' +
                '<select id="swal-motivo-egreso" class="swal2-input" style="width:100%;margin:0;height:40px;font-size:14px;">' +
                '<option value="">-- Seleccionar motivo --</option>' +
                '<option value="merma">Merma / Dano</option>' +
                '<option value="devolucion_cliente">Devolucion al cliente</option>' +
                '<option value="perdida">Perdida</option>' +
                '<option value="ajuste">Ajuste de inventario</option>' +
                '<option value="otro">Otro</option></select></div>' +
                '<div style="text-align:left;"><label style="font-weight:600;font-size:13px;display:block;margin-bottom:4px;">Notas <span id="swal-nota-req" style="color:#e74c3c;display:none;">(requerido para Otro)</span></label>' +
                '<textarea id="swal-notas-egreso" class="swal2-textarea" placeholder="Observaciones..." style="width:100%;margin:0;height:70px;"></textarea></div>',
            showCancelButton: true,
            confirmButtonColor: '#e74c3c',
            cancelButtonColor: '#95a5a6',
            confirmButtonText: 'Registrar Egreso',
            cancelButtonText: 'Cancelar',
            didOpen: function () {
                document.getElementById('swal-motivo-egreso').addEventListener('change', function () {
                    document.getElementById('swal-nota-req').style.display = this.value === 'otro' ? 'inline' : 'none';
                });
            },
            preConfirm: function () {
                var c = parseInt(document.getElementById('swal-cantidad-egreso').value);
                var m = document.getElementById('swal-motivo-egreso').value;
                var n = document.getElementById('swal-notas-egreso').value;
                if (!c || c < 1) { Swal.showValidationMessage('Cantidad invalida'); return false; }
                if (c > stockActual) { Swal.showValidationMessage('Solo hay ' + stockActual + ' unidades disponibles'); return false; }
                if (!m) { Swal.showValidationMessage('Debes seleccionar un motivo'); return false; }
                if (m === 'otro' && !n.trim()) { Swal.showValidationMessage('Las notas son obligatorias para "Otro"'); return false; }
                return { cantidad: c, motivo: m, notas: n };
            }
        }).then(function (result) {
            if (!result.isConfirmed) return;
            fetch(ajaxUrl, {
                method: 'POST',
                headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                body: new URLSearchParams({ action: 'merc_registrar_egreso', product_id: productId, cantidad: result.value.cantidad, motivo: result.value.motivo, notas: result.value.notas, nonce: nonce })
            }).then(function (r) { return r.json(); }).then(function (res) {
                if (res.success) {
                    Swal.fire({ icon: 'success', title: 'Egreso registrado', text: res.data.message, confirmButtonColor: '#27ae60' }).then(function () { cargarProductos(); });
                } else {
                    Swal.fire({ icon: 'error', title: 'Error', text: (res.data && res.data.message) || 'Error desconocido', confirmButtonColor: '#e74c3c' });
                }
            });
        });
    }

    // Cargar productos inicialmente
    cargarProductos();

    // Recargar productos automaticamente cuando cambie el cliente del envio
    const clienteInput = document.getElementById('shipper_id');

    if (clienteInput) {
        clienteInput.addEventListener('change', function () {
            console.log('Cliente cambiado, recargando productos...');
            cargarProductos();
        });
    }

    // Handler para boton "Nuevo Producto"
    const btnNuevo = document.getElementById('btn-nuevo');
    if (btnNuevo) {
        btnNuevo.addEventListener('click', function () {
            console.log('Abriendo modal de nuevo producto');
            abrirModalNuevoProducto();
        });
    } else {
        console.warn('Boton btn-nuevo no encontrado');
    }

    // Funcion para abrir modal
    function abrirModalNuevoProducto() {
        let modal = document.getElementById('modal-nuevo-producto');

        // Si el modal ya existe (por ejemplo no fue removido), asegurarnos de resetear el formulario
        if (modal) {
            try {
                const existingForm = modal.querySelector('#form-nuevo-producto');
                if (existingForm) {
                    existingForm.reset();
                    // establecer valor por defecto para cantidad
                    const cantidadInput = existingForm.querySelector('input[name="cantidad"]');
                    if (cantidadInput) cantidadInput.value = 1;
                }
                // recargar clientes en el select
                cargarClientesParaNuevoProducto(modal);
            } catch (e) {
                console.warn('No se pudo resetear modal existente:', e);
            }
        }

        if (!modal) {
            // Crear modal si no existe (similar al de functions.php)
            modal = document.createElement('div');
            modal.id = 'modal-nuevo-producto';
            modal.style.cssText = `
                position: fixed;
                top: 0;
                left: 0;
                width: 100%;
                height: 100%;
                background: rgba(0, 0, 0, 0.6);
                display: flex;
                justify-content: center;
                align-items: center;
                z-index: 9999;
            `;

            modal.innerHTML = `
                <div class="modal-box" style="position: relative; background: white; border-radius: 12px; width: 90%; max-width: 500px; max-height: 90vh; display: flex; flex-direction: column; box-shadow: 0 10px 40px rgba(0,0,0,0.3);">
                    <div class="modal-header" style="display: flex; justify-content: space-between; align-items: center; padding: 15px 20px; border-bottom: 1px solid #ecf0f1; flex-shrink: 0;">
                        <h3 style="margin: 0; font-size: 20px; color: #2c3e50;">📦 Crear Nuevo Producto</h3>
                        <button class="modal-close-btn" style="background: none; border: none; font-size: 24px; color: #7f8c8d; cursor: pointer; padding: 0; line-height: 1; width: 30px; height: 30px; display: flex; align-items: center; justify-content: center;">&times;</button>
                    </div>
                    
                    <form id="form-nuevo-producto" style="overflow-y: auto; flex: 1;">
                        <div class="form-group" style="padding: 12px 20px;">
                            <label style="display: block; margin-bottom: 5px; font-weight: 600; color: #2c3e50; font-size: 14px;">Nombre del Producto *</label>
                            <input type="text" name="nombre" required placeholder="Nombre del producto" style="width: 100%; padding: 10px 12px; border: 2px solid #dfe6e9; border-radius: 6px; font-size: 14px; box-sizing: border-box;">
                        </div>
                        
                        <div class="form-group" style="padding: 12px 20px;">
                            <label style="display: block; margin-bottom: 5px; font-weight: 600; color: #2c3e50; font-size: 14px;">Codigo de Barras <small>(opcional)</small></label>
                            <input type="text" name="codigo_barras" placeholder="Codigo o SKU" style="width: 100%; padding: 10px 12px; border: 2px solid #dfe6e9; border-radius: 6px; font-size: 14px; box-sizing: border-box;">
                            <small style="color: #7f8c8d; font-size: 12px; display: block; margin-top: 3px;">📦 Codigo unico para identificar el producto</small>
                        </div>
                        
                        <div class="form-group" style="padding: 12px 20px;">
                            <label style="display: block; margin-bottom: 5px; font-weight: 600; color: #2c3e50; font-size: 14px;">Cantidad *</label>
                            <input type="number" name="cantidad" min="1" required placeholder="0" value="1" style="width: 100%; padding: 10px 12px; border: 2px solid #dfe6e9; border-radius: 6px; font-size: 14px; box-sizing: border-box;">
                        </div>
                        
                        <div class="form-group" style="padding: 12px 20px;">
                            <label style="display: block; margin-bottom: 5px; font-weight: 600; color: #2c3e50; font-size: 14px;">Cliente Asignado <small>(opcional)</small></label>
                            <select id="cliente-select" name="cliente_asignado" style="width: 100%; padding: 10px 12px; border: 2px solid #dfe6e9; border-radius: 6px; font-size: 14px; box-sizing: border-box;">
                                <option value="">-- Seleccionar cliente --</option>
                            </select>
                            <small style="color: #7f8c8d; font-size: 12px; display: block; margin-top: 3px;">👤 Selecciona un cliente para asignar este producto</small>
                        </div>
                        
                        <div class="form-group" style="padding: 12px 20px;">
                            <label style="display: block; margin-bottom: 5px; font-weight: 600; color: #2c3e50; font-size: 14px;">Peso (kg) <small>(opcional)</small></label>
                            <input type="number" name="peso" min="0" step="0.01" placeholder="0.00" style="width: 100%; padding: 10px 12px; border: 2px solid #dfe6e9; border-radius: 6px; font-size: 14px; box-sizing: border-box;">
                            <small style="color: #7f8c8d; font-size: 12px; display: block; margin-top: 3px;">⚖️ Peso del producto en kilogramos</small>
                        </div>
                        
                        <div class="form-group" style="padding: 12px 20px;">
                            <label style="display: block; margin-bottom: 5px; font-weight: 600; color: #2c3e50; font-size: 14px;">Tipo de Medida <small>(opcional)</small></label>
                            <select name="tipo_medida" style="width: 100%; padding: 10px 12px; border: 2px solid #dfe6e9; border-radius: 6px; font-size: 14px; box-sizing: border-box;">
                                <option value="">-- Seleccionar --</option>
                                <option value="talla">Talla</option>
                                <option value="color">Color</option>
                                <option value="modelo">Modelo</option>
                                <option value="otro">Otro</option>
                            </select>
                            <small style="color: #7f8c8d; font-size: 12px; display: block; margin-top: 3px;">📏 Tipo de medida del producto</small>
                        </div>
                        
                        <div class="form-group" style="padding: 12px 20px;">
                            <label style="display: block; margin-bottom: 5px; font-weight: 600; color: #2c3e50; font-size: 14px;">Valor de Medida <small>(opcional)</small></label>
                            <input type="text" name="valor_medida" placeholder="Ej: S, M, L, XL o 100ml" style="width: 100%; padding: 10px 12px; border: 2px solid #dfe6e9; border-radius: 6px; font-size: 14px; box-sizing: border-box;">
                            <small style="color: #7f8c8d; font-size: 12px; display: block; margin-top: 3px;">📏 Valor especifico de la medida (talla, color, etc.)</small>
                        </div>
                        
                        <div class="form-group" style="padding: 12px 20px;">
                            <label style="display: block; margin-bottom: 5px; font-weight: 600; color: #2c3e50; font-size: 14px;">Dimensiones (cm) <small>(opcional)</small></label>
                            <div style="display: flex; flex-direction: column; gap: 8px;">
                                <input type="number" name="largo" min="0" step="0.1" placeholder="Largo (cm)" style="width: 100%; padding: 10px 12px; border: 2px solid #dfe6e9; border-radius: 6px; font-size: 14px; box-sizing: border-box;">
                                <input type="number" name="ancho" min="0" step="0.1" placeholder="Ancho (cm)" style="width: 100%; padding: 10px 12px; border: 2px solid #dfe6e9; border-radius: 6px; font-size: 14px; box-sizing: border-box;">
                                <input type="number" name="alto" min="0" step="0.1" placeholder="Alto (cm)" style="width: 100%; padding: 10px 12px; border: 2px solid #dfe6e9; border-radius: 6px; font-size: 14px; box-sizing: border-box;">
                            </div>
                            <small style="color: #7f8c8d; font-size: 12px; display: block; margin-top: 3px;">📦 Ingresa las dimensiones en centimetros</small>
                        </div>
                    </form>
                    
                    <div class="modal-footer" style="display: flex; gap: 10px; justify-content: flex-end; padding: 15px 20px; border-top: 1px solid #ecf0f1; background: #f8f9fa; flex-shrink: 0;">
                        <button type="button" class="modal-close-btn" style="background: #6c757d; color: white; box-shadow: 0 2px 6px rgba(108, 117, 125, 0.3); padding: 10px 24px; font-size: 14px; border: none; border-radius: 6px; font-weight: 600; cursor: pointer;">Cancelar</button>
                        <button type="submit" form="form-nuevo-producto" style="background: #3498db; color: white; box-shadow: 0 2px 6px rgba(52, 152, 219, 0.3); padding: 10px 24px; font-size: 14px; border: none; border-radius: 6px; font-weight: 600; cursor: pointer;">Guardar Producto</button>
                    </div>
                </div>
            `;

            document.body.appendChild(modal);

            // Cargar clientes en el select del modal nuevo
            cargarClientesParaNuevoProducto(modal);

            // Cerrar modal: removemos el nodo para limpiar estado
            document.querySelectorAll('.modal-close-btn').forEach(btn => {
                btn.addEventListener('click', function () {
                    modal.style.display = 'none';
                    modal.remove();
                });
            });

            // Cerrar al hacer click en el backdrop: remover nodo
            modal.addEventListener('click', function (e) {
                if (e.target === modal) {
                    modal.style.display = 'none';
                    modal.remove();
                }
            });

            // Submit del formulario
            document.getElementById('form-nuevo-producto').addEventListener('submit', function (e) {
                e.preventDefault();
                console.log('Guardando nuevo producto...');
                const formData = new FormData(this);

                // Obtener cliente seleccionado directamente del select
                const clienteSelect = document.getElementById('cliente-select');
                const clienteId = clienteSelect ? clienteSelect.value : 0;

                const datos = {
                    action: 'merc_guardar_producto',
                    nombre: formData.get('nombre'),
                    codigo_barras: formData.get('codigo_barras'),
                    cantidad: parseInt(formData.get('cantidad')) || 1,
                    cliente_asignado: clienteId,
                    tipo_medida: formData.get('tipo_medida') || '',
                    valor_medida: formData.get('valor_medida') || '',
                    peso: parseFloat(formData.get('peso')) || 0,
                    largo: parseFloat(formData.get('largo')) || 0,
                    ancho: parseFloat(formData.get('ancho')) || 0,
                    alto: parseFloat(formData.get('alto')) || 0
                };

                if (nonce) {
                    datos.nonce = nonce;
                }

                console.log('Datos a enviar:', datos);

                fetch(ajaxUrl, {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/x-www-form-urlencoded',
                    },
                    body: new URLSearchParams(datos)
                })
                    .then(r => r.json())
                    .then(res => {
                        console.log('Respuesta guardado:', res);
                        if (res.success) {
                            Swal.fire({
                                icon: 'success',
                                title: '&#201;xito',
                                text: 'Producto creado exitosamente',
                                confirmButtonColor: '#3498db'
                            }).then(() => {
                                // Resetear formulario para garantizar estado limpio
                                try {
                                    const f = document.getElementById('form-nuevo-producto');
                                    if (f) f.reset();
                                } catch (e) { /* noop */ }
                                // Remover modal y recargar tabla
                                modal.style.display = 'none';
                                try { modal.remove(); } catch (err) { /* noop */ }
                                cargarProductos(); // Recargar tabla
                            });
                        } else {
                            Swal.fire({
                                icon: 'error',
                                title: 'Error',
                                text: res.data || 'Error desconocido',
                                confirmButtonColor: '#e74c3c'
                            });
                        }
                    })
                    .catch(err => {
                        console.error('Error:', err);
                        Swal.fire({
                            icon: 'error',
                            title: 'Error de conexion',
                            text: 'No se pudo conectar con el servidor',
                            confirmButtonColor: '#e74c3c'
                        });
                    });
            });
        }

        // Mostrar modal (si fue creado ahora o ya existia)
        if (modal) modal.style.display = 'flex';
    }

    // Carga la lista de clientes y la inyecta en el select dentro del modal (nuevo)
    function cargarClientesParaNuevoProducto(modalElement) {
        if (!modalElement) return;
        const select = modalElement.querySelector('#cliente-select');
        if (!select) return;

        // Vaciar opciones previas y dejar placeholder
        select.innerHTML = '<option value="">-- Seleccionar cliente --</option>';

        fetch(ajaxUrl, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/x-www-form-urlencoded',
            },
            body: new URLSearchParams({ action: 'merc_obtener_clientes_lista', nonce: nonce })
        })
            .then(r => r.json())
            .then(res => {
                if (res.success && res.data && res.data.clientes) {
                    res.data.clientes.forEach(cliente => {
                        const opt = document.createElement('option');
                        opt.value = String(cliente.id);
                        opt.textContent = cliente.nombre;
                        select.appendChild(opt);
                    });
                } else {
                    console.warn('No se obtuvieron clientes para nuevo producto', res);
                }
            })
            .catch(err => {
                console.error('Error cargando clientes para nuevo producto:', err);
            });
    }

    // Funcion global para editar producto
    window.editarProducto = function (id) {
        console.log('Editando producto ID:', id);
        abrirModalEditarProducto(id);
    };

    // Funcion para abrir modal de edicion
    function abrirModalEditarProducto(productId) {
        console.log('Abriendo modal de edicion para producto:', productId);

        // Primero obtener los datos del producto
        fetch(ajaxUrl, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/x-www-form-urlencoded',
            },
            body: new URLSearchParams({
                action: 'merc_obtener_producto',
                product_id: productId,
                nonce: nonce
            })
        })
            .then(r => r.json())
            .then(res => {
                console.log('Datos del producto:', res);
                if (res.success && res.data) {
                    mostrarModalEdicion(res.data);
                } else {
                    Swal.fire({
                        icon: 'error',
                        title: 'Error',
                        text: 'Error al cargar los datos del producto',
                        confirmButtonColor: '#e74c3c'
                    });
                }
            })
            .catch(err => {
                console.error('Error:', err);
                Swal.fire({
                    icon: 'error',
                    title: 'Error de conexion',
                    text: 'No se pudo conectar con el servidor',
                    confirmButtonColor: '#e74c3c'
                });
            });
    }

    function mostrarModalEdicion(producto) {
        let modal = document.getElementById('modal-editar-producto');

        // Remover modal anterior si existe
        if (modal) {
            modal.remove();
        }

        // Crear nuevo modal
        modal = document.createElement('div');
        modal.id = 'modal-editar-producto';
        modal.style.cssText = `
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(0, 0, 0, 0.6);
            display: flex;
            justify-content: center;
            align-items: center;
            z-index: 9999;
        `;

        modal.innerHTML = `
            <div class="modal-box" style="position: relative; background: white; border-radius: 12px; width: 90%; max-width: 500px; max-height: 90vh; display: flex; flex-direction: column; box-shadow: 0 10px 40px rgba(0,0,0,0.3);">
                <div class="modal-header" style="display: flex; justify-content: space-between; align-items: center; padding: 15px 20px; border-bottom: 1px solid #ecf0f1; flex-shrink: 0;">
                    <h3 style="margin: 0; font-size: 20px; color: #2c3e50;">&#9998; Editar Producto</h3>
                    <button class="modal-close-btn" style="background: none; border: none; font-size: 24px; color: #7f8c8d; cursor: pointer; padding: 0; line-height: 1; width: 30px; height: 30px; display: flex; align-items: center; justify-content: center;">&times;</button>
                </div>
                
                <form id="form-editar-producto" style="overflow-y: auto; flex: 1;">
                    <input type="hidden" id="producto-id" value="${producto.id}">
                    
                    <div class="form-group" style="padding: 12px 20px;">
                        <label style="display: block; margin-bottom: 5px; font-weight: 600; color: #2c3e50; font-size: 14px;">Nombre del Producto *</label>
                        <input type="text" id="edit-nombre" value="${producto.nombre || ''}" required style="width: 100%; padding: 10px 12px; border: 2px solid #dfe6e9; border-radius: 6px; font-size: 14px; box-sizing: border-box;">
                    </div>
                    
                    <div class="form-group" style="padding: 12px 20px;">
                        <label style="display: block; margin-bottom: 5px; font-weight: 600; color: #2c3e50; font-size: 14px;">Codigo de Barras <small>(opcional)</small></label>
                        <input type="text" id="edit-codigo" value="${producto.codigo_barras || ''}" style="width: 100%; padding: 10px 12px; border: 2px solid #dfe6e9; border-radius: 6px; font-size: 14px; box-sizing: border-box;">
                    </div>
                    
                    <div class="form-group" style="padding: 12px 20px;">
                        <label style="display: block; margin-bottom: 5px; font-weight: 600; color: #2c3e50; font-size: 14px;">Stock actual</label>
                        <input type="number" id="edit-cantidad" min="0" value="${producto.cantidad || 0}" readonly style="width: 100%; padding: 10px 12px; border: 2px solid #dfe6e9; border-radius: 6px; font-size: 14px; box-sizing: border-box; background: #f8f9fa; color: #6c757d; cursor: not-allowed;">
                        <small style="color: #e67e22; font-size: 12px; display: block; margin-top: 4px;">El stock solo se modifica mediante los botones Ingreso / Egreso en la tabla.</small>
                    </div>
                    
                    <div class="form-group" style="padding: 12px 20px;">
                        <label style="display: block; margin-bottom: 5px; font-weight: 600; color: #2c3e50; font-size: 14px;">Cliente Asignado <small>(opcional)</small></label>
                        <select id="edit-cliente" style="width: 100%; padding: 10px 12px; border: 2px solid #dfe6e9; border-radius: 6px; font-size: 14px; box-sizing: border-box;">
                            <option value="">-- Seleccionar cliente --</option>
                        </select>
                    </div>
                    
                    <div class="form-group" style="padding: 12px 20px;">
                        <label style="display: block; margin-bottom: 5px; font-weight: 600; color: #2c3e50; font-size: 14px;">Estado</label>
                        <select id="edit-estado" style="width: 100%; padding: 10px 12px; border: 2px solid #dfe6e9; border-radius: 6px; font-size: 14px; box-sizing: border-box;">
                            <option value="sin_asignar">&#128230; Sin Asignar</option>
                            <option value="asignado">Asignado</option>
                            <option value="entregado">Entregado</option>
                        </select>
                    </div>
                    
                    <div class="form-group" style="padding: 12px 20px;">
                        <label style="display: block; margin-bottom: 5px; font-weight: 600; color: #2c3e50; font-size: 14px;">Peso (kg) <small>(opcional)</small></label>
                        <input type="number" id="edit-peso" min="0" step="0.01" value="${producto.peso || 0}" style="width: 100%; padding: 10px 12px; border: 2px solid #dfe6e9; border-radius: 6px; font-size: 14px; box-sizing: border-box;">
                    </div>
                    
                    <div class="form-group" style="padding: 12px 20px;">
                        <label style="display: block; margin-bottom: 5px; font-weight: 600; color: #2c3e50; font-size: 14px;">Tipo de Medida <small>(opcional)</small></label>
                        <select id="edit-tipo_medida" style="width: 100%; padding: 10px 12px; border: 2px solid #dfe6e9; border-radius: 6px; font-size: 14px; box-sizing: border-box;">
                            <option value="">-- Seleccionar --</option>
                            <option value="talla">Talla</option>
                            <option value="color">Color</option>
                            <option value="modelo">Modelo</option>
                            <option value="otro">Otro</option>
                        </select>
                    </div>
                    
                    <div class="form-group" style="padding: 12px 20px;">
                        <label style="display: block; margin-bottom: 5px; font-weight: 600; color: #2c3e50; font-size: 14px;">Valor de Medida <small>(opcional)</small></label>
                        <input type="text" id="edit-valor_medida" placeholder="Ej: S, M, L, XL o 100ml" value="${producto.valor_medida || ''}" style="width: 100%; padding: 10px 12px; border: 2px solid #dfe6e9; border-radius: 6px; font-size: 14px; box-sizing: border-box;">
                    </div>
                    
                    <div class="form-group" style="padding: 12px 20px;">
                        <label style="display: block; margin-bottom: 5px; font-weight: 600; color: #2c3e50; font-size: 14px;">Dimensiones (cm) <small>(opcional)</small></label>
                        <div style="display: flex; flex-direction: column; gap: 8px;">
                            <input type="number" id="edit-largo" min="0" step="0.1" placeholder="Largo (cm)" value="${producto.largo || 0}" style="width: 100%; padding: 10px 12px; border: 2px solid #dfe6e9; border-radius: 6px; font-size: 14px; box-sizing: border-box;">
                            <input type="number" id="edit-ancho" min="0" step="0.1" placeholder="Ancho (cm)" value="${producto.ancho || 0}" style="width: 100%; padding: 10px 12px; border: 2px solid #dfe6e9; border-radius: 6px; font-size: 14px; box-sizing: border-box;">
                            <input type="number" id="edit-alto" min="0" step="0.1" placeholder="Alto (cm)" value="${producto.alto || 0}" style="width: 100%; padding: 10px 12px; border: 2px solid #dfe6e9; border-radius: 6px; font-size: 14px; box-sizing: border-box;">
                        </div>
                    </div>
                    
                    <div class="form-group" style="padding: 12px 20px;">
                        <label style="display: block; margin-bottom: 8px; font-weight: 600; color: #2c3e50; font-size: 14px;">Foto del Producto <small>(opcional)</small></label>
                        <div style="display:flex;align-items:center;gap:14px;">
                            <img id="edit-foto-preview" src="${producto.foto_url || ''}" alt="foto" style="width:72px;height:72px;object-fit:cover;border-radius:8px;border:2px solid #dfe6e9;display:${producto.foto_url ? 'block' : 'none'};" />
                            <span id="edit-foto-placeholder" style="display:${producto.foto_url ? 'none' : 'flex'};width:72px;height:72px;align-items:center;justify-content:center;border:2px dashed #dfe6e9;border-radius:8px;font-size:28px;color:#bdc3c7;">&#128247;</span>
                            <div>
                                <label for="edit-foto-input" style="display:inline-block;background:#3498db;color:#fff;padding:7px 14px;border-radius:6px;cursor:pointer;font-size:13px;font-weight:600;">&#128444;&#65039; Cambiar foto</label>
                                <input type="file" id="edit-foto-input" accept="image/*" style="display:none;" />
                                <small style="display:block;color:#7f8c8d;margin-top:5px;font-size:11px;">Se actualiza automaticamente al seleccionar</small>
                            </div>
                        </div>
                    </div>
                </form>
                
                <div class="modal-footer" style="display: flex; gap: 10px; justify-content: flex-end; padding: 15px 20px; border-top: 1px solid #ecf0f1; background: #f8f9fa; flex-shrink: 0;">
                    <button type="button" class="modal-close-btn" style="background: #6c757d; color: white; box-shadow: 0 2px 6px rgba(108, 117, 125, 0.3); padding: 10px 24px; font-size: 14px; border: none; border-radius: 6px; font-weight: 600; cursor: pointer;">Cancelar</button>
                    <button type="button" id="btn-eliminar" style="background: #dc3545; color: white; box-shadow: 0 2px 6px rgba(220, 53, 69, 0.3); padding: 10px 24px; font-size: 14px; border: none; border-radius: 6px; font-weight: 600; cursor: pointer;">🗑️ Eliminar</button>
                    <button type="submit" form="form-editar-producto" style="background: #3498db; color: white; box-shadow: 0 2px 6px rgba(52, 152, 219, 0.3); padding: 10px 24px; font-size: 14px; border: none; border-radius: 6px; font-weight: 600; cursor: pointer;">💾 Guardar</button>
                </div>
            </div>
        `;

        document.body.appendChild(modal);

        // Activar subida de foto
        if (typeof window._attachFotoUpload === 'function') window._attachFotoUpload(producto.id);

        // Cargar clientes en el select pasando el objeto producto
        cargarClientesParaEdicion(producto);

        // Establecer estado actual
        document.getElementById('edit-estado').value = producto.estado || 'sin_asignar';
        document.getElementById('edit-tipo_medida').value = producto.tipo_medida || '';

        // Cerrar modal
        document.querySelectorAll('.modal-close-btn').forEach(btn => {
            btn.addEventListener('click', function () {
                modal.style.display = 'none';
                modal.remove();
            });
        });

        // Cerrar al hacer click en el backdrop
        modal.addEventListener('click', function (e) {
            if (e.target === modal) {
                modal.style.display = 'none';
                modal.remove();
            }
        });

        // Eliminar producto
        document.getElementById('btn-eliminar').addEventListener('click', function () {
            Swal.fire({
                icon: 'warning',
                title: 'Â¿Estas seguro?',
                text: 'Esta accion no se puede deshacer',
                showCancelButton: true,
                confirmButtonColor: '#dc3545',
                cancelButtonColor: '#6c757d',
                confirmButtonText: 'Si, eliminar',
                cancelButtonText: 'Cancelar'
            }).then((result) => {
                if (result.isConfirmed) {
                    eliminarProducto(producto.id);
                }
            });
        });

        // Submit del formulario
        document.getElementById('form-editar-producto').addEventListener('submit', function (e) {
            e.preventDefault();
            guardarProductoEditado(producto.id);
        });
    }

    function cargarClientesParaEdicion(producto) {
        const select = document.getElementById('edit-cliente');
        if (!select) return;

        console.log('Producto recibido:', producto);
        console.log('Cliente asignado (valor):', producto.cliente_asignado);
        console.log('Cliente asignado (tipo):', typeof producto.cliente_asignado);

        fetch(ajaxUrl, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/x-www-form-urlencoded',
            },
            body: new URLSearchParams({
                action: 'merc_obtener_clientes_lista',
                nonce: nonce
            })
        })
            .then(r => r.json())
            .then(res => {
                if (res.success && res.data && res.data.clientes) {
                    // Limpiar opciones previas
                    select.innerHTML = '<option value="">-- Seleccionar cliente --</option>';

                    res.data.clientes.forEach(cliente => {
                        let option = document.createElement('option');
                        option.value = String(cliente.id); // Convertir a string
                        option.textContent = cliente.nombre;
                        select.appendChild(option);
                    });

                    // Convertir cliente_asignado a string para comparacion consistente
                    const clienteId = String(producto.cliente_asignado || '');
                    console.log('Cliente ID a buscar (string):', clienteId);

                    // Establecer el cliente actual ANTES de inicializar SelectSearchable
                    if (clienteId && clienteId !== '0' && clienteId !== '') {
                        console.log('Estableciendo valor del select a:', clienteId);
                        select.value = clienteId;
                    }

                    // Inicializar SelectSearchable despues
                    setTimeout(() => {
                        if (typeof window.SelectSearchable !== 'undefined') {
                            console.log('Inicializando SelectSearchable para edit-cliente');
                            const searchable = new window.SelectSearchable(select);
                            console.log('âœ“ SelectSearchable inicializado en modal de edicion');
                            console.log('Valor del select despues de init:', select.value);

                            // Actualizar el input de busqueda con el nombre del cliente
                            setTimeout(() => {
                                const searchInput = select.parentNode.querySelector('.select-search-input');
                                console.log('SearchInput encontrado:', !!searchInput);

                                if (searchInput && select.value) {
                                    const selectedOption = Array.from(select.options).find(o => o.value === select.value);
                                    console.log('Opcion seleccionada:', selectedOption?.textContent);

                                    if (selectedOption) {
                                        searchInput.value = selectedOption.textContent;
                                        console.log('Input de busqueda actualizado a:', selectedOption.textContent);
                                    }
                                }
                            }, 50);
                        } else {
                            console.warn('âš&nbsp;ï¸ SelectSearchable no disponible aun');
                        }
                    }, 200);
                }
            })
            .catch(err => {
                console.error('Error cargando clientes:', err);
            });
    }

    function guardarProductoEditado(productId) {
        const datos = {
            action: 'merc_actualizar_producto',
            product_id: productId,
            nombre: document.getElementById('edit-nombre').value,
            codigo_barras: document.getElementById('edit-codigo').value,
            cantidad: parseInt(document.getElementById('edit-cantidad').value) || 0,
            cliente_asignado: document.getElementById('edit-cliente').value,
            estado: document.getElementById('edit-estado').value,
            peso: parseFloat(document.getElementById('edit-peso').value) || 0,
            tipo_medida: document.getElementById('edit-tipo_medida').value,
            valor_medida: document.getElementById('edit-valor_medida').value,
            largo: parseFloat(document.getElementById('edit-largo').value) || 0,
            ancho: parseFloat(document.getElementById('edit-ancho').value) || 0,
            alto: parseFloat(document.getElementById('edit-alto').value) || 0,
            nonce: nonce
        };

        fetch(ajaxUrl, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/x-www-form-urlencoded',
            },
            body: new URLSearchParams(datos)
        })
            .then(r => r.json())
            .then(res => {
                console.log('Respuesta actualizacion:', res);
                if (res.success) {
                    Swal.fire({
                        icon: 'success',
                        title: 'A‰xito',
                        text: 'Producto actualizado correctamente',
                        confirmButtonColor: '#3498db'
                    }).then(() => {
                        try {
                            const fe = document.getElementById('form-editar-producto');
                            if (fe) fe.reset();
                        } catch (e) { /* noop */ }
                        const modalEdit = document.getElementById('modal-editar-producto');
                        if (modalEdit) try { modalEdit.remove(); } catch (err) { /* noop */ }
                        cargarProductos(); // Recargar tabla
                    });
                } else {
                    Swal.fire({
                        icon: 'error',
                        title: 'Error',
                        text: res.data || 'Error desconocido',
                        confirmButtonColor: '#e74c3c'
                    });
                }
            })
            .catch(err => {
                console.error('Error:', err);
                Swal.fire({
                    icon: 'error',
                    title: 'Error de conexion',
                    text: 'No se pudo conectar con el servidor',
                    confirmButtonColor: '#e74c3c'
                });
            });
    }

    function eliminarProducto(productId) {
        const datos = {
            action: 'merc_eliminar_producto',
            product_id: productId,
            nonce: nonce
        };

        fetch(ajaxUrl, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/x-www-form-urlencoded',
            },
            body: new URLSearchParams(datos)
        })
            .then(r => r.json())
            .then(res => {
                console.log('Respuesta eliminacion:', res);
                if (res.success) {
                    Swal.fire({
                        icon: 'success',
                        title: 'A‰xito',
                        text: 'Producto eliminado correctamente',
                        confirmButtonColor: '#3498db'
                    }).then(() => {
                        document.getElementById('modal-editar-producto').remove();
                        cargarProductos(); // Recargar tabla
                    });
                } else {
                    Swal.fire({
                        icon: 'error',
                        title: 'Error',
                        text: res.data || 'Error desconocido',
                        confirmButtonColor: '#e74c3c'
                    });
                }
            })
            .catch(err => {
                console.error('Error:', err);
                Swal.fire({
                    icon: 'error',
                    title: 'Error de conexion',
                    text: 'No se pudo conectar con el servidor',
                    confirmButtonColor: '#e74c3c'
                });
            });
    }

    // Funcion global para refrescar productos
    window.refrescarProductos = function () {
        cargarProductos();
    };

    // Funcion global para eliminar producto
    window.eliminarProducto = function (productId, productName, cantidad) {
        Swal.fire({
            icon: 'warning',
            title: 'Confirmar eliminacion',
            html: `Â¿Esta seguro de que desea eliminar el producto <strong>"${productName}"</strong> con <strong>${cantidad}</strong> unidades?<br><small style="color: #e74c3c;">Esta accion no se puede deshacer.</small>`,
            showCancelButton: true,
            confirmButtonColor: '#e74c3c',
            confirmButtonText: 'ðŸ—‘ï¸ Si, eliminar',
            cancelButtonText: 'Cancelar',
            cancelButtonColor: '#95a5a6'
        }).then((result) => {
            if (result.isConfirmed) {
                const datos = {
                    action: 'merc_eliminar_producto',
                    product_id: productId,
                    nonce: nonce
                };

                fetch(ajaxUrl, {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/x-www-form-urlencoded',
                    },
                    body: new URLSearchParams(datos)
                })
                    .then(r => r.json())
                    .then(res => {
                        console.log('Respuesta eliminacion:', res);
                        if (res.success) {
                            Swal.fire({
                                icon: 'success',
                                title: 'Eliminado',
                                text: 'Producto eliminado correctamente',
                                confirmButtonColor: '#27ae60'
                            }).then(() => {
                                cargarProductos(); // Recargar tabla
                            });
                        } else {
                            Swal.fire({
                                icon: 'error',
                                title: 'Error',
                                text: res.data || 'No se pudo eliminar el producto',
                                confirmButtonColor: '#e74c3c'
                            });
                        }
                    })
                    .catch(err => {
                        console.error('Error al eliminar:', err);
                        Swal.fire({
                            icon: 'error',
                            title: 'Error de conexion',
                            text: 'No se pudo conectar con el servidor',
                            confirmButtonColor: '#e74c3c'
                        });
                    });
            }
        });
    };


    // -----------------------------------------------------------------------
    // MODAL UNIDADES con 2 pestanas: Unidades | Historial
    // -----------------------------------------------------------------------
    window.openUnitsModal = function (productId, productName) {
        var existing = document.getElementById('modal-units-hist');
        if (existing) existing.remove();

        var modal = document.createElement('div');
        modal.id = 'modal-units-hist';
        modal.style.cssText = 'position:fixed;top:0;left:0;width:100%;height:100%;background:rgba(0,0,0,0.55);display:flex;justify-content:center;align-items:center;z-index:99999;';
        modal.innerHTML = [
            '<div style="background:#fff;border-radius:12px;width:95%;max-width:780px;max-height:90vh;display:flex;flex-direction:column;box-shadow:0 12px 40px rgba(0,0,0,0.3);">',
            '<div style="display:flex;justify-content:space-between;align-items:center;padding:16px 20px;border-bottom:1px solid #eee;">',
            '<h3 style="margin:0;font-size:18px;color:#2c3e50;">Producto: ' + productName + '</h3>',
            '<button id="btn-close-units" style="background:none;border:none;font-size:24px;cursor:pointer;color:#7f8c8d;">&times;</button></div>',
            '<div style="display:flex;padding:0 20px;border-bottom:1px solid #eee;">',
            '<button class="merc-tab" data-tab="panel-unidades" style="padding:12px 20px;border:none;border-bottom:3px solid #3498db;font-weight:700;cursor:pointer;color:#3498db;background:none;">Unidades</button>',
            '<button class="merc-tab" data-tab="panel-historial" style="padding:12px 20px;border:none;border-bottom:3px solid transparent;font-weight:600;cursor:pointer;color:#7f8c8d;background:none;">Historial</button></div>',
            '<div style="flex:1;overflow-y:auto;">',
            '<div id="panel-unidades" class="merc-panel"><div style="padding:20px;text-align:center;color:#7f8c8d;">Cargando...</div></div>',
            '<div id="panel-historial" class="merc-panel" style="display:none;"><div style="padding:20px;text-align:center;color:#7f8c8d;">Selecciona la pestana Historial para cargar.</div></div>',
            '</div></div>'
        ].join('');

        document.body.appendChild(modal);

        document.getElementById('btn-close-units').onclick = function () { modal.remove(); };
        modal.onclick = function (e) { if (e.target === modal) modal.remove(); };

        var histCargado = false;
        modal.querySelectorAll('.merc-tab').forEach(function (tab) {
            tab.addEventListener('click', function () {
                modal.querySelectorAll('.merc-tab').forEach(function (t) {
                    t.style.borderBottomColor = 'transparent';
                    t.style.color = '#7f8c8d';
                    t.style.fontWeight = '600';
                });
                this.style.borderBottomColor = '#3498db';
                this.style.color = '#3498db';
                this.style.fontWeight = '700';
                modal.querySelectorAll('.merc-panel').forEach(function (p) { p.style.display = 'none'; });
                document.getElementById(this.dataset.tab).style.display = 'block';
                if (this.dataset.tab === 'panel-historial' && !histCargado) {
                    histCargado = true; cargarHistorialModal(productId);
                }
            });
        });

        cargarUnidadesModal(productId);

        function cargarUnidadesModal(pid) {
            fetch(ajaxUrl, {
                method: 'POST',
                headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                body: new URLSearchParams({ action: 'merc_get_product_units', product_id: pid, nonce: nonce })
            }).then(function (r) { return r.json(); }).then(function (res) {
                var panel = document.getElementById('panel-unidades');
                if (!panel) return;
                if (!res.success || !res.data || res.data.length === 0) {
                    panel.innerHTML = '<div style="padding:30px;text-align:center;color:#7f8c8d;">Sin unidades registradas.</div>'; return;
                }
                var html = '<table style="width:100%;border-collapse:collapse;font-size:13px;"><thead><tr style="background:#f8f9fa;border-bottom:2px solid #dee2e6;">';
                html += '<th style="padding:10px 16px;text-align:left;">SKU/ID</th><th style="padding:10px 16px;">Estado</th>';
                html += '<th style="padding:10px 16px;">Envio</th><th style="padding:10px 16px;">Motorizado</th><th style="padding:10px 16px;">Fecha</th>';
                if (window.MERC_IS_ADMIN) {
                    html += '<th style="padding:10px 16px;text-align:center;">Acción</th>';
                }
                html += '</tr></thead><tbody>';
                res.data.forEach(function (u, i) {
                    var bg = i % 2 === 0 ? '#fff' : '#f9f9f9';
                    var st = String(u.status_effective || u.status || '').toLowerCase();
                    var isD = ['delivered', 'entregado'].includes(st);
                    var isA = ['assigned', 'asignado'].includes(st);
                    var stTxt = isD ? 'Entregado' : (isA ? 'Asignado' : 'Disponible');
                    var stClr = isD ? '#27ae60' : (isA ? '#3498db' : '#95a5a6');

                    var tracking = u.tracking && u.tracking !== '-' ? u.tracking : (u.shipment_id ? '#' + u.shipment_id : '-');

                    html += '<tr style="background:' + bg + ';border-bottom:1px solid #eee;">';
                    html += '<td style="padding:10px 16px;font-weight:600;">' + (u.sku || u.id) + '</td>';
                    html += '<td style="padding:10px 16px;"><span style="background:' + stClr + ';color:#fff;padding:3px 8px;border-radius:4px;font-size:12px;">' + stTxt + '</span></td>';
                    html += '<td style="padding:10px 16px;text-align:center;color:#7f8c8d;">' + tracking + '</td>';
                    html += '<td style="padding:10px 16px;text-align:center;">' + (u.motorizado || '-') + '</td>';
                    html += '<td style="padding:10px 16px;color:#7f8c8d;font-size:12px;">' + (u.created_at || '-') + '</td>';

                    // Botón de eliminar (Solo admins)
                    if (window.MERC_IS_ADMIN) {
                        html += '<td style="padding:10px 16px;text-align:center;">';
                        html += '<button type="button" class="merc-btn-eliminar-unidad" data-id="' + u.id + '" title="Eliminar Unidad" style="background:transparent;border:none;color:#e74c3c;cursor:pointer;"><i class="fa fa-trash"></i></button>';
                        html += '</td>';
                    }
                    html += '</tr>';
                });
                html += '</tbody></table>';
                panel.innerHTML = html;

                // Add event listeners for delete buttons
                var btnEliminar = panel.querySelectorAll('.merc-btn-eliminar-unidad');
                btnEliminar.forEach(function (btn) {
                    btn.addEventListener('click', function () {
                        var unitId = this.getAttribute('data-id');
                        if (!confirm('¿Seguro que deseas eliminar esta unidad física?\n\nSi está asignada, el envío asociado se cambiará a estado ANULADO y la unidad se restará del inventario.')) {
                            return;
                        }

                        var prevHtml = this.innerHTML;
                        this.innerHTML = '<i class="fa fa-spinner fa-spin"></i>';
                        this.disabled = true;

                        fetch(ajaxUrl, {
                            method: 'POST',
                            headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                            body: new URLSearchParams({ action: 'merc_eliminar_unidad', unit_id: unitId, nonce: nonce })
                        }).then(function (r) { return r.json(); }).then(function (res) {
                            if (res.success) {
                                // Reload units and update stock
                                cargarUnidadesModal(pid);
                                if (res.data && typeof res.data.nuevo_stock !== 'undefined') {
                                    // Update main table row if exists
                                    var stockCell = document.querySelector('tr[data-id="' + pid + '"] .merc-stock-badge');
                                    if (stockCell) stockCell.textContent = res.data.nuevo_stock;
                                    var modalTitle = document.getElementById('merc-modal-title');
                                    if (modalTitle) {
                                        modalTitle.textContent = modalTitle.textContent.replace(/\(Stock: \d+\)/, '(Stock: ' + res.data.nuevo_stock + ')');
                                    }
                                }
                            } else {
                                alert('Error: ' + (res.data ? res.data.message : 'Desconocido'));
                                btn.innerHTML = prevHtml;
                                btn.disabled = false;
                            }
                        }).catch(function () {
                            alert('Error de conexión al eliminar.');
                            btn.innerHTML = prevHtml;
                            btn.disabled = false;
                        });
                    });
                });
            }).catch(function () {
                var p = document.getElementById('panel-unidades');
                if (p) p.innerHTML = '<div style="padding:20px;color:#e74c3c;">Error al cargar unidades.</div>';
            });
        }

        function cargarHistorialModal(pid) {
            var panel = document.getElementById('panel-historial');
            if (panel) panel.innerHTML = '<div style="padding:20px;text-align:center;color:#7f8c8d;">Cargando historial...</div>';
            fetch(ajaxUrl, {
                method: 'POST',
                headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                body: new URLSearchParams({ action: 'merc_get_historial_movimientos', product_id: pid, nonce: nonce })
            }).then(function (r) { return r.json(); }).then(function (res) {
                if (!panel) return;
                if (!res.success || !res.data || !res.data.movimientos || res.data.movimientos.length === 0) {
                    panel.innerHTML = '<div style="padding:30px;text-align:center;color:#7f8c8d;">Sin movimientos registrados.</div>'; return;
                }
                var ml = { ingreso_mercaderia: 'Ingreso mercaderia', merma: 'Merma/Dano', devolucion_cliente: 'Devolucion cliente', perdida: 'Perdida', ajuste: 'Ajuste inventario', otro: 'Otro' };
                var html = '<table style="width:100%;border-collapse:collapse;font-size:13px;"><thead><tr style="background:#f8f9fa;border-bottom:2px solid #dee2e6;">';
                html += '<th style="padding:10px 16px;text-align:left;">Fecha</th><th style="padding:10px 16px;text-align:center;">Tipo</th>';
                html += '<th style="padding:10px 16px;text-align:center;">Cant.</th><th style="padding:10px 16px;">Motivo</th>';
                html += '<th style="padding:10px 16px;">Notas</th><th style="padding:10px 16px;">Por</th></tr></thead><tbody>';
                res.data.movimientos.forEach(function (m, i) {
                    var bg = i % 2 === 0 ? '#fff' : '#f9f9f9';
                    var isI = m.tipo === 'ingreso';
                    var badge = isI
                        ? '<span style="background:#27ae60;color:#fff;padding:3px 8px;border-radius:4px;font-size:12px;">Ingreso</span>'
                        : '<span style="background:#e74c3c;color:#fff;padding:3px 8px;border-radius:4px;font-size:12px;">Egreso</span>';
                    html += '<tr style="background:' + bg + ';border-bottom:1px solid #eee;">';
                    html += '<td style="padding:10px 16px;color:#7f8c8d;font-size:12px;">' + (m.created_at || '-') + '</td>';
                    html += '<td style="padding:10px 16px;text-align:center;">' + badge + '</td>';
                    html += '<td style="padding:10px 16px;text-align:center;font-weight:700;color:' + (isI ? '#27ae60' : '#e74c3c') + ';">' + m.cantidad + '</td>';
                    html += '<td style="padding:10px 16px;">' + (ml[m.motivo] || m.motivo) + '</td>';
                    html += '<td style="padding:10px 16px;color:#7f8c8d;">' + (m.notas || '-') + '</td>';
                    html += '<td style="padding:10px 16px;">' + (m.admin_nombre || '-') + '</td></tr>';
                });
                html += '</tbody></table>';
                panel.innerHTML = html;
            }).catch(function () {
                if (panel) panel.innerHTML = '<div style="padding:20px;color:#e74c3c;">Error al cargar historial.</div>';
            });
        }
    };

    window._attachFotoUpload = function (productId) {
        const input = document.getElementById('edit-foto-input');
        if (!input) return;
        input.addEventListener('change', function () {
            if (!this.files || !this.files[0]) return;
            const file = this.files[0];
            const formData = new FormData();
            formData.append('action', 'merc_subir_foto_producto');
            formData.append('product_id', productId);
            formData.append('foto', file);
            formData.append('nonce', nonce);

            Swal.fire({ title: 'Subiendo...', allowOutsideClick: false, didOpen: () => { Swal.showLoading(); } });

            fetch(ajaxUrl, { method: 'POST', body: formData })
                .then(r => r.json())
                .then(res => {
                    if (res.success) {
                        const preview = document.getElementById('edit-foto-preview');
                        const placeholder = document.getElementById('edit-foto-placeholder');
                        if (preview) {
                            preview.src = res.data.url;
                            preview.style.display = 'block';
                        }
                        if (placeholder) placeholder.style.display = 'none';
                        Swal.fire({ icon: 'success', title: 'Foto actualizada', toast: true, position: 'top-end', showConfirmButton: false, timer: 3000 });
                        cargarProductos();
                    } else {
                        Swal.fire({ icon: 'error', title: 'Error', text: res.data.message });
                    }
                })
                .catch(err => {
                    console.error(err);
                    Swal.fire({ icon: 'error', title: 'Error de red' });
                });
        });
    };

});
