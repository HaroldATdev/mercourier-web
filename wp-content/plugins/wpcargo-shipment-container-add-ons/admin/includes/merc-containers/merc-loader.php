<?php
/**
 * Mercourier Container Management Integration
 * Integración de auto-asignación, motorizados duales y sincronización
 * en WPCargo Shipment Container Add-ons
 * 
 * @package wpcargo-shipment-container-add-ons
 * @subpackage merc-containers
 */

if (!defined('ABSPATH')) {
    exit;
}

// Cargar módulos de Mercourier
require_once(dirname(__FILE__) . '/merc-helpers.php');
require_once(dirname(__FILE__) . '/merc-auto.php');
require_once(dirname(__FILE__) . '/merc-motorizado.php');
require_once(dirname(__FILE__) . '/merc-state.php');
require_once(dirname(__FILE__) . '/merc-ajax.php');
require_once(dirname(__FILE__) . '/merc-base.php');
require_once(dirname(__FILE__) . '/merc-edit.php');

error_log("✅ Mercourier Container Management loaded into WPCargo");
