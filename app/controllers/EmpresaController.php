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

    public const PROVINCIAS = ['San José', 'Alajuela', 'Cartago', 'Heredia', 'Guanacaste', 'Puntarenas', 'Limón'];

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
            "SELECT f.id_vehiculo, f.placa, f.tipo_vehiculo, f.capacidad_kg, f.estado, f.id_tarifa,
                    zo.nombre AS zona_origen, zd.nombre AS zona_destino
             FROM flota f
             LEFT JOIN tarifas t ON t.id_tarifa = f.id_tarifa
             LEFT JOIN zonas_cobertura zo ON zo.id_zona = t.id_zona_origen
             LEFT JOIN zonas_cobertura zd ON zd.id_zona = t.id_zona_destino
             WHERE f.id_empresa = :empresa AND f.activo = 1
             ORDER BY f.placa"
        );
        $stmt->execute([':empresa' => $idEmpresa]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    // Retorna las rutas (tarifas activas) de la empresa para asignarlas a un vehiculo
    public function listarRutasEmpresa(int $idEmpresa): array
    {
        $stmt = $this->pdo->prepare(
            "SELECT t.id_tarifa, zo.nombre AS zona_origen, zd.nombre AS zona_destino
             FROM tarifas t
             INNER JOIN zonas_cobertura zo ON zo.id_zona = t.id_zona_origen
             INNER JOIN zonas_cobertura zd ON zd.id_zona = t.id_zona_destino
             WHERE t.id_empresa = :empresa AND t.activo = 1
             ORDER BY zo.nombre, zd.nombre"
        );
        $stmt->execute([':empresa' => $idEmpresa]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    // Retorna el id de tarifa validado (debe ser de la empresa) o null si no se eligio ruta
    private function resolverRuta(int $idEmpresa, array $datos): ?int
    {
        $idTarifa = (int) ($datos['id_tarifa'] ?? 0);
        if ($idTarifa <= 0) {
            return null;
        }
        foreach ($this->listarRutasEmpresa($idEmpresa) as $ruta) {
            if ((int) $ruta['id_tarifa'] === $idTarifa) {
                return $idTarifa;
            }
        }
        return null;
    }

    // Retorna un vehiculo activo de la empresa (o null si no existe o no es suyo)
    public function obtenerVehiculo(int $idEmpresa, int $idVehiculo): ?array
    {
        $stmt = $this->pdo->prepare(
            "SELECT id_vehiculo, placa, tipo_vehiculo, capacidad_kg, estado, id_tarifa
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
            $errores[] = 'La capacidad en kg debe ser un número mayor que cero y menor que 100.000.000.';
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
                "INSERT INTO flota (id_empresa, placa, tipo_vehiculo, capacidad_kg, estado, id_tarifa)
                 VALUES (:empresa, :placa, :tipo, :capacidad, :estado, :tarifa)"
            );
            $stmt->execute([
                ':tarifa'    => $this->resolverRuta($idEmpresa, $datos),
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
                     capacidad_kg = :capacidad, estado = :estado, id_tarifa = :tarifa
                 WHERE id_vehiculo = :id AND id_empresa = :empresa AND activo = 1"
            );
            $stmt->execute([
                ':tarifa'    => $this->resolverRuta($idEmpresa, $datos),
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
     *  Rutas y tarifas
     * ========================================================= */

    // Retorna las rutas activas de la empresa con sus zonas y precios
    public function listarTarifas(int $idEmpresa): array
    {
        $stmt = $this->pdo->prepare(
            "SELECT t.id_tarifa, zo.nombre AS zona_origen, zo.provincia AS provincia_origen,
                    zd.nombre AS zona_destino, zd.provincia AS provincia_destino,
                    t.distancia_km, t.precio_base, t.precio_por_km,
                    (t.precio_base + t.precio_por_km * t.distancia_km) AS monto_estimado
             FROM tarifas t
             INNER JOIN zonas_cobertura zo ON zo.id_zona = t.id_zona_origen
             INNER JOIN zonas_cobertura zd ON zd.id_zona = t.id_zona_destino
             WHERE t.id_empresa = :empresa AND t.activo = 1
             ORDER BY zo.nombre, zd.nombre"
        );
        $stmt->execute([':empresa' => $idEmpresa]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    // Retorna una ruta activa de la empresa (o null si no existe o no es suya)
    public function obtenerTarifa(int $idEmpresa, int $idTarifa): ?array
    {
        foreach ($this->listarTarifas($idEmpresa) as $t) {
            if ((int) $t['id_tarifa'] === $idTarifa) {
                return $t;
            }
        }
        return null;
    }

    // Convierte un texto numerico (acepta coma decimal) a float, o null si no es valido
    private function numero(?string $valor): ?float
    {
        $valor = str_replace(',', '.', trim($valor ?? ''));
        return is_numeric($valor) ? (float) $valor : null;
    }

    // Valida los precios y la distancia de una ruta; retorna la lista de errores
    private function validarPrecios(array $d): array
    {
        $errores   = [];
        $distancia = $this->numero($d['distancia_km'] ?? '');
        $base      = $this->numero($d['precio_base'] ?? '');
        $porKm     = $this->numero($d['precio_por_km'] ?? '');

        if ($distancia === null || $distancia <= 0 || $distancia >= 1000000) {
            $errores[] = 'La distancia debe ser un número mayor que cero.';
        }
        if ($base === null || $base < 0 || $base >= 100000000) {
            $errores[] = 'La tarifa base debe ser un número igual o mayor que cero.';
        }
        if ($porKm === null || $porKm < 0 || $porKm >= 100000000) {
            $errores[] = 'El precio por km debe ser un número igual o mayor que cero.';
        }
        return $errores;
    }

    // Retorna el id de la zona (la crea si no existe) y la asocia a la empresa
    private function asegurarZona(int $idEmpresa, string $nombre, string $provincia): int
    {
        $stmt = $this->pdo->prepare(
            "SELECT id_zona FROM zonas_cobertura WHERE nombre = :n AND provincia = :p"
        );
        $stmt->execute([':n' => $nombre, ':p' => $provincia]);
        $idZona = $stmt->fetchColumn();

        if ($idZona === false) {
            $stmt = $this->pdo->prepare(
                "INSERT INTO zonas_cobertura (nombre, provincia) OUTPUT INSERTED.id_zona
                 VALUES (:n, :p)"
            );
            $stmt->execute([':n' => $nombre, ':p' => $provincia]);
            $idZona = $stmt->fetchColumn();
        }

        $stmt = $this->pdo->prepare(
            "IF NOT EXISTS (SELECT 1 FROM empresa_zona WHERE id_empresa = :e1 AND id_zona = :z1)
                 INSERT INTO empresa_zona (id_empresa, id_zona) VALUES (:e2, :z2)"
        );
        $stmt->execute([':e1' => $idEmpresa, ':z1' => $idZona, ':e2' => $idEmpresa, ':z2' => $idZona]);
        return (int) $idZona;
    }

    // Crea una ruta nueva (o reactiva una eliminada con el mismo origen y destino)
    public function agregarTarifa(int $idEmpresa, array $datos): array
    {
        $errores = $this->validarPrecios($datos);
        $origen  = trim($datos['origen'] ?? '');
        $destino = trim($datos['destino'] ?? '');
        $provOri = trim($datos['provincia_origen'] ?? '');
        $provDes = trim($datos['provincia_destino'] ?? '');

        foreach ([$origen, $destino] as $zona) {
            if ($zona === '' || mb_strlen($zona) > 100) {
                $errores[] = 'Escribe el origen y el destino (máximo 100 caracteres).';
                break;
            }
        }
        if (!in_array($provOri, self::PROVINCIAS, true) || !in_array($provDes, self::PROVINCIAS, true)) {
            $errores[] = 'Elige la provincia del origen y del destino.';
        }
        if (!empty($errores)) {
            return ['exito' => false, 'errores' => $errores];
        }

        try {
            $this->pdo->beginTransaction();
            $idOrigen  = $this->asegurarZona($idEmpresa, $origen, $provOri);
            $idDestino = $this->asegurarZona($idEmpresa, $destino, $provDes);

            $params = [
                ':distancia' => $this->numero($datos['distancia_km']),
                ':base'      => $this->numero($datos['precio_base']),
                ':porkm'     => $this->numero($datos['precio_por_km']),
                ':empresa'   => $idEmpresa,
                ':origen'    => $idOrigen,
                ':destino'   => $idDestino,
            ];

            $stmt = $this->pdo->prepare(
                "SELECT id_tarifa, activo FROM tarifas
                 WHERE id_empresa = :empresa AND id_zona_origen = :origen AND id_zona_destino = :destino"
            );
            $stmt->execute([':empresa' => $idEmpresa, ':origen' => $idOrigen, ':destino' => $idDestino]);
            $existente = $stmt->fetch(PDO::FETCH_ASSOC);

            if ($existente && (int) $existente['activo'] === 1) {
                $this->pdo->rollBack();
                return ['exito' => false, 'errores' => ['Ya tienes una ruta con ese origen y destino.']];
            }

            if ($existente) {
                $stmt = $this->pdo->prepare(
                    "UPDATE tarifas SET distancia_km = :distancia, precio_base = :base,
                            precio_por_km = :porkm, activo = 1
                     WHERE id_tarifa = :id"
                );
                $stmt->execute([
                    ':distancia' => $params[':distancia'], ':base' => $params[':base'],
                    ':porkm' => $params[':porkm'], ':id' => $existente['id_tarifa'],
                ]);
            } else {
                $stmt = $this->pdo->prepare(
                    "INSERT INTO tarifas (id_empresa, id_zona_origen, id_zona_destino,
                                          distancia_km, precio_base, precio_por_km)
                     VALUES (:empresa, :origen, :destino, :distancia, :base, :porkm)"
                );
                $stmt->execute($params);
            }
            $this->pdo->commit();
            return ['exito' => true];
        } catch (PDOException $e) {
            if ($this->pdo->inTransaction()) {
                $this->pdo->rollBack();
            }
            return ['exito' => false, 'errores' => ['Ocurrió un error al guardar la ruta. Intenta de nuevo.']];
        }
    }

    // Edita la distancia y los precios de una ruta activa de la empresa
    public function editarTarifa(int $idEmpresa, int $idTarifa, array $datos): array
    {
        $errores = $this->validarPrecios($datos);
        if ($idTarifa <= 0) {
            $errores[] = 'La ruta indicada no es válida.';
        }
        if (!empty($errores)) {
            return ['exito' => false, 'errores' => $errores];
        }

        try {
            $stmt = $this->pdo->prepare(
                "UPDATE tarifas SET distancia_km = :distancia, precio_base = :base, precio_por_km = :porkm
                 WHERE id_tarifa = :id AND id_empresa = :empresa AND activo = 1"
            );
            $stmt->execute([
                ':distancia' => $this->numero($datos['distancia_km']),
                ':base'      => $this->numero($datos['precio_base']),
                ':porkm'     => $this->numero($datos['precio_por_km']),
                ':id'        => $idTarifa,
                ':empresa'   => $idEmpresa,
            ]);
            if ($stmt->rowCount() === 0) {
                return ['exito' => false, 'errores' => ['No encontramos esa ruta.']];
            }
            return ['exito' => true];
        } catch (PDOException $e) {
            return ['exito' => false, 'errores' => ['Ocurrió un error al guardar la ruta. Intenta de nuevo.']];
        }
    }

    // Elimina logicamente una ruta (activo = 0) y la desasigna de los vehiculos
    public function eliminarTarifa(int $idEmpresa, int $idTarifa): array
    {
        if ($idTarifa <= 0 || !$this->obtenerTarifa($idEmpresa, $idTarifa)) {
            return ['exito' => false, 'errores' => ['No encontramos esa ruta.']];
        }

        try {
            $this->pdo->beginTransaction();
            $stmt = $this->pdo->prepare(
                "UPDATE tarifas SET activo = 0 WHERE id_tarifa = :id AND id_empresa = :empresa"
            );
            $stmt->execute([':id' => $idTarifa, ':empresa' => $idEmpresa]);
            $stmt = $this->pdo->prepare(
                "UPDATE flota SET id_tarifa = NULL WHERE id_tarifa = :id AND id_empresa = :empresa"
            );
            $stmt->execute([':id' => $idTarifa, ':empresa' => $idEmpresa]);
            $this->pdo->commit();
            return ['exito' => true];
        } catch (PDOException $e) {
            if ($this->pdo->inTransaction()) {
                $this->pdo->rollBack();
            }
            return ['exito' => false, 'errores' => ['Ocurrió un error al eliminar la ruta. Intenta de nuevo.']];
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
