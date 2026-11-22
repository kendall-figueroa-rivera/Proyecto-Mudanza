<?php
/**
 * Archivo: Usuario.php
 * Modulo: BD - Usuario
 * Responsable: Kendall Figueroa
 * Descripcion: Acceso a datos de la tabla usuarios. Por ahora contiene las
 *              consultas de la pagina "Mi cuenta"; las consultas de login y
 *              registro se pasan aqui desde AuthController en la siguiente
 *              etapa.
 */

class Usuario
{
    private PDO $pdo;

    public function __construct(PDO $pdo)
    {
        $this->pdo = $pdo;
    }

    // Busca un usuario por su id junto con el nombre de su rol; retorna null si no existe
    public function buscarPorId(int $idUsuario): ?array
    {
        $stmt = $this->pdo->prepare(
            "SELECT u.id_usuario, u.nombre, u.correo, u.telefono, u.fecha_registro,
                    r.nombre AS rol
             FROM usuarios u
             INNER JOIN roles r ON r.id_rol = u.id_rol
             WHERE u.id_usuario = :id"
        );
        $stmt->execute([':id' => $idUsuario]);
        $fila = $stmt->fetch(PDO::FETCH_ASSOC);

        return $fila ?: null;
    }

    // Retorna el hash de la contrasena de un usuario activo, o null si no existe o esta inactivo
    public function obtenerHash(int $idUsuario): ?string
    {
        $stmt = $this->pdo->prepare(
            "SELECT password_hash FROM usuarios
             WHERE id_usuario = :id AND activo = 1"
        );
        $stmt->execute([':id' => $idUsuario]);
        $hash = $stmt->fetchColumn();

        return $hash === false ? null : (string) $hash;
    }

    // Actualiza el nombre y el telefono (null si se deja vacio) de un usuario
    public function actualizarPerfil(int $idUsuario, string $nombre, ?string $telefono): void
    {
        $stmt = $this->pdo->prepare(
            "UPDATE usuarios
             SET nombre = :nombre, telefono = :telefono
             WHERE id_usuario = :id"
        );
        $stmt->execute([
            ':nombre'   => $nombre,
            ':telefono' => $telefono,
            ':id'       => $idUsuario,
        ]);
    }

    // Guarda un nuevo hash de contrasena para el usuario
    public function actualizarContrasena(int $idUsuario, string $hash): void
    {
        $stmt = $this->pdo->prepare(
            "UPDATE usuarios SET password_hash = :hash WHERE id_usuario = :id"
        );
        $stmt->execute([':hash' => $hash, ':id' => $idUsuario]);
    }
}
