<?php if ( ! defined( 'ABSPATH' ) ) exit; ?>

<div id="la-popup-overlay" role="dialog" aria-modal="true" aria-label="Anuncio">
    <div class="la-popup-box">
        <button id="la-popup-close" aria-label="Cerrar anuncio">&#10005;</button>
        <div class="la-popup-img-wrap">
            <img
                src="<?php echo esc_url( $data['image_url'] ); ?>"
                alt="Anuncio"
                class="la-popup-img"
            />
        </div>
    </div>
</div>

