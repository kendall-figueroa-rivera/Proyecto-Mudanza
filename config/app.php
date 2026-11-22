<?php
/**
 * Archivo: app.php
 * Modulo: Configuracion
 * Responsable: Kendall Figueroa
 * Descripcion: Configuracion general de la aplicacion.
 *
 *   base_url: direccion con la que se abre el proyecto en el navegador, sin barra
 *             final. Se usa para armar los enlaces que van en los correos (por
 *             ejemplo, el de recuperar contrasena). Se escribe aqui y no se toma
 *             de la peticion, porque un atacante podria falsificar el nombre del
 *             servidor y hacer que el correo apunte a otro sitio. Si el proyecto
 *             se abre en otra direccion, se cambia solo este valor.
 */

return [
    'base_url' => 'http://localhost/Proyecto-Mudanza',
];
