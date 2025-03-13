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
            PRIMARY KEY  (id),
            KEY business_id (business_id),
            KEY review_date (review_date)
        ) $charset_collate;";

        require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
        dbDelta($sql);

        // Crear opciones por defecto
        $default_options = array(
            'google_maps_api_key' => '',
            'business_id' => '',
            'update_frequency' => 'daily',
            'cache_duration' => 3600,
            'max_reviews' => 100
        );

        add_option('reviews_maps_options', $default_options);
    }
} 