jQuery(document).ready( function($){
    const ajaxURL               = wpcieAjaxHandler.ajaxURL;
    const ajaxNonce             = wpcieAjaxHandler.ajaxNonce;
    const processRequestLabel   = wpcieAjaxHandler.processRequestLabel;
    const dateRequired          = wpcieAjaxHandler.dateRequired;
    const uploadingFile         = wpcieAjaxHandler.uploadingFile;
    const processComplete       = wpcieAjaxHandler.processComplete;
    
    let saveOptionTimer;
    let exportTimer;
    let recordCounter = 0;
    const saveSelectedoption = () => {
        let selectoptions= {};				
        $.each($("#wpcie-multiselect_to option"), function( ) {
            var metaKey     = $(this).attr("value");
            var metaValue   = $(this).text();	
            selectoptions[metaKey] = metaValue;		
        });
        saveOptionTimer = setTimeout(() => {
            $.ajax({				
                url : ajaxURL,				
                type : 'post',				
                data : {				
                    action : 'wpcie_save_template_options',				
                    selectoptions: selectoptions				
                },				
                success : function( response ) { }				
            });	
        }, 500 );
    }
    const downloadFile =  function(fileURL, fileName) {
        // for non-IE
        if (!window.ActiveXObject) {
            var save = document.createElement('a');
            save.href = fileURL;
            save.target = '_blank';
            var filename = fileURL.substring(fileURL.lastIndexOf('/')+1);
            save.download = fileName || filename;
            if ( navigator.userAgent.toLowerCase().match(/(ipad|iphone|safari)/) && navigator.userAgent.search("Chrome") < 0) {
                    document.location = save.href; 
                // window event not working here
                }else{
                    var evt = new MouseEvent('click', {
                        'view': window,
                        'bubbles': true,
                        'cancelable': false
                    });
                    save.dispatchEvent(evt);
                    (window.URL || window.webkitURL).revokeObjectURL(save.href);
                }	
        }
        // for IE < 11
        else if ( !! window.ActiveXObject && document.execCommand)     {
            var _window = window.open(fileURL, '_blank');
            _window.document.close();
            _window.document.execCommand('SaveAs', true, fileName || fileURL)
            _window.close();
        }
    }
    if ($.isFunction( $.fn.pickadate )) {
		// Get the elements
		var from_input = $('#wpcie-export-form #startingDate').pickadate({
			format: 'yyyy-mm-dd',
		}),
		from_picker = from_input.pickadate('picker');
		var to_input = $('#wpcie-export-form #endingDate').pickadate({
			format: 'yyyy-mm-dd',
		}),
		to_picker = to_input.pickadate('picker');
		
		if( from_picker && to_picker ){

			// Check if there’s a “from” or “to” date to start with and if so, set their appropriate properties.
			if (from_picker.get('value')) {
				to_picker.set('min', from_picker.get('select'))
			}
			if (to_picker.get('value')) {
				from_picker.set('max', to_picker.get('select'))
			}
			
			// Apply event listeners in case of setting new “from” / “to” limits to have them update on the other end. If ‘clear’ button is pressed, reset the value.
			from_picker.on('set', function (event) {
				if (event.select) {
					to_picker.set('min', from_picker.get('select'))
				} else if ('clear' in event) {
					to_picker.set('min', false)
				}
			});
			to_picker.on('set', function (event) {
				if (event.select) {
					from_picker.set('max', to_picker.get('select'))
				} else if ('clear' in event) {
					from_picker.set('max', false)
				}
			});
		}
	}
    $('#wpcie-multiselect').multiselect({
        sort: false,
        autoSort: false,
        autoSortAvailable: false,
        afterMoveToRight: function(Multiselect, $options, event, silent, skipStack) {
            clearTimeout(saveOptionTimer);
            saveSelectedoption();
        },
        afterMoveToLeft: function(Multiselect, $options, event, silent, skipStack) {
            clearTimeout(saveOptionTimer);
            saveSelectedoption();
        }
    });	
    // Export Form Submission
    $('#wpcie-export-form').on('submit', function( e ){
        e.preventDefault();
        const formData      = $(this).serializeArray();
        const selMetakeys   = $('#wpcie-multiselect_to').val();
        const startingDate  = $('#startingDate').val();
        const endingDate  = $('#endingDate').val();
        if( !startingDate || !endingDate){
            alert(dateRequired);
            if( !startingDate ){ $('#startingDate').focus(); return; }
            if( !endingDate ){ $('#endingDate').focus();  return; }
        }
        $('.wpcie-main_wrapper').find('.wpcie_export-notification').remove();
        clearTimeout(exportTimer);
        $.ajax({
            type:"POST",
            data:{
                action:'wpcie_export_data',  
                nonce: ajaxNonce,  
                formData:formData,
                selMetakeys:selMetakeys
            },
            url : ajaxURL,
            beforeSend:function(){
                //** Proccessing
                $('.wpcie-main_wrapper').append(`
                    <div class="wpcie_export-notification alert alert-info text-center">${processRequestLabel}...</div>
                `);
            },
            success:function(response){
                if( response.status == 'error'){
                    $('.wpcie-main_wrapper').find('.wpcie_export-notification').removeClass('alert-info').addClass('alert-danger');
                    $('.wpcie-main_wrapper').find('.wpcie_export-notification').text( response.message );
                }else{
                    $('.wpcie-main_wrapper').find('.wpcie_export-notification').removeClass('alert-info').addClass('alert-success');
                    $('.wpcie-main_wrapper').find('.wpcie_export-notification').text( response.message );
                    downloadFile( response.file.file_url, response.file.file_name );
                } 
                exportTimer = setTimeout( function(){
                    $('.wpcie-main_wrapper').find('.wpcie_export-notification').remove();
                }, 3000 );
            }
        });
    });
    // Download Import template
    $('#wpcie-download-csv-template').on('click', function(e){
        e.preventDefault();
        e.preventDefault();
        $('.wpcie-main_wrapper').find('.wpcie_export-notification').remove();
        $.ajax({
            type:"POST",
            data:{
                action:'download_import_template',  
            },
            url : ajaxURL,
            beforeSend:function(){
                //** Proccessing
                $('.wpcie-main_wrapper').append(`
                    <div class="wpcie_export-notification alert alert-info text-center">${processRequestLabel}...</div>
                `);
            },
            success:function(response){

                $('.wpcie-main_wrapper').find('.wpcie_export-notification').removeClass('alert-info').addClass('alert-success');
                $('.wpcie-main_wrapper').find('.wpcie_export-notification').text( response.message );
                downloadFile( response.file.file_url, response.file.file_name );
                
                exportTimer = setTimeout( function(){
                    $('.wpcie-main_wrapper').find('.wpcie_export-notification').remove();
                }, 3000 );
            }
        });
    });
    // Import process
    $('#wpcie-import-form_wrapper').on('submit', '#wpcie-import-form', function( e ){
        e.preventDefault();
        const currForm = $(this);
        $('#wpcie-import-notification_wrapper').html('');
        $.ajax({
            url : ajaxURL,
            type: "POST",
            data:  new FormData(this),
            contentType: false,
            cache: false,
            processData:false,
            beforeSend: function() {   
                recordCounter = 0; 
                
                $('#wpcie-import-notification_wrapper').append( `<div id="tc-import-result" class="container mt-4 p-2" style="max-height: 260px; overflow-y: scroll;color: #383d41; background-color: #f1f1f1; border-color: #d6d8db;"><p >${uploadingFile}...</p></div>`);
            },
            success: function (response) {
                // If server returned WP AJAX success with job_id (we enqueue)
                if ( response && response.success && response.data && response.data.job_id ) {
                    const jobId = response.data.job_id;
                    $('#wpcie-import-notification_wrapper').find('#tc-import-result').append( `<p>${response.data.message}</p><ul class="import-record-list"></ul>`);
                    $('#wpcie-import-notification_wrapper').append( '<div id="import_loading_wapper"><div id="loading_percentage" style="background-color: #00c851;"><p style="color: #044820;padding: 0 12px;"></p></div></div>' );
                    $('#wpcie-import-notification_wrapper').append(`<div id="merc-job-status" class="mt-2">Estado: <span class="status">queued</span> | Procesados: <span class="processed">0</span> | Fallidos: <span class="failed">0</span></div>`);

                    // Poll job status every 2s and update UI; when finished, fetch results
                    const poll = setInterval( function() {
                        $.post( ajaxURL, { action: 'merc_import_job_status', job_id: jobId }, function( resp ){
                            if ( resp && resp.success && resp.data ) {
                                const d = resp.data;
                                $('#merc-job-status .status').text( d.status );
                                $('#merc-job-status .processed').text( d.rows_processed );
                                $('#merc-job-status .failed').text( d.rows_failed );
                                if ( d.status === 'completed' || d.status === 'failed' ) {
                                    clearInterval( poll );
                                    // Fetch created shipments for this job and append to results
                                    $.post( ajaxURL, { action: 'merc_import_job_results', job_id: jobId }, function( res2 ) {
                                        if ( res2 && res2.success && res2.data && res2.data.shipments ) {
                                            const ships = res2.data.shipments;
                                            if ( ships.length ) {
                                                ships.forEach( s => {
                                                    $('#wpcie-import-notification_wrapper').find('#tc-import-result .import-record-list').append(`<li class="success">${s.post_title} (ID:${s.ID})</li>`);
                                                } );
                                            }
                                        }
                                        const finalMsg = d.status === 'completed' ? 'Proceso completado' : 'Proceso finalizado con errores';
                                        $('#wpcie-import-notification_wrapper').find('#tc-import-result').append(`<p class="finish" style="color: #044820;font-size: 1.2rem;">${finalMsg}</p>`);
                                    }, 'json' );
                                }
                            }
                        }, 'json' );
                    }, 2000 );

                } else if( response.status == 'success' ){
                    // Legacy behaviour: per-record saving from server
                    $('#wpcie-import-notification_wrapper').find('#tc-import-result').append( `<p>${response.message}</p><ul class="import-record-list"></ul>`);
                    $('#wpcie-import-notification_wrapper').append( '<div id="import_loading_wapper"><div id="loading_percentage" style="background-color: #00c851;"><p style="color: #044820;padding: 0 12px;"></p></div></div>' );
                    var records     = response.data;
                    var recordCount = records.length;
                    // Lanzar guardados por fila usando cola para limitar concurrencia
                    for( let i = 0; i < records.length; i++ ){
                        enqueueSave({ record: records[i], recordCount: recordCount });
                    }

                    // Verificación posterior: cuando terminen los guardados, consultar existencia
                    const verifyInterval = setInterval(function(){
                        if ( typeof recordCounter !== 'undefined' && recordCounter === recordCount ) {
                            clearInterval( verifyInterval );
                            // Para cada registro, comprobar existencia y obtener motivo si fue descartado
                            records.forEach( rec => {
                                let tracking = rec.post_title || rec.wpcargo_tracking_number || rec.tracking || rec.wpcargo_shipment_number || rec.shipment_number || null;
                                if ( ! tracking ) {
                                    var m = (response && response.message) ? String(response.message).match(/MERC-\d+/) : null;
                                    if ( m ) tracking = m[0];
                                }
                                if ( tracking ) {
                                    // consultamos directamente el motivo por tracking
                                    $.post( ajaxURL, { action: 'merc_get_discard_reason', tracking: tracking, nonce: ajaxNonce }, function( reasonResp ) {
                                        try {
                                            if ( reasonResp && reasonResp.success && reasonResp.data && reasonResp.data.reason ) {
                                                var r = reasonResp.data.reason;
                                                var msg = '';
                                                try { msg = (typeof r === 'string') ? r : JSON.stringify(r); } catch(e) { msg = String(r); }
                                                var shipment = (reasonResp.data && reasonResp.data.shipment) ? reasonResp.data.shipment : null;
                                                var extra = '';
                                                if ( shipment ) {
                                                    var parts = [];
                                                    if ( shipment.title ) parts.push('Envío: ' + shipment.title);
                                                    if ( shipment.receiver_phone ) parts.push('Tel: ' + shipment.receiver_phone);
                                                    if ( shipment.wpcargo_distrito_destino ) parts.push('Distrito: ' + shipment.wpcargo_distrito_destino);
                                                    if ( parts.length ) extra = ' — ' + parts.join(' | ');
                                                }
                                                $('#tc-import-result').find('.import-record-list').append(`<li class="error">Fila descartada (tracking:${tracking}): ${msg}${extra}</li>`);
                                            }
                                        } catch(err) { console.error('wpcie: error handling merc_get_discard_reason', err); }
                                    }, 'json').fail(function(jqXHR, textStatus, errorThrown){
                                        console.warn('wpcie: merc_get_discard_reason request failed for', tracking, textStatus);
                                    });
                                }
                            });
                        }
                    }, 800 );
                } else {
                    $('#wpcie-import-notification_wrapper').html('');
                    $('#wpcie-import-notification_wrapper').prepend(`<div class="alert alert-danger">${response.message || 'Error en la importación'}</div>`);
                }
                currForm.find('[name="uploadedfile"]').val('');
            },
            error: function(jqXHR, textStatus, errorThrown) {
                console.log( textStatus, errorThrown );
            }
        });
    });

    async function save_records( record, recordCount ){     
        try {
            const response = await $.ajax({
                url: ajaxURL,
                type: 'POST',
                dataType: 'json',
                data: { action: 'wpcie_save_records', record: record },
                timeout: 30000
            });
            console.log('wpcie: save_records result', response, record);
            recordCounter++;
            const loadPercent = Math.ceil((recordCounter / recordCount) * 100);
            $('#wpcie-import-notification_wrapper').find('#tc-import-result .import-record-list').append(`<li class="${response.status}">${response.message}.</li>`);
            $('#wpcie-import-notification_wrapper').find('#loading_percentage').css('width', loadPercent + "%");
            $('#wpcie-import-notification_wrapper').find('#loading_percentage p').text(loadPercent + "%");

            // Si el guardado fue exitoso, consultar si el importador CSV lo descartó posteriormente
            if (response && response.status === 'success') {
                var tracking = record.post_title || record.wpcargo_tracking_number || record.tracking || record.wpcargo_shipment_number || record.shipment_number || null;
                if (!tracking) {
                    var m = (response && response.message) ? String(response.message).match(/MERC-\d+/) : null;
                    if (m) {
                        tracking = m[0];
                        console.log('wpcie: tracking extraído de response.message', tracking);
                    }
                }
                if (tracking) {
                    console.log('wpcie: checking discard reason for', tracking, record, response);
                    var $placeholder = $("<li class='checking'>Comprobando motivo...</li>");
                    $('#wpcie-import-notification_wrapper').find('#tc-import-result .import-record-list').append($placeholder);
                    setTimeout(function () {
                        var phone = record.wpcargo_receiver_phone || record.receiver_phone || record.phone || record.celular || record.telefono || null;
                        var name = record.wpcargo_receiver_name || record.receiver_name || record.name || null;
                        enqueueDiscardRequest({ tracking: tracking, receiver_phone: phone, receiver_name: name, placeholder: $placeholder, record: record });
                    }, 700);
                }
            }

            if (recordCount == recordCounter) {
                $('#wpcie-import-notification_wrapper').find('#tc-import-result .processing').remove();
                $('#wpcie-import-notification_wrapper').find('#tc-import-result').append(`<p class="finish" style="color: #044820;font-size: 1.2rem;">${processComplete}!</p>`);
            }
        } catch (err) {
            console.error('wpcie: save_records ajax error', err);
            recordCounter++;
            const loadPercent = Math.ceil((recordCounter / recordCount) * 100);
            $('#wpcie-import-notification_wrapper').find('#loading_percentage').css('width', loadPercent + "%");
            $('#wpcie-import-notification_wrapper').find('#loading_percentage p').text(loadPercent + "%");
            $('#wpcie-import-notification_wrapper').find('#tc-import-result .outbound_po-list').append(`<li class="error" style="color: #ff0000;">Server error</li>`);
        }
    }

    // --- Save records queue to avoid firing many concurrent save requests ---
    const saveQueue = [];
    const saveConcurrency = 3;
    let saveActive = 0;

    function enqueueSave(job) {
        saveQueue.push(job);
        processSaveQueue();
    }

    function processSaveQueue() {
        if (saveActive >= saveConcurrency) return;
        const job = saveQueue.shift();
        if (!job) return;
        saveActive++;
        // call save_records which is async; ensure we continue after it's done
        save_records(job.record, job.recordCount).finally(function(){
            saveActive--;
            setTimeout(processSaveQueue, 50);
        });
    }
    // --- Discard reason request queue to limit concurrent admin-ajax calls ---
    const discardQueue = [];
    const discardConcurrency = 4;
    let discardActive = 0;

    function enqueueDiscardRequest(job) {
        discardQueue.push(job);
        processDiscardQueue();
    }

    function processDiscardQueue() {
        if (discardActive >= discardConcurrency) return;
        const job = discardQueue.shift();
        if (!job) return;
        discardActive++;
        const tracking = job.tracking;
        console.log('wpcie: sending merc_get_discard_reason (queued)', tracking);
        $.ajax({
            url: ajaxURL,
            type: 'POST',
            dataType: 'json',
            data: { action: 'merc_get_discard_reason', tracking: tracking, receiver_phone: job.receiver_phone, receiver_name: job.receiver_name, nonce: ajaxNonce },
            timeout: 20000 // 20s per request
        }).done(function(reasonResp){
            try {
                // existing handling but minimal: replace placeholder or remove
                if ( reasonResp && reasonResp.success && reasonResp.data ) {
                    var errorMsg = null;
                    if ( reasonResp.data.reason ) {
                        if ( typeof reasonResp.data.reason === 'string' ) errorMsg = reasonResp.data.reason;
                        else if ( Array.isArray( reasonResp.data.reason ) ) errorMsg = reasonResp.data.reason.join('; ');
                        else errorMsg = JSON.stringify(reasonResp.data.reason);
                    } else if ( Array.isArray(reasonResp.data.raw) ) {
                        var reasons = [];
                        reasonResp.data.raw.forEach(function(it){ if ( typeof it === 'string' && /error|fecha|distrito|invalid|missing|shipping_date_before_today|fecha_in_past/i.test(it) ) reasons.push(it.replace(/^Error:\s*/i,'')); });
                        if ( reasons.length ) errorMsg = reasons.join('; ');
                    }
                    var shipment = (reasonResp.data && reasonResp.data.shipment) ? reasonResp.data.shipment : null;
                    var recipient = {};
                    if ( shipment ) {
                        recipient.phone = shipment.receiver_phone || '';
                        recipient.district = shipment.wpcargo_distrito_destino || '';
                        recipient.title = shipment.title || '';
                    } else {
                        recipient.phone = job.record.wpcargo_receiver_phone || job.record.receiver_phone || job.record.phone || '';
                        recipient.district = job.record.wpcargo_distrito_destino || job.record.distrito_destino || '';
                        recipient.title = job.record.post_title || job.record.wpcargo_tracking_number || '';
                    }
                    var extra = [];
                    if ( recipient.title ) extra.push(recipient.title);
                    if ( recipient.phone ) extra.push('Tel: ' + recipient.phone);
                    if ( recipient.district ) extra.push('Distrito: ' + recipient.district);
                    var extraStr = extra.length ? ' — ' + extra.join(' | ') : '';
                    if ( errorMsg ) {
                        try { job.placeholder.replaceWith(`<li class="error">Registro guardado pero descartado (tracking:${tracking}): ${errorMsg}${extraStr}</li>`); } catch(e){}
                    } else {
                        try { job.placeholder.remove(); } catch(e){}
                    }
                } else {
                    try { job.placeholder.remove(); } catch(e){}
                }
            } catch(e){ console.error('wpcie: error processing discard response', e); try{ job.placeholder.remove(); }catch(_){} }
        }).fail(function(jqXHR, textStatus, errorThrown){
            console.warn('wpcie: merc_get_discard_reason failed for', tracking, textStatus);
            // remove placeholder on failure to avoid UI backlog
            try { job.placeholder.remove(); } catch(e){}
        }).always(function(){
            discardActive--;
            // schedule next tick to continue processing queue
            setTimeout(processDiscardQueue, 50);
        });
    }
});