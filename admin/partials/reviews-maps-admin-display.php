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
    <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>">
        <?php wp_nonce_field('reviews_maps_test_api'); ?>
        <input type="hidden" name="action" value="reviews_maps_test_api">
        <?php submit_button('Probar Conexión API', 'primary', 'submit', false); ?>
    </form>

    <hr>

    <h2>Actualización Manual</h2>
    <p>Haz clic en el botón para actualizar manualmente las reseñas desde la API de Google Places.</p>
    <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>">
        <?php wp_nonce_field('reviews_maps_manual_update'); ?>
        <input type="hidden" name="action" value="reviews_maps_manual_update">
        <input type="hidden" name="reviews_maps_manual_update" value="1">
        <?php submit_button('Actualizar Reseñas Ahora', 'secondary', 'submit', false); ?>
    </form>

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
    <p><strong>Nota importante:</strong> Los Place IDs pueden cambiar ocasionalmente. Si recibes un error "NOT_FOUND" o "no longer valid", necesitas actualizar el Place ID usando el método anterior.</p>
</div> 