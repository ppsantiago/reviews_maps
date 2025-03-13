<?php
/**
 * Proporciona una vista para la página de configuración del plugin
 */

// Si el usuario no tiene permisos, salir
if (!current_user_can('manage_options')) {
    return;
}

// Mostrar mensajes de configuración guardada
settings_errors('reviews_maps_messages');
?>

<div class="wrap">
    <h1><?php echo esc_html(get_admin_page_title()); ?></h1>
    
    <form method="post" action="options.php">
        <?php
        settings_fields($this->plugin_name);
        do_settings_sections($this->plugin_name);
        submit_button('Guardar Configuración');
        ?>
    </form>

    <hr>

    <h2>Probar Conexión</h2>
    <p>Usa este botón para verificar si la API Key y el Place ID son válidos:</p>
    <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>" id="test-api-form">
        <?php wp_nonce_field('reviews_maps_test_api'); ?>
        <input type="hidden" name="action" value="reviews_maps_test_api">
        <?php submit_button('Probar Conexión API', 'primary', 'submit', false); ?>
        <span class="spinner" id="test-spinner" style="float: none; visibility: hidden;"></span>
    </form>

    <hr>

    <h2>Actualización Manual</h2>
    <p>Haz clic en el botón para añadir hasta 10 reseñas más recientes desde la API de Google Places. Las reseñas existentes no se eliminarán.</p>
    <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>" id="manual-update-form">
        <?php wp_nonce_field('reviews_maps_manual_update'); ?>
        <input type="hidden" name="action" value="reviews_maps_manual_update">
        <input type="hidden" name="reviews_maps_manual_update" value="1">
        <?php submit_button('Actualizar Reseñas Ahora', 'secondary', 'submit', false); ?>
        <span class="spinner" id="update-spinner" style="float: none; visibility: hidden;"></span>
    </form>

    <script type="text/javascript">
    jQuery(document).ready(function($) {
        // Efecto de carga para actualización manual
        $('#manual-update-form').on('submit', function() {
            $('#update-spinner').css('visibility', 'visible');
            $('input[type="submit"]', this).attr('disabled', 'disabled');
        });
        
        // Efecto de carga para prueba de API
        $('#test-api-form').on('submit', function() {
            $('#test-spinner').css('visibility', 'visible');
            $('input[type="submit"]', this).attr('disabled', 'disabled');
        });
    });
    </script>

    <hr>

    <h2>Reseñas Guardadas</h2>
    <?php
    require_once REVIEWS_MAPS_PLUGIN_DIR . 'includes/class-reviews-maps-places-api.php';
    $places_api = new Reviews_Maps_Places_API($this->plugin_name, $this->version);
    $last_update = $places_api->get_last_api_update();
    $reviews = $places_api->get_reviews_from_db(10); // Mostrar las 10 reseñas más recientes
    ?>
    
    <?php if ($last_update): ?>
    <p>Última actualización: <strong><?php echo date_i18n(get_option('date_format') . ' ' . get_option('time_format'), strtotime($last_update)); ?></strong></p>
    <?php endif; ?>
    
    <?php if (empty($reviews)): ?>
    <p>No hay reseñas guardadas aún. Utiliza el botón "Actualizar Reseñas Ahora" para importar reseñas.</p>
    <?php else: ?>
    <p>Mostrando las <?php echo count($reviews); ?> reseñas más recientes de un total de 
    <?php 
    global $wpdb;
    $table_name = $wpdb->prefix . 'reviews_maps';
    $total = $wpdb->get_var($wpdb->prepare("SELECT COUNT(*) FROM $table_name WHERE business_id = %s", $places_api->get_business_id()));
    echo $total;
    ?> reseñas guardadas.</p>
    
    <table class="wp-list-table widefat fixed striped">
        <thead>
            <tr>
                <th>Autor</th>
                <th>Calificación</th>
                <th>Reseña</th>
                <th>Fecha</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($reviews as $review): ?>
            <tr>
                <td>
                    <?php echo esc_html($review['author_name']); ?>
                    <?php if (!empty($review['photo_url'])): ?>
                    <br><img src="<?php echo esc_url($review['photo_url']); ?>" alt="<?php echo esc_attr($review['author_name']); ?>" width="50" height="50" style="border-radius: 50%;">
                    <?php endif; ?>
                </td>
                <td><?php echo str_repeat('★', intval($review['rating'])) . str_repeat('☆', 5 - intval($review['rating'])); ?></td>
                <td>
                    <?php echo esc_html($review['review_text']); ?>
                    <?php if (!empty($review['owner_response'])): ?>
                    <div class="owner-response" style="margin-top: 10px; padding: 8px; background: #f5f5f5; border-left: 4px solid #0073aa;">
                        <strong>Respuesta del propietario:</strong><br>
                        <?php echo esc_html($review['owner_response']); ?>
                    </div>
                    <?php endif; ?>
                </td>
                <td><?php echo date_i18n(get_option('date_format'), strtotime($review['review_date'])); ?></td>
            </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
    <?php endif; ?>

    <hr>

    <h2>Información Adicional</h2>
    <h3>¿Cómo obtener tu API Key de Google Maps?</h3>
    <ol>
        <li>Ve a la <a href="https://console.cloud.google.com" target="_blank">Google Cloud Console</a></li>
        <li>Crea un nuevo proyecto o selecciona uno existente</li>
        <li>Habilita la Places API para tu proyecto</li>
        <li>Ve a Credenciales y crea una nueva API Key</li>
        <li>Restringe la API Key a tu dominio para mayor seguridad</li>
    </ol>

    <h3>¿Cómo obtener el ID del Negocio?</h3>
    <ol>
        <li>Usa la <a href="https://developers.google.com/maps/documentation/javascript/examples/places-placeid-finder" target="_blank">herramienta oficial de Google para buscar Place ID</a></li>
        <li>Escribe el nombre exacto de tu negocio y selecciónalo de la lista</li>
        <li>La herramienta mostrará el Place ID actual y válido</li>
        <li>Copia el Place ID completo (comienza con "ChIJ..." o similar)</li>
        <li>Asegúrate de que tu API Key tenga habilitada la Places API</li>
    </ol>

    <p><strong>Nota importante:</strong> Si el ID del negocio deja de funcionar, el plugin intentará encontrar automáticamente un nuevo ID utilizando el nombre del negocio. Por favor, asegúrate de que el nombre del negocio sea exactamente como aparece en Google Maps.</p>
</div> 