<?php
/**
 * La clase que maneja las opciones del plugin en el área de administración
 */
class Reviews_Maps_Admin_Options {
    /**
     * El identificador único del plugin
     */
    private $plugin_name;

    /**
     * La versión actual del plugin
     */
    private $version;

    /**
     * Inicializar la clase y establecer sus propiedades
     */
    public function __construct($plugin_name, $version) {
        $this->plugin_name = $plugin_name;
        $this->version = $version;
    }

    /**
     * Agregar la página de opciones al menú de administración
     */
    public function add_plugin_admin_menu() {
        add_options_page(
            'Reviews Maps Options',
            'Reviews Maps',
            'manage_options',
            $this->plugin_name,
            array($this, 'display_plugin_setup_page')
        );
    }

    /**
     * Registrar la configuración del plugin
     */
    public function register_settings() {
        register_setting(
            $this->plugin_name,
            'reviews_maps_options',
            array($this, 'validate_options')
        );

        add_settings_section(
            'reviews_maps_main_section',
            'Configuración Principal',
            array($this, 'render_section_text'),
            $this->plugin_name
        );

        add_settings_field(
            'google_maps_api_key',
            'API Key de Google Maps',
            array($this, 'render_api_key_field'),
            $this->plugin_name,
            'reviews_maps_main_section'
        );

        add_settings_field(
            'business_id',
            'ID del Negocio',
            array($this, 'render_business_id_field'),
            $this->plugin_name,
            'reviews_maps_main_section'
        );

        add_settings_field(
            'update_time',
            'Hora de Actualización',
            array($this, 'render_update_time_field'),
            $this->plugin_name,
            'reviews_maps_main_section'
        );

        add_settings_field(
            'max_reviews',
            'Máximo de Reseñas',
            array($this, 'render_max_reviews_field'),
            $this->plugin_name,
            'reviews_maps_main_section'
        );

        // Agregar acción para el botón de actualización manual
        add_action('admin_post_reviews_maps_manual_update', array($this, 'handle_manual_update'));
    }

    /**
     * Renderizar la página de configuración
     */
    public function display_plugin_setup_page() {
        include_once('partials/reviews-maps-admin-display.php');
    }

    /**
     * Renderizar el texto de la sección
     */
    public function render_section_text() {
        echo '<p>Configura los ajustes principales del plugin.</p>';
    }

    /**
     * Renderizar el campo de API Key
     */
    public function render_api_key_field() {
        $options = get_option('reviews_maps_options');
        ?>
        <input type="text" 
               name="reviews_maps_options[google_maps_api_key]" 
               value="<?php echo esc_attr($options['google_maps_api_key'] ?? ''); ?>" 
               class="regular-text">
        <?php
    }

    /**
     * Renderizar el campo de ID del negocio
     */
    public function render_business_id_field() {
        $options = get_option('reviews_maps_options');
        ?>
        <input type="text" 
               name="reviews_maps_options[business_id]" 
               value="<?php echo esc_attr($options['business_id'] ?? ''); ?>" 
               class="regular-text">
        <?php
    }

    /**
     * Renderizar el campo de hora de actualización
     */
    public function render_update_time_field() {
        $options = get_option('reviews_maps_options');
        ?>
        <input type="time" 
               name="reviews_maps_options[update_time]" 
               value="<?php echo esc_attr($options['update_time'] ?? '00:00'); ?>">
        <?php
    }

    /**
     * Renderizar el campo de máximo de reseñas
     */
    public function render_max_reviews_field() {
        $options = get_option('reviews_maps_options');
        ?>
        <input type="number" 
               name="reviews_maps_options[max_reviews]" 
               value="<?php echo esc_attr($options['max_reviews'] ?? 100); ?>" 
               min="1" 
               max="1000">
        <?php
    }

    /**
     * Validar las opciones
     */
    public function validate_options($input) {
        $valid = array();
        
        // Validar API Key
        $valid['google_maps_api_key'] = sanitize_text_field($input['google_maps_api_key']);
        
        // Validar Business ID
        $valid['business_id'] = sanitize_text_field($input['business_id']);
        
        // Validar hora de actualización
        $valid['update_time'] = sanitize_text_field($input['update_time']);
        
        // Validar máximo de reseñas
        $valid['max_reviews'] = absint($input['max_reviews']);
        if ($valid['max_reviews'] < 1) $valid['max_reviews'] = 1;
        if ($valid['max_reviews'] > 1000) $valid['max_reviews'] = 1000;

        return $valid;
    }

    /**
     * Manejar la actualización manual de reseñas
     */
    public function handle_manual_update() {
        if (!isset($_POST['reviews_maps_manual_update']) || !check_admin_referer('reviews_maps_manual_update')) {
            return;
        }

        if (!current_user_can('manage_options')) {
            wp_die('No tienes permisos para realizar esta acción.');
        }

        $places_api = new Reviews_Maps_Places_API($this->plugin_name, $this->version);
        $result = $places_api->save_reviews_to_db();

        if (is_wp_error($result)) {
            add_settings_error(
                'reviews_maps_messages',
                'reviews_maps_update_error',
                'Error al actualizar las reseñas: ' . $result->get_error_message(),
                'error'
            );
        } else {
            add_settings_error(
                'reviews_maps_messages',
                'reviews_maps_update_success',
                'Reseñas actualizadas correctamente.',
                'success'
            );
        }

        // Redirigir de vuelta a la página de opciones
        wp_redirect(add_query_arg('settings-updated', 'true', wp_get_referer()));
        exit;
    }

    /**
     * Manejar la prueba de conexión a la API
     */
    public function handle_test_api() {
        if (!isset($_POST['action']) || $_POST['action'] !== 'reviews_maps_test_api' || !check_admin_referer('reviews_maps_test_api')) {
            return;
        }

        if (!current_user_can('manage_options')) {
            wp_die('No tienes permisos para realizar esta acción.');
        }

        require_once REVIEWS_MAPS_PLUGIN_DIR . 'includes/class-reviews-maps-places-api.php';
        $places_api = new Reviews_Maps_Places_API($this->plugin_name, $this->version);
        $result = $places_api->test_api_connection();

        if (is_wp_error($result)) {
            add_settings_error(
                'reviews_maps_messages',
                'reviews_maps_api_error',
                'Error de conexión a la API: ' . $result->get_error_message(),
                'error'
            );
        } else {
            add_settings_error(
                'reviews_maps_messages',
                'reviews_maps_api_success',
                'Conexión exitosa a la API. Negocio encontrado: ' . esc_html($result['place_name']),
                'success'
            );
        }

        // Redirigir de vuelta a la página de opciones
        wp_redirect(add_query_arg('settings-updated', 'true', wp_get_referer()));
        exit;
    }
} 