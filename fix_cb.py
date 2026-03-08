content = open(
    'c:/Users/Usuario/mercourier-web/wp-content/plugins/merc-table-customizer/admin/classes/class-shipment-table.php',
    'r', encoding='utf-8'
).read()

# ── 1. Cambiar headerHtml para saltar el 1er TH en lugar de reemplazarlo ────
# (ahora generamos el 1er TH por card, abajo)
old_header_build = (
    "\t\t\t\tif ($headerRow.length) {\n"
    "\t\t\t\t\tlet isFirst = true;\n"
    "\t\t\t\t\t$headerRow.find('th').each(function() {\n"
    "\t\t\t\t\t\tif (isFirst) {\n"
    "\t\t\t\t\t\t\t// Primer TH tiene #wpcfe-select-all: lo neutralizamos para evitar\n"
    "\t\t\t\t\t\t\t// IDs duplicados. Cada card ya tiene su propio .merc-tienda-checkbox.\n"
    "\t\t\t\t\t\t\theaderHtml += '<th class=\"merc-card-select-all-th\" style=\"text-align:center;padding:8px;\"><input type=\"checkbox\" class=\"merc-card-select-all\" title=\"Seleccionar todos\" style=\"cursor:pointer;width:16px;height:16px;display:inline-block;position:static;margin:0;\"></th>';\n"
    "\t\t\t\t\t\t\tisFirst = false;\n"
    "\t\t\t\t\t\t} else {\n"
    "\t\t\t\t\t\t\theaderHtml += '<th>' + $(this).html() + '</th>';\n"
    "\t\t\t\t\t\t}\n"
    "\t\t\t\t\t});\n"
    "\t\t\t\t}"
)
new_header_build = (
    "\t\t\t\tif ($headerRow.length) {\n"
    "\t\t\t\t\tlet isFirst = true;\n"
    "\t\t\t\t\t$headerRow.find('th').each(function() {\n"
    "\t\t\t\t\t\tif (isFirst) { isFirst = false; return; } // 1er TH (wpcfe-select-all) se genera por card\n"
    "\t\t\t\t\t\theaderHtml += '<th>' + $(this).html() + '</th>';\n"
    "\t\t\t\t\t});\n"
    "\t\t\t\t}"
)
if old_header_build in content:
    content = content.replace(old_header_build, new_header_build, 1)
    print("Fix 1 header build OK")
else:
    print("Fix 1 NOT FOUND")

# ── 2. Quitar .merc-tienda-checkbox del card-header + simplificar click ──────
old_card_header = (
    "\t\t\t\tconst $header = $('<div class=\"merc-tienda-card-header\"></div>').html(\n"
    "\t\t\t\t\t'<div class=\"merc-tienda-info\">' +\n"
    "\t\t\t\t\t'<input type=\"checkbox\" class=\"merc-tienda-checkbox\">' +\n"
    "\t\t\t\t\t'<strong>' + tienda + '</strong>' +\n"
    "\t\t\t\t\t'<span style=\"font-size:11px; opacity:0.8;\">(' + rowsForTienda.length + ' envíos)</span>' +\n"
    "\t\t\t\t\t'</div>' +\n"
    "\t\t\t\t\t'<span class=\"merc-tienda-icon\">▼</span>'\n"
    "\t\t\t\t);"
)
new_card_header = (
    "\t\t\t\tconst $header = $('<div class=\"merc-tienda-card-header\"></div>').html(\n"
    "\t\t\t\t\t'<div class=\"merc-tienda-info\">' +\n"
    "\t\t\t\t\t'<strong>' + tienda + '</strong>' +\n"
    "\t\t\t\t\t'<span style=\"font-size:11px; opacity:0.8;\">(' + rowsForTienda.length + ' envíos)</span>' +\n"
    "\t\t\t\t\t'</div>' +\n"
    "\t\t\t\t\t'<span class=\"merc-tienda-icon\">▼</span>'\n"
    "\t\t\t\t);"
)
if old_card_header in content:
    content = content.replace(old_card_header, new_card_header, 1)
    print("Fix 2 card-header OK")
else:
    print("Fix 2 NOT FOUND")

# ── 3. Tabla interna: generar 1er TH por card con Bootstrap form-check ───────
old_inner_table = (
    "\t\t\t\t// Tabla interna CON headers\n"
    "\t\t\t\tconst $innerTable = $('<table class=\"merc-tienda-card-table wpc-shipment-history table table-hover table-sm\"><thead><tr>' + headerHtml + '</tr></thead><tbody></tbody></table>');"
)
new_inner_table = (
    "\t\t\t\t// Tabla interna CON headers\n"
    "\t\t\t\t// 1er TH: select-all con el mismo patrón Bootstrap que las filas\n"
    "\t\t\t\tconst saId = 'merc-sa-' + tiendaSlug;\n"
    "\t\t\t\tconst firstTh = '<th class=\"merc-card-select-all-th\" style=\"position:relative;width:32px;min-width:32px;padding-left:1.25rem;\">'\n"
    "\t\t\t\t\t+ '<input type=\"checkbox\" class=\"form-check-input merc-card-select-all\" id=\"' + saId + '\" style=\"position:absolute;margin-top:.25rem;margin-left:-1.25rem;\">'\n"
    "\t\t\t\t\t+ '<label class=\"form-check-label\" for=\"' + saId + '\"></label>'\n"
    "\t\t\t\t\t+ '</th>';\n"
    "\t\t\t\tconst $innerTable = $('<table class=\"merc-tienda-card-table wpc-shipment-history table table-hover table-sm\"><thead><tr>' + firstTh + headerHtml + '</tr></thead><tbody></tbody></table>');"
)
if old_inner_table in content:
    content = content.replace(old_inner_table, new_inner_table, 1)
    print("Fix 3 inner table OK")
