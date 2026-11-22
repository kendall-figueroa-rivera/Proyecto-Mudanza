<?php
/**
 * Archivo: AuthController.php
 * Modulo: Autenticacion
 * Responsable: Kendall Figueroa
 * Descripcion: Controla login, registro, el registro de auditoria de accesos
 *              y la pagina "Mi cuenta" (editar datos y cambiar contrasena).
 *              Las consultas a la base de datos viven en los modelos Usuario,
 *              Rol y LoginAuditoria (app/models).
 */

require_once __DIR__ . '/../models/Usuario.php';
require_once __DIR__ . '/../models/Rol.php';
require_once __DIR__ . '/../models/LoginAuditoria.php';

// Escapa texto para imprimirlo de forma segura dentro del HTML
if (!function_exists('h')) {
    function h($valor): string
    {
        return htmlspecialchars((string) ($valor ?? ''), ENT_QUOTES, 'UTF-8');
    }
}

class AuthController
{
    private PDO $pdo;
    private Usuario $usuarios;
    private Rol $roles;
    private LoginAuditoria $accesos;

    public function __construct(PDO $pdo)
    {
        $this->pdo      = $pdo;
        $this->usuarios = new Usuario($pdo);
        $this->roles    = new Rol($pdo);
        $this->accesos  = new LoginAuditoria($pdo);
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
            $idRol = $this->roles->obtenerIdPorNombre($datos['tipo_cuenta']);
            $hash  = password_hash($datos['password'], PASSWORD_DEFAULT);

            // El modelo crea el usuario (sp_registrar_usuario) y, si es empresa, su registro pendiente
            $idUsuario = $this->usuarios->registrarCuenta($idRol, $datos, $hash);

            return ['exito' => true, 'id_usuario' => $idUsuario];

        } catch (PDOException $e) {
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

        $usuario = $this->usuarios->buscarParaLogin($correo);

        $exitoso = $usuario
            && (bool) $usuario['activo']
            && password_verify($password, $usuario['password_hash']);

        $this->accesos->registrar($usuario['id_usuario'] ?? null, $correo, $exitoso, $ip);

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

    // --- Sesion, seguridad y avisos (misma convencion que los demas controladores) ---

    // Redirige al login si no hay sesion iniciada; retorna el id del usuario
    public static function exigirSesion(): int
    {
        if (!isset($_SESSION['id_usuario'])) {
            header('Location: login.php');
            exit;
        }

        return (int) $_SESSION['id_usuario'];
    }

    // Genera (una vez por sesion) el token que protege los formularios POST
    public static function tokenCsrf(): string
    {
        if (empty($_SESSION['csrf'])) {
            $_SESSION['csrf'] = bin2hex(random_bytes(32));
        }

        return $_SESSION['csrf'];
    }

    // Compara el token recibido con el de la sesion; retorna true si coincide
    public static function validarCsrf(?string $token): bool
    {
        return is_string($token) && hash_equals($_SESSION['csrf'] ?? '', $token);
    }

    // Guarda un mensaje para mostrarlo despues de una redireccion
    public static function guardarAviso(string $tipo, string $texto): void
    {
        $_SESSION['aviso'] = ['tipo' => $tipo, 'texto' => $texto];
    }

    // Retorna y borra el mensaje pendiente de mostrar (o null si no hay)
    public static function tomarAviso(): ?array
    {
        $aviso = $_SESSION['aviso'] ?? null;
        unset($_SESSION['aviso']);

        return $aviso;
    }

    // --- Mi cuenta ---

    // Retorna los datos del usuario con sesion iniciada (o null si no existe)
    public function obtenerUsuario(int $idUsuario): ?array
    {
        return $this->usuarios->buscarPorId($idUsuario);
    }

    // Valida y guarda el nombre y el telefono; actualiza el nombre guardado en la sesion
    public function actualizarPerfil(int $idUsuario, array $datos): array
    {
        $nombre   = trim($datos['nombre'] ?? '');
        $telefono = trim($datos['telefono'] ?? '');
        $errores  = [];

        if ($nombre === '' || mb_strlen($nombre) > 100) {
            $errores[] = 'Escribe tu nombre (máximo 100 caracteres).';
        }
        if ($telefono !== '' && !preg_match('/^[0-9+\-\s]{8,20}$/', $telefono)) {
            $errores[] = 'El teléfono solo puede tener números, espacios, + y guiones (8 a 20 caracteres).';
        }
        if (!empty($errores)) {
            return ['exito' => false, 'errores' => $errores];
        }

        try {
            $this->usuarios->actualizarPerfil(
                $idUsuario,
                $nombre,
                $telefono !== '' ? $telefono : null
            );
            $_SESSION['nombre'] = $nombre;

            return ['exito' => true];
        } catch (PDOException $e) {
            return ['exito' => false, 'errores' => ['Ocurrió un error al guardar tus datos. Intenta de nuevo.']];
        }
    }

    // Cambia la contrasena: exige la actual, una nueva de 8+ caracteres y su confirmacion
    public function cambiarContrasena(int $idUsuario, array $datos): array
    {
        $actual    = (string) ($datos['password_actual'] ?? '');
        $nueva     = (string) ($datos['password_nueva'] ?? '');
        $confirmar = (string) ($datos['password_confirmar'] ?? '');
        $errores   = [];

        if ($actual === '') {
            $errores[] = 'Escribe tu contraseña actual.';
        }
        if (strlen($nueva) < 8) {
            $errores[] = 'La contraseña nueva debe tener al menos 8 caracteres.';
        }
        if ($nueva !== $confirmar) {
            $errores[] = 'La confirmación no coincide con la contraseña nueva.';
        }
        if ($actual !== '' && $nueva === $actual) {
            $errores[] = 'La contraseña nueva debe ser distinta de la actual.';
        }
        if (!empty($errores)) {
            return ['exito' => false, 'errores' => $errores];
        }

        try {
            $hash = $this->usuarios->obtenerHash($idUsuario);

            if ($hash === null || !password_verify($actual, $hash)) {
                return ['exito' => false, 'errores' => ['La contraseña actual no es correcta.']];
            }

            $this->usuarios->actualizarContrasena($idUsuario, password_hash($nueva, PASSWORD_DEFAULT));
            session_regenerate_id(true);

            return ['exito' => true];
        } catch (PDOException $e) {
            return ['exito' => false, 'errores' => ['Ocurrió un error al cambiar la contraseña. Intenta de nuevo.']];
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

    // Traduce los errores de SQL Server a mensajes claros. El numero del error (por ejemplo el 50002
    // de un THROW) viene en errorInfo[1]; el texto del mensaje NO lo incluye.
    private function mensajeAmigable(PDOException $e): string
    {
        $codigo = (int) ($e->errorInfo[1] ?? 0);
        $msg    = $e->getMessage();

        // THROW definidos en sp_registrar_usuario (con respaldo por el texto, por si el driver no llena errorInfo)
        if ($codigo === 50002 || str_contains($msg, 'El correo ya está registrado')) {
            return 'El correo ya se encuentra registrado.';
        }
        if ($codigo === 50001 || str_contains($msg, 'El rol indicado no existe')) {
            return 'El rol indicado no existe.';
        }

        // Llave unica repetida (2627 o 2601): la cedula juridica ya existe, o dos personas se registraron a la vez
        if ($codigo === 2627 || $codigo === 2601) {
            if (str_contains($msg, 'empresas')) {
                return 'La identificación (cédula jurídica) ya está registrada.';
            }

            return 'El correo ya se encuentra registrado.';
        }

        return 'Ocurrió un error al procesar la solicitud. Intenta de nuevo.';
    }
}