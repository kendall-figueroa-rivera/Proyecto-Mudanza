<?php
/**
 * Archivo: AuthController.php
 * Modulo: Autenticacion
 * Responsable: Kendall Figueroa
 * Descripcion: Controla login, registro, el registro de auditoria de accesos
 *              y la pagina "Mi cuenta" (editar datos y cambiar contrasena).
 *              Tambien controla la recuperacion de contrasena por correo.
 *              Las consultas a la base de datos viven en los modelos Usuario,
 *              Rol, LoginAuditoria y TokenRecuperacion (app/models).
 */

require_once __DIR__ . '/../models/Usuario.php';
require_once __DIR__ . '/../models/Rol.php';
require_once __DIR__ . '/../models/LoginAuditoria.php';
require_once __DIR__ . '/../models/TokenRecuperacion.php';

// Escapa texto para imprimirlo de forma segura dentro del HTML
if (!function_exists('h')) {
    function h($valor): string
    {
        return htmlspecialchars((string) ($valor ?? ''), ENT_QUOTES, 'UTF-8');
    }
}

class AuthController
{
    // Minutos que dura un enlace de recuperacion y maximo de enlaces que se pueden pedir por hora
    private const MINUTOS_VIGENCIA_ENLACE  = 30;
    private const MAX_SOLICITUDES_POR_HORA = 3;

    private PDO $pdo;
    private Usuario $usuarios;
    private Rol $roles;
    private LoginAuditoria $accesos;
    private TokenRecuperacion $tokens;

