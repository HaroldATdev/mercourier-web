<div class="mb-4 border-bottom">
    <?php if( can_wpcie_export() ): ?>
        <a href="<?php echo get_the_permalink(); ?>" class="btn btn-sm btn-<?php echo ( $template_type != 'import' ) ? 'success' : 'light' ; ?>"><?php esc_html_e( 'Export', 'wpc-import-export' ); ?></a>
    <?php endif; ?>
    <?php if( can_wpcie_import() ): ?>
        <a href="<?php echo get_the_permalink().'?type=import'; ?>" class="btn btn-sm btn-<?php echo ( $template_type == 'import' ) ? 'success' : 'light' ; ?>"><?php esc_html_e( 'Import', 'wpc-import-export' ); ?></a>
    <?php endif; ?>
</div>