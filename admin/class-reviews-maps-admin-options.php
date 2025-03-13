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
            'reviews_maps_general_section',
            'Configuración General',
            array($this, 'general_section_callback'),
            $this->plugin_name
        );

        add_settings_field(
            'google_maps_api_key',
            'API Key de Google Maps',
            array($this, 'google_maps_api_key_render'),
            $this->plugin_name,
            'reviews_maps_general_section'
        );

        add_settings_field(
            'business_name',
            'Nombre del Negocio',
            array($this, 'business_name_render'),
            $this->plugin_name,
            'reviews_maps_general_section'
        );

        add_settings_field(
            'business_id',
            'ID del Negocio',
            array($this, 'business_id_render'),
            $this->plugin_name,
            'reviews_maps_general_section'
        );

        add_settings_field(
            'update_time',
            'Hora de Actualización',
            array($this, 'render_update_time_field'),
            $this->plugin_name,
            'reviews_maps_general_section'
        );

        add_settings_field(
            'max_reviews',
            'Máximo de Reseñas',
            array($this, 'render_max_reviews_field'),
            $this->plugin_name,
            'reviews_maps_general_section'
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
    public function general_section_callback() {
        echo '<p>Configura los ajustes principales del plugin.</p>';
    }

    /**
     * Renderizar el campo de API Key
     */
    public function google_maps_api_key_render() {
        $options = get_option('reviews_maps_options');
        ?>
        <input type="text" 
               name="reviews_maps_options[google_maps_api_key]" 
               value="<?php echo esc_attr($options['google_maps_api_key'] ?? ''); ?>" 
               class="regular-text">
        <?php
    }

    /**
     * Renderizar el campo del nombre del negocio
     */
    public function business_name_render() {
        $options = get_option('reviews_maps_options');
        ?>
        <input type="text" name="reviews_maps_options[business_name]" value="<?php echo esc_attr($options['business_name'] ?? ''); ?>" class="regular-text">
        <p class="description">Introduce el nombre exacto del negocio como aparece en Google Maps. Se usará para buscar el ID si el actual no es válido.</p>
        <?php
    }

    /**
     * Renderizar el campo de ID del negocio
     */
    public function business_id_render() {
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
        
        $valid['google_maps_api_key'] = sanitize_text_field($input['google_maps_api_key']);
        $valid['business_name'] = sanitize_text_field($input['business_name'] ?? '');
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

        require_once REVIEWS_MAPS_PLUGIN_DIR . 'includes/class-reviews-maps-places-api.php';
        $places_api = new Reviews_Maps_Places_API($this->plugin_name, $this->version);
        
        // Obtener el modo y límite desde las opciones (o usar valores predeterminados)
        $options = get_option('reviews_maps_options');
        $add_only = true; // Siempre añadir sin eliminar las existentes
        $limit = 50; // Aumentado a 50 reseñas por actualización
        
        // Usar update_existing_reviews para verificar si hay reseñas nuevas
        $result = $places_api->update_existing_reviews($add_only, $limit);

        if (is_wp_error($result)) {
            // Registrar el error para que se muestre en la interfaz
            add_settings_error(
                'reviews_maps_messages',
                'reviews_maps_error',
                'Error al actualizar reseñas: ' . $result->get_error_message(),
                'error'
            );
            
            // Guardar mensajes para que persistan después de la redirección
            set_transient('reviews_maps_admin_notices', get_settings_errors('reviews_maps_messages'), 30);
        } else if ($result === false) {
            add_settings_error(
                'reviews_maps_messages',
                'reviews_maps_warning',
                'No se encontraron reseñas nuevas para actualizar.',
                'warning'
            );
            
            // Guardar mensajes para que persistan después de la redirección
            set_transient('reviews_maps_admin_notices', get_settings_errors('reviews_maps_messages'), 30);
        } else if (is_numeric($result) && $result > 0) {
            // Éxito con número de reseñas
            add_settings_error(
                'reviews_maps_messages',
                'reviews_maps_updated',
                'Se han añadido ' . $result . ' nuevas reseñas más recientes sin eliminar las existentes.',
                'success'
            );
            
            // Guardar mensajes para que persistan después de la redirección
            set_transient('reviews_maps_admin_notices', get_settings_errors('reviews_maps_messages'), 30);
        } else if (is_numeric($result) && $result === 0) {
            // No hay nuevas reseñas para añadir
            add_settings_error(
                'reviews_maps_messages',
                'reviews_maps_info',
                'No se han encontrado nuevas reseñas para añadir. Las reseñas existentes se mantienen.',
                'info'
            );
            
            // Guardar mensajes para que persistan después de la redirección
            set_transient('reviews_maps_admin_notices', get_settings_errors('reviews_maps_messages'), 30);
        } else {
            // Éxito genérico
            add_settings_error(
                'reviews_maps_messages',
                'reviews_maps_updated',
                'Reseñas actualizadas correctamente.',
                'success'
            );
            
            // Guardar mensajes para que persistan después de la redirección
            set_transient('reviews_maps_admin_notices', get_settings_errors('reviews_maps_messages'), 30);
        }

        // Redirigir de vuelta a la página de opciones (corregido)
        wp_redirect(admin_url('options-general.php?page=' . $this->plugin_name));
        exit;
    }

    /**
     * Mostrar mensajes de notificación guardados
     */
    public function show_admin_notices() {
        $screen = get_current_screen();
        
        // Solo mostrar en la página de nuestro plugin
        if (strpos($screen->id, $this->plugin_name) === false) {
            return;
        }
        
        // Obtener mensajes guardados
        $notices = get_transient('reviews_maps_admin_notices');
        
        if ($notices) {
            foreach ($notices as $notice) {
                echo '<div class="notice notice-' . esc_attr($notice['type']) . ' is-dismissible"><p>' . wp_kses_post($notice['message']) . '</p></div>';
            }
            
            // Limpiar el transient
            delete_transient('reviews_maps_admin_notices');
        }
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
            
            // Guardar mensajes para que persistan después de la redirección
            set_transient('reviews_maps_admin_notices', get_settings_errors('reviews_maps_messages'), 30);
        } else {
            add_settings_error(
                'reviews_maps_messages',
                'reviews_maps_api_success',
                'Conexión exitosa a la API. Negocio encontrado: ' . esc_html($result['place_name']),
                'success'
            );
            
            // Guardar mensajes para que persistan después de la redirección
            set_transient('reviews_maps_admin_notices', get_settings_errors('reviews_maps_messages'), 30);
        }

        // Redirigir de vuelta a la página de opciones (corregido)
        wp_redirect(admin_url('options-general.php?page=' . $this->plugin_name));
        exit;
    }
} 