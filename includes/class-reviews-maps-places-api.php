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
     * Buscar el Place ID utilizando el nombre del negocio
     */
    public function find_place_id_by_name() {
        $api_key = $this->options['google_maps_api_key'];
        $business_name = $this->options['business_name'] ?? '';

        if (empty($api_key) || empty($business_name)) {
            error_log('Reviews Maps: API Key o Nombre del Negocio no configurados');
            return new WP_Error('missing_config', 'API Key o Nombre del Negocio no configurados');
        }

        $url = add_query_arg(
            array(
                'input' => $business_name,
                'inputtype' => 'textquery',
                'fields' => 'place_id',
                'key' => $api_key
            ),
            'https://maps.googleapis.com/maps/api/place/findplacefromtext/json'
        );

        // Registrar URL (ocultando la API Key por seguridad)
        $log_url = preg_replace('/key=([^&]*)/', 'key=HIDDEN', $url);
        error_log('Reviews Maps: Buscando place_id por nombre: ' . $log_url);

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
            error_log('Reviews Maps: Error en la respuesta de la API - Estado: ' . $data['status'] . ', Mensaje: ' . $error_message);
            error_log('Reviews Maps: Respuesta completa: ' . $body);
            return new WP_Error('api_error', $error_message);
        }

        if (empty($data['candidates']) || !isset($data['candidates'][0]['place_id'])) {
            error_log('Reviews Maps: No se encontraron resultados para el nombre del negocio');
            return new WP_Error('no_results', 'No se encontraron resultados para el nombre del negocio');
        }

        $place_id = $data['candidates'][0]['place_id'];
        error_log('Reviews Maps: Nuevo place_id encontrado: ' . $place_id);

        // Actualizar el place_id en las opciones
        $this->update_place_id($place_id);

        return $place_id;
    }

    /**
     * Actualizar el place_id en las opciones
     */
    private function update_place_id($place_id) {
        $options = get_option('reviews_maps_options');
        $options['business_id'] = $place_id;
        update_option('reviews_maps_options', $options);
        $this->options = $options;
        error_log('Reviews Maps: Place ID actualizado en las opciones: ' . $place_id);
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
                'reviews_no_translations' => 'true',
                'reviews_sort' => 'newest',
                'language' => 'es', // Preferencia por reseñas en español
                'key' => $api_key
            ),
            'https://maps.googleapis.com/maps/api/place/details/json'
        );

        // Registrar URL (ocultando la API Key por seguridad)
        $log_url = preg_replace('/key=([^&]*)/', 'key=HIDDEN', $url);
        error_log('Reviews Maps: Consultando URL: ' . $log_url);

        $response = wp_remote_get($url, array(
            'timeout' => 30, // Aumentar el timeout para evitar errores
            'sslverify' => true
        ));

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
            error_log('Reviews Maps: Error en la respuesta de la API - Estado: ' . $data['status'] . ', Mensaje: ' . $error_message);
            error_log('Reviews Maps: Respuesta completa: ' . $body);
            
            // Si el place_id no es válido y tenemos un nombre de negocio, intentar encontrar un nuevo place_id
            if ($data['status'] === 'NOT_FOUND' && !empty($this->options['business_name'])) {
                error_log('Reviews Maps: Intentando encontrar un nuevo place_id por nombre...');
                $new_place_id = $this->find_place_id_by_name();
                
                if (!is_wp_error($new_place_id)) {
                    // Intentar de nuevo con el nuevo place_id
                    return $this->get_business_details();
                }
            }
            
            return new WP_Error('api_error', $error_message);
        }

        if (!isset($data['result']) || !isset($data['result']['reviews'])) {
            error_log('Reviews Maps: Respuesta válida pero sin reseñas. Respuesta: ' . $body);
            return array(
                'name' => $data['result']['name'] ?? '',
                'rating' => $data['result']['rating'] ?? 0,
                'reviews' => array()
            );
        }

        // Registrar información detallada sobre las reseñas
        $reviews_count = count($data['result']['reviews']);
        error_log('Reviews Maps: Reseñas encontradas: ' . $reviews_count);
        if ($reviews_count > 0) {
            $first_review_date = date('Y-m-d H:i:s', $data['result']['reviews'][0]['time']);
            $last_review_date = date('Y-m-d H:i:s', end($data['result']['reviews'])['time']);
            error_log('Reviews Maps: Rango de fechas de reseñas - Primera: ' . $first_review_date . ', Última: ' . $last_review_date);
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
     * 
     * @param bool $add_only Si es true, solo añade nuevas reseñas sin borrar las existentes
     * @param int $limit Número máximo de reseñas a guardar
     * @return int|bool|WP_Error Número de reseñas insertadas, false si no hay reseñas, o WP_Error en caso de error
     */
    public function save_reviews_to_db($add_only = true, $limit = 50) {
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
        error_log('Reviews Maps: Intentando guardar reseñas (modo: ' . ($add_only ? 'añadir' : 'reemplazar') . ', límite: ' . $limit . ')');
        error_log('Reviews Maps: Total de reseñas obtenidas: ' . count($reviews));

        // Ordenar las reseñas por fecha, de más reciente a más antigua
        usort($reviews, function($a, $b) {
            return $b['time'] - $a['time']; // Orden descendente
        });

        // Obtener todas las reseñas existentes por su fecha para comparación eficiente
        $existing_reviews = array();
        $existing_result = $wpdb->get_results(
            $wpdb->prepare(
                "SELECT id, review_date, author_name FROM $table_name WHERE business_id = %s ORDER BY review_date DESC",
                $this->options['business_id']
            ),
            ARRAY_A
        );
        
        if (!empty($existing_result)) {
            foreach ($existing_result as $review) {
                // Guardar por timestamp y autor para una identificación más precisa
                $key = strtotime($review['review_date']) . '_' . md5($review['author_name']);
                $existing_reviews[$key] = $review['id'];
            }
        }
        
        error_log('Reviews Maps: Reseñas existentes: ' . count($existing_reviews));

        $wpdb->query('START TRANSACTION');

        try {
            // Si no estamos en modo de añadir, eliminar reseñas antiguas
            if (!$add_only) {
                $deleted = $wpdb->delete($table_name, array('business_id' => $this->options['business_id']));
                error_log('Reviews Maps: Registros eliminados: ' . $deleted);
            }

            $inserted_count = 0;
            
            // Procesar todas las reseñas, aplicando el límite solo al final
            $processed_count = 0;
            
            foreach ($reviews as $review) {
                // Si alcanzamos el límite, detenemos el procesamiento
                if ($processed_count >= $limit && $limit > 0) {
                    error_log('Reviews Maps: Se alcanzó el límite de procesamiento: ' . $limit);
                    break;
                }
                
                // Validar campos requeridos
                if (!isset($review['author_name']) || !isset($review['rating']) || !isset($review['text']) || !isset($review['time'])) {
                    error_log('Reviews Maps: Reseña con formato incorrecto: ' . json_encode($review));
                    continue;
                }

                $review_date = date('Y-m-d H:i:s', $review['time']);
                // Para depuración
                error_log('Reviews Maps: Procesando reseña de ' . $review['author_name'] . ' con fecha ' . $review_date);

                // Crear una clave única para esta reseña
                $review_key = $review['time'] . '_' . md5($review['author_name']);
                
                // En modo añadir, verificar si ya existe una reseña con la misma fecha y autor
                if ($add_only && isset($existing_reviews[$review_key])) {
                    error_log('Reviews Maps: Reseña de ' . $review['author_name'] . ' con fecha ' . $review_date . ' ya existe, omitiendo');
                    continue;
                }

                $data = array(
                    'business_id' => $this->options['business_id'],
                    'author_name' => sanitize_text_field($review['author_name']),
                    'rating' => intval($review['rating']),
                    'review_text' => sanitize_textarea_field($review['text']),
                    'review_date' => $review_date,
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
                error_log('Reviews Maps: Insertando reseña con fecha: ' . $data['review_date']);

                $result = $wpdb->insert($table_name, $data);
                
                if ($result === false) {
                    error_log('Reviews Maps: Error al insertar reseña - ' . $wpdb->last_error);
                    throw new Exception($wpdb->last_error);
                }
                
                $inserted_count++;
                $processed_count++;
                error_log('Reviews Maps: Reseña insertada ID: ' . $wpdb->insert_id);
            }

            $wpdb->query('COMMIT');
            error_log('Reviews Maps: Reseñas guardadas correctamente. Total nuevas: ' . $inserted_count);
            return $inserted_count;
        } catch (Exception $e) {
            $wpdb->query('ROLLBACK');
            error_log('Reviews Maps: Error en la transacción - ' . $e->getMessage());
            return new WP_Error('db_error', $e->getMessage());
        }
    }

    /**
     * Actualizar reseñas existentes
     * 
     * @param bool $add_only Si es true, solo añade nuevas reseñas sin borrar las existentes
     * @param int $limit Número máximo de reseñas a guardar
     * @return int|bool|WP_Error Número de reseñas insertadas, false si no hay reseñas, o WP_Error en caso de error
     */
    public function update_existing_reviews($add_only = true, $limit = 50) {
        // Comprobar si hay reseñas nuevas comparando con la API
        $force_update = false;
        
        // Obtener la reseña más reciente de la base de datos
        global $wpdb;
        $table_name = $wpdb->prefix . 'reviews_maps';
        $most_recent_db_review = $wpdb->get_row(
            $wpdb->prepare(
                "SELECT review_date FROM $table_name WHERE business_id = %s ORDER BY review_date DESC LIMIT 1",
                $this->options['business_id']
            )
        );
        
        // Si no hay reseñas en la base de datos, forzar la actualización
        if (!$most_recent_db_review) {
            error_log('Reviews Maps: No hay reseñas en la base de datos, forzando actualización');
            $force_update = true;
        } else {
            // Obtener la fecha de la reseña más reciente
            $most_recent_db_date = strtotime($most_recent_db_review->review_date);
            
            // Obtener las reseñas de la API y comprobar si hay alguna más reciente
            $api_reviews = $this->get_business_reviews();
            
            if (is_wp_error($api_reviews)) {
                error_log('Reviews Maps: Error al verificar reseñas nuevas - ' . $api_reviews->get_error_message());
                // Si hay error en la API, verificamos por tiempo
                return $this->should_update_reviews() ? $this->save_reviews_to_db($add_only, $limit) : false;
            }
            
            if (!empty($api_reviews)) {
                // Ordenar por fecha (más reciente primero)
                usort($api_reviews, function($a, $b) {
                    return $b['time'] - $a['time'];
                });
                
                // Verificar si la reseña más reciente de la API es más nueva que la más reciente en la BD
                $most_recent_api_date = $api_reviews[0]['time'];
                
                if ($most_recent_api_date > $most_recent_db_date) {
                    error_log('Reviews Maps: Se encontraron reseñas más recientes en la API. BD: ' . 
                             date('Y-m-d H:i:s', $most_recent_db_date) . ', API: ' . 
                             date('Y-m-d H:i:s', $most_recent_api_date));
                    $force_update = true;
                } else {
                    error_log('Reviews Maps: No hay reseñas nuevas disponibles en la API');
                    
                    // Incluso si no hay reseñas nuevas, actualizamos si han pasado más de 24 horas
                    $force_update = $this->should_update_reviews();
                }
            } else {
                error_log('Reviews Maps: No se obtuvieron reseñas de la API para comparar');
                // Si no hay reseñas de la API, verificamos por tiempo
                $force_update = $this->should_update_reviews();
            }
        }
        
        if ($force_update) {
            error_log('Reviews Maps: Actualizando reseñas');
            return $this->save_reviews_to_db($add_only, $limit);
        }
        
        error_log('Reviews Maps: No es necesario actualizar las reseñas');
        return false;
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
            error_log('Reviews Maps: API Key o Place ID no configurados para prueba de conexión');
            return new WP_Error('missing_config', 'API Key o Place ID no configurados. Por favor, completa estos campos primero.');
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

        // Registrar URL (ocultando la API Key por seguridad)
        $log_url = preg_replace('/key=([^&]*)/', 'key=HIDDEN', $url);
        error_log('Reviews Maps: Probando conexión API: ' . $log_url);

        $response = wp_remote_get($url);

        if (is_wp_error($response)) {
            error_log('Reviews Maps: Error en la prueba de conexión - ' . $response->get_error_message());
            return new WP_Error('request_error', 'Error al conectar con la API de Google: ' . $response->get_error_message());
        }

        $status_code = wp_remote_retrieve_response_code($response);
        $body = wp_remote_retrieve_body($response);
        $data = json_decode($body, true);

        // Registrar respuesta para depuración
        error_log('Reviews Maps: Respuesta de prueba - Código: ' . $status_code);
        
        if ($status_code !== 200) {
            error_log('Reviews Maps: Error HTTP en prueba de conexión: ' . $status_code);
            return new WP_Error('http_error', 'Error HTTP: ' . $status_code);
        }

        if ($data['status'] !== 'OK') {
            $error_message = isset($data['error_message']) ? $data['error_message'] : 'Error desconocido en la API';
            
            // Mensajes personalizados para errores comunes
            if ($data['status'] === 'NOT_FOUND') {
                $error_message = 'El Place ID proporcionado (' . $place_id . ') no es válido. Por favor, obtén un nuevo ID usando la herramienta oficial de Google.';
            } elseif ($data['status'] === 'INVALID_REQUEST') {
                $error_message = 'Solicitud inválida. Verifica que el Place ID tenga el formato correcto.';
            } elseif ($data['status'] === 'REQUEST_DENIED') {
                $error_message = 'Solicitud denegada. Verifica que tu API Key sea válida y tenga habilitada la Places API.';
            } elseif ($data['status'] === 'OVER_QUERY_LIMIT') {
                $error_message = 'Se ha excedido el límite de consultas. Intenta más tarde o actualiza tu plan de Google Cloud.';
            }
            
            error_log('Reviews Maps: Error en prueba de conexión - Estado: ' . $data['status'] . ', Mensaje: ' . $error_message);
            return new WP_Error('api_error', $error_message);
        }

        error_log('Reviews Maps: Prueba de conexión exitosa - Negocio: ' . $data['result']['name']);
        return array(
            'success' => true,
            'place_name' => $data['result']['name'],
            'place_id' => $data['result']['place_id']
        );
    }

    /**
     * Obtener el business_id actual
     * 
     * @return string El ID del negocio
     */
    public function get_business_id() {
        return $this->options['business_id'] ?? '';
    }
} 