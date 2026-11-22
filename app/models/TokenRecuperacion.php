<?php
/**
 * Archivo: TokenRecuperacion.php
 * Modulo: BD - Recuperacion de contrasena
 * Responsable: Kendall Figueroa
 * Descripcion: Acceso a datos de la tabla token_recuperacion. Del token que se
 *              envia por correo solo se guarda su hash SHA-256, asi que quien
 *              pudiera leer la tabla no podria usar los enlaces pendientes.
 *              Las fechas se reciben como texto ISO 8601 (AAAA-MM-DDTHH:MM:SS),
 *              que SQL Server interpreta igual sin importar el idioma del servidor.
 */

class TokenRecuperacion
{
    private PDO $pdo;

    public function __construct(PDO $pdo)
    {
        $this->pdo = $pdo;
    }

    // Retorna el hash SHA-256 (64 caracteres) con el que se guarda y se busca un token
    public static function hashear(string $token): string
    {
        return hash('sha256', $token);
    }

    // Guarda un token nuevo (ya hasheado) con sus fechas de creacion y de vencimiento
    public function crear(int $idUsuario, string $tokenHash, string $creacion, string $vencimiento): void
    {
        $stmt = $this->pdo->prepare(
            "INSERT INTO token_recuperacion (id_usuario, token, fecha_creacion, fecha_expiracion, usado)
             VALUES (:id_usuario, :token, :creacion, :vencimiento, 0)"
        );
        $stmt->execute([
            ':id_usuario' => $idUsuario,
            ':token'      => $tokenHash,
            ':creacion'   => $creacion,
            ':vencimiento' => $vencimiento,
        ]);
    }

    // Marca como usados todos los tokens pendientes de un usuario (queda valido solo el mas reciente)
    public function invalidarPendientes(int $idUsuario): void
    {
        $stmt = $this->pdo->prepare(
            "UPDATE token_recuperacion SET usado = 1
             WHERE id_usuario = :id_usuario AND usado = 0"
        );
        $stmt->execute([':id_usuario' => $idUsuario]);
    }

    // Cuenta cuantos tokens se crearon para un usuario desde una fecha (limite de solicitudes por hora)
    public function contarCreadosDesde(int $idUsuario, string $desde): int
    {
        $stmt = $this->pdo->prepare(
            "SELECT COUNT(*) FROM token_recuperacion
             WHERE id_usuario = :id_usuario AND fecha_creacion >= :desde"
        );
        $stmt->execute([':id_usuario' => $idUsuario, ':desde' => $desde]);

        return (int) $stmt->fetchColumn();
    }

    // Busca un token sin usar por su hash, con los datos de su usuario; null si no existe
    public function buscarSinUsar(string $tokenHash): ?array
    {
        $stmt = $this->pdo->prepare(
            "SELECT t.id_token, t.id_usuario, t.fecha_expiracion, t.usado,
                    u.nombre, u.correo, u.activo
             FROM token_recuperacion t
             INNER JOIN usuarios u ON u.id_usuario = t.id_usuario
             WHERE t.token = :token AND t.usado = 0"
        );
        $stmt->execute([':token' => $tokenHash]);
        $fila = $stmt->fetch(PDO::FETCH_ASSOC);

        return $fila ?: null;
    }

    // Marca un token como usado; retorna false si otra peticion ya lo habia usado (un solo uso)
    public function marcarUsado(int $idToken): bool
    {
        $stmt = $this->pdo->prepare(
            "UPDATE token_recuperacion SET usado = 1
             WHERE id_token = :id_token AND usado = 0"
        );
        $stmt->execute([':id_token' => $idToken]);

        return $stmt->rowCount() === 1;
    }
}
