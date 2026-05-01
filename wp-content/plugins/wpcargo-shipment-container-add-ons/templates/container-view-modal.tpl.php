<div class="modal fade" id="assgnShipmentModal" tabindex="-1" role="dialog" aria-labelledby="assgnShipmentModalTitle" aria-hidden="true">
  <div class="modal-dialog modal-lg" style="margin-top: 36px;" role="document">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title" id="assgnShipmentModalTitle"><?php _e('Assigned Shipment list', 'wpcargo-shipment-container') ?></h5>
        <button type="button" class="close" data-dismiss="modal" aria-label="Close">
          <span aria-hidden="true">&times;</span>
        </button>
      </div>
      <div class="modal-body">
            
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-secondary" data-dismiss="modal"><?php _e('Close', 'wpcargo-shipment-container') ?></button>
      </div>
    </div>
  </div>
</div>
<script>
    jQuery(document).ready(function($){

        // ── Modal de barcodes: solo para el indicador VERDE (Todo asignado) ──
        $('#container-list').on('click', '.openAssShipmentModal', function(e){
            e.preventDefault();
            const assignedShipments = $(this).data('shipments');
            $('#assgnShipmentModal .modal-body').html('');
            $('#assgnShipmentModal .modal-body').prepend('<div class="container"><div id="assgListWrapper" class="row"></div></div>');
            $.each( assignedShipments, function( key, value ) {
                $('#assgnShipmentModal .modal-body').find('#assgListWrapper').append(
                    `<div class="col-md-6 p-2 border text-center">
                        <a href="${value.url}" target="_blank">${value.barcode}
                        ${value.number}</a>
                    </div>`
                );
            });
            $('#assgnShipmentModal').modal('show');
        });

        // ── Popup de pendientes: para el indicador ROJO ──
        $('#container-list').on('click', '.openPendingDetail', function(e){
            e.preventDefault();
            let pending;
            try { pending = $(this).data('pending'); } catch(err) { return; }
            if (!pending) return;

            let html = '<div style="text-align:left;">';

            if (pending.recojo && pending.recojo.length > 0) {
                html += '<div style="margin-bottom:18px;">';
                html += '<p style="font-weight:700; color:#e8363c; margin-bottom:8px;"><i class="fa fa-box" style="margin-right:6px;"></i>Recojos pendientes de asignar</p>';
                html += '<ul style="padding-left:20px; margin:0;">';
                pending.recojo.forEach(function(name) {
                    html += `<li style="padding:4px 0;"><strong>${name}</strong></li>`;
                });
                html += '</ul></div>';
            }

            if (pending.entrega && pending.entrega.length > 0) {
                html += '<div>';
                html += '<p style="font-weight:700; color:#28a745; margin-bottom:8px;"><i class="fa fa-truck" style="margin-right:6px;"></i>Entregas pendientes de asignar</p>';
                html += '<ul style="padding-left:20px; margin:0;">';
                pending.entrega.forEach(function(s) {
                    const trackUrl = `/?wpcfe=track&num=${encodeURIComponent(s.title)}`;
                    html += `<li style="padding:4px 0;"><a href="${trackUrl}" target="_blank" style="font-weight:600;">${s.title}</a></li>`;
                });
                html += '</ul></div>';
            }

            html += '</div>';

            if (typeof Swal !== 'undefined') {
                Swal.fire({
                    title: '<span style="font-size:1.1rem;">Pendientes de asignación</span>',
                    html: html || '<p>Sin datos disponibles</p>',
                    icon: 'warning',
                    confirmButtonText: 'Entendido',
                    confirmButtonColor: '#e8363c',
                    width: '500px',
                    customClass: { popup: 'merc-pending-popup' }
                });
            } else {
                alert('Datos de pendientes:\n' + JSON.stringify(pending, null, 2));
            }
        });

    });
</script>

