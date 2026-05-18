<?php if ( ! defined( 'ABSPATH' ) ) exit; ?>
<th>Nombre de Tienda</th>
<?php if ( current_user_can('manage_options') ) : ?>
<th>Mensaje</th>
<?php endif; ?>
<th>Destinatario / Distrito</th>
<th>Fecha</th>
<th>Tipo de Servicio</th>
<th>Cambio de Producto</th>
<th>Estado</th>
<th>Motorizado Entrega</th>
<?php if ( current_user_can('manage_options') ) : ?>
<th>Validación</th>
<?php endif; ?>

