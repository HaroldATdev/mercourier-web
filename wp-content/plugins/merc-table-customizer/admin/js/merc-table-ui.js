/**
 * merc-table-ui.js
 * Reordena columnas del #shipment-list para el historial de envíos.
 */
(function ($) {
    $(function () {
        // Si el accordion ya fue generado, no procesar la tabla
        if ($('#shipment-history-accordion').length > 0) {
            console.log('🛑 merc-table-ui.js: Accordion ya procesado, saltando reorden');
            return;
        }

        var $table = $('#shipment-list');
        if (!$table.length) return;

        function findThIndexByText($ths, text) {
            var idx = -1;
            $ths.each(function (i) {
                var t = $(this).text().toUpperCase().trim();
                if (t.indexOf(text.toUpperCase()) !== -1) { idx = i; return false; }
            });
            return idx;
        }

        function moveColumn(afterText, moveText) {
            var $ths    = $table.find('thead tr:first th');
            var afterIdx = findThIndexByText($ths, afterText);
            var moveIdx  = findThIndexByText($ths, moveText);
            if (afterIdx === -1 || moveIdx === -1 || moveIdx === afterIdx + 1) return;

            var $moveTh  = $ths.eq(moveIdx);
            var $afterTh = $ths.eq(afterIdx);
            $moveTh.insertAfter($afterTh);

            $table.find('tbody tr').each(function () {
                var $cells  = $(this).find('td');
                var $moveTd  = $cells.eq(moveIdx);
                var $afterTd = $cells.eq(afterIdx);
                if ($moveTd.length && $afterTd.length) {
                    $moveTd.insertAfter($afterTd);
                }
            });
        }

        // "Estado" queda justo después de "Cambio de Producto"
        moveColumn('Cambio de Producto', 'Estado');

        // Columna de seguimiento va al final (última columna)
        function moveColumnToEnd(moveText) {
            var $ths    = $table.find('thead tr:first th');
            var moveIdx = findThIndexByText($ths, moveText);
            if (moveIdx === -1 || moveIdx === $ths.length - 1) return;

            var $moveTh = $ths.eq(moveIdx);
            $table.find('thead tr:first').append($moveTh);

            $table.find('tbody tr').each(function () {
                var $cells  = $(this).find('td');
                var $moveTd = $cells.eq(moveIdx);
                if ($moveTd.length) { $(this).append($moveTd); }
            });
        }

        function aplicarOrden() {
            ['Número de seguimiento', 'Seguimiento', 'Tracking Number', 'Número de Tracking'].forEach(function (candidate) {
                moveColumnToEnd(candidate);
            });
        }

        aplicarOrden();
        setTimeout(aplicarOrden, 800);
    });
})(jQuery);
