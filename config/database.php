<?php
/**
 * Archivo: database.php
 * Modulo: Base de datos
 * Responsable: Daniel Gómez Mora
 * Descripcion: Conexion centralizada a SQL Server mediante PDO.
 */

$server = ".\\SQLEXPRESS";
$database = "plataforma_mudanzas";
$user = "administrador";
$password = "Mudanzas.2026!";

try {
    $pdo = new PDO(
        "sqlsrv:Server=$server;Database=$database;TrustServerCertificate=1",
        $user,
        $password
    );

    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    http_response_code(500);
    die("No se pudo conectar a la base de datos.");
}

return $pdo;