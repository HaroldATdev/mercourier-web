content = open('c:/Users/Usuario/mercourier-web/wp-content/plugins/merc-table-customizer/admin/classes/class-shipment-table.php', 'r', encoding='utf-8').read()

old = "\t\t\t\t\t\t\t\t\t\twindow.open(d.file_url, '_blank');\n\t\t\t\t\t\t\t\t\t} else { alert('Error al generar el PDF'); }"

new = ("\t\t\t\t\t\t\t\t\t\tvar a = document.createElement('a');\n"
       "\t\t\t\t\t\t\t\t\t\ta.href = d.file_url;\n"
       "\t\t\t\t\t\t\t\t\t\ta.target = '_blank';\n"
       "\t\t\t\t\t\t\t\t\t\ta.download = (d.file_name || 'etiquetas') + '.pdf';\n"
       "\t\t\t\t\t\t\t\t\t\tdocument.body.appendChild(a);\n"
       "\t\t\t\t\t\t\t\t\t\ta.click();\n"
       "\t\t\t\t\t\t\t\t\t\tdocument.body.removeChild(a);\n"
       "\t\t\t\t\t\t\t\t\t} else { alert('Error al generar el PDF'); }")

if old in content:
    content = content.replace(old, new, 1)
    open('c:/Users/Usuario/mercourier-web/wp-content/plugins/merc-table-customizer/admin/classes/class-shipment-table.php', 'w', encoding='utf-8').write(content)
    print('OK - replaced')
else:
    print('NOT FOUND')
    # Show the actual content near window.open
    idx = content.find("window.open(d.file_url")
    if idx >= 0:
        print(repr(content[idx-20:idx+120]))
