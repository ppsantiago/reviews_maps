<?php
/**
 * Plugin Name: Reviews Maps
 * Plugin URI: https://tudominio.com/reviews-maps
 * Description: Un plugin para mostrar reseñas en mapas interactivos
 * Version: 1.0.0
 * Author: Tu Nombre
 * Author URI: https://tudominio.com
 * Text Domain: reviews-maps
 * Domain Path: /languages
 */

// Si este archivo es llamado directamente, abortar
if (!defined('WPINC')) {
    die;
}

// Definir constantes del plugin
define('REVIEWS_MAPS_VERSION', '1.0.0');
define('REVIEWS_MAPS_PLUGIN_DIR', plugin_dir_path(__FILE__));
define('REVIEWS_MAPS_PLUGIN_URL', plugin_dir_url(__FILE__));

// Incluir archivos necesarios
require_once REVIEWS_MAPS_PLUGIN_DIR . 'includes/class-reviews-maps-activator.php';
require_once REVIEWS_MAPS_PLUGIN_DIR . 'includes/class-reviews-maps-deactivator.php';
require_once REVIEWS_MAPS_PLUGIN_DIR . 'includes/class-reviews-maps.php';

// Registrar las funciones de activación y desactivación
register_activation_hook(__FILE__, array('Reviews_Maps_Activator', 'activate'));
register_deactivation_hook(__FILE__, array('Reviews_Maps_Deactivator', 'deactivate'));

// Inicializar el plugin
function run_reviews_maps() {
    $plugin = new Reviews_Maps();
    $plugin->run();
}
run_reviews_maps(); 