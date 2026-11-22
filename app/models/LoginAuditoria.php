<?php
/**
 * Archivo: LoginAuditoria.php
 * Modulo: BD - Auditoria de accesos
 * Responsable: Kendall Figueroa
 * Descripcion: Acceso a datos de la tabla login_auditoria, donde se guarda cada
 *              intento de inicio de sesion (exitoso o fallido).
 */

class LoginAuditoria
{
    private PDO $pdo;

    public function __construct(PDO $pdo)
    {
        $this->pdo = $pdo;
    }

    // Registra un intento de acceso; el id de usuario es null si el correo no existe
    public function registrar(?int $idUsuario, string $correo, bool $exitoso, ?string $ip): void
    {
        $stmt = $this->pdo->prepare(
            "INSERT INTO login_auditoria (id_usuario, correo_intentado, exitoso, ip_origen)
             VALUES (:id_usuario, :correo, :exitoso, :ip)"
        );
        $stmt->execute([
            ':id_usuario' => $idUsuario,
            ':correo'     => $correo,
            ':exitoso'    => $exitoso ? 1 : 0,
            ':ip'         => $ip,
        ]);
    }
}
