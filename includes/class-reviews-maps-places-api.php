<?php
/**
 * La clase que maneja la integración con la API de Google Places
 */
class Reviews_Maps_Places_API {
    /**
     * El ID del plugin
     */
    private $plugin_name;

    /**
     * La versión del plugin
     */
    private $version;

    /**
     * Las opciones del plugin
     */
    private $options;

    /**
     * Inicializar la clase y establecer sus propiedades
     */
    public function __construct($plugin_name, $version) {
        $this->plugin_name = $plugin_name;
        $this->version = $version;
        $this->options = get_option('reviews_maps_options');
    }

    /**
     * Verificar si es necesario actualizar las reseñas
     */
    public function should_update_reviews() {
        global $wpdb;
        $table_name = $wpdb->prefix . 'reviews_maps';

        // Obtener la última actualización de la API
        $last_update = $wpdb->get_var(
            "SELECT MAX(api_last_update) FROM $table_name"
        );

        if (!$last_update) {
            return true;
        }

        // Verificar si han pasado 24 horas desde la última actualización
        $last_update_timestamp = strtotime($last_update);
        $current_timestamp = current_time('timestamp');
        $hours_diff = ($current_timestamp - $last_update_timestamp) / 3600;

        return $hours_diff >= 24;
    }

    /**
     * Obtener detalles del negocio usando la API de Google Places
     */
    public function get_business_details() {
        $api_key = $this->options['google_maps_api_key'];
        $place_id = $this->options['business_id'];

        if (empty($api_key) || empty($place_id)) {
            error_log('Reviews Maps: API Key o Place ID no configurados');
            return new WP_Error('missing_config', 'API Key o Place ID no configurados');
        }

        $url = add_query_arg(
            array(
                'place_id' => $place_id,
                'fields' => 'name,rating,reviews,formatted_address,geometry',
                'key' => $api_key
            ),
            'https://maps.googleapis.com/maps/api/place/details/json'
        );

        // Registrar URL (ocultando la API Key por seguridad)
        $log_url = preg_replace('/key=([^&]*)/', 'key=HIDDEN', $url);
        error_log('Reviews Maps: Consultando URL: ' . $log_url);

        $response = wp_remote_get($url);

        if (is_wp_error($response)) {
            error_log('Reviews Maps: Error en la llamada a la API - ' . $response->get_error_message());
            return $response;
        }

        $body = wp_remote_retrieve_body($response);
        $data = json_decode($body, true);

        // Registrar respuesta para depuración
        error_log('Reviews Maps: Código de respuesta: ' . wp_remote_retrieve_response_code($response));
        
        if ($data['status'] !== 'OK') {
            $error_message = isset($data['error_message']) ? $data['error_message'] : 'Error desconocido en la API';
            
            // Mensaje personalizado para Place ID inválido
            if ($data['status'] === 'NOT_FOUND') {
                $error_message = 'El Place ID proporcionado (' . $place_id . ') ya no es válido. Por favor, actualiza el Place ID usando la herramienta oficial de Google.';
            } elseif ($data['status'] === 'INVALID_REQUEST') {
                $error_message = 'Solicitud inválida. Verifica que el Place ID y la API Key sean correctos.';
            } elseif ($data['status'] === 'OVER_QUERY_LIMIT') {
                $error_message = 'Se ha excedido el límite de consultas a la API. Intenta más tarde o considera aumentar tu cuota.';
            } elseif ($data['status'] === 'REQUEST_DENIED') {
                $error_message = 'Solicitud denegada. Verifica que la API Key sea válida y tenga habilitada la Places API.';
            }
            
            error_log('Reviews Maps: Error en la respuesta de la API - Estado: ' . $data['status'] . ', Mensaje: ' . $error_message);
            error_log('Reviews Maps: Respuesta completa: ' . $body);
            return new WP_Error('api_error', $error_message);
        }

        if (!isset($data['result']) || !isset($data['result']['reviews'])) {
            error_log('Reviews Maps: Respuesta válida pero sin reseñas. Respuesta: ' . $body);
        } else {
            error_log('Reviews Maps: Reseñas encontradas: ' . count($data['result']['reviews']));
        }

        return $data['result'];
    }

    /**
     * Obtener reseñas del negocio
     */
    public function get_business_reviews() {
        $business_details = $this->get_business_details();

        if (is_wp_error($business_details)) {
            error_log('Reviews Maps: Error al obtener detalles del negocio - ' . $business_details->get_error_message());
            return $business_details;
        }

        if (!isset($business_details['reviews']) || empty($business_details['reviews'])) {
            error_log('Reviews Maps: No se encontraron reseñas en la respuesta de la API');
            return array();
        }

        // Registrar para depuración
        error_log('Reviews Maps: Se encontraron ' . count($business_details['reviews']) . ' reseñas para el negocio');
        
        return $business_details['reviews'];
    }

