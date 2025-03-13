<?php
/**
 * Fired during plugin deactivation
 */
class Reviews_Maps_Deactivator {
    /**
     * No eliminamos la tabla ni las opciones al desactivar
     * Solo limpiamos la caché si es necesario
     */
    public static function deactivate() {
        // Limpiar cualquier caché temporal si existe
        wp_cache_delete('reviews_maps_cache', 'options');
    }
} 