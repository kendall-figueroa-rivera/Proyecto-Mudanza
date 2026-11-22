<?php
/**
 * Archivo: Rol.php
 * Modulo: BD - Rol
 * Responsable: Kendall Figueroa
 * Descripcion: Acceso a datos de la tabla roles.
 */

class Rol
{
    private PDO $pdo;

    public function __construct(PDO $pdo)
    {
        $this->pdo = $pdo;
    }

    // Retorna el id de un rol a partir de su nombre; lanza RuntimeException si el rol no existe
    public function obtenerIdPorNombre(string $nombreRol): int
    {
        $stmt = $this->pdo->prepare("SELECT id_rol FROM roles WHERE nombre = :nombre");
        $stmt->execute([':nombre' => $nombreRol]);
        $fila = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$fila) {
            throw new RuntimeException("El rol '$nombreRol' no existe en la tabla roles.");
        }

        return (int) $fila['id_rol'];
    }
}
