<?php
/**
 * La clase que maneja las opciones del plugin en el panel de administración
 */
class Reviews_Maps_Admin_Options {
    /**
     * El ID del plugin
     */
    private $plugin_name;

    /**
     * La versión del plugin
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
     * Registrar la página de opciones en el menú de administración
     */
    public function add_plugin_admin_menu() {
        add_menu_page(
            'Reviews Maps', // Título de la página
            'Reviews Maps', // Texto del menú
            'manage_options', // Capacidad requerida
            $this->plugin_name, // Slug del menú
            array($this, 'display_plugin_setup_page'), // Función que renderiza la página
            'dashicons-location', // Icono
            30 // Posición en el menú
        );
    }

    /**
     * Renderizar la página de configuración del plugin
     */
    public function display_plugin_setup_page() {
        include_once('partials/reviews-maps-admin-display.php');
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
            array($this, 'render_section_info'),
            $this->plugin_name
        );

        // Campo API Key
        add_settings_field(
            'google_maps_api_key',
            'API Key de Google Maps',
            array($this, 'render_api_key_field'),
            $this->plugin_name,
            'reviews_maps_main_section'
        );

        // Campo ID del Negocio
        add_settings_field(
            'business_id',
            'ID del Negocio',
            array($this, 'render_business_id_field'),
            $this->plugin_name,
            'reviews_maps_main_section'
        );

        // Campo Frecuencia de Actualización
        add_settings_field(
            'update_frequency',
            'Frecuencia de Actualización',
            array($this, 'render_update_frequency_field'),
            $this->plugin_name,
            'reviews_maps_main_section'
        );

        // Campo Duración de Caché
        add_settings_field(
            'cache_duration',
            'Duración de Caché (segundos)',
            array($this, 'render_cache_duration_field'),
            $this->plugin_name,
            'reviews_maps_main_section'
        );

        // Campo Máximo de Reseñas
        add_settings_field(
            'max_reviews',
            'Máximo de Reseñas',
            array($this, 'render_max_reviews_field'),
            $this->plugin_name,
            'reviews_maps_main_section'
        );
    }

    /**
     * Renderizar la información de la sección
     */
    public function render_section_info() {
        echo '<p>Ingresa la información necesaria para que el plugin funcione correctamente.</p>';
    }

    /**
     * Renderizar el campo de API Key
     */
    public function render_api_key_field() {
        $options = get_option('reviews_maps_options');
        $value = isset($options['google_maps_api_key']) ? $options['google_maps_api_key'] : '';
        ?>
        <input type="text" name="reviews_maps_options[google_maps_api_key]" value="<?php echo esc_attr($value); ?>" class="regular-text">
        <p class="description">Ingresa tu API Key de Google Maps</p>
        <?php
    }

    /**
     * Renderizar el campo de ID del Negocio
     */
    public function render_business_id_field() {
        $options = get_option('reviews_maps_options');
        $value = isset($options['business_id']) ? $options['business_id'] : '';
        ?>
        <input type="text" name="reviews_maps_options[business_id]" value="<?php echo esc_attr($value); ?>" class="regular-text">
        <p class="description">Ingresa el ID del negocio en Google Maps</p>
        <?php
    }

    /**
     * Renderizar el campo de Frecuencia de Actualización
     */
    public function render_update_frequency_field() {
        $options = get_option('reviews_maps_options');
        $value = isset($options['update_frequency']) ? $options['update_frequency'] : 'daily';
        ?>
        <select name="reviews_maps_options[update_frequency]">
            <option value="hourly" <?php selected($value, 'hourly'); ?>>Cada hora</option>
            <option value="daily" <?php selected($value, 'daily'); ?>>Diariamente</option>
            <option value="weekly" <?php selected($value, 'weekly'); ?>>Semanalmente</option>
            <option value="monthly" <?php selected($value, 'monthly'); ?>>Mensualmente</option>
        </select>
        <p class="description">Selecciona con qué frecuencia se actualizarán las reseñas</p>
        <?php
    }

    /**
     * Renderizar el campo de Duración de Caché
     */
    public function render_cache_duration_field() {
        $options = get_option('reviews_maps_options');
        $value = isset($options['cache_duration']) ? $options['cache_duration'] : 3600;
        ?>
        <input type="number" name="reviews_maps_options[cache_duration]" value="<?php echo esc_attr($value); ?>" class="small-text">
        <p class="description">Duración de la caché en segundos (3600 = 1 hora)</p>
        <?php
    }

    /**
     * Renderizar el campo de Máximo de Reseñas
     */
    public function render_max_reviews_field() {
        $options = get_option('reviews_maps_options');
        $value = isset($options['max_reviews']) ? $options['max_reviews'] : 100;
        ?>
        <input type="number" name="reviews_maps_options[max_reviews]" value="<?php echo esc_attr($value); ?>" class="small-text">
        <p class="description">Número máximo de reseñas a mostrar</p>
        <?php
    }

    /**
     * Validar las opciones antes de guardarlas
     */
    public function validate_options($input) {
        $valid = array();

        // Validar API Key
        $valid['google_maps_api_key'] = sanitize_text_field($input['google_maps_api_key']);

        // Validar Business ID
        $valid['business_id'] = sanitize_text_field($input['business_id']);

        // Validar Frecuencia de Actualización
        $valid['update_frequency'] = sanitize_text_field($input['update_frequency']);
        if (!in_array($valid['update_frequency'], array('hourly', 'daily', 'weekly', 'monthly'))) {
            $valid['update_frequency'] = 'daily';
        }

        // Validar Duración de Caché
        $valid['cache_duration'] = absint($input['cache_duration']);
        if ($valid['cache_duration'] < 300) { // Mínimo 5 minutos
            $valid['cache_duration'] = 3600;
        }

        // Validar Máximo de Reseñas
        $valid['max_reviews'] = absint($input['max_reviews']);
        if ($valid['max_reviews'] < 10) { // Mínimo 10 reseñas
            $valid['max_reviews'] = 100;
        }

        return $valid;
    }
} 