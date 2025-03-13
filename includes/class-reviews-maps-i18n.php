<?php
/**
 * Define la funcionalidad de internacionalización
 */
class Reviews_Maps_i18n {
    /**
     * Cargar el dominio de texto del plugin para la traducción
     */
    public function load_plugin_textdomain() {
        load_plugin_textdomain(
            'reviews-maps',
            false,
            dirname(dirname(plugin_basename(__FILE__))) . '/languages/'
        );
    }
} 