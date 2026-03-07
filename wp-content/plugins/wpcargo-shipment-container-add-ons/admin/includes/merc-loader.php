<?php
/**
 * Loader para Módulos de Contenedores Mercourier
 *
 * @package wpcargo-shipment-container-add-ons
 * @subpackage merc-containers
 */

if (!defined('ABSPATH')) {
    exit;
}

// Define the path to merc-containers
define('MERC_CONTAINERS_PATH', dirname(__FILE__) . '/merc-containers/');

// Load helper functions first
require_once(MERC_CONTAINERS_PATH . 'helpers.php');

// Load auto-assignment functionality
require_once(MERC_CONTAINERS_PATH . 'auto-assignment.php');

error_log('✅ Mercourier Containers módulos cargados en wpcargo-shipment-container-add-ons');
