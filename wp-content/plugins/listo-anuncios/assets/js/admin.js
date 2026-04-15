(function ($) {
    'use strict';

    var mediaFrames = {};
    var selected    = { web: { id: 0, url: '' }, panel: { id: 0, url: '' } };

    // ── Abrir selector de medios ──────────────────────────────────────────
    $(document).on('click', '.la-btn-upload', function () {
        var tipo = $(this).data('tipo');

        if ( mediaFrames[tipo] ) {
            mediaFrames[tipo].open();
            return;
        }

        mediaFrames[tipo] = wp.media({
            title:    'Seleccionar imagen para el anuncio',
            button:   { text: 'Usar esta imagen' },
            multiple: false,
            library:  { type: 'image' },
        });

        mediaFrames[tipo].on('select', function () {
            var attachment = mediaFrames[tipo].state().get('selection').first().toJSON();
            selected[tipo].id  = attachment.id;
            selected[tipo].url = attachment.url;

            var $preview = $('.la-new-preview--' + tipo);
            $preview.find('.la-preview-img').attr('src', attachment.url);
            $preview.fadeIn(200);

            var img = new Image();
            img.onload = function () {
                $preview.find('.la-preview-dims').text(img.naturalWidth + ' × ' + img.naturalHeight + ' px');
            };
            img.src = attachment.url;

            $('html, body').animate({
                scrollTop: $preview.offset().top - 80
            }, 300);
        });

        mediaFrames[tipo].open();
    });

    // ── Cancelar ──────────────────────────────────────────────────────────
    $(document).on('click', '.la-btn-cancel', function () {
        var tipo = $(this).data('tipo');
        selected[tipo] = { id: 0, url: '' };
        $('.la-new-preview--' + tipo).fadeOut(150);
    });

    // ── Guardar ───────────────────────────────────────────────────────────
    $(document).on('click', '.la-btn-save', function () {
        var tipo   = $(this).data('tipo');
        var action = $(this).data('action');

        if ( ! selected[tipo].id || ! selected[tipo].url ) {
            showNotice('Selecciona una imagen primero.', 'err');
            return;
        }

        var $btn = $(this).prop('disabled', true).text('Guardando…');

        $.post(LA_Admin.ajax_url, {
            action:    action,
            nonce:     LA_Admin.nonce,
            image_id:  selected[tipo].id,
            image_url: selected[tipo].url,
        }, function (res) {
            $btn.prop('disabled', false).text('Guardar y activar anuncio');
            if (res.success) {
                showNotice('Anuncio guardado y activado. (Versión #' + res.data.version + ')', 'ok');
                setTimeout(function () { location.reload(); }, 1200);
            } else {
                showNotice('Error: ' + (res.data || 'No se pudo guardar.'), 'err');
            }
        }).fail(function () {
            $btn.prop('disabled', false).text('Guardar y activar anuncio');
            showNotice('Error de conexión. Inténtalo de nuevo.', 'err');
        });
    });

    // ── Eliminar ──────────────────────────────────────────────────────────
    $(document).on('click', '.la-btn-delete', function () {
        if ( ! confirm('¿Estás seguro? El pop-up dejará de mostrarse.') ) return;

        var action = $(this).data('action');
        var $btn   = $(this).prop('disabled', true).text('Eliminando…');

        $.post(LA_Admin.ajax_url, {
            action: action,
            nonce:  LA_Admin.nonce,
        }, function (res) {
            if (res.success) {
                showNotice('Anuncio eliminado correctamente.', 'ok');
                setTimeout(function () { location.reload(); }, 900);
            } else {
                $btn.prop('disabled', false).text('Eliminar anuncio');
                showNotice('Error al eliminar.', 'err');
            }
        });
    });

    // ── Helper: mostrar notice ────────────────────────────────────────────
    function showNotice(msg, type) {
        var $n = $('#la-notice');
        $n.removeClass('la-notice--ok la-notice--err')
          .addClass('la-notice--' + type)
          .html(msg)
          .fadeIn(200);
        setTimeout(function () { $n.fadeOut(400); }, 5000);
    }

})(jQuery);
