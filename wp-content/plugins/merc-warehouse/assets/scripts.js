// Parser JSON seguro: elimina BOM (U+FEFF) y whitespace inicial que
// PHP o plugins de caché pueden inyectar antes del JSON.
function parseJsonSafe(r) {
    return r.text().then(function(text) {
        // Regex quita cualquier combinación de espacios, newlines y BOM al inicio
        var cleaned = text.replace(/^[\s\uFEFF]+/, '');
        return JSON.parse(cleaned);
    });
}

document.addEventListener('DOMContentLoaded', function () {
    console.log('Merc Warehouse cargado');
    
    // Si mercAlmacenData no está disponible, intentamos sin nonce
    const nonce = (typeof window.mercAlmacenData !== 'undefined') ? window.mercAlmacenData.nonce : '';
    const isAdmin = (typeof window.mercAlmacenData !== 'undefined') ? window.mercAlmacenData.isAdmin : false;
    
    // Obtener URL AJAX
    const ajaxUrl = (typeof ajaxurl !== 'undefined') ? ajaxurl : '/wp-admin/admin-ajax.php';
    
    console.log('Configuración:', { nonce: !!nonce, isAdmin, ajaxUrl });
    
    // Función para cargar productos
    function cargarProductos() {
        console.log('Iniciando carga de productos...');
        
        // Preparar datos del formulario
        const formData = new URLSearchParams({
            action: 'merc_almacen_get_productos'
        });
        
        // Añadir nonce si está disponible
        if (nonce) {
            formData.append('nonce', nonce);
        }
        
        // Cargar datos del almacén
        fetch(ajaxUrl, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/x-www-form-urlencoded',
            },
            body: formData.toString()
        })
        .then(r => {
            console.log('Respuesta HTTP:', r.status);
            return parseJsonSafe(r);
        })
        .then(res => {
            console.log('Respuesta AJAX:', res);
            if (res.success && res.data && res.data.productos) {
                console.log('Productos cargados:', res.data.productos.length);
                renderProducts(res.data.productos);
                updateStats(res.data.productos);
            } else {
                console.warn('No hay productos o error en respuesta:', res);
                const tabla = document.getElementById('almacen-tabla');
                if (tabla) {
                    tabla.innerHTML = '<p style="padding:20px;color:#e74c3c;">Error al cargar productos</p>';
                }
            }
        })
        .catch(error => {
            console.error('Error en AJAX:', error);
            const tabla = document.getElementById('almacen-tabla');
            if (tabla) {
                tabla.innerHTML = '<p style="padding:20px;color:#e74c3c;">Error de conexión: ' + error.message + '</p>';
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
            container.innerHTML = '<div style="padding:40px;text-align:center;color:#7f8c8d;"><p>📦 No hay productos en el almacén</p></div>';
            return;
        }
        
        let html = '<table class="tabla-productos" style="width:100%;border-collapse:collapse;margin-top:20px;">';
        html += '<thead><tr style="background:#f8f9fa;border-bottom:2px solid #dee2e6;">';
        html += '<th style="padding:12px;text-align:left;">Producto</th>';
        html += '<th style="padding:12px;text-align:center;">Cantidad</th>';
        html += '<th style="padding:12px;text-align:left;">Cliente</th>';
        html += '<th style="padding:12px;text-align:left;">Creado</th>';
        html += '<th style="padding:12px;text-align:center;">Estado</th>';
        if (isAdmin) {
            html += '<th style="padding:12px;text-align:center;">Acciones</th>';
        }
        html += '</tr></thead><tbody>';
        
        productos.forEach(prod => {
            const estadoClass = prod.estado === 'asignado' ? 'badge-asignado' : (prod.estado === 'entregado' ? 'badge-entregado' : 'badge-sin-asignar');
            const estadoText = prod.estado === 'asignado' ? '🚚 Asignado' : (prod.estado === 'entregado' ? '✅ Entregado' : '📦 Sin Asignar');
            
            html += '<tr style="border-bottom:1px solid #ecf0f1;">';
            html += '<td style="padding:12px;"><strong>' + (prod.nombre || 'Sin nombre') + '</strong></td>';
            html += '<td style="padding:12px;text-align:center;"><span style="background:#d4edda;color:#155724;padding:4px 8px;border-radius:4px;font-weight:bold;">' + (prod.cantidad || 0) + '</span></td>';
            html += '<td style="padding:12px;">' + (prod.billing_company || 'N/A') + '</td>';
            html += '<td style="padding:12px;font-size:13px;color:#7f8c8d;">' + (prod.fecha_creacion || '-') + '</td>';
            html += '<td style="padding:12px;text-align:center;"><span style="display:inline-block;padding:6px 12px;background:#e9ecef;color:#495057;border-radius:6px;font-size:12px;font-weight:600;">' + estadoText + '</span></td>';
            if (isAdmin) {
                html += '<td style="padding:12px;text-align:center;"><button class="btn-edit" onclick="window.editarProducto(' + prod.id + ')">✏️ Editar</button></td>';
            }
            html += '</tr>';
        });
        
        html += '</tbody></table>';
        container.innerHTML = html;
    }
    
    function updateStats(productos) {
        console.log('Actualizando estadísticas...');
        
        // Contar usuarios únicos
        const usuarios = new Set();
        productos.forEach(p => {
            if (p.cliente_asignado) usuarios.add(p.cliente_asignado);
        });
        
        // Contar totales
        const total = productos.reduce((acc, p) => acc + (parseInt(p.cantidad) || 0), 0);
        const asignados = productos.reduce((acc, p) => acc + (p.estado === 'asignado' ? (parseInt(p.cantidad) || 0) : 0), 0);
        
        const statUsuarios = document.getElementById('stat-usuarios');
        const statTotal = document.getElementById('stat-total');
        const statAsignados = document.getElementById('stat-asignados');
        
        if (statUsuarios) statUsuarios.textContent = usuarios.size;
        if (statTotal) statTotal.textContent = total;
        if (statAsignados) statAsignados.textContent = asignados;
        
        console.log('Estadísticas actualizadas:', { usuarios: usuarios.size, total, asignados });
    }
    
    // Cargar productos inicialmente
    cargarProductos();
    
    // Handler para botón "Nuevo Producto"
    const btnNuevo = document.getElementById('btn-nuevo');
    if (btnNuevo) {
        btnNuevo.addEventListener('click', function() {
            console.log('Abriendo modal de nuevo producto');
            abrirModalNuevoProducto();
        });
    } else {
        console.warn('Botón btn-nuevo no encontrado');
    }
    
    // Función para abrir modal
    function abrirModalNuevoProducto() {
        let modal = document.getElementById('modal-nuevo-producto');
        
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
                            <label style="display: block; margin-bottom: 5px; font-weight: 600; color: #2c3e50; font-size: 14px;">Código de Barras <small>(opcional)</small></label>
                            <input type="text" name="codigo_barras" placeholder="Código o SKU" style="width: 100%; padding: 10px 12px; border: 2px solid #dfe6e9; border-radius: 6px; font-size: 14px; box-sizing: border-box;">
                            <small style="color: #7f8c8d; font-size: 12px; display: block; margin-top: 3px;">📦 Código único para identificar el producto</small>
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
                            <label style="display: block; margin-bottom: 5px; font-weight: 600; color: #2c3e50; font-size: 14px;">Dimensiones (cm) <small>(opcional)</small></label>
                            <div style="display: flex; gap: 8px; align-items: center;">
                                <input type="number" name="largo" min="0" step="0.1" placeholder="Largo" style="flex: 1; padding: 10px 12px; border: 2px solid #dfe6e9; border-radius: 6px; font-size: 14px; box-sizing: border-box;">
                                <span style="color: #7f8c8d;">×</span>
                                <input type="number" name="ancho" min="0" step="0.1" placeholder="Ancho" style="flex: 1; padding: 10px 12px; border: 2px solid #dfe6e9; border-radius: 6px; font-size: 14px; box-sizing: border-box;">
                                <span style="color: #7f8c8d;">×</span>
                                <input type="number" name="alto" min="0" step="0.1" placeholder="Alto" style="flex: 1; padding: 10px 12px; border: 2px solid #dfe6e9; border-radius: 6px; font-size: 14px; box-sizing: border-box;">
                            </div>
                            <small style="color: #7f8c8d; font-size: 12px; display: block; margin-top: 3px;">📦 Dimensiones: Largo × Ancho × Alto</small>
                        </div>
                    </form>
                    
                    <div class="modal-footer" style="display: flex; gap: 10px; justify-content: flex-end; padding: 15px 20px; border-top: 1px solid #ecf0f1; background: #f8f9fa; flex-shrink: 0;">
                        <button type="button" class="modal-close-btn" style="background: #6c757d; color: white; box-shadow: 0 2px 6px rgba(108, 117, 125, 0.3); padding: 10px 24px; font-size: 14px; border: none; border-radius: 6px; font-weight: 600; cursor: pointer;">Cancelar</button>
                        <button type="submit" form="form-nuevo-producto" style="background: #3498db; color: white; box-shadow: 0 2px 6px rgba(52, 152, 219, 0.3); padding: 10px 24px; font-size: 14px; border: none; border-radius: 6px; font-weight: 600; cursor: pointer;">Guardar Producto</button>
                    </div>
                </div>
            `;
            
            document.body.appendChild(modal);
            
            // Cerrar modal
            document.querySelectorAll('.modal-close-btn').forEach(btn => {
                btn.addEventListener('click', function() {
                    modal.style.display = 'none';
                });
            });
            
            // Cerrar al hacer click en el backdrop
            modal.addEventListener('click', function(e) {
                if (e.target === modal) {
                    modal.style.display = 'none';
                }
            });
            
            // Submit del formulario
            document.getElementById('form-nuevo-producto').addEventListener('submit', function(e) {
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
                .then(parseJsonSafe)
                .then(res => {
                    console.log('Respuesta guardado:', res);
                    if (res.success) {
                        Swal.fire({
                            icon: 'success',
                            title: 'Éxito',
                            text: 'Producto creado exitosamente',
                            confirmButtonColor: '#3498db'
                        }).then(() => {
                            modal.style.display = 'none';
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
                        title: 'Error de conexión',
                        text: 'No se pudo conectar con el servidor',
                        confirmButtonColor: '#e74c3c'
                    });
                });
            });
        }
        
        modal.style.display = 'flex';
    }
    
    // Función global para editar producto
    window.editarProducto = function(id) {
        console.log('Editando producto ID:', id);
        abrirModalEditarProducto(id);
    };
    
    // Función para abrir modal de edición
    function abrirModalEditarProducto(productId) {
        console.log('Abriendo modal de edición para producto:', productId);
        
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
        .then(parseJsonSafe)
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
                title: 'Error de conexión',
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
                    <h3 style="margin: 0; font-size: 20px; color: #2c3e50;">✏️ Editar Producto</h3>
                    <button class="modal-close-btn" style="background: none; border: none; font-size: 24px; color: #7f8c8d; cursor: pointer; padding: 0; line-height: 1; width: 30px; height: 30px; display: flex; align-items: center; justify-content: center;">&times;</button>
                </div>
                
                <form id="form-editar-producto" style="overflow-y: auto; flex: 1;">
                    <input type="hidden" id="producto-id" value="${producto.id}">
                    
                    <div class="form-group" style="padding: 12px 20px;">
                        <label style="display: block; margin-bottom: 5px; font-weight: 600; color: #2c3e50; font-size: 14px;">Nombre del Producto *</label>
                        <input type="text" id="edit-nombre" value="${producto.nombre || ''}" required style="width: 100%; padding: 10px 12px; border: 2px solid #dfe6e9; border-radius: 6px; font-size: 14px; box-sizing: border-box;">
                    </div>
                    
                    <div class="form-group" style="padding: 12px 20px;">
                        <label style="display: block; margin-bottom: 5px; font-weight: 600; color: #2c3e50; font-size: 14px;">Código de Barras <small>(opcional)</small></label>
                        <input type="text" id="edit-codigo" value="${producto.codigo_barras || ''}" style="width: 100%; padding: 10px 12px; border: 2px solid #dfe6e9; border-radius: 6px; font-size: 14px; box-sizing: border-box;">
                    </div>
                    
                    <div class="form-group" style="padding: 12px 20px;">
                        <label style="display: block; margin-bottom: 5px; font-weight: 600; color: #2c3e50; font-size: 14px;">Cantidad *</label>
                        <input type="number" id="edit-cantidad" min="0" value="${producto.cantidad || 0}" required style="width: 100%; padding: 10px 12px; border: 2px solid #dfe6e9; border-radius: 6px; font-size: 14px; box-sizing: border-box;">
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
                            <option value="sin_asignar">📦 Sin Asignar</option>
                            <option value="asignado">🚚 Asignado</option>
                            <option value="entregado">✅ Entregado</option>
                        </select>
                    </div>
                    
                    <div class="form-group" style="padding: 12px 20px;">
                        <label style="display: block; margin-bottom: 5px; font-weight: 600; color: #2c3e50; font-size: 14px;">Peso (kg) <small>(opcional)</small></label>
                        <input type="number" id="edit-peso" min="0" step="0.01" value="${producto.peso || 0}" style="width: 100%; padding: 10px 12px; border: 2px solid #dfe6e9; border-radius: 6px; font-size: 14px; box-sizing: border-box;">
                    </div>
                    
                    <div class="form-group" style="padding: 12px 20px;">
                        <label style="display: block; margin-bottom: 5px; font-weight: 600; color: #2c3e50; font-size: 14px;">Dimensiones (cm) <small>(opcional)</small></label>
                        <div style="display: flex; gap: 8px; align-items: center;">
                            <input type="number" id="edit-largo" min="0" step="0.1" placeholder="Largo" value="${producto.largo || 0}" style="flex: 1; padding: 10px 12px; border: 2px solid #dfe6e9; border-radius: 6px; font-size: 14px; box-sizing: border-box;">
                            <span style="color: #7f8c8d;">×</span>
                            <input type="number" id="edit-ancho" min="0" step="0.1" placeholder="Ancho" value="${producto.ancho || 0}" style="flex: 1; padding: 10px 12px; border: 2px solid #dfe6e9; border-radius: 6px; font-size: 14px; box-sizing: border-box;">
                            <span style="color: #7f8c8d;">×</span>
                            <input type="number" id="edit-alto" min="0" step="0.1" placeholder="Alto" value="${producto.alto || 0}" style="flex: 1; padding: 10px 12px; border: 2px solid #dfe6e9; border-radius: 6px; font-size: 14px; box-sizing: border-box;">
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
        
        // Cargar clientes en el select pasando el objeto producto
        cargarClientesParaEdicion(producto);
        
        // Establecer estado actual
        document.getElementById('edit-estado').value = producto.estado || 'sin_asignar';
        
        // Cerrar modal
        document.querySelectorAll('.modal-close-btn').forEach(btn => {
            btn.addEventListener('click', function() {
                modal.style.display = 'none';
                modal.remove();
            });
        });
        
        // Cerrar al hacer click en el backdrop
        modal.addEventListener('click', function(e) {
            if (e.target === modal) {
                modal.style.display = 'none';
                modal.remove();
            }
        });
        
        // Eliminar producto
        document.getElementById('btn-eliminar').addEventListener('click', function() {
            Swal.fire({
                icon: 'warning',
                title: '¿Estás seguro?',
                text: 'Esta acción no se puede deshacer',
                showCancelButton: true,
                confirmButtonColor: '#dc3545',
                cancelButtonColor: '#6c757d',
                confirmButtonText: 'Sí, eliminar',
                cancelButtonText: 'Cancelar'
            }).then((result) => {
                if (result.isConfirmed) {
                    eliminarProducto(producto.id);
                }
            });
        });
        
        // Submit del formulario
        document.getElementById('form-editar-producto').addEventListener('submit', function(e) {
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
        .then(parseJsonSafe)
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
                
                // Convertir cliente_asignado a string para comparación consistente
                const clienteId = String(producto.cliente_asignado || '');
                console.log('Cliente ID a buscar (string):', clienteId);
                
                // Establecer el cliente actual ANTES de inicializar SelectSearchable
                if (clienteId && clienteId !== '0' && clienteId !== '') {
                    console.log('Estableciendo valor del select a:', clienteId);
                    select.value = clienteId;
                }
                
                // Inicializar SelectSearchable después
                setTimeout(() => {
                    if (typeof window.SelectSearchable !== 'undefined') {
                        console.log('Inicializando SelectSearchable para edit-cliente');
                        const searchable = new window.SelectSearchable(select);
                        console.log('✓ SelectSearchable inicializado en modal de edición');
                        console.log('Valor del select después de init:', select.value);
                        
                        // Actualizar el input de búsqueda con el nombre del cliente
                        setTimeout(() => {
                            const searchInput = select.parentNode.querySelector('.select-search-input');
                            console.log('SearchInput encontrado:', !!searchInput);
                            
                            if (searchInput && select.value) {
                                const selectedOption = Array.from(select.options).find(o => o.value === select.value);
                                console.log('Opción seleccionada:', selectedOption?.textContent);
                                
                                if (selectedOption) {
                                    searchInput.value = selectedOption.textContent;
                                    console.log('Input de búsqueda actualizado a:', selectedOption.textContent);
                                }
                            }
                        }, 50);
                    } else {
                        console.warn('⚠️ SelectSearchable no disponible aún');
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
        .then(parseJsonSafe)
        .then(res => {
            console.log('Respuesta actualización:', res);
            if (res.success) {
                Swal.fire({
                    icon: 'success',
                    title: 'Éxito',
                    text: 'Producto actualizado correctamente',
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
                title: 'Error de conexión',
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
        .then(parseJsonSafe)
        .then(res => {
            console.log('Respuesta eliminación:', res);
            if (res.success) {
                Swal.fire({
                    icon: 'success',
                    title: 'Éxito',
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
                title: 'Error de conexión',
                text: 'No se pudo conectar con el servidor',
                confirmButtonColor: '#e74c3c'
            });
        });
    }
    
    // Función global para refrescar productos
    window.refrescarProductos = function() {
        cargarProductos();
    };
});

