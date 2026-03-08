content = open('c:/Users/Usuario/mercourier-web/wp-content/plugins/merc-table-customizer/admin/classes/class-shipment-table.php', 'r', encoding='utf-8').read()

# ── 1. Primer <th>: añadir checkbox .merc-card-select-all ───────────────────
old1 = (
    "\t\t\t\t\tif (isFirst) {\n"
    "\t\t\t\t\t\t// Primer TH tiene #wpcfe-select-all: lo neutralizamos para evitar\n"
    "\t\t\t\t\t\t// IDs duplicados. Cada card ya tiene su propio .merc-tienda-checkbox.\n"
    "\t\t\t\t\t\theaderHtml += '<th></th>';\n"
    "\t\t\t\t\t\tisFirst = false;\n"
    "\t\t\t\t\t}"
)
new1 = (
    "\t\t\t\t\tif (isFirst) {\n"
    "\t\t\t\t\t\t// Primer TH: reemplazamos #wpcfe-select-all con nuestro propio\n"
    "\t\t\t\t\t\t// checkbox por-card para evitar IDs duplicados y tener control funcional.\n"
    "\t\t\t\t\t\theaderHtml += '<th class=\"merc-card-select-all-th\"><input type=\"checkbox\" class=\"merc-card-select-all form-check-input\" title=\"Seleccionar todos\" style=\"cursor:pointer;width:16px;height:16px;\"></th>';\n"
    "\t\t\t\t\t\tisFirst = false;\n"
    "\t\t\t\t\t}"
)
if old1 in content:
    content = content.replace(old1, new1, 1)
    print('Fix 1 OK')
else:
    print('Fix 1 NOT FOUND')

# ── 2. Tienda checkbox handler: también propaga a .merc-card-select-all ──────
old2 = (
    "\t\t\t\t\t$header.find('input[type=\"checkbox\"]').on('change', function() {\n"
    "\t\t\t\t\t\tconst isChecked = $(this).prop('checked');\n"
    "\t\t\t\t\t\t$(this).prop('indeterminate', false);\n"
    "\t\t\t\t\t\t$innerTbody.find('input[type=\"checkbox\"]').prop('checked', isChecked);\n"
    "\t\t\t\t\t\tupdateGlobalCheckboxState();\n"
    "\t\t\t\t\t});"
)
new2 = (
    "\t\t\t\t\t// Checkbox en HEADER de la card (barra superior)\n"
    "\t\t\t\t\t$header.find('input[type=\"checkbox\"]').on('change', function() {\n"
    "\t\t\t\t\t\tconst isChecked = $(this).prop('checked');\n"
    "\t\t\t\t\t\t$(this).prop('indeterminate', false);\n"
    "\t\t\t\t\t\t$innerTable.find('.merc-card-select-all').prop('checked', isChecked).prop('indeterminate', false);\n"
    "\t\t\t\t\t\t$innerTbody.find('.wpcfe-shipments').prop('checked', isChecked);\n"
    "\t\t\t\t\t\tupdateGlobalCheckboxState();\n"
    "\t\t\t\t\t});\n"
    "\n"
    "\t\t\t\t\t// Checkbox en HEADER de la tabla interna (primera columna)\n"
    "\t\t\t\t\t$innerTable.find('.merc-card-select-all').on('change', function() {\n"
    "\t\t\t\t\t\tconst isChecked = $(this).prop('checked');\n"
    "\t\t\t\t\t\t$(this).prop('indeterminate', false);\n"
    "\t\t\t\t\t\t$header.find('.merc-tienda-checkbox').prop('checked', isChecked).prop('indeterminate', false);\n"
    "\t\t\t\t\t\t$innerTbody.find('.wpcfe-shipments').prop('checked', isChecked);\n"
    "\t\t\t\t\t\tupdateGlobalCheckboxState();\n"
    "\t\t\t\t\t});\n"
    "\n"
    "\t\t\t\t\t// Sync encabezados al cambiar filas individuales\n"
    "\t\t\t\t\t$innerTbody.on('change', '.wpcfe-shipments', function() {\n"
    "\t\t\t\t\t\tconst total   = $innerTbody.find('.wpcfe-shipments').length;\n"
    "\t\t\t\t\t\tconst checked = $innerTbody.find('.wpcfe-shipments:checked').length;\n"
    "\t\t\t\t\t\tconst allChecked = checked === total && total > 0;\n"
    "\t\t\t\t\t\tconst someChecked = checked > 0 && checked < total;\n"
    "\t\t\t\t\t\t$innerTable.find('.merc-card-select-all').prop('checked', allChecked).prop('indeterminate', someChecked);\n"
    "\t\t\t\t\t\t$header.find('.merc-tienda-checkbox').prop('checked', allChecked).prop('indeterminate', someChecked);\n"
    "\t\t\t\t\t\tupdateGlobalCheckboxState();\n"
    "\t\t\t\t\t});"
)
if old2 in content:
    content = content.replace(old2, new2, 1)
    print('Fix 2 OK')
else:
    print('Fix 2 NOT FOUND')
    # show context
    idx = content.find("$header.find('input[type=\"checkbox\"]').on('change'")
    if idx >= 0:
        print(repr(content[idx:idx+200]))

# ── 3. updateGlobalCheckboxState: también actualiza .merc-card-select-all ───
old3 = (
    "\t\t\t\t$c.find('.merc-tienda-checkbox')\n"
    "\t\t\t\t\t  .prop('checked', ct > 0 && cc === ct)\n"
    "\t\t\t\t\t  .prop('indeterminate', cc > 0 && cc < ct);\n"
    "\t\t\t\t});"
)
new3 = (
    "\t\t\t\t$c.find('.merc-tienda-checkbox')\n"
    "\t\t\t\t\t  .prop('checked', ct > 0 && cc === ct)\n"
    "\t\t\t\t\t  .prop('indeterminate', cc > 0 && cc < ct);\n"
    "\t\t\t\t$c.find('.merc-card-select-all')\n"
    "\t\t\t\t\t  .prop('checked', ct > 0 && cc === ct)\n"
    "\t\t\t\t\t  .prop('indeterminate', cc > 0 && cc < ct);\n"
    "\t\t\t\t});"
)
if old3 in content:
    content = content.replace(old3, new3, 1)
    print('Fix 3 OK')
else:
    print('Fix 3 NOT FOUND')

# ── 4. #merc-select-all-global: también actualiza .merc-card-select-all ─────
old4 = (
    "\t\t\t\t\t$('.merc-tienda-checkbox').prop('checked', ck).prop('indeterminate', false);\n"
    "\t\t\t\t\t$('.wpcfe-shipments').prop('checked', ck);"
)
new4 = (
    "\t\t\t\t\t$('.merc-tienda-checkbox').prop('checked', ck).prop('indeterminate', false);\n"
    "\t\t\t\t\t$('.merc-card-select-all').prop('checked', ck).prop('indeterminate', false);\n"
    "\t\t\t\t\t$('.wpcfe-shipments').prop('checked', ck);"
)
if old4 in content:
    content = content.replace(old4, new4, 1)
    print('Fix 4 OK')
else:
    print('Fix 4 NOT FOUND')

open('c:/Users/Usuario/mercourier-web/wp-content/plugins/merc-table-customizer/admin/classes/class-shipment-table.php', 'w', encoding='utf-8').write(content)
print('Done')
