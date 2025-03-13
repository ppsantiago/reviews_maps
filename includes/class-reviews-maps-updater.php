<?php
/**
 * La clase que maneja las actualizaciones del plugin
 */
class Reviews_Maps_Updater {
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
     * Verificar y ejecutar actualizaciones necesarias
     */
    public function check_updates() {
        $current_version = get_option('reviews_maps_version', '0.0.0');
        
        if (version_compare($current_version, $this->version, '<')) {
            $this->update_database();
            update_option('reviews_maps_version', $this->version);
        }
    }

    /**
     * Actualizar la estructura de la base de datos
     */
    private function update_database() {
        global $wpdb;
        $table_name = $wpdb->prefix . 'reviews_maps';

        // Verificar si la columna api_last_update existe
        $column_exists = $wpdb->get_results("SHOW COLUMNS FROM $table_name LIKE 'api_last_update'");
        if (empty($column_exists)) {
            $wpdb->query("ALTER TABLE $table_name ADD COLUMN api_last_update datetime NOT NULL AFTER last_updated");
            $wpdb->query("ALTER TABLE $table_name ADD INDEX api_last_update (api_last_update)");
        }

        // Actualizar registros existentes
        $wpdb->query("UPDATE $table_name SET api_last_update = last_updated WHERE api_last_update = '0000-00-00 00:00:00'");
    }
} 