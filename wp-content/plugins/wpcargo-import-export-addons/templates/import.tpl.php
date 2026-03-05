<style>
    /* width */
    #wpcie-import-notification_wrapper ::-webkit-scrollbar { width: 8px; }
    /* Track */
    #wpcie-import-notification_wrapper ::-webkit-scrollbar-track { box-shadow: inset 0 0 5px transparent; border-radius: 10px; }
    /* Handle */
    #wpcie-import-notification_wrapper ::-webkit-scrollbar-thumb { background: #00c851; border-radius: 6px; }
    /* Handle on hover */
    #wpcie-import-notification_wrapper ::-webkit-scrollbar-thumb:hover { background: #00871d; }
    #import_loading_wapper #loading_percentage{ min-width: 60px;}
    ul.import-record-list{ padding-left: 8px; }
    ul.import-record-list li{ list-style: none; } 
    ul.import-record-list li:before{ margin-right: 6px; } 
    ul.import-record-list .success { color: #28a745!important; }
    ul.import-record-list .success:before { content: "\2713\0020"; }
    ul.import-record-list .error { color: #dc3545!important; }
    ul.import-record-list .error:before { content: "\2718"; }
    .record_count-notice { color: #dc3545!important; font-size: .8rem; }
    .record_count-notice:before { content: "\26A0"; font-size: 1.2rem; margin-right: 6px; }
</style>
<div class="row">
    <section id="wpcie-import-form_wrapper" class="col-md-6 border-right">
        <form id="wpcie-import-form" method="POST" enctype="multipart/form-data" class="container-fluid" >
            <input type="hidden" name="action" value="wpcie_import_file">
            <input type="hidden" name="nonce" value="<?php echo wp_create_nonce('wpcargo_import_export_ajaxnonce'); ?>">
            <section class="row">
                <div class="col-md-6">
                    <div class="input-group mb-3">
                        <div class="form-group">
                            <label for="uploadedfile"><?php esc_html_e( 'Import CSV File', 'wpc-import-export' ); ?></label>
                            <input type="file" class="form-control-file" id="uploadedfile" name="uploadedfile" 
                            accept="
                            application/octet-stream,
                            application/vnd.openxmlformats-officedocument.spreadsheetml.sheet, 
                            application/vnd.ms-excel,
                            text/comma-separated-values,
                            text/x-comma-separated-values,
                            text/tab-separated-values,
                            text/csv,
                            application/csv,
                            application/x-csv,
                            .csv
                            " required>
                        </div>
                    </div>
                </div>
            </section>
            <input type="hidden" name="post_type" value="wpcargo_shipment" />
            <input type="submit" class="btn btn-primary btn-sm" name="import_shipment" value="<?php esc_html_e( 'Import Shipment', 'wpc-import-export' ); ?>" />
            <div class="row mt-4">
            <div class="row mt-4">
            <div class="col-sm-12">
                <h4 class="description">🚀 Instrucciones para importar envíos</h4>
                <ol id="import-instruction">
                    <?php
                        // Enlace de plantilla por defecto (para usuarios normales)
                        $wpcie_template_default = 'https://docs.google.com/spreadsheets/d/1ghiTKERwuhmBN9wCw8mnzjEJ0ei7x-BR/edit?usp=sharing&ouid=110464129199262507142&rtpof=true&sd=true';
                        // Enlace alternativo exclusivo para administradores
                        $wpcie_template_admin = 'https://docs.google.com/spreadsheets/d/1w9AaVMV6_2PvPeEeESfwzDMmtLktoUJ6nwV-OC2493k/edit?gid=0#gid=0';
                        $wpcie_template_link = current_user_can('manage_options') ? $wpcie_template_admin : $wpcie_template_default;
                    ?>
                    <li>📄 <a href="<?php echo esc_url( $wpcie_template_link ); ?>" target="_blank" class="description">Copia la plantilla en Google Sheets</a> → Abre la plantilla y haz una copia para ingresar tus datos.</li>
                    <li>✅ Completa todos los campos → Llena todas las columnas sin dejar espacios vacíos. <strong>No debe haber celdas ni filas vacías</strong>, ya que esto puede generar errores y evitar la carga de los envíos.</li>
                    <li>💾 Descarga el archivo → Guarda la hoja de cálculo en formato CSV.</li>
                    <li>📤 Sube los pedidos → Selecciona el archivo CSV y cárgalo en el sistema.</li>
                    <li>📺 Si tienes dudas <a href="https://google.com" target="_blank" class="description">Consulta el tutorial en YouTube</a></li>
                </ol>
                <p>🔹 <strong>Nota:</strong> Asegúrate de revisar tu archivo antes de subirlo para evitar errores.</p>
            </div>
</div>

</div>

        </form>
    </section>
    <section id="wpcie-import-notification_wrapper" class="col-md-6"></section>
</div>

