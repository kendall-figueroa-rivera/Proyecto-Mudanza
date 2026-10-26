<?php
/**
 * Archivo: EmpresaController.php
 * Modulo: Empresa
 * Responsable: [nombre del integrante]
 * Descripcion: Controla el perfil de la empresa (consulta y edicion) y el CRUD
 *              de la flota de vehiculos (agregar, editar y eliminar). La
 *              eliminacion de vehiculos es logica: se marca activo = 0.
 */

// Escapa texto para imprimirlo de forma segura dentro del HTML
if (!function_exists('h')) {
    function h($valor): string
    {
        return htmlspecialchars((string) ($valor ?? ''), ENT_QUOTES, 'UTF-8');
    }
}

class EmpresaController
{
    // Nombre visible de cada estado de vehiculo
    public const ESTADOS_VEHICULO = [
        'disponible'    => 'Disponible',
        'en_servicio'   => 'En servicio',
        'mantenimiento' => 'En mantenimiento',
    ];

    private PDO $pdo;

    public function __construct(PDO $pdo)
    {
        $this->pdo = $pdo;
    }

    /* =========================================================
     *  Sesion, seguridad y mensajes
     * ========================================================= */

    // Redirige al login si no hay una sesion de empresa activa; retorna el id del usuario
    public static function exigirEmpresa(): int
    {
        if (!isset($_SESSION['id_usuario']) || ($_SESSION['rol'] ?? '') !== 'empresa') {
            header('Location: ../auth/login.php');
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

    /* =========================================================
     *  Perfil de la empresa
     * ========================================================= */

    // Retorna la empresa del usuario con sesion iniciada (o null si no tiene)
    public function obtenerEmpresaPorUsuario(int $idUsuario): ?array
    {
        $stmt = $this->pdo->prepare(
            "SELECT id_empresa, nombre_comercial, identificacion, telefono,
                    direccion, descripcion, estado, fecha_registro
             FROM empresas
             WHERE id_usuario = :usuario"
        );
        $stmt->execute([':usuario' => $idUsuario]);
        $fila = $stmt->fetch(PDO::FETCH_ASSOC);
        return $fila ?: null;
    }

    // Valida los datos del formulario de perfil; retorna la lista de errores
    private function validarPerfil(array $d): array
    {
        $errores   = [];
        $nombre    = trim($d['nombre_comercial'] ?? '');
        $telefono  = trim($d['telefono'] ?? '');
        $direccion = trim($d['direccion'] ?? '');

        if ($nombre === '' || mb_strlen($nombre) > 150) {
            $errores[] = 'Escribe el nombre comercial (máximo 150 caracteres).';
        }
        if ($telefono !== '' && !preg_match('/^[0-9+\-\s]{8,20}$/', $telefono)) {
            $errores[] = 'El teléfono solo puede tener números, espacios, + y guiones (8 a 20 caracteres).';
        }
        if (mb_strlen($direccion) > 255) {
            $errores[] = 'La dirección no puede pasar de 255 caracteres.';
        }
        if (mb_strlen(trim($d['descripcion'] ?? '')) > 500) {
            $errores[] = 'La descripción no puede pasar de 500 caracteres.';
        }

        return $errores;
    }

    // Actualiza el perfil de la empresa del usuario con los datos del formulario
    public function actualizarPerfil(int $idUsuario, array $datos): array
    {
        $errores = $this->validarPerfil($datos);
        if (!empty($errores)) {
            return ['exito' => false, 'errores' => $errores];
        }

        $telefono    = trim($datos['telefono'] ?? '');
        $direccion   = trim($datos['direccion'] ?? '');
        $descripcion = trim($datos['descripcion'] ?? '');

        try {
            $stmt = $this->pdo->prepare(
                "UPDATE empresas
                 SET nombre_comercial = :nombre, telefono = :telefono,
                     direccion = :direccion, descripcion = :descripcion
                 WHERE id_usuario = :usuario"
            );
            $stmt->execute([
                ':nombre'      => trim($datos['nombre_comercial']),
                ':telefono'    => $telefono !== '' ? $telefono : null,
                ':direccion'   => $direccion !== '' ? $direccion : null,
                ':descripcion' => $descripcion !== '' ? $descripcion : null,
                ':usuario'     => $idUsuario,
            ]);
            return ['exito' => true];
        } catch (PDOException $e) {
            return ['exito' => false, 'errores' => [$this->mensajeAmigable($e)]];
        }
    }

    /* =========================================================
     *  Flota de vehiculos
     * ========================================================= */

    // Retorna los vehiculos activos de la empresa ordenados por placa
    public function listarFlota(int $idEmpresa): array
    {
        $stmt = $this->pdo->prepare(
            "SELECT id_vehiculo, placa, tipo_vehiculo, capacidad_kg, estado
             FROM flota
             WHERE id_empresa = :empresa AND activo = 1
             ORDER BY placa"
        );
        $stmt->execute([':empresa' => $idEmpresa]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    // Retorna un vehiculo activo de la empresa (o null si no existe o no es suyo)
    public function obtenerVehiculo(int $idEmpresa, int $idVehiculo): ?array
    {
        $stmt = $this->pdo->prepare(
            "SELECT id_vehiculo, placa, tipo_vehiculo, capacidad_kg, estado
             FROM flota
             WHERE id_vehiculo = :id AND id_empresa = :empresa AND activo = 1"
        );
        $stmt->execute([':id' => $idVehiculo, ':empresa' => $idEmpresa]);
        $fila = $stmt->fetch(PDO::FETCH_ASSOC);
        return $fila ?: null;
    }

    // Valida los datos del formulario de vehiculo; retorna la lista de errores
    private function validarVehiculo(array $d): array
    {
        $errores   = [];
        $placa     = trim($d['placa'] ?? '');
        $tipo      = trim($d['tipo_vehiculo'] ?? '');
        $capacidad = str_replace(',', '.', trim($d['capacidad_kg'] ?? ''));
        $estado    = $d['estado'] ?? '';

        if (!preg_match('/^[A-Za-z0-9\-]{3,15}$/', $placa)) {
            $errores[] = 'La placa debe tener entre 3 y 15 letras, números o guiones.';
        }
        if ($tipo === '' || mb_strlen($tipo) > 50) {
            $errores[] = 'Escribe el tipo de vehículo (máximo 50 caracteres).';
        }
        if (!is_numeric($capacidad) || (float) $capacidad <= 0 || (float) $capacidad >= 100000000) {
            $errores[] = 'La capacidad en kg debe ser un número mayor que cero.';
        }
        if (!isset(self::ESTADOS_VEHICULO[$estado])) {
            $errores[] = 'Elige un estado de vehículo válido.';
        }

        return $errores;
    }

    // Agrega un vehiculo a la flota de la empresa
    public function agregarVehiculo(int $idEmpresa, array $datos): array
    {
        $errores = $this->validarVehiculo($datos);
        if (!empty($errores)) {
            return ['exito' => false, 'errores' => $errores];
        }

        try {
            $stmt = $this->pdo->prepare(
                "INSERT INTO flota (id_empresa, placa, tipo_vehiculo, capacidad_kg, estado)
                 VALUES (:empresa, :placa, :tipo, :capacidad, :estado)"
            );
            $stmt->execute([
                ':empresa'   => $idEmpresa,
                ':placa'     => strtoupper(trim($datos['placa'])),
                ':tipo'      => trim($datos['tipo_vehiculo']),
                ':capacidad' => str_replace(',', '.', trim($datos['capacidad_kg'])),
                ':estado'    => $datos['estado'],
            ]);
            return ['exito' => true];
        } catch (PDOException $e) {
            return ['exito' => false, 'errores' => [$this->mensajeAmigable($e)]];
        }
    }

    // Edita un vehiculo activo que pertenece a la empresa
    public function editarVehiculo(int $idEmpresa, int $idVehiculo, array $datos): array
    {
        $errores = $this->validarVehiculo($datos);
        if ($idVehiculo <= 0) {
            $errores[] = 'El vehículo indicado no es válido.';
        }
        if (!empty($errores)) {
            return ['exito' => false, 'errores' => $errores];
        }

        try {
            $stmt = $this->pdo->prepare(
                "UPDATE flota
                 SET placa = :placa, tipo_vehiculo = :tipo,
                     capacidad_kg = :capacidad, estado = :estado
                 WHERE id_vehiculo = :id AND id_empresa = :empresa AND activo = 1"
            );
            $stmt->execute([
                ':placa'     => strtoupper(trim($datos['placa'])),
                ':tipo'      => trim($datos['tipo_vehiculo']),
                ':capacidad' => str_replace(',', '.', trim($datos['capacidad_kg'])),
                ':estado'    => $datos['estado'],
                ':id'        => $idVehiculo,
                ':empresa'   => $idEmpresa,
            ]);
            if ($stmt->rowCount() === 0) {
                return ['exito' => false, 'errores' => ['No encontramos ese vehículo en tu flota.']];
            }
            return ['exito' => true];
        } catch (PDOException $e) {
            return ['exito' => false, 'errores' => [$this->mensajeAmigable($e)]];
        }
    }

    // Elimina logicamente un vehiculo (activo = 0); no permite hacerlo si esta en servicio
    public function eliminarVehiculo(int $idEmpresa, int $idVehiculo): array
    {
        if ($idVehiculo <= 0) {
            return ['exito' => false, 'errores' => ['El vehículo indicado no es válido.']];
        }

        $vehiculo = $this->obtenerVehiculo($idEmpresa, $idVehiculo);
        if (!$vehiculo) {
            return ['exito' => false, 'errores' => ['No encontramos ese vehículo en tu flota.']];
        }
        if ($vehiculo['estado'] === 'en_servicio') {
            return ['exito' => false, 'errores' => ['No puedes eliminar un vehículo que está en servicio.']];
        }

        try {
            $stmt = $this->pdo->prepare(
                "UPDATE flota SET activo = 0
                 WHERE id_vehiculo = :id AND id_empresa = :empresa"
            );
            $stmt->execute([':id' => $idVehiculo, ':empresa' => $idEmpresa]);
            return ['exito' => true];
        } catch (PDOException $e) {
            return ['exito' => false, 'errores' => [$this->mensajeAmigable($e)]];
        }
    }

    /* =========================================================
     *  Apoyo
     * ========================================================= */

    // Traduce los errores de la base de datos a mensajes para el usuario
    private function mensajeAmigable(PDOException $e): string
    {
        $codigo = (int) ($e->errorInfo[1] ?? 0);
        $msg    = $e->getMessage();

        if ($codigo === 2627 || $codigo === 2601 || str_contains($msg, 'UNIQUE')) {
            return 'Ya existe un vehículo registrado con esa placa.';
        }
        return 'Ocurrió un error al procesar la solicitud. Intenta de nuevo.';
    }
}
