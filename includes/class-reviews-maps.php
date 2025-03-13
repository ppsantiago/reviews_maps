<?php
/**
 * La clase principal del plugin
 */
class Reviews_Maps {
    /**
     * El loader que registra todas las acciones y filtros del plugin
     */
    protected $loader;

    /**
     * El identificador único del plugin
     */
    protected $plugin_name;

    /**
     * La versión actual del plugin
     */
    protected $version;

    /**
     * Inicializar la clase y establecer sus propiedades
     */
    public function __construct() {
        $this->plugin_name = 'reviews-maps';
        $this->version = REVIEWS_MAPS_VERSION;

        $this->load_dependencies();
        $this->set_locale();
        $this->define_admin_hooks();
        $this->define_public_hooks();
        $this->define_cron_hooks();
        $this->check_updates();
    }

    /**
     * Cargar las dependencias necesarias para el plugin
     */
    private function load_dependencies() {
        require_once REVIEWS_MAPS_PLUGIN_DIR . 'includes/class-reviews-maps-loader.php';
        require_once REVIEWS_MAPS_PLUGIN_DIR . 'includes/class-reviews-maps-i18n.php';
        require_once REVIEWS_MAPS_PLUGIN_DIR . 'includes/class-reviews-maps-activator.php';
        require_once REVIEWS_MAPS_PLUGIN_DIR . 'includes/class-reviews-maps-deactivator.php';
        require_once REVIEWS_MAPS_PLUGIN_DIR . 'includes/class-reviews-maps-places-api.php';
        require_once REVIEWS_MAPS_PLUGIN_DIR . 'includes/class-reviews-maps-updater.php';
        require_once REVIEWS_MAPS_PLUGIN_DIR . 'admin/class-reviews-maps-admin.php';
        require_once REVIEWS_MAPS_PLUGIN_DIR . 'admin/class-reviews-maps-admin-options.php';
        require_once REVIEWS_MAPS_PLUGIN_DIR . 'public/class-reviews-maps-public.php';

        $this->loader = new Reviews_Maps_Loader();
    }

    /**
     * Verificar actualizaciones del plugin
     */
    private function check_updates() {
        $updater = new Reviews_Maps_Updater($this->plugin_name, $this->version);
        $updater->check_updates();
    }

    /**
     * Definir la funcionalidad relacionada con la internacionalización del plugin
     */
    private function set_locale() {
        $plugin_i18n = new Reviews_Maps_i18n();
        $this->loader->add_action('plugins_loaded', $plugin_i18n, 'load_plugin_textdomain');
    }

    /**
     * Registrar todos los hooks relacionados con la funcionalidad del área de administración
     */
    private function define_admin_hooks() {
        $plugin_admin = new Reviews_Maps_Admin($this->get_plugin_name(), $this->get_version());
        $plugin_admin_options = new Reviews_Maps_Admin_Options($this->get_plugin_name(), $this->get_version());

        $this->loader->add_action('admin_enqueue_scripts', $plugin_admin, 'enqueue_styles');
        $this->loader->add_action('admin_enqueue_scripts', $plugin_admin, 'enqueue_scripts');
        
        // Hooks para la página de opciones
        $this->loader->add_action('admin_menu', $plugin_admin_options, 'add_plugin_admin_menu');
        $this->loader->add_action('admin_init', $plugin_admin_options, 'register_settings');
        $this->loader->add_action('admin_post_reviews_maps_manual_update', $plugin_admin_options, 'handle_manual_update');
        $this->loader->add_action('admin_post_reviews_maps_test_api', $plugin_admin_options, 'handle_test_api');
    }

    /**
     * Registrar todos los hooks relacionados con la funcionalidad del área pública
     */
    private function define_public_hooks() {
        $plugin_public = new Reviews_Maps_Public($this->get_plugin_name(), $this->get_version());
        $this->loader->add_action('wp_enqueue_scripts', $plugin_public, 'enqueue_styles');
        $this->loader->add_action('wp_enqueue_scripts', $plugin_public, 'enqueue_scripts');
        $this->loader->add_action('init', $plugin_public, 'register_shortcodes');
    }

    /**
     * Registrar los hooks relacionados con las tareas programadas
     */
    private function define_cron_hooks() {
        $places_api = new Reviews_Maps_Places_API($this->get_plugin_name(), $this->get_version());
        $this->loader->add_action('reviews_maps_daily_update', $places_api, 'update_existing_reviews');
    }

    /**
     * Ejecutar el loader para ejecutar todos los hooks con WordPress
     */
    public function run() {
        $this->loader->run();
    }

    /**
     * El nombre del plugin usado para identificarlo únicamente dentro del contexto de WordPress
     */
    public function get_plugin_name() {
        return $this->plugin_name;
    }

    /**
     * La referencia a la clase que coordina los hooks del plugin
     */
    public function get_loader() {
        return $this->loader;
    }

    /**
     * Recuperar el número de versión del plugin
     */
    public function get_version() {
        return $this->version;
    }
} 