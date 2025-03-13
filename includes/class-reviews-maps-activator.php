<?php
/**
 * Fired during plugin activation
 */
class Reviews_Maps_Activator {
    /**
     * Crear la tabla de reseñas en la base de datos
     */
    public static function activate() {
        global $wpdb;
        $table_name = $wpdb->prefix . 'reviews_maps';
        $charset_collate = $wpdb->get_charset_collate();

        $sql = "CREATE TABLE IF NOT EXISTS $table_name (
            id bigint(20) NOT NULL AUTO_INCREMENT,
            business_id varchar(255) NOT NULL,
            author_name varchar(255) NOT NULL,
            rating int(1) NOT NULL,
            review_text text NOT NULL,
            review_date datetime NOT NULL,
            photo_url varchar(255) DEFAULT NULL,
            owner_response text DEFAULT NULL,
            last_updated datetime NOT NULL,
            api_last_update datetime NOT NULL,
            PRIMARY KEY  (id),
            KEY business_id (business_id),
            KEY review_date (review_date),
            KEY api_last_update (api_last_update),
            KEY business_review_date (business_id, review_date),
            KEY business_api_update (business_id, api_last_update)
        ) $charset_collate;";

        require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
        dbDelta($sql);

        // Verificar si la columna api_last_update existe
        $column_exists = $wpdb->get_results("SHOW COLUMNS FROM $table_name LIKE 'api_last_update'");
        if (empty($column_exists)) {
            $wpdb->query("ALTER TABLE $table_name ADD COLUMN api_last_update datetime NOT NULL AFTER last_updated");
            $wpdb->query("ALTER TABLE $table_name ADD INDEX api_last_update (api_last_update)");
            $wpdb->query("ALTER TABLE $table_name ADD INDEX business_api_update (business_id, api_last_update)");
        }

        // Verificar y actualizar índices compuestos
        $indexes = $wpdb->get_results("SHOW INDEX FROM $table_name");
        $has_business_review_date = false;
        $has_business_api_update = false;

        foreach ($indexes as $index) {
            if ($index->Key_name === 'business_review_date') {
                $has_business_review_date = true;
            }
            if ($index->Key_name === 'business_api_update') {
                $has_business_api_update = true;
            }
        }

        if (!$has_business_review_date) {
            $wpdb->query("ALTER TABLE $table_name ADD INDEX business_review_date (business_id, review_date)");
        }
        if (!$has_business_api_update) {
            $wpdb->query("ALTER TABLE $table_name ADD INDEX business_api_update (business_id, api_last_update)");
        }

        // Crear opciones por defecto
        $default_options = array(
            'google_maps_api_key' => '',
            'business_name' => '',
            'business_id' => '',
            'update_time' => '00:00', // Hora por defecto para actualización diaria
            'cache_duration' => 3600,
            'max_reviews' => 100
        );

        // Actualizar opciones existentes si es necesario
        $existing_options = get_option('reviews_maps_options', array());
        $updated_options = wp_parse_args($existing_options, $default_options);
        update_option('reviews_maps_options', $updated_options);

        // Programar el cron job diario
        if (!wp_next_scheduled('reviews_maps_daily_update')) {
            wp_schedule_event(strtotime('tomorrow ' . $updated_options['update_time']), 'daily', 'reviews_maps_daily_update');
        }
    }
} 