<?php
/**
 * La clase que define toda la funcionalidad del área pública
 */
class Reviews_Maps_Public {
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
     * Registrar los estilos para el área pública
     */
    public function enqueue_styles() {
        wp_enqueue_style(
            $this->plugin_name,
            REVIEWS_MAPS_PLUGIN_URL . 'public/css/reviews-maps-public.css',
            array(),
            $this->version,
            'all'
        );
    }

    /**
     * Registrar los scripts para el área pública
     */
    public function enqueue_scripts() {
        wp_enqueue_script(
            $this->plugin_name,
            REVIEWS_MAPS_PLUGIN_URL . 'public/js/reviews-maps-public.js',
            array('jquery'),
            $this->version,
            false
        );

        // Localizar el script con las opciones necesarias
        wp_localize_script(
            $this->plugin_name,
            'reviewsMapsPublic',
            array(
                'ajaxurl' => admin_url('admin-ajax.php'),
                'nonce' => wp_create_nonce('reviews_maps_public_nonce')
            )
        );
    }

    /**
     * Registrar el shortcode para mostrar las reseñas
     */
    public function register_shortcodes() {
        add_shortcode('reviews_maps', array($this, 'display_reviews'));
    }

    /**
     * Función callback para el shortcode [reviews_maps]
     */
    public function display_reviews($atts) {
        // Obtener las opciones del plugin
        $options = get_option('reviews_maps_options');
        if (!$options) {
            return '<p>Error: El plugin no está configurado correctamente.</p>';
        }

        // Obtener las reseñas de la base de datos
        global $wpdb;
        $table_name = $wpdb->prefix . 'reviews_maps';
        $reviews = $wpdb->get_results(
            $wpdb->prepare(
                "SELECT * FROM $table_name ORDER BY rating DESC, date DESC LIMIT %d",
                $options['max_reviews']
            )
        );

        if (empty($reviews)) {
            return '<p>No hay reseñas disponibles en este momento.</p>';
        }

        // Obtener la última actualización
        $last_update = $wpdb->get_var("SELECT api_last_update FROM $table_name ORDER BY api_last_update DESC LIMIT 1");
        $last_update_text = $last_update ? sprintf(
            '<p class="reviews-maps-last-update">Última actualización: %s</p>',
            date_i18n(get_option('date_format') . ' ' . get_option('time_format'), strtotime($last_update))
        ) : '';

        // Iniciar el buffer de salida
        ob_start();
        ?>
        <div class="reviews-maps-container">
            <?php echo $last_update_text; ?>
            <div class="reviews-maps-grid">
                <?php foreach ($reviews as $review): ?>
                    <div class="reviews-maps-review">
                        <div class="reviews-maps-review-header">
                            <div class="reviews-maps-review-author">
                                <?php echo esc_html($review->author_name); ?>
                            </div>
                            <div class="reviews-maps-review-rating">
                                <?php echo str_repeat('★', $review->rating) . str_repeat('☆', 5 - $review->rating); ?>
                            </div>
                        </div>
                        <div class="reviews-maps-review-content">
                            <?php echo wp_kses_post($review->content); ?>
                        </div>
                        <div class="reviews-maps-review-date">
                            <?php echo date_i18n(get_option('date_format'), strtotime($review->date)); ?>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>
        <?php
        return ob_get_clean();
    }
} 