# Reviews Maps - Plugin de WordPress

## Descripción
Reviews Maps es un plugin de WordPress que permite mostrar y gestionar automáticamente las reseñas de Google Places de tu negocio. El plugin obtiene las reseñas a través de la API de Google Places, las almacena en la base de datos local y las muestra en tu sitio web mediante un shortcode.

## Características
- Importación automática de reseñas desde Google Places
- Actualización programada diaria de reseñas
- Panel de administración intuitivo
- Shortcode para mostrar las reseñas en cualquier página
- Soporte para respuestas del propietario
- Visualización de fotos de perfil de los autores
- Sistema de caché para optimizar el rendimiento
- Actualización manual de reseñas con límite configurable
- Interfaz responsive y moderna

## Requisitos
- WordPress 5.0 o superior
- PHP 7.2 o superior
- MySQL 5.6 o superior
- API Key de Google Maps con Places API habilitada
- Place ID del negocio

## Instalación
1. Sube la carpeta `reviews-maps` al directorio `/wp-content/plugins/`
2. Activa el plugin a través del menú 'Plugins' en WordPress
3. Ve a Ajustes → Reviews Maps para configurar el plugin

## Configuración
1. **API Key de Google Maps:**
   - Ve a la [Google Cloud Console](https://console.cloud.google.com)
   - Crea un nuevo proyecto o selecciona uno existente
   - Habilita la Places API
   - Crea una nueva API Key
   - Restringe la API Key a tu dominio

2. **ID del Negocio (Place ID):**
   - Usa la [herramienta de Google](https://developers.google.com/maps/documentation/javascript/examples/places-placeid-finder)
   - Busca tu negocio por nombre
   - Copia el Place ID (comienza con "ChIJ...")

3. **Configuración del Plugin:**
   - Introduce la API Key y el Place ID en la página de configuración
   - Establece la hora de actualización automática
   - Configura el número máximo de reseñas a mostrar

## Uso
### Shortcode Básico
```
[reviews_maps]
```

### Opciones de Visualización
- Las reseñas se muestran en un diseño responsive
- Incluye:
  - Nombre del autor
  - Foto de perfil (si está disponible)
  - Calificación con estrellas
  - Texto de la reseña
  - Fecha
  - Respuesta del propietario (si existe)

## Actualización de Reseñas
### Automática
- Las reseñas se actualizan automáticamente una vez al día
- La hora de actualización es configurable
- Solo se añaden reseñas nuevas, no se eliminan las existentes

### Manual
1. Ve al panel de administración del plugin
2. Selecciona la cantidad de reseñas a importar (10-200)
3. Haz clic en "Actualizar Reseñas Ahora"

## Solución de Problemas
### La API Key no funciona
- Verifica que la Places API esté habilitada
- Comprueba las restricciones de la API Key
- Usa el botón "Probar Conexión API" en el panel

### No se muestran reseñas
- Verifica que el Place ID sea correcto
- Comprueba los logs de error en wp-content/debug.log
- Asegúrate de que existan reseñas en Google Places

### Errores de Base de Datos
- Desactiva y reactiva el plugin
- Verifica los permisos de la base de datos
- Comprueba el prefijo de las tablas en wp-config.php

## Soporte
Para reportar problemas o solicitar ayuda:
1. Revisa la documentación completa
2. Verifica los logs de error
3. Contacta con el soporte técnico

## Licencia
Este plugin está licenciado bajo la GPL v2 o posterior.

## Créditos
Desarrollado por [Tu Nombre/Empresa]
Versión: 1.0.0 