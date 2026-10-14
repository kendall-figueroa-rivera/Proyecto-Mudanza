<?php
/**
 * Archivo: AuthController.php
 * Modulo: Autenticacion
 * Responsable: Kendall Figueroa
 * Descripcion: Controla login, registro y el registro de auditoria de accesos.
 *              La recuperacion/cambio de contrasena se implementa en un
 *              incremento posterior (vistas recuperar.php y cambiar_contrasena.php
 *              ya existen como placeholder en el repositorio).
 */

class AuthController
{
    private PDO $pdo;

    public function __construct(PDO $pdo)
    {
        $this->pdo = $pdo;
    }

    /**
     * Registra un usuario nuevo (cliente o empresa) usando sp_registrar_usuario.
     * Si el tipo de cuenta es "empresa", crea tambien el registro base en la
     * tabla empresas, con estado "pendiente" hasta que el administrador la
     * apruebe (el resto del perfil, como zonas de cobertura y flota, se
     * completa despues desde el modulo de empresa).
     */
    public function registrar(array $datos): array
    {
        $errores = $this->validarRegistro($datos);
        if (!empty($errores)) {
            return ['exito' => false, 'errores' => $errores];
        }

        try {
            $idRol = $this->obtenerIdRol($datos['tipo_cuenta']);
            $hash = password_hash($datos['password'], PASSWORD_DEFAULT);

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
                ':telefono'      => $datos['telefono'] !== '' ? $datos['telefono'] : null,
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
            return ['exito' => true, 'id_usuario' => $idUsuario];

        } catch (PDOException $e) {
            if ($this->pdo->inTransaction()) {
                $this->pdo->rollBack();
            }
            return ['exito' => false, 'errores' => [$this->mensajeAmigable($e)]];
        }
    }

    /**
     * Valida credenciales, registra el intento (exitoso o fallido) en
     * login_auditoria, y si todo esta correcto abre la sesion.
     */
    public function iniciarSesion(string $correo, string $password): array
    {
        $ip = $_SERVER['REMOTE_ADDR'] ?? null;

        $stmt = $this->pdo->prepare(
            "SELECT u.id_usuario, u.nombre, u.password_hash, u.activo, r.nombre AS rol
             FROM usuarios u
             INNER JOIN roles r ON r.id_rol = u.id_rol
             WHERE u.correo = :correo"
        );
        $stmt->execute([':correo' => $correo]);
        $usuario = $stmt->fetch(PDO::FETCH_ASSOC);

        $exitoso = $usuario
            && (bool) $usuario['activo']
            && password_verify($password, $usuario['password_hash']);

        $this->registrarAuditoria($usuario['id_usuario'] ?? null, $correo, $exitoso, $ip);

        if (!$exitoso) {
            return ['exito' => false, 'errores' => ['Correo o contraseña incorrectos.']];
        }

        session_regenerate_id(true);
        $_SESSION['id_usuario'] = (int) $usuario['id_usuario'];
        $_SESSION['nombre']     = $usuario['nombre'];
        $_SESSION['rol']        = $usuario['rol'];

        return ['exito' => true, 'rol' => $usuario['rol']];
    }

    public function cerrarSesion(): void
    {
        $_SESSION = [];
        if (session_status() === PHP_SESSION_ACTIVE) {
            session_destroy();
        }
    }

    // --- Metodos de apoyo (privados) ---

    private function validarRegistro(array $d): array
    {
        $errores = [];

        if (empty(trim($d['nombre'] ?? ''))) {
            $errores[] = 'El nombre es obligatorio.';
        }
        if (empty($d['correo']) || !filter_var($d['correo'], FILTER_VALIDATE_EMAIL)) {
            $errores[] = 'El correo no tiene un formato válido.';
        }
        if (empty($d['password']) || strlen($d['password']) < 8) {
            $errores[] = 'La contraseña debe tener al menos 8 caracteres.';
        }
        if (($d['password'] ?? '') !== ($d['password_confirm'] ?? '')) {
            $errores[] = 'Las contraseñas no coinciden.';
        }
        if (!in_array($d['tipo_cuenta'] ?? '', ['cliente', 'empresa'], true)) {
            $errores[] = 'Debe seleccionar un tipo de cuenta válido.';
        }
        if (($d['tipo_cuenta'] ?? '') === 'empresa') {
            if (empty(trim($d['nombre_comercial'] ?? ''))) {
                $errores[] = 'El nombre comercial es obligatorio para empresas.';
            }
            if (empty(trim($d['identificacion'] ?? ''))) {
                $errores[] = 'La identificación (cédula jurídica) es obligatoria para empresas.';
            }
        }

        return $errores;
    }

    private function obtenerIdRol(string $nombreRol): int
    {
        $stmt = $this->pdo->prepare("SELECT id_rol FROM roles WHERE nombre = :nombre");
        $stmt->execute([':nombre' => $nombreRol]);
        $fila = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$fila) {
            throw new RuntimeException("El rol '$nombreRol' no existe en la tabla roles.");
        }

        return (int) $fila['id_rol'];
    }

    private function registrarAuditoria(?int $idUsuario, string $correo, bool $exitoso, ?string $ip): void
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

    private function mensajeAmigable(PDOException $e): string
    {
        $msg = $e->getMessage();

        // Traduce los codigos THROW definidos en sp_registrar_usuario
        if (str_contains($msg, '50002')) {
            return 'El correo ya se encuentra registrado.';
        }
        if (str_contains($msg, '50001')) {
            return 'El rol indicado no existe.';
        }

        return 'Ocurrió un error al procesar la solicitud. Intenta de nuevo.';
    }
}