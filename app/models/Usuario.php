<?php
/**
 * Archivo: Usuario.php
 * Modulo: BD - Usuario
 * Responsable: Kendall Figueroa
 * Descripcion: Acceso a datos de la tabla usuarios: registro de cuentas, busqueda
 *              para el inicio de sesion y las consultas de la pagina "Mi cuenta".
 */

class Usuario
{
    private PDO $pdo;

    public function __construct(PDO $pdo)
    {
        $this->pdo = $pdo;
    }

    // Busca por correo los datos necesarios para validar el inicio de sesion (incluye el rol); null si no existe
    public function buscarParaLogin(string $correo): ?array
    {
        $stmt = $this->pdo->prepare(
            "SELECT u.id_usuario, u.nombre, u.password_hash, u.activo, r.nombre AS rol
             FROM usuarios u
             INNER JOIN roles r ON r.id_rol = u.id_rol
             WHERE u.correo = :correo"
        );
        $stmt->execute([':correo' => $correo]);
        $fila = $stmt->fetch(PDO::FETCH_ASSOC);

        return $fila ?: null;
    }

    // Busca un usuario por su correo (sin contrasena); null si no existe
    public function buscarPorCorreo(string $correo): ?array
    {
        $stmt = $this->pdo->prepare(
            "SELECT id_usuario, nombre, correo, activo
             FROM usuarios
             WHERE correo = :correo"
        );
        $stmt->execute([':correo' => $correo]);
        $fila = $stmt->fetch(PDO::FETCH_ASSOC);

        return $fila ?: null;
    }

    /**
     * Crea una cuenta nueva con sp_registrar_usuario. Si el tipo de cuenta es
     * "empresa", crea tambien el registro base en la tabla empresas con estado
     * "pendiente" hasta que el administrador la apruebe (RF-21). Todo ocurre en
     * una sola transaccion. Retorna el id del usuario creado; si algo falla
     * deshace los cambios y vuelve a lanzar la excepcion para que el controlador
     * la traduzca a un mensaje.
     */
    public function registrarCuenta(int $idRol, array $datos, string $hash): int
    {
        try {
            $this->pdo->beginTransaction();

            // Llama al procedimiento almacenado creado por el modulo de base de datos
            $stmt = $this->pdo->prepare(
                "EXEC sp_registrar_usuario
                    @id_rol = :id_rol,
                    @nombre = :nombre,
                    @correo = :correo,
                    @password_hash = :password_hash,
                    @telefono = :telefono"
            );
            $stmt->execute([
                ':id_rol'        => $idRol,
                ':nombre'        => $datos['nombre'],
                ':correo'        => $datos['correo'],
                ':password_hash' => $hash,
                ':telefono'      => ($datos['telefono'] ?? '') !== '' ? $datos['telefono'] : null,
            ]);
            $fila = $stmt->fetch(PDO::FETCH_ASSOC);
            $idUsuario = (int) $fila['id_usuario'];

            if ($datos['tipo_cuenta'] === 'empresa') {
                // Estado "pendiente": requiere aprobacion del administrador (RF-21)
                $stmtEmpresa = $this->pdo->prepare(
                    "INSERT INTO empresas (id_usuario, nombre_comercial, identificacion, estado)
                     VALUES (:id_usuario, :nombre_comercial, :identificacion, 'pendiente')"
                );
                $stmtEmpresa->execute([
                    ':id_usuario'       => $idUsuario,
                    ':nombre_comercial' => $datos['nombre_comercial'],
                    ':identificacion'   => $datos['identificacion'],
                ]);
            }

            $this->pdo->commit();

            return $idUsuario;
        } catch (PDOException $e) {
            if ($this->pdo->inTransaction()) {
                $this->pdo->rollBack();
            }
            throw $e;
        }
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
