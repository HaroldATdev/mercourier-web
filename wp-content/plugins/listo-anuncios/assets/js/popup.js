(function ($) {
    'use strict';

    var STORAGE_KEY = 'la_popup_seen_v' + LA_Popup.version;
    if (sessionStorage.getItem(STORAGE_KEY)) return;

    $(window).on('load', function () {

        var html = [
            '<div id="la-popup-overlay" style="',
                'position:fixed!important;',
                'top:0!important;left:0!important;',
                'width:100vw!important;height:100vh!important;',
                'background:rgba(0,0,0,0.78)!important;',
                'display:flex!important;',
                'align-items:center!important;',
                'justify-content:center!important;',
                'z-index:2147483647!important;',
                'padding:20px!important;',
                'box-sizing:border-box!important;',
                'opacity:0;transition:opacity .3s ease;">',
              '<div id="la-popup-box" style="',
                  'position:relative!important;',
                  'background:#fff!important;',
                  'border-radius:12px!important;',
                  'max-width:580px!important;',
                  'width:100%!important;',
                  'overflow:visible!important;',
                  'box-shadow:0 20px 60px rgba(0,0,0,.55)!important;',
                  'transform:translateY(20px);transition:transform .35s ease;">',
                '<button id="la-popup-close" style="',
                    'position:absolute!important;',
                    'top:-13px!important;right:-13px!important;',
                    'width:28px!important;height:28px!important;',
                    'border-radius:50%!important;',
                    'background:#222!important;',
                    'border:3px solid #fff!important;',
                    'color:#fff!important;',
                    'font-size:13px!important;font-weight:700!important;',
                    'line-height:1!important;',
                    'cursor:pointer!important;',
                    'display:flex!important;',
                    'align-items:center!important;',
                    'justify-content:center!important;',
                    'z-index:2147483647!important;',
                    'box-shadow:0 2px 8px rgba(0,0,0,.4)!important;',
                    'padding:0!important;box-sizing:border-box!important;">&#10005;</button>',
                '<div style="line-height:0;border-radius:12px;overflow:hidden;">',
                  '<img src="' + LA_Popup.image_url + '" alt="Anuncio" style="',
                      'width:100%!important;height:auto!important;',
                      'display:block!important;',
                      'max-height:80vh!important;',
                      'object-fit:contain!important;',
                      'border-radius:12px!important;" />',
                '</div>',
              '</div>',
            '</div>'
        ].join('');

        $('body').append(html);

        var $overlay = $('#la-popup-overlay');
        var $box     = $('#la-popup-box');

        sessionStorage.setItem(STORAGE_KEY, '1');

        // Animar entrada
        setTimeout(function () {
            $overlay.css('opacity', '1');
            $box.css('transform', 'translateY(0)');
        }, 50);

        // Cerrar con X — usando delegación de eventos en body
        $(document).on('click', '#la-popup-close', function (e) {
            e.stopPropagation();
            cerrar();
        });

        // Cerrar al hacer clic fuera
        $(document).on('click', '#la-popup-overlay', function (e) {
            if ($(e.target).attr('id') === 'la-popup-overlay') cerrar();
        });

        // Cerrar con ESC
        $(document).on('keydown.lapopup', function (e) {
            if (e.key === 'Escape') cerrar();
        });
    });

    function cerrar() {
        // Limpiar eventos inmediatamente antes de animar
        $(document).off('click', '#la-popup-close');
        $(document).off('click', '#la-popup-overlay');
        $(document).off('keydown.lapopup');

        var $overlay = $('#la-popup-overlay');
        $overlay.css({
            'opacity': '0',
            'pointer-events': 'none'  // Deshabilitar clicks en el overlay inmediatamente
        });
        $('#la-popup-box').css('transform', 'translateY(20px)');

        setTimeout(function () {
            $overlay.remove();
        }, 350);
    }

})(jQuery);
