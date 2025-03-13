<?php
/**
 * Proporciona una vista para la página de configuración del plugin
 */
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

    <div class="reviews-maps-info">
        <h2>Información Adicional</h2>
        <p>Para obtener tu API Key de Google Maps:</p>
        <ol>
            <li>Ve a la <a href="https://console.cloud.google.com/" target="_blank">Google Cloud Console</a></li>
            <li>Crea un nuevo proyecto o selecciona uno existente</li>
            <li>Habilita las APIs necesarias:
                <ul>
                    <li>Maps JavaScript API</li>
                    <li>Places API</li>
                </ul>
            </li>
            <li>Crea credenciales (API Key)</li>
            <li>Restringe la API Key para mayor seguridad</li>
        </ol>

        <p>Para obtener el ID del negocio:</p>
        <ol>
            <li>Busca tu negocio en Google Maps</li>
            <li>La URL tendrá un formato como: <code>https://www.google.com/maps/place/Tu+Negocio/@XX.XXXXX,-XX.XXXXX,XXz/</code></li>
            <li>El ID del negocio es el texto que aparece después de <code>/place/</code> y antes de <code>/@</code></li>
        </ol>
    </div>
</div> 