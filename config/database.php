<?php
/**
 * Archivo: database.php
 * Modulo: Base de datos
 * Responsable: Daniel Gómez Mora
 * Descripcion: Conexion centralizada a SQL Server mediante PDO.
 */

$envFile = dirname(__DIR__) . DIRECTORY_SEPARATOR . '.env';

if (is_file($envFile)) {
    $envValues = parse_ini_file($envFile, false, INI_SCANNER_RAW);

    if ($envValues === false) {
        throw new RuntimeException('No se pudo leer el archivo .env.');
    }

    foreach ($envValues as $name => $value) {
        if (getenv($name) === false) {
            putenv($name . '=' . $value);
        }
    }
}

if (!in_array('sqlsrv', PDO::getAvailableDrivers(), true)) {
    throw new RuntimeException(
        'Falta el controlador PDO_SQLSRV. Instale y habilite la extension compatible con PHP de XAMPP.'
    );
}

$server = getenv('DB_SERVER') ?: 'SANTIDELL';
$database = getenv('DB_NAME') ?: 'plataforma_mudanzas';
$user = getenv('DB_USER');
$password = getenv('DB_PASSWORD');

if (($user === false) !== ($password === false)) {
    throw new RuntimeException('Configure DB_USER y DB_PASSWORD juntos, o deje ambos sin definir para usar autenticacion de Windows.');
}

$dsn = sprintf(
    'sqlsrv:Server=%s;Database=%s;TrustServerCertificate=1',
    $server,
    $database
);

$pdo = new PDO($dsn, $user === false ? null : $user, $password === false ? null : $password, [
    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
]);

return $pdo;