    /**
     * Guardar reseñas en la base de datos
     */
    public function save_reviews_to_db() {
        global $wpdb;
        $table_name = $wpdb->prefix . 'reviews_maps';
        $current_time = current_time('mysql');

        // Verificar que la tabla existe
        $table_exists = $wpdb->get_var("SHOW TABLES LIKE '$table_name'") === $table_name;
        if (!$table_exists) {
            error_log('Reviews Maps: La tabla ' . $table_name . ' no existe. Activando plugin para crearla.');
            // Crear la tabla si no existe
            require_once REVIEWS_MAPS_PLUGIN_DIR . 'includes/class-reviews-maps-activator.php';
            Reviews_Maps_Activator::activate();
            
            // Verificar nuevamente
            $table_exists = $wpdb->get_var("SHOW TABLES LIKE '$table_name'") === $table_name;
            if (!$table_exists) {
                error_log('Reviews Maps: No se pudo crear la tabla ' . $table_name);
                return new WP_Error('db_error', 'La tabla de la base de datos no existe y no se pudo crear');
            }
        }

        $reviews = $this->get_business_reviews();
        if (is_wp_error($reviews)) {
            error_log('Reviews Maps: Error al obtener reseñas - ' . $reviews->get_error_message());
            return $reviews;
        }

        if (empty($reviews)) {
            error_log('Reviews Maps: No hay reseñas para guardar');
            return false;
        }

        // Registrar para depuración
        error_log('Reviews Maps: Intentando guardar ' . count($reviews) . ' reseñas');
        error_log('Reviews Maps: Ejemplo de estructura de reseña: ' . json_encode(reset($reviews)));

        $wpdb->query('START TRANSACTION');

        try {
            // Primero, eliminar reseñas antiguas del mismo business_id
            $deleted = $wpdb->delete($table_name, array('business_id' => $this->options['business_id']));
            error_log('Reviews Maps: Registros eliminados: ' . $deleted);

            $inserted_count = 0;
            foreach ($reviews as $review) {
                // Validar campos requeridos
                if (!isset($review['author_name']) || !isset($review['rating']) || !isset($review['text']) || !isset($review['time'])) {
                    error_log('Reviews Maps: Reseña con formato incorrecto: ' . json_encode($review));
                    continue;
                }

                // Para depuración
                error_log('Reviews Maps: Procesando reseña de ' . $review['author_name']);

                $data = array(
                    'business_id' => $this->options['business_id'],
                    'author_name' => sanitize_text_field($review['author_name']),
                    'rating' => intval($review['rating']),
                    'review_text' => sanitize_textarea_field($review['text']),
                    'review_date' => date('Y-m-d H:i:s', $review['time']),
                    'photo_url' => isset($review['profile_photo_url']) ? esc_url_raw($review['profile_photo_url']) : null,
                    'owner_response' => isset($review['author_reply']) && isset($review['author_reply']['text']) 
                        ? sanitize_textarea_field($review['author_reply']['text']) 
                        : (isset($review['author_response']) && isset($review['author_response']['text']) 
                            ? sanitize_textarea_field($review['author_response']['text']) 
                            : null),
                    'last_updated' => $current_time,
                    'api_last_update' => $current_time
                );

                // Para depuración
                error_log('Reviews Maps: Datos a insertar: ' . json_encode($data));

                $result = $wpdb->insert($table_name, $data);
                
                if ($result === false) {
                    error_log('Reviews Maps: Error al insertar reseña - ' . $wpdb->last_error);
                    throw new Exception($wpdb->last_error);
                }
                
                $inserted_count++;
                error_log('Reviews Maps: Reseña insertada ID: ' . $wpdb->insert_id);
            }

            $wpdb->query('COMMIT');
            error_log('Reviews Maps: Reseñas guardadas correctamente. Total: ' . $inserted_count);
            return true;
        } catch (Exception $e) {
            $wpdb->query('ROLLBACK');
            error_log('Reviews Maps: Error en la transacción - ' . $e->getMessage());
            return new WP_Error('db_error', $e->getMessage());
        }
    }

    /**
     * Actualizar reseñas existentes
     */
    public function update_existing_reviews() {
        if (!$this->should_update_reviews()) {
            error_log('Reviews Maps: No es necesario actualizar las reseñas');
            return false;
        }

        return $this->save_reviews_to_db();
    }

    /**
     * Obtener reseñas de la base de datos
     */
    public function get_reviews_from_db($limit = null) {
        global $wpdb;
        $table_name = $wpdb->prefix . 'reviews_maps';

        $query = "SELECT * FROM $table_name WHERE business_id = %s ORDER BY review_date DESC";
        if ($limit) {
            $query .= " LIMIT %d";
        }

        $params = array($this->options['business_id']);
        if ($limit) {
            $params[] = $limit;
        }

        return $wpdb->get_results(
            $wpdb->prepare($query, $params),
            ARRAY_A
        );
    }

    /**
     * Obtener la última actualización de la API
     */
    public function get_last_api_update() {
        global $wpdb;
        $table_name = $wpdb->prefix . 'reviews_maps';

        return $wpdb->get_var(
            "SELECT MAX(api_last_update) FROM $table_name"
        );
    }

    /**
     * Probar la conexión a la API de Google Places
     */
    public function test_api_connection() {
        $api_key = $this->options['google_maps_api_key'];
        $place_id = $this->options['business_id'];

        if (empty($api_key) || empty($place_id)) {
            return new WP_Error('missing_config', 'API Key o Place ID no configurados');
        }

        // Primero, probar con una solicitud más pequeña (solo el nombre)
        $url = add_query_arg(
            array(
                'place_id' => $place_id,
                'fields' => 'name,place_id',
                'key' => $api_key
            ),
            'https://maps.googleapis.com/maps/api/place/details/json'
        );

        $response = wp_remote_get($url);

        if (is_wp_error($response)) {
            return $response;
        }

        $body = wp_remote_retrieve_body($response);
        $data = json_decode($body, true);

        if ($data['status'] !== 'OK') {
            $error_message = isset($data['error_message']) ? $data['error_message'] : 'Error desconocido en la API';
            return new WP_Error('api_error', $error_message);
        }

        return array(
            'success' => true,
            'place_name' => $data['result']['name'],
            'place_id' => $data['result']['place_id']
        );
    }
} 