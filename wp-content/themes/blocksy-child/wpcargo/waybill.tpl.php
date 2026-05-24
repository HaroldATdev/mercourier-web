<?php
$shipment_id = $shipmentDetails['shipmentID'];

if (!function_exists('shorten_url')) {
    function shorten_url($long_url) {
        $api_url = 'https://is.gd/create.php?format=simple&url=' . urlencode($long_url);
        $ch = curl_init($api_url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, 5);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        $short_url = curl_exec($ch);
        curl_close($ch);
        return ($short_url && strpos($short_url, 'Error') === false) ? trim($short_url) : $long_url;
    }
}

if (!function_exists('get_qr_code_url')) {
    function get_qr_code_url($data) {
        return 'https://api.qrserver.com/v1/create-qr-code/?size=160x160&data=' . urlencode($data);
    }
}

if (!function_exists('get_maps_url')) {
    function get_maps_url($shipment_id) {
        $link_maps = trim(get_post_meta($shipment_id, 'link_maps', true));
        if (!empty($link_maps)) return $link_maps;
        $address = trim(get_post_meta($shipment_id, 'wpcargo_receiver_address', true));
        if (!empty($address)) {
            $long_url = 'https://www.google.com/maps/search/?api=1&query=' . urlencode($address);
            return shorten_url($long_url);
        }
        return '';
    }
}
?>
<style>
@page {
    size: 50mm 75mm portrait !important;
    margin: 0 !important;
}

@media print {
    html, body {
        width: 50mm !important;
        height: 75mm !important;
        margin: 0 !important;
        padding: 0 !important;
    }
    .thermal-label {
        margin: 0 !important;
        padding: 1mm 1.5mm !important;
        width: 50mm !important;
        height: 75mm !important;
    }
}

*, *::before, *::after {
    box-sizing: border-box;
    margin: 0;
    padding: 0;
}

html, body {
    width: 50mm !important;
    max-width: 50mm !important;
    margin: 0 !important;
    padding: 0 !important;
    overflow: hidden !important;
    font-family: Arial, Helvetica, sans-serif;
    color: #000;
    background: #fff;
}

/* ── ETIQUETA ── */
.thermal-label {
    width: 50mm !important;
    height: 75mm !important;
    overflow: hidden !important;
    position: static !important;
    background: #fff !important;
    padding: 1mm 1.5mm !important;
    margin: 0 !important;
    display: block !important;
}

/* MARCA DE AGUA */
.watermark-logo {
    position: absolute;
    top: 25%;
    left: 5%;
    opacity: 0.08;
    z-index: 0;
    width: 90%;
    text-align: center;
}
.watermark-logo img { width: 90px; }

/* CONTENIDO */
.label-content {
    position: relative;
    z-index: 1;
    width: 100%;
}

.label-header {
    text-align: center;
    padding-bottom: 1.5px;
    border-bottom: 1.5px solid #000;
    margin-bottom: 1.5px;
}
.logo-wrap {
    display: flex;
    align-items: center;
    justify-content: center;
    margin-bottom: 1.5px;
}
.logo-wrap img {
    max-height: 18px !important;
    width: auto !important;
    height: auto !important;
}
.brand-name {
    font-size: 10px;
    font-weight: bold;
    text-transform: uppercase;
    line-height: 1.2;
    word-wrap: break-word;
}

.tracking-section {
    text-align: center;
    padding-bottom: 1.5px;
    border-bottom: 1.5px solid #000;
    margin-bottom: 1.5px;
}
.barcode-container img {
    max-width: 100%;
    max-height: 18px;
    display: block;
    margin: 0 auto;
}
.tracking-number {
    font-size: 13px;
    font-weight: bold;
    margin-top: 1px;
    line-height: 1.1;
}

.destination-section {
    text-align: center;
    padding-bottom: 1.5px;
    border-bottom: 1.5px solid #000;
    margin-bottom: 1.5px;
}
.destination-value {
    font-size: 16px;
    font-weight: bold;
    line-height: 1.1;
    word-wrap: break-word;
}

.receiver-section {
    padding-bottom: 1.5px;
    border-bottom: 1.5px solid #000;
    margin-bottom: 1.5px;
}
.receiver-section > div { margin-bottom: 2px; }
.receiver-section > div:last-child { margin-bottom: 0; }

.field-label {
    font-size: 7px;
    font-weight: bold;
    text-transform: uppercase;
    display: block;
    line-height: 1;
}
.field-value {
    font-size: 10px;
    font-weight: bold;
    display: block;
    word-wrap: break-word;
    overflow-wrap: break-word;
    white-space: normal;
    line-height: 1.2;
}

