<?php
/**
 * Vista: logout.php
 * Modulo: Autenticacion
 * Responsable: Kendall Figueroa
 * Descripcion: Cierra la sesion activa y redirige al home.
 *              (Pendiente que quedo del modulo de auth, cerrado aqui porque
 *              el Home ya lo necesita para su boton "Cerrar sesion".)
 */

session_start();
require_once __DIR__ . '/../../../config/database.php';
require_once __DIR__ . '/../../controllers/AuthController.php';

$auth = new AuthController($pdo);
$auth->cerrarSesion();

header('Location: ../publico/home.php');
exit;
