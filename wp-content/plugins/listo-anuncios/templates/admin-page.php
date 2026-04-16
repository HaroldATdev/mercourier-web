<?php if ( ! defined( 'ABSPATH' ) ) exit; ?>

<div class="la-wrap">

    <div class="la-header">
        <div class="la-header__icon">
            <i class="fa fa-bullhorn" style="font-size:36px; color:#2563EB;"></i>
        </div>
        <div>
            <h1 class="la-header__title">Anuncios</h1>
            <p class="la-header__sub">Gestiona los pop-ups para la página web y el panel de clientes.</p>
        </div>
    </div>

    <?php foreach ( [
        'web'   => [ 'data' => $data_web,   'label' => 'Página Web',          'sub' => 'Visible para todos los visitantes de la web.',           'action_save' => 'la_save_web',   'action_delete' => 'la_delete_web'   ],
        'panel' => [ 'data' => $data_panel, 'label' => 'Panel de WPCargo',    'sub' => 'Visible solo para clientes logueados (no administradores).', 'action_save' => 'la_save_panel', 'action_delete' => 'la_delete_panel' ],
    ] as $tipo => $config ) :
        $data = $config['data'];
    ?>

    <div class="la-card">
        <h2 class="la-card__title">
            Anuncio — <?php echo $config['label']; ?>
            <small style="font-weight:400; font-size:13px; color:#64748B; margin-left:8px;"><?php echo $config['sub']; ?></small>
        </h2>

        <div class="la-status-row">
            <span class="la-badge <?php echo ! empty( $data['activo'] ) && ! empty( $data['image_url'] ) ? 'la-badge--on' : 'la-badge--off'; ?>">
                <?php echo ! empty( $data['activo'] ) && ! empty( $data['image_url'] ) ? 'Activo' : 'Inactivo'; ?>
            </span>
            <?php if ( ! empty( $data['version'] ) ) : ?>
                <span class="la-version">Version: <strong>#<?php echo intval( $data['version'] ); ?></strong></span>
            <?php endif; ?>
        </div>

        <?php if ( ! empty( $data['image_url'] ) ) : ?>
            <div class="la-current-preview">
                <p class="la-label">IMAGEN ACTUAL:</p>
                <div class="la-preview-frame">
                    <img src="<?php echo esc_url( $data['image_url'] ); ?>" alt="Anuncio actual" />
                </div>
                <button
                    class="la-btn la-btn--danger la-btn-delete"
                    data-action="<?php echo $config['action_delete']; ?>">
                    Eliminar anuncio
                </button>
            </div>
        <?php else : ?>
            <p class="la-empty">No hay ningún anuncio activo. Sube una imagen abajo.</p>
        <?php endif; ?>

        <div style="margin-top: 20px; padding-top: 20px; border-top: 1px solid #E2E8F0;">
            <p class="la-label">SUBIR NUEVA IMAGEN:</p>

            <div class="la-specs">
                <div class="la-spec-item">
                    <i class="fa fa-crop la-spec-icon"></i>
                    <div><strong>Tamaño recomendado</strong><span>800 x 600 px o 600 x 800 px</span></div>
                </div>
                <div class="la-spec-item">
                    <i class="fa fa-file-image-o la-spec-icon"></i>
                    <div><strong>Formatos</strong><span>JPG, PNG, WebP, GIF</span></div>
                </div>
            </div>

            <div class="la-upload-zone la-upload-zone--<?php echo $tipo; ?>">
                <p>Haz clic para seleccionar una imagen</p>
                <button
                    class="la-btn la-btn--primary la-btn-upload"
                    data-tipo="<?php echo $tipo; ?>">
                    Seleccionar imagen
                </button>
            </div>

            <div class="la-new-preview la-new-preview--<?php echo $tipo; ?>" style="display:none;">
                <p class="la-label">VISTA PREVIA:</p>
                <div class="la-preview-frame la-preview-frame--new">
                    <img class="la-preview-img" src="" alt="Vista previa" />
                    <div class="la-preview-overlay">
                        <span class="la-preview-dims"></span>
                    </div>
                </div>
                <div class="la-preview-actions">
                    <button
                        class="la-btn la-btn--success la-btn-save"
                        data-tipo="<?php echo $tipo; ?>"
                        data-action="<?php echo $config['action_save']; ?>">
                        Guardar y activar anuncio
                    </button>
                    <button class="la-btn la-btn--ghost la-btn-cancel" data-tipo="<?php echo $tipo; ?>">
                        Cancelar
                    </button>
                </div>
            </div>
        </div>
    </div>

    <?php endforeach; ?>

    <div id="la-notice" class="la-notice" style="display:none;"></div>

</div>