    public function __construct(PDO $pdo)
    {
        $this->pdo      = $pdo;
        $this->usuarios = new Usuario($pdo);
        $this->roles    = new Rol($pdo);
        $this->accesos  = new LoginAuditoria($pdo);
        $this->tokens   = new TokenRecuperacion($pdo);
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

    // --- Recuperacion de contrasena ---

    /**
     * Genera un enlace de recuperacion y lo envia por correo. Siempre responde lo
     * mismo exista o no el correo, para no revelar que correos estan registrados.
     * Retorna ['exito' => bool, 'errores' => [...], 'enlace_local' => ?string]; el
     * enlace_local solo viene con valor si el correo no se pudo enviar Y la peticion
     * viene de este mismo equipo (127.0.0.1), para poder probar sin servidor de correo.
     */
    public function solicitarRecuperacion(string $correo): array
    {
        $correo = trim($correo);
        if (!filter_var($correo, FILTER_VALIDATE_EMAIL)) {
            return ['exito' => false, 'errores' => ['Ingresá un correo con formato válido.'], 'enlace_local' => null];
        }

        $enlaceLocal = null;

        try {
            $usuario = $this->usuarios->buscarPorCorreo($correo);

            if ($usuario && (bool) $usuario['activo']) {
                $idUsuario = (int) $usuario['id_usuario'];
                $ahora     = time();

                // Limite por hora, para que no se use para inundar de correos a una persona
                $recientes = $this->tokens->contarCreadosDesde($idUsuario, self::fechaIso($ahora - 3600));

                if ($recientes < self::MAX_SOLICITUDES_POR_HORA) {
                    $token = bin2hex(random_bytes(32));

                    // Un enlace nuevo deja sin efecto los anteriores; solo se guarda el hash del token
                    $this->tokens->invalidarPendientes($idUsuario);
                    $this->tokens->crear(
                        $idUsuario,
                        TokenRecuperacion::hashear($token),
                        self::fechaIso($ahora),
                        self::fechaIso($ahora + self::MINUTOS_VIGENCIA_ENLACE * 60)
                    );

                    $enlace = self::urlBase() . '/app/views/auth/restablecer.php?token=' . $token;

                    if (!$this->enviarCorreoRecuperacion($usuario, $enlace)) {
                        error_log('[MudaConecta] No se pudo enviar el correo de recuperacion a ' . $correo
                            . '. Enlace: ' . $enlace);
                        if (self::esPeticionLocal()) {
                            $enlaceLocal = $enlace;
                        }
                    }
                }
            }
        } catch (Throwable $e) {
            // Un error interno no debe delatar si el correo existe: se registra y se responde igual
            error_log('[MudaConecta] Error al solicitar la recuperacion de contrasena: ' . $e->getMessage());
        }

        return ['exito' => true, 'errores' => [], 'enlace_local' => $enlaceLocal];
    }

    // Retorna los datos del usuario dueno del token si el enlace es valido (existe, sin usar, vigente y cuenta activa); si no, null
    public function validarTokenRecuperacion(string $token): ?array
    {
        if (!preg_match('/^[0-9a-f]{64}$/', $token)) {
            return null;
        }

        try {
            $fila = $this->tokens->buscarSinUsar(TokenRecuperacion::hashear($token));
        } catch (PDOException $e) {
            return null;
        }

        if (!$fila || !(bool) $fila['activo'] || self::aTimestamp($fila['fecha_expiracion']) < time()) {
            return null;
        }

        return $fila;
    }

    // Cambia la contrasena con un enlace valido; el enlace sirve una sola vez
    public function restablecerContrasena(string $token, string $nueva, string $confirmar): array
    {
        $errores = [];
        if (strlen($nueva) < 8) {
            $errores[] = 'La contraseña debe tener al menos 8 caracteres.';
        }
        if ($nueva !== $confirmar) {
            $errores[] = 'Las contraseñas no coinciden.';
        }
        if (!empty($errores)) {
            return ['exito' => false, 'errores' => $errores, 'enlace_invalido' => false];
        }

        $fila = $this->validarTokenRecuperacion($token);
        if (!$fila) {
            return ['exito' => false, 'errores' => ['El enlace no es válido o ya venció. Pedí uno nuevo.'], 'enlace_invalido' => true];
        }

        try {
            $this->pdo->beginTransaction();

            // Si otra peticion ya uso este enlace, no se cambia nada
            if (!$this->tokens->marcarUsado((int) $fila['id_token'])) {
                $this->pdo->rollBack();

                return ['exito' => false, 'errores' => ['El enlace no es válido o ya venció. Pedí uno nuevo.'], 'enlace_invalido' => true];
            }

            $this->usuarios->actualizarContrasena((int) $fila['id_usuario'], password_hash($nueva, PASSWORD_DEFAULT));
            $this->tokens->invalidarPendientes((int) $fila['id_usuario']);

            $this->pdo->commit();
        } catch (PDOException $e) {
            if ($this->pdo->inTransaction()) {
                $this->pdo->rollBack();
            }

            return ['exito' => false, 'errores' => ['Ocurrió un error al cambiar la contraseña. Intentá de nuevo.'], 'enlace_invalido' => false];
        }

        // Aviso de seguridad: si no fue la persona, sabe que debe actuar (no es critico si falla)
        $this->enviarCorreo(
            $fila['correo'],
            'Tu contraseña de MudaConecta fue cambiada',
            '<p>Hola, ' . h($fila['nombre']) . ':</p>'
            . '<p>La contraseña de tu cuenta de MudaConecta se acaba de cambiar. '
            . 'Si no fuiste vos, pedí un enlace nuevo para recuperarla y revisá tu correo.</p>'
        );

        return ['exito' => true, 'errores' => [], 'enlace_invalido' => false];
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

    // Envia el correo con el enlace para restablecer la contrasena; retorna false si no se pudo enviar
    private function enviarCorreoRecuperacion(array $usuario, string $enlace): bool
    {
        $html = '<p>Hola, ' . h($usuario['nombre']) . ':</p>'
            . '<p>Recibimos una solicitud para restablecer la contraseña de tu cuenta de MudaConecta. '
            . 'Hacé clic en el siguiente enlace para crear una nueva. El enlace vence en '
            . self::MINUTOS_VIGENCIA_ENLACE . ' minutos y solo se puede usar una vez:</p>'
            . '<p><a href="' . h($enlace) . '">Restablecer mi contraseña</a></p>'
            . '<p>Si no fuiste vos, ignorá este mensaje: tu contraseña no cambia.</p>';

        return $this->enviarCorreo($usuario['correo'], 'Recuperá tu contraseña de MudaConecta', $html);
    }

    // Envia un correo con el servicio Mailer (app/services/Mailer.php); false si no existe o falla
    private function enviarCorreo(string $para, string $asunto, string $html): bool
    {
        $ruta = __DIR__ . '/../services/Mailer.php';
        if (!class_exists('Mailer', false) && is_file($ruta)) {
            require_once $ruta;
        }
        if (!class_exists('Mailer', false)) {
            return false;
        }

        try {
            return (bool) Mailer::enviar($para, $asunto, $html);
        } catch (Throwable $e) {
            error_log('[MudaConecta] Error del servicio de correo: ' . $e->getMessage());

            return false;
        }
    }

    // Direccion base del proyecto, tomada de config/app.php (nunca de la peticion, para que no se pueda falsificar)
    private static function urlBase(): string
    {
        $config = is_file(__DIR__ . '/../../config/app.php') ? include __DIR__ . '/../../config/app.php' : [];
        $base   = is_array($config) ? (string) ($config['base_url'] ?? '') : '';

        return rtrim($base !== '' ? $base : 'http://localhost/Proyecto-Mudanza', '/');
    }

    // Indica si la peticion viene de este mismo equipo
    private static function esPeticionLocal(): bool
    {
        return in_array($_SERVER['REMOTE_ADDR'] ?? '', ['127.0.0.1', '::1'], true);
    }

    // Fecha en formato ISO 8601 (AAAA-MM-DDTHH:MM:SS), el que SQL Server entiende en cualquier idioma
    private static function fechaIso(int $marcaDeTiempo): string
    {
        return date('Y-m-d\TH:i:s', $marcaDeTiempo);
    }

    // Convierte una fecha leida de la base (texto u objeto DateTime) a marca de tiempo
    private static function aTimestamp($fecha): int
    {
        if ($fecha instanceof DateTimeInterface) {
            return $fecha->getTimestamp();
        }

        return (int) strtotime((string) $fecha);
    }
}
