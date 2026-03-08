content = open(
    'c:/Users/Usuario/mercourier-web/wp-content/plugins/merc-table-customizer/admin/classes/class-shipment-table.php',
    'r', encoding='utf-8'
).read()

# ── 1. CSS: fuerza apariencia nativa de los checkboxes ──────────────────────
old_css = "\t\t\t.merc-tienda-card-table tbody td {\n\t\t\t\tpadding: 8px 12px;\n\t\t\t\tvertical-align: middle;\n\t\t\t\tfont-size: 13px;\n\t\t\t}"
new_css = ("\t\t\t.merc-tienda-card-table tbody td {\n"
           "\t\t\t\tpadding: 8px 12px;\n"
           "\t\t\t\tvertical-align: middle;\n"
           "\t\t\t\tfont-size: 13px;\n"
           "\t\t\t}\n"
           "\n"
           "\t\t\t/* Fuerza apariencia nativa de los checkboxes de control */\n"
           "\t\t\t.merc-card-select-all,\n"
           "\t\t\t.merc-tienda-checkbox {\n"
           "\t\t\t\t-webkit-appearance: checkbox !important;\n"
           "\t\t\t\tappearance: checkbox !important;\n"
           "\t\t\t\topacity: 1 !important;\n"
           "\t\t\t\tposition: static !important;\n"
           "\t\t\t\tdisplay: inline-block !important;\n"
           "\t\t\t\twidth: 16px !important;\n"
           "\t\t\t\theight: 16px !important;\n"
           "\t\t\t\tmargin: 0 !important;\n"
           "\t\t\t\tcursor: pointer;\n"
           "\t\t\t}")
if old_css in content:
    content = content.replace(old_css, new_css, 1)
    print("Fix 1 CSS OK")
else:
    print("Fix 1 CSS NOT FOUND")

# ── 2. Eliminar handlers directos de la card-building loop ──────────────────
old_handlers = (
    "\n"
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
new_handlers = ""  # Remove completely – moved to document delegation
if old_handlers in content:
    content = content.replace(old_handlers, new_handlers, 1)
    print("Fix 2 handlers removed OK")
else:
    print("Fix 2 NOT FOUND - checking...")
    idx = content.find("Checkbox en HEADER de la card")
    if idx >= 0:
        print(repr(content[idx-5:idx+100]))

# ── 3. Reemplazar el handler de .wpcfe-shipments en document.ready ──────────
old_ready = "\t\t\t\t// Sync checkbox global cuando cambia cualquier fila individual\n\t\t\t\t$(document).on('change', '.wpcfe-shipments', updateGlobalCheckboxState);"
new_ready = (
    "\t\t\t\t// ── Checkbox select-all de cada card (delegado desde document) ──────\n"
    "\t\t\t\t$(document).on('change', '.merc-card-select-all', function() {\n"
    "\t\t\t\t\tvar $cb = $(this);\n"
    "\t\t\t\t\tvar isChecked = $cb.prop('checked');\n"
    "\t\t\t\t\t$cb.prop('indeterminate', false);\n"
    "\t\t\t\t\tvar $card = $cb.closest('.merc-tienda-card');\n"
    "\t\t\t\t\t$card.find('.wpcfe-shipments').prop('checked', isChecked);\n"
    "\t\t\t\t\t$card.find('.merc-tienda-checkbox').prop('checked', isChecked).prop('indeterminate', false);\n"
    "\t\t\t\t\tupdateGlobalCheckboxState();\n"
    "\t\t\t\t});\n"
    "\n"
    "\t\t\t\t// ── Checkbox card-header (delegado) ────────────────────────────────\n"
    "\t\t\t\t$(document).on('change', '.merc-tienda-checkbox', function() {\n"
    "\t\t\t\t\tvar $cb = $(this);\n"
    "\t\t\t\t\tvar isChecked = $cb.prop('checked');\n"
    "\t\t\t\t\t$cb.prop('indeterminate', false);\n"
    "\t\t\t\t\tvar $card = $cb.closest('.merc-tienda-card');\n"
    "\t\t\t\t\t$card.find('.wpcfe-shipments').prop('checked', isChecked);\n"
    "\t\t\t\t\t$card.find('.merc-card-select-all').prop('checked', isChecked).prop('indeterminate', false);\n"
    "\t\t\t\t\tupdateGlobalCheckboxState();\n"
    "\t\t\t\t});\n"
    "\n"
    "\t\t\t\t// ── Sync encabezados al cambiar fila individual ─────────────────────\n"
    "\t\t\t\t$(document).on('change', '.wpcfe-shipments', function() {\n"
    "\t\t\t\t\tvar $card = $(this).closest('.merc-tienda-card');\n"
    "\t\t\t\t\tupdateGlobalCheckboxState();\n"
    "\t\t\t\t\tif (!$card.length) return;\n"
    "\t\t\t\t\tvar total   = $card.find('.wpcfe-shipments').length;\n"
    "\t\t\t\t\tvar checked = $card.find('.wpcfe-shipments:checked').length;\n"
    "\t\t\t\t\tvar allCk   = checked === total && total > 0;\n"
    "\t\t\t\t\tvar someCk  = checked > 0 && checked < total;\n"
    "\t\t\t\t\t$card.find('.merc-card-select-all').prop('checked', allCk).prop('indeterminate', someCk);\n"
    "\t\t\t\t\t$card.find('.merc-tienda-checkbox').prop('checked', allCk).prop('indeterminate', someCk);\n"
    "\t\t\t\t});"
)
if old_ready in content:
    content = content.replace(old_ready, new_ready, 1)
    print("Fix 3 document.ready OK")
else:
    print("Fix 3 NOT FOUND")

open(
    'c:/Users/Usuario/mercourier-web/wp-content/plugins/merc-table-customizer/admin/classes/class-shipment-table.php',
    'w', encoding='utf-8'
).write(content)
print("Done")