else:
    print("Fix 3 NOT FOUND")

# ── 4. Simplificar el click handler (ya no hay checkbox en el header) ─────────
old_click = (
    "\t\t\t\t\t$header.on('click', function(e) {\n"
    "\t\t\t\t\t\tif (!$(e.target).is('input[type=\"checkbox\"]') && !$(e.target).closest('input[type=\"checkbox\"]').length) {\n"
    "\t\t\t\t\t\t\t$card.toggleClass('collapsed');\n"
    "\t\t\t\t\t\t}\n"
    "\t\t\t\t\t});"
)
new_click = (
    "\t\t\t\t\t$header.on('click', function() {\n"
    "\t\t\t\t\t\t$card.toggleClass('collapsed');\n"
    "\t\t\t\t\t});"
)
if old_click in content:
    content = content.replace(old_click, new_click, 1)
    print("Fix 4 click handler OK")
else:
    print("Fix 4 NOT FOUND")

# ── 5. Limpiar referencias a .merc-tienda-checkbox en document handlers ───────
# En el handler de .merc-card-select-all: quitar la línea que actualiza .merc-tienda-checkbox
old_sa_handler = (
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
new_sa_handler = (
    "\t\t\t\t\t$card.find('.wpcfe-shipments').prop('checked', isChecked);\n"
    "\t\t\t\t\tupdateGlobalCheckboxState();\n"
    "\t\t\t\t});\n"
    "\n"
    "\t\t\t\t// ── Sync encabezado al cambiar fila individual ──────────────────────\n"
    "\t\t\t\t$(document).on('change', '.wpcfe-shipments', function() {\n"
    "\t\t\t\t\tvar $card = $(this).closest('.merc-tienda-card');\n"
    "\t\t\t\t\tupdateGlobalCheckboxState();\n"
    "\t\t\t\t\tif (!$card.length) return;\n"
    "\t\t\t\t\tvar total   = $card.find('.wpcfe-shipments').length;\n"
    "\t\t\t\t\tvar checked = $card.find('.wpcfe-shipments:checked').length;\n"
    "\t\t\t\t\tvar allCk   = checked === total && total > 0;\n"
    "\t\t\t\t\tvar someCk  = checked > 0 && checked < total;\n"
    "\t\t\t\t\t$card.find('.merc-card-select-all').prop('checked', allCk).prop('indeterminate', someCk);\n"
    "\t\t\t\t});"
)
if old_sa_handler in content:
    content = content.replace(old_sa_handler, new_sa_handler, 1)
    print("Fix 5 handlers cleanup OK")
else:
    print("Fix 5 NOT FOUND")

# ── 6. updateGlobalCheckboxState: quitar referencia a .merc-tienda-checkbox ──
old_gcs = (
    "\t\t\t\t$c.find('.merc-tienda-checkbox')\n"
    "\t\t\t\t\t  .prop('checked', ct > 0 && cc === ct)\n"
    "\t\t\t\t\t  .prop('indeterminate', cc > 0 && cc < ct);\n"
    "\t\t\t\t$c.find('.merc-card-select-all')\n"
    "\t\t\t\t\t  .prop('checked', ct > 0 && cc === ct)\n"
    "\t\t\t\t\t  .prop('indeterminate', cc > 0 && cc < ct);\n"
    "\t\t\t\t});"
)
new_gcs = (
    "\t\t\t\t$c.find('.merc-card-select-all')\n"
    "\t\t\t\t\t  .prop('checked', ct > 0 && cc === ct)\n"
    "\t\t\t\t\t  .prop('indeterminate', cc > 0 && cc < ct);\n"
    "\t\t\t\t});"
)
if old_gcs in content:
    content = content.replace(old_gcs, new_gcs, 1)
    print("Fix 6 updateGCS OK")
else:
    print("Fix 6 NOT FOUND")

# ── 7. #merc-select-all-global: quitar referencia a .merc-tienda-checkbox ────
old_gsa = (
    "\t\t\t\t\t$('.merc-tienda-checkbox').prop('checked', ck).prop('indeterminate', false);\n"
    "\t\t\t\t\t$('.merc-card-select-all').prop('checked', ck).prop('indeterminate', false);\n"
    "\t\t\t\t\t$('.wpcfe-shipments').prop('checked', ck);"
)
new_gsa = (
    "\t\t\t\t\t$('.merc-card-select-all').prop('checked', ck).prop('indeterminate', false);\n"
    "\t\t\t\t\t$('.wpcfe-shipments').prop('checked', ck);"
)
if old_gsa in content:
    content = content.replace(old_gsa, new_gsa, 1)
    print("Fix 7 merc-select-all-global OK")
else:
    print("Fix 7 NOT FOUND")

# ── 8. CSS: quitar .merc-tienda-checkbox del selector de apariencia ──────────
old_css_sel = (
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
    "\t\t\t}"
)
new_css_sel = (
    "\t\t\t/* El .merc-card-select-all usa el patrón Bootstrap form-check-input,\n"
    "\t\t\t   thus su posicionamiento es relativo al <th> padre */\n"
    "\t\t\t.merc-card-select-all-th {\n"
    "\t\t\t\twidth: 32px;\n"
    "\t\t\t\tmin-width: 32px;\n"
    "\t\t\t}"
)
if old_css_sel in content:
    content = content.replace(old_css_sel, new_css_sel, 1)
    print("Fix 8 CSS OK")
else:
    print("Fix 8 NOT FOUND")

open(
    'c:/Users/Usuario/mercourier-web/wp-content/plugins/merc-table-customizer/admin/classes/class-shipment-table.php',
    'w', encoding='utf-8'
).write(content)
print("Done")
