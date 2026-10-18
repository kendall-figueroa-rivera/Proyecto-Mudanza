<?php
/**
 * Archivo: index.php (raiz del proyecto)
 * Modulo: Cliente (punto de entrada temporal)
 * Responsable: [nombre del integrante]
 * Descripcion: Redirige al home cuando se abre http://localhost/Proyecto-Mudanza/
 *              Es temporal mientras el coordinador termina el enrutamiento en
 *              public/index.php; cuando ese exista, este archivo se puede borrar.
 */

header('Location: app/views/publico/home.php');
exit;