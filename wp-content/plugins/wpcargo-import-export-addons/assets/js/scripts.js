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
                if( response.status == 'success' ){
                    $('#wpcie-import-notification_wrapper').find('#tc-import-result').append( `<p>${response.message}</p><ul class="import-record-list"></ul>`);
                    $('#wpcie-import-notification_wrapper').append( '<div id="import_loading_wapper"><div id="loading_percentage" style="background-color: #00c851;"><p style="color: #044820;padding: 0 12px;"></p></div></div>' );
                    var records     = response.data;
                    var recordCount = records.length;
                    for( let i = 0; i < records.length; i++ ){
                        save_records( records[i], recordCount );
                    }  
                }else{
                    $('#wpcie-import-notification_wrapper').html('');
                    $('#wpcie-import-notification_wrapper').prepend(`<div class="alert alert-danger">${response.message}</div>`);
                }
                currForm.find('[name="uploadedfile"]').val('');
            },
            error: function(jqXHR, textStatus, errorThrown) {
                console.log( textStatus, errorThrown );
            }
        });
    });

    async function save_records( record, recordCount ){     
        const result = await $.ajax({
          url : ajaxURL,
          type: "post",
          data: {
            action : 'wpcie_save_records',
            record : record,
          },
          beforeSend: function() {},
          success: function (response) {
            recordCounter ++;
            const loadPercent = Math.ceil( ( recordCounter / recordCount ) * 100 );
            $('#wpcie-import-notification_wrapper').find( '#tc-import-result .import-record-list' ).append(`<li class="${response.status}">${response.message}.</li>`);  
            $('#wpcie-import-notification_wrapper').find( '#loading_percentage' ).css("width", loadPercent + "%");  
            $('#wpcie-import-notification_wrapper').find( '#loading_percentage p' ).text( loadPercent + "%");  
            if( recordCount == recordCounter ){
              $('#wpcie-import-notification_wrapper').find( '#tc-import-result .processing' ).remove();
              $('#wpcie-import-notification_wrapper').find( '#tc-import-result' ).append(`<p class="finish" style="color: #044820;font-size: 1.2rem;">${processComplete}!</p>`);
            }
          },
          error: function(jqXHR, textStatus, errorThrown) {
            recordCounter ++;
            const loadPercent = Math.ceil( ( recordCounter / recordCount ) * 100 );
            $('#wpcie-import-notification_wrapper').find( '#loading_percentage' ).css("width", loadPercent + "%");  
            $('#wpcie-import-notification_wrapper').find( '#loading_percentage p' ).text( loadPercent + "%");  
            $('#wpcie-import-notification_wrapper').find( '#tc-import-result .outbound_po-list' ).append(`<li class="error" style="color: #ff0000;">Server error 502</li>`);  
          }
        });
    }
});