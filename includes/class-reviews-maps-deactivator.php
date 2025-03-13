<?php
/**
 * Fired during plugin deactivation
 */
class Reviews_Maps_Deactivator {
    /**
     * Limpiar las tareas programadas y las opciones del plugin
     */
    public static function deactivate() {
        // Limpiar el cron job
        wp_clear_scheduled_hook('reviews_maps_daily_update');
        
        // Eliminar las opciones del plugin
        delete_option('reviews_maps_options');
    }
} 