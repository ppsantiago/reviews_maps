<?php
/**
 * La clase específica del área de administración del plugin
 */
class Reviews_Maps_Admin {
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
     * Registrar los estilos para el área de administración
     */
    public function enqueue_styles() {
        wp_enqueue_style(
            $this->plugin_name,
            plugin_dir_url(__FILE__) . 'css/reviews-maps-admin.css',
            array(),
            $this->version,
            'all'
        );
    }

    /**
     * Registrar el JavaScript para el área de administración
     */
    public function enqueue_scripts() {
        wp_enqueue_script(
            $this->plugin_name,
            plugin_dir_url(__FILE__) . 'js/reviews-maps-admin.js',
            array('jquery'),
            $this->version,
            false
        );
    }
} 