.qr-section {
    text-align: center;
    padding-top: 1px;
}
.qr-code {
    width: 42px;
    height: 42px;
    display: block;
    margin: 0 auto 1px;
}
.qr-label {
    font-size: 6px;
    line-height: 1.2;
    word-wrap: break-word;
}
</style>

<script>
(function () {
    /* 1. Forzar viewport angosto */
    var mv = document.querySelector('meta[name="viewport"]');
    if (mv) mv.remove();
    var m = document.createElement('meta');
    m.name = 'viewport';
    m.content = 'width=189,initial-scale=1';
    document.head.appendChild(m);

    function resetWrappers() {
        /* 2. Resetear html y body */
        var s = 'width:50mm!important;max-width:50mm!important;margin:0!important;padding:0!important;overflow:hidden!important;';
        document.documentElement.setAttribute('style', s + 'height:75mm!important;');
        document.body.setAttribute('style', s + 'height:75mm!important;max-height:75mm!important;');

        /* 3. Ocultar / neutralizar todos los hijos del body excepto .thermal-label */
        var kids = document.body.children;
        for (var i = 0; i < kids.length; i++) {
            var el = kids[i];
            if (!el.classList.contains('thermal-label')) {
                el.setAttribute('style',
                    'display:none!important;width:0!important;height:0!important;' +
                    'margin:0!important;padding:0!important;overflow:hidden!important;' +
                    'position:absolute!important;top:0!important;left:0!important;');
            } else {
                el.setAttribute('style',
                    'width:50mm!important;height:75mm!important;max-height:75mm!important;' +
                    'overflow:hidden!important;position:relative!important;' +
                    'background:#fff!important;padding:1mm 1.5mm!important;' +
                    'margin:0!important;display:block!important;');
            }
        }
    }

    /* Ejecutar en DOMContentLoaded y antes de imprimir */
    document.addEventListener('DOMContentLoaded', resetWrappers);
    window.addEventListener('beforeprint', resetWrappers);

    /* MutationObserver: re-aplica si WPCargo modifica el DOM después */
    var obs = new MutationObserver(resetWrappers);
    document.addEventListener('DOMContentLoaded', function () {
        obs.observe(document.body, { childList: true, subtree: false, attributes: true });
    });
})();
</script>

<div class="thermal-label">

    <div class="watermark-logo">
        <img src="https://mercourier.com/wp-content/uploads/2025/09/Logo-MERC.png" alt="Mercourier">
    </div>

    <div class="label-content">

        <div class="label-header">
            <div class="logo-wrap">
                <?php echo $shipmentDetails['logo']; ?>
            </div>
            <div class="brand-name">
                <?php echo get_post_meta($shipment_id, 'wpcargo_tiendaname', true); ?>
            </div>
        </div>

        <div class="tracking-section">
            <div class="barcode-container">
                <img src="<?php echo $shipmentDetails['barcode']; ?>" alt="Barcode">
            </div>
            <div class="tracking-number">
                <?php echo get_the_title($shipment_id); ?>
            </div>
        </div>

        <div class="destination-section">
            <div class="destination-value">
                <?php echo strtoupper(get_post_meta($shipment_id, 'wpcargo_distrito_destino', true)); ?>
            </div>
        </div>

        <div class="receiver-section">
            <div>
                <span class="field-label">Destinatario</span>
                <span class="field-value"><?php echo get_post_meta($shipment_id, 'wpcargo_receiver_name', true); ?></span>
            </div>
            <div>
                <span class="field-label">Teléfono</span>
                <span class="field-value"><?php echo get_post_meta($shipment_id, 'wpcargo_receiver_phone', true); ?></span>
            </div>
            <div>
                <span class="field-label">Dirección</span>
                <span class="field-value"><?php echo get_post_meta($shipment_id, 'wpcargo_receiver_address', true); ?></span>
            </div>
        </div>

        <?php
        $maps_url = get_maps_url($shipment_id);
        if (!empty($maps_url)):
            $qr_url = get_qr_code_url($maps_url);
        ?>
        <div class="qr-section">
            <img class="qr-code" src="<?php echo esc_url($qr_url); ?>" alt="QR">
            <div class="qr-label">Delivery autorizado por MERCourier. Visítanos: www.mercourier.com</div>
        </div>
        <?php endif; ?>

    </div>
</div>
