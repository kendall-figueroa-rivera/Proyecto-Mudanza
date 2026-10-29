<?php
/**
 * Archivo: ClienteController.php
 * Modulo: Cliente
 * Responsable: [nombre del integrante]
 * Descripcion: Controla la busqueda y directorio de empresas (RF-09, RF-10),
 *              la generacion y consulta de proformas (RF-11, RF-12, RF-13),
 *              las solicitudes de servicio y su cancelacion (RF-14, RF-15),
 *              las resenas post-servicio (RF-17, RF-18) y el formulario de
 *              contacto a soporte (RF-25).
 *              Las operaciones principales usan los procedimientos almacenados
 *              sp_generar_proforma, sp_crear_solicitud y
 *              sp_calcular_calificacion_promedio.
 */

// Escapa texto para imprimirlo de forma segura dentro del HTML
if (!function_exists('h')) {
    function h($valor): string
    {
        return htmlspecialchars((string) ($valor ?? ''), ENT_QUOTES, 'UTF-8');
    }
}

// Da formato de colones a un monto (ej. 125000 -> ₡125.000,00)
if (!function_exists('colones')) {
    function colones($monto): string
    {
        return '₡' . number_format((float) $monto, 2, ',', '.');
    }
}

class ClienteController
{
    // Dias que una proforma se mantiene vigente antes de expirar (RF-13)
    public const DIAS_VIGENCIA_PROFORMA = 15;

    // Correo que recibe los mensajes del formulario de contacto (RF-25)
    public const CORREO_SOPORTE = 'soporte@mudaconecta.com';

    // Nombre visible de cada estado de proforma (la BD guarda aceptada/vencida)
    public const ESTADOS_PROFORMA = [
        'vigente'  => 'Vigente',
        'aceptada' => 'Reservada',
        'vencida'  => 'Expirada',
    ];

    // Nombre visible de cada estado de solicitud
    public const ESTADOS_SOLICITUD = [
        'pendiente'  => 'Pendiente',
        'aceptada'   => 'Aceptada',
        'rechazada'  => 'Rechazada',
        'completada' => 'Completada',
        'cancelada'  => 'Cancelada',
    ];

    private PDO $pdo;

    public function __construct(PDO $pdo)
    {
        $this->pdo = $pdo;
    }

    /* =========================================================
     *  Sesion, seguridad y mensajes
     * ========================================================= */

    // Redirige al login si no hay una sesion de cliente activa; retorna el id del cliente
    public static function exigirCliente(): int
    {
        if (!isset($_SESSION['id_usuario']) || ($_SESSION['rol'] ?? '') !== 'cliente') {
            header('Location: ../auth/login.php');
            exit;
        }
        return (int) $_SESSION['id_usuario'];
    }

    // Indica si la persona que navega es un cliente con sesion iniciada
    public static function esCliente(): bool
    {
        return isset($_SESSION['id_usuario']) && ($_SESSION['rol'] ?? '') === 'cliente';
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
     *  RF-09 / RF-10: busqueda, directorio y perfil de empresa
     * ========================================================= */

    // Retorna todas las zonas de cobertura ordenadas por provincia y nombre
    public function listarZonas(): array
    {
        $stmt = $this->pdo->query(
            "SELECT id_zona, nombre, provincia, canton
             FROM zonas_cobertura
             ORDER BY provincia, nombre"
        );
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    // Busca empresas activas por nombre, zona de origen/destino (texto) y calificacion minima
    public function buscarEmpresas(array $filtros): array
    {
        $nombre  = trim($filtros['nombre'] ?? '');
        $origen  = trim($filtros['origen'] ?? '');
        $destino = trim($filtros['destino'] ?? '');
        $minimo  = (int) ($filtros['calificacion'] ?? 0);

        $sql = "SELECT e.id_empresa, e.nombre_comercial, e.descripcion, e.telefono,
                       ISNULL(r.promedio, 0) AS promedio, ISNULL(r.total, 0) AS total_resenas,
                       z.zonas
                FROM empresas e
                OUTER APPLY (
                    SELECT CAST(AVG(CAST(calificacion AS DECIMAL(4,2))) AS DECIMAL(3,2)) AS promedio,
                           COUNT(*) AS total
                    FROM resenas
                    WHERE id_empresa = e.id_empresa AND estado = 'visible'
                ) r
                OUTER APPLY (
                    SELECT STRING_AGG(zc.nombre, ', ') AS zonas
                    FROM empresa_zona ez
                    INNER JOIN zonas_cobertura zc ON zc.id_zona = ez.id_zona
                    WHERE ez.id_empresa = e.id_empresa
                ) z
                WHERE e.estado = 'activa'";
        $params = [];

        if ($nombre !== '') {
            $sql .= " AND e.nombre_comercial LIKE :nombre";
            $params[':nombre'] = '%' . $nombre . '%';
        }
        // Cada filtro de zona usa parametros con nombre distinto (sqlsrv no permite repetirlos)
        foreach (['origen' => $origen, 'destino' => $destino] as $clave => $texto) {
            if ($texto === '') {
                continue;
            }
            $sql .= " AND EXISTS (
                        SELECT 1 FROM empresa_zona ez2
                        INNER JOIN zonas_cobertura z2 ON z2.id_zona = ez2.id_zona
                        WHERE ez2.id_empresa = e.id_empresa
                          AND (z2.nombre LIKE :{$clave}_n OR z2.provincia LIKE :{$clave}_p
                               OR z2.canton LIKE :{$clave}_c))";
            $params[":{$clave}_n"] = '%' . $texto . '%';
            $params[":{$clave}_p"] = '%' . $texto . '%';
            $params[":{$clave}_c"] = '%' . $texto . '%';
        }
        if ($minimo >= 1 && $minimo <= 5) {
            $sql .= " AND ISNULL(r.promedio, 0) >= :minimo";
            $params[':minimo'] = $minimo;
        }

        $sql .= " ORDER BY ISNULL(r.promedio, 0) DESC, e.nombre_comercial";

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    // Busca empresas con tarifa activa para una ruta exacta y retorna el monto estimado
    public function buscarRutas(int $idZonaOrigen, int $idZonaDestino, string $orden = 'precio'): array
    {
        if ($idZonaOrigen <= 0 || $idZonaDestino <= 0) {
            return [];
        }

        $ordenSql = $orden === 'calificacion'
            ? 'ISNULL(r.promedio, 0) DESC, monto_estimado'
            : 'monto_estimado, ISNULL(r.promedio, 0) DESC';

        $stmt = $this->pdo->prepare(
            "SELECT e.id_empresa, e.nombre_comercial, e.descripcion,
                    t.distancia_km, t.precio_base, t.precio_por_km,
                    (t.precio_base + t.precio_por_km * t.distancia_km) AS monto_estimado,
                    ISNULL(r.promedio, 0) AS promedio, ISNULL(r.total, 0) AS total_resenas
             FROM tarifas t
             INNER JOIN empresas e ON e.id_empresa = t.id_empresa
             OUTER APPLY (
                 SELECT CAST(AVG(CAST(calificacion AS DECIMAL(4,2))) AS DECIMAL(3,2)) AS promedio,
                        COUNT(*) AS total
                 FROM resenas
                 WHERE id_empresa = e.id_empresa AND estado = 'visible'
             ) r
             WHERE t.activo = 1
               AND e.estado = 'activa'
               AND t.id_zona_origen = :origen
               AND t.id_zona_destino = :destino
             ORDER BY $ordenSql"
        );
        $stmt->execute([':origen' => $idZonaOrigen, ':destino' => $idZonaDestino]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    // Retorna los datos de una empresa activa, o null si no existe o no esta activa
    public function obtenerEmpresa(int $idEmpresa): ?array
    {
        $stmt = $this->pdo->prepare(
            "SELECT e.id_empresa, e.nombre_comercial, e.descripcion, e.telefono,
                    e.direccion, e.fecha_registro, u.correo
             FROM empresas e
             INNER JOIN usuarios u ON u.id_usuario = e.id_usuario
             WHERE e.id_empresa = :id AND e.estado = 'activa'"
        );
        $stmt->execute([':id' => $idEmpresa]);
        $empresa = $stmt->fetch(PDO::FETCH_ASSOC);
        return $empresa ?: null;
    }

    // Retorna el promedio y total de resenas de una empresa usando sp_calcular_calificacion_promedio
    public function obtenerCalificacion(int $idEmpresa): array
    {
        $stmt = $this->pdo->prepare("EXEC sp_calcular_calificacion_promedio @id_empresa = :id");
        $stmt->execute([':id' => $idEmpresa]);
        $fila = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($fila === false) {
            throw new RuntimeException('No se pudo obtener la calificación de la empresa.');
        }

        return [
            'promedio'      => (float) $fila['promedio'],
            'total_resenas' => (int) $fila['total_resenas'],
        ];
    }

    // Retorna las zonas donde trabaja una empresa
    public function obtenerZonasEmpresa(int $idEmpresa): array
    {
        $stmt = $this->pdo->prepare(
            "SELECT z.id_zona, z.nombre, z.provincia, z.canton
             FROM empresa_zona ez
             INNER JOIN zonas_cobertura z ON z.id_zona = ez.id_zona
             WHERE ez.id_empresa = :id
             ORDER BY z.provincia, z.nombre"
        );
        $stmt->execute([':id' => $idEmpresa]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    // Retorna las rutas con tarifa activa de una empresa, con su precio estimado
    public function obtenerTarifasEmpresa(int $idEmpresa): array
    {
        $stmt = $this->pdo->prepare(
            "SELECT t.id_zona_origen, t.id_zona_destino,
                    zo.nombre AS zona_origen, zd.nombre AS zona_destino,
                    t.distancia_km, t.precio_base, t.precio_por_km,
                    (t.precio_base + t.precio_por_km * t.distancia_km) AS monto_estimado
             FROM tarifas t
             INNER JOIN zonas_cobertura zo ON zo.id_zona = t.id_zona_origen
             INNER JOIN zonas_cobertura zd ON zd.id_zona = t.id_zona_destino
             WHERE t.id_empresa = :id AND t.activo = 1
             ORDER BY zo.nombre, zd.nombre"
        );
        $stmt->execute([':id' => $idEmpresa]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    // Retorna las resenas visibles de una empresa, de la mas reciente a la mas antigua
    public function obtenerResenasEmpresa(int $idEmpresa): array
    {
        $stmt = $this->pdo->prepare(
            "SELECT r.calificacion, r.comentario, r.fecha, u.nombre AS cliente
             FROM resenas r
             INNER JOIN usuarios u ON u.id_usuario = r.id_cliente
             WHERE r.id_empresa = :id AND r.estado = 'visible'
             ORDER BY r.fecha DESC"
        );
        $stmt->execute([':id' => $idEmpresa]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    // Retorna las empresas activas (id y nombre) para el selector de la proforma
    public function listarEmpresasActivas(): array
    {
        $stmt = $this->pdo->query(
            "SELECT id_empresa, nombre_comercial
             FROM empresas
             WHERE estado = 'activa'
             ORDER BY nombre_comercial"
        );
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /* =========================================================
     *  RF-11 / RF-12 / RF-13: proformas
     * ========================================================= */

    // Genera una proforma con sp_generar_proforma; retorna el id creado o los errores
    public function generarProforma(int $idCliente, array $datos): array
    {
        $idEmpresa = (int) ($datos['id_empresa'] ?? 0);
        $origen    = (int) ($datos['zona_origen'] ?? 0);
        $destino   = (int) ($datos['zona_destino'] ?? 0);

        $errores = [];
        if ($idEmpresa <= 0) {
            $errores[] = 'Elige la empresa con la que quieres cotizar.';
        }
        if ($origen <= 0) {
            $errores[] = 'Elige la zona de origen.';
        }
        if ($destino <= 0) {
            $errores[] = 'Elige la zona de destino.';
        }
        if (!empty($errores)) {
            return ['exito' => false, 'errores' => $errores];
        }

        try {
            $stmt = $this->pdo->prepare(
                "EXEC sp_generar_proforma
                    @id_cliente = :cliente,
                    @id_empresa = :empresa,
                    @id_zona_origen = :origen,
                    @id_zona_destino = :destino"
            );
            $stmt->execute([
                ':cliente' => $idCliente,
                ':empresa' => $idEmpresa,
                ':origen'  => $origen,
                ':destino' => $destino,
            ]);
            $fila = $stmt->fetch(PDO::FETCH_ASSOC);
            return ['exito' => true, 'id_proforma' => (int) $fila['id_proforma']];
        } catch (PDOException $e) {
            return ['exito' => false, 'errores' => [$this->mensajeAmigable($e)]];
        }
    }

    // Marca como vencidas las proformas vigentes del cliente que ya pasaron su plazo (RF-13)
    public function marcarProformasVencidas(int $idCliente): void
    {
        // Los dias van escritos en el SQL (no como parametro) porque PDO los enviaria
        // como texto y SQL Server no permite operar DATEADD con texto. Es seguro porque
        // es una constante entera de la clase, nunca un dato del usuario.
        $dias = (int) self::DIAS_VIGENCIA_PROFORMA;
        $stmt = $this->pdo->prepare(
            "UPDATE proformas
             SET estado = 'vencida'
             WHERE id_cliente = :cliente
               AND estado = 'vigente'
               AND fecha_generacion < DATEADD(DAY, -$dias, GETDATE())"
        );
        $stmt->execute([':cliente' => $idCliente]);
    }

    // Retorna una proforma del cliente con los nombres de empresa y zonas, o null
    public function obtenerProforma(int $idCliente, int $idProforma): ?array
    {
        $this->marcarProformasVencidas($idCliente);
        $dias = (int) self::DIAS_VIGENCIA_PROFORMA;

        $stmt = $this->pdo->prepare(
            "SELECT p.id_proforma, p.id_empresa, p.distancia_km, p.monto_total, p.estado,
                    p.fecha_generacion, e.nombre_comercial,
                    zo.nombre AS zona_origen, zo.provincia AS provincia_origen,
                    zd.nombre AS zona_destino, zd.provincia AS provincia_destino,
                    t.precio_base, t.precio_por_km,
                    DATEADD(DAY, $dias, p.fecha_generacion) AS fecha_vence,
                    s.id_solicitud
             FROM proformas p
             INNER JOIN empresas e ON e.id_empresa = p.id_empresa
             INNER JOIN zonas_cobertura zo ON zo.id_zona = p.id_zona_origen
             INNER JOIN zonas_cobertura zd ON zd.id_zona = p.id_zona_destino
             INNER JOIN tarifas t ON t.id_tarifa = p.id_tarifa
             LEFT JOIN solicitudes s ON s.id_proforma = p.id_proforma
             WHERE p.id_proforma = :id AND p.id_cliente = :cliente"
        );
        $stmt->execute([
            ':id'      => $idProforma,
            ':cliente' => $idCliente,
        ]);
        $proforma = $stmt->fetch(PDO::FETCH_ASSOC);
        return $proforma ?: null;
    }

    // Retorna el historial de proformas del cliente, opcionalmente filtrado por estado
    public function listarProformas(int $idCliente, string $estado = ''): array
    {
        $this->marcarProformasVencidas($idCliente);
        $dias = (int) self::DIAS_VIGENCIA_PROFORMA;

        $sql = "SELECT p.id_proforma, p.monto_total, p.estado, p.fecha_generacion,
                       p.distancia_km, e.nombre_comercial,
                       zo.nombre AS zona_origen, zd.nombre AS zona_destino,
                       DATEADD(DAY, $dias, p.fecha_generacion) AS fecha_vence,
                       s.id_solicitud
                FROM proformas p
                INNER JOIN empresas e ON e.id_empresa = p.id_empresa
                INNER JOIN zonas_cobertura zo ON zo.id_zona = p.id_zona_origen
                INNER JOIN zonas_cobertura zd ON zd.id_zona = p.id_zona_destino
                LEFT JOIN solicitudes s ON s.id_proforma = p.id_proforma
                WHERE p.id_cliente = :cliente";
        $params = [':cliente' => $idCliente];

        if (array_key_exists($estado, self::ESTADOS_PROFORMA)) {
            $sql .= " AND p.estado = :estado";
            $params[':estado'] = $estado;
        }
        $sql .= " ORDER BY p.fecha_generacion DESC";

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /* =========================================================
     *  RF-14 / RF-15: solicitudes de servicio
     * ========================================================= */

    // Valida los datos del formulario de solicitud; retorna la lista de errores
    private function validarSolicitud(array $d): array
    {
        $errores = [];
        $origen  = trim($d['direccion_origen'] ?? '');
        $destino = trim($d['direccion_destino'] ?? '');
        $fecha   = $d['fecha_mudanza'] ?? '';

        if ((int) ($d['id_proforma'] ?? 0) <= 0) {
            $errores[] = 'La proforma indicada no es válida.';
        }
        if ($origen === '' || mb_strlen($origen) > 255) {
            $errores[] = 'Escribe la dirección de origen (máximo 255 caracteres).';
        }
        if ($destino === '' || mb_strlen($destino) > 255) {
            $errores[] = 'Escribe la dirección de destino (máximo 255 caracteres).';
        }
        $fechaObj = DateTime::createFromFormat('Y-m-d', $fecha);
        if (!$fechaObj || $fechaObj->format('Y-m-d') !== $fecha) {
            $errores[] = 'Elige una fecha de mudanza válida.';
        } elseif ($fecha < date('Y-m-d')) {
            $errores[] = 'La fecha de mudanza no puede ser anterior a hoy.';
        }
        if (mb_strlen(trim($d['descripcion'] ?? '')) > 500) {
            $errores[] = 'La descripción no puede pasar de 500 caracteres.';
        }

        return $errores;
    }

    // Crea una solicitud a partir de una proforma vigente usando sp_crear_solicitud
    public function crearSolicitud(int $idCliente, array $datos): array
    {
        $errores = $this->validarSolicitud($datos);
        if (!empty($errores)) {
            return ['exito' => false, 'errores' => $errores];
        }

        $this->marcarProformasVencidas($idCliente);
        $descripcion = trim($datos['descripcion'] ?? '');

        try {
            $stmt = $this->pdo->prepare(
                "EXEC sp_crear_solicitud
                    @id_cliente = :cliente,
                    @id_proforma = :proforma,
                    @direccion_origen = :origen,
                    @direccion_destino = :destino,
                    @fecha_mudanza = :fecha,
                    @descripcion = :descripcion"
            );
            $stmt->execute([
                ':cliente'     => $idCliente,
                ':proforma'    => (int) $datos['id_proforma'],
                ':origen'      => trim($datos['direccion_origen']),
                ':destino'     => trim($datos['direccion_destino']),
                ':fecha'       => $datos['fecha_mudanza'],
                ':descripcion' => $descripcion !== '' ? $descripcion : null,
            ]);
            $fila = $stmt->fetch(PDO::FETCH_ASSOC);
            return ['exito' => true, 'id_solicitud' => (int) $fila['id_solicitud']];
        } catch (PDOException $e) {
            return ['exito' => false, 'errores' => [$this->mensajeAmigable($e)]];
        }
    }

    // Retorna todas las solicitudes del cliente con empresa, ruta, monto y si ya fue calificada
    public function listarSolicitudes(int $idCliente): array
    {
        $stmt = $this->pdo->prepare(
            "SELECT s.id_solicitud, s.estado, s.fecha_mudanza, s.fecha_creacion,
                    s.direccion_origen, s.direccion_destino, s.descripcion,
                    s.motivo_respuesta, p.monto_total, p.id_proforma,
                    e.nombre_comercial, e.telefono AS telefono_empresa,
                    zo.nombre AS zona_origen, zd.nombre AS zona_destino,
                    r.id_resena
             FROM solicitudes s
             INNER JOIN proformas p ON p.id_proforma = s.id_proforma
             INNER JOIN empresas e ON e.id_empresa = p.id_empresa
             INNER JOIN zonas_cobertura zo ON zo.id_zona = p.id_zona_origen
             INNER JOIN zonas_cobertura zd ON zd.id_zona = p.id_zona_destino
             LEFT JOIN resenas r ON r.id_solicitud = s.id_solicitud
             WHERE s.id_cliente = :cliente
             ORDER BY s.fecha_creacion DESC"
        );
        $stmt->execute([':cliente' => $idCliente]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    // Indica si una solicitud se puede cancelar (solo pendiente o aceptada y antes de la fecha)
    public static function sePuedeCancelar(array $solicitud): bool
    {
        return in_array($solicitud['estado'], ['pendiente', 'aceptada'], true)
            && substr((string) $solicitud['fecha_mudanza'], 0, 10) >= date('Y-m-d');
    }

    // Cancela una solicitud del cliente y notifica a la empresa (RF-15)
    public function cancelarSolicitud(int $idCliente, int $idSolicitud): array
    {
        if ($idSolicitud <= 0) {
            return ['exito' => false, 'errores' => ['La solicitud indicada no es válida.']];
        }

        try {
            $this->pdo->beginTransaction();

            $stmt = $this->pdo->prepare(
                "SELECT s.estado, s.fecha_mudanza, e.id_usuario AS id_usuario_empresa
                 FROM solicitudes s
                 INNER JOIN proformas p ON p.id_proforma = s.id_proforma
                 INNER JOIN empresas e ON e.id_empresa = p.id_empresa
                 WHERE s.id_solicitud = :id AND s.id_cliente = :cliente"
            );
            $stmt->execute([':id' => $idSolicitud, ':cliente' => $idCliente]);
            $solicitud = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$solicitud) {
                $this->pdo->rollBack();
                return ['exito' => false, 'errores' => ['No encontramos esa solicitud en tu cuenta.']];
            }
            if (!self::sePuedeCancelar($solicitud)) {
                $this->pdo->rollBack();
                return ['exito' => false, 'errores' => [
                    'Solo puedes cancelar solicitudes pendientes o aceptadas cuya fecha no haya pasado.',
                ]];
            }

            $stmt = $this->pdo->prepare(
                "UPDATE solicitudes SET estado = 'cancelada' WHERE id_solicitud = :id"
            );
            $stmt->execute([':id' => $idSolicitud]);

            $stmt = $this->pdo->prepare(
                "INSERT INTO notificaciones (id_usuario, mensaje) VALUES (:usuario, :mensaje)"
            );
            $stmt->execute([
                ':usuario' => $solicitud['id_usuario_empresa'],
                ':mensaje' => "El cliente canceló la solicitud de mudanza #$idSolicitud.",
            ]);

            $this->pdo->commit();
            return ['exito' => true];
        } catch (PDOException $e) {
            if ($this->pdo->inTransaction()) {
                $this->pdo->rollBack();
            }
            return ['exito' => false, 'errores' => [$this->mensajeAmigable($e)]];
        }
    }

    /* =========================================================
     *  RF-17 / RF-18: calificacion y resena post-servicio
     * ========================================================= */

    // Retorna una solicitud completada y sin resena del cliente, o null si no se puede calificar
    public function obtenerSolicitudParaCalificar(int $idCliente, int $idSolicitud): ?array
    {
        $stmt = $this->pdo->prepare(
            "SELECT s.id_solicitud, s.fecha_mudanza, p.id_empresa, e.nombre_comercial,
                    zo.nombre AS zona_origen, zd.nombre AS zona_destino
             FROM solicitudes s
             INNER JOIN proformas p ON p.id_proforma = s.id_proforma
             INNER JOIN empresas e ON e.id_empresa = p.id_empresa
             INNER JOIN zonas_cobertura zo ON zo.id_zona = p.id_zona_origen
             INNER JOIN zonas_cobertura zd ON zd.id_zona = p.id_zona_destino
             LEFT JOIN resenas r ON r.id_solicitud = s.id_solicitud
             WHERE s.id_solicitud = :id
               AND s.id_cliente = :cliente
               AND s.estado = 'completada'
               AND r.id_resena IS NULL"
        );
        $stmt->execute([':id' => $idSolicitud, ':cliente' => $idCliente]);
        $solicitud = $stmt->fetch(PDO::FETCH_ASSOC);
        return $solicitud ?: null;
    }

    // Retorna las solicitudes completadas del cliente que todavia no tienen resena
    public function listarPendientesDeCalificar(int $idCliente): array
    {
        $stmt = $this->pdo->prepare(
            "SELECT s.id_solicitud, s.fecha_mudanza, e.nombre_comercial,
                    zo.nombre AS zona_origen, zd.nombre AS zona_destino
             FROM solicitudes s
             INNER JOIN proformas p ON p.id_proforma = s.id_proforma
             INNER JOIN empresas e ON e.id_empresa = p.id_empresa
             INNER JOIN zonas_cobertura zo ON zo.id_zona = p.id_zona_origen
             INNER JOIN zonas_cobertura zd ON zd.id_zona = p.id_zona_destino
             LEFT JOIN resenas r ON r.id_solicitud = s.id_solicitud
             WHERE s.id_cliente = :cliente AND s.estado = 'completada' AND r.id_resena IS NULL
             ORDER BY s.fecha_mudanza DESC"
        );
        $stmt->execute([':cliente' => $idCliente]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    // Retorna las resenas que el cliente ya escribio
    public function listarResenasCliente(int $idCliente): array
    {
        $stmt = $this->pdo->prepare(
            "SELECT r.calificacion, r.comentario, r.fecha, r.estado, e.nombre_comercial
             FROM resenas r
             INNER JOIN empresas e ON e.id_empresa = r.id_empresa
             WHERE r.id_cliente = :cliente
             ORDER BY r.fecha DESC"
        );
        $stmt->execute([':cliente' => $idCliente]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    // Guarda la calificacion y resena de una solicitud completada y notifica a la empresa
    public function registrarResena(int $idCliente, array $datos): array
    {
        $idSolicitud  = (int) ($datos['id_solicitud'] ?? 0);
        $calificacion = (int) ($datos['calificacion'] ?? 0);
        $comentario   = trim($datos['comentario'] ?? '');

        $errores = [];
        if ($calificacion < 1 || $calificacion > 5) {
            $errores[] = 'Elige una calificación de 1 a 5 estrellas.';
        }
        if (mb_strlen($comentario) > 500) {
            $errores[] = 'El comentario no puede pasar de 500 caracteres.';
        }
        if (!empty($errores)) {
            return ['exito' => false, 'errores' => $errores];
        }

        $solicitud = $this->obtenerSolicitudParaCalificar($idCliente, $idSolicitud);
        if (!$solicitud) {
            return ['exito' => false, 'errores' => [
                'Solo puedes calificar mudanzas completadas que aún no tengan reseña.',
            ]];
        }

        try {
            $this->pdo->beginTransaction();

            $stmt = $this->pdo->prepare(
                "INSERT INTO resenas (id_solicitud, id_cliente, id_empresa, calificacion, comentario)
                 VALUES (:solicitud, :cliente, :empresa, :calificacion, :comentario)"
            );
            $stmt->execute([
                ':solicitud'    => $idSolicitud,
                ':cliente'      => $idCliente,
                ':empresa'      => $solicitud['id_empresa'],
                ':calificacion' => $calificacion,
                ':comentario'   => $comentario !== '' ? $comentario : null,
            ]);

            $stmt = $this->pdo->prepare(
                "INSERT INTO notificaciones (id_usuario, mensaje)
                 SELECT id_usuario, :mensaje FROM empresas WHERE id_empresa = :empresa"
            );
            $stmt->execute([
                ':mensaje' => "Recibiste una reseña de $calificacion estrellas por la solicitud #$idSolicitud.",
                ':empresa' => $solicitud['id_empresa'],
            ]);

            $this->pdo->commit();
            return ['exito' => true];
        } catch (PDOException $e) {
            if ($this->pdo->inTransaction()) {
                $this->pdo->rollBack();
            }
            return ['exito' => false, 'errores' => [$this->mensajeAmigable($e)]];
        }
    }

    /* =========================================================
     *  Panel del cliente
     * ========================================================= */

    // Retorna los contadores que se muestran en el panel del cliente
    public function obtenerResumen(int $idCliente): array
    {
        $this->marcarProformasVencidas($idCliente);

        $stmt = $this->pdo->prepare(
            "SELECT
                (SELECT COUNT(*) FROM proformas
                  WHERE id_cliente = :c1 AND estado = 'vigente') AS proformas_vigentes,
                (SELECT COUNT(*) FROM solicitudes
                  WHERE id_cliente = :c2 AND estado IN ('pendiente', 'aceptada')) AS solicitudes_activas,
                (SELECT COUNT(*) FROM solicitudes s
                  LEFT JOIN resenas r ON r.id_solicitud = s.id_solicitud
                  WHERE s.id_cliente = :c3 AND s.estado = 'completada'
                    AND r.id_resena IS NULL) AS por_calificar"
        );
        $stmt->execute([':c1' => $idCliente, ':c2' => $idCliente, ':c3' => $idCliente]);
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }

    // Retorna la proxima mudanza aceptada o pendiente del cliente, o null
    public function obtenerProximaMudanza(int $idCliente): ?array
    {
        $stmt = $this->pdo->prepare(
            "SELECT TOP 1 s.id_solicitud, s.fecha_mudanza, s.estado, e.nombre_comercial,
                    zo.nombre AS zona_origen, zd.nombre AS zona_destino
             FROM solicitudes s
             INNER JOIN proformas p ON p.id_proforma = s.id_proforma
             INNER JOIN empresas e ON e.id_empresa = p.id_empresa
             INNER JOIN zonas_cobertura zo ON zo.id_zona = p.id_zona_origen
             INNER JOIN zonas_cobertura zd ON zd.id_zona = p.id_zona_destino
             WHERE s.id_cliente = :cliente
               AND s.estado IN ('pendiente', 'aceptada')
               AND s.fecha_mudanza >= CAST(GETDATE() AS DATE)
             ORDER BY s.fecha_mudanza"
        );
        $stmt->execute([':cliente' => $idCliente]);
        $mudanza = $stmt->fetch(PDO::FETCH_ASSOC);
        return $mudanza ?: null;
    }

    // Retorna las notificaciones mas recientes del usuario
    public function listarNotificaciones(int $idUsuario, int $limite = 8): array
    {
        $limite = max(1, min($limite, 50));
        $stmt = $this->pdo->prepare(
            "SELECT TOP ($limite) id_notificacion, mensaje, leida, fecha
             FROM notificaciones
             WHERE id_usuario = :usuario
             ORDER BY fecha DESC"
        );
        $stmt->execute([':usuario' => $idUsuario]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    // Marca como leidas todas las notificaciones del usuario
    public function marcarNotificacionesLeidas(int $idUsuario): void
    {
        $stmt = $this->pdo->prepare(
            "UPDATE notificaciones SET leida = 1 WHERE id_usuario = :usuario AND leida = 0"
        );
        $stmt->execute([':usuario' => $idUsuario]);
    }

    /* =========================================================
     *  RF-25: contacto a soporte (envia correo, no guarda en BD)
     * ========================================================= */

    // Retorna el correo de un usuario para prellenar el formulario de contacto
    public function obtenerCorreoUsuario(int $idUsuario): string
    {
        $stmt = $this->pdo->prepare("SELECT correo FROM usuarios WHERE id_usuario = :id");
        $stmt->execute([':id' => $idUsuario]);
        return (string) $stmt->fetchColumn();
    }

    // Valida y envia por correo el mensaje del formulario de contacto
    public function enviarContacto(array $datos): array
    {
        // Se quitan saltos de linea para evitar inyeccion de cabeceras en el correo
        $nombre  = trim(str_replace(["\r", "\n"], ' ', $datos['nombre'] ?? ''));
        $correo  = trim(str_replace(["\r", "\n"], '', $datos['correo'] ?? ''));
        $asunto  = trim(str_replace(["\r", "\n"], ' ', $datos['asunto'] ?? ''));
        $mensaje = trim($datos['mensaje'] ?? '');

        $errores = [];
        if ($nombre === '' || mb_strlen($nombre) > 100) {
            $errores[] = 'Escribe tu nombre (máximo 100 caracteres).';
        }
        if (!filter_var($correo, FILTER_VALIDATE_EMAIL)) {
            $errores[] = 'Escribe un correo válido para poder responderte.';
        }
        if ($asunto === '' || mb_strlen($asunto) > 120) {
            $errores[] = 'Escribe el asunto (máximo 120 caracteres).';
        }
        if (mb_strlen($mensaje) < 10 || mb_strlen($mensaje) > 2000) {
            $errores[] = 'El mensaje debe tener entre 10 y 2000 caracteres.';
        }
        if (!empty($errores)) {
            return ['exito' => false, 'errores' => $errores];
        }

        $cuerpo = "Mensaje enviado desde el formulario de contacto de MudaConecta\n\n"
                . "Nombre: $nombre\n"
                . "Correo: $correo\n"
                . "Fecha: " . date('d/m/Y H:i') . "\n\n"
                . $mensaje;

        $cabeceras = [
            'From'         => 'MudaConecta <no-responder@mudaconecta.com>',
            'Reply-To'     => $correo,
            'Content-Type' => 'text/plain; charset=UTF-8',
        ];
        $asuntoCodificado = '=?UTF-8?B?' . base64_encode("[Contacto] $asunto") . '?=';

        $enviado = @mail(self::CORREO_SOPORTE, $asuntoCodificado, $cuerpo, $cabeceras);

        if (!$enviado) {
            return ['exito' => false, 'errores' => [
                'No se pudo enviar el mensaje en este momento. Escríbenos directamente a '
                . self::CORREO_SOPORTE . '.',
            ]];
        }
        return ['exito' => true];
    }

    /* =========================================================
     *  Apoyo
     * ========================================================= */

    // Traduce los codigos THROW de los procedimientos almacenados a mensajes para el usuario
    private function mensajeAmigable(PDOException $e): string
    {
        $codigo = (int) ($e->errorInfo[1] ?? 0);
        $msg    = $e->getMessage();

        $mensajes = [
            50011 => 'Tu cuenta no está activa. Contacta a soporte.',
            50012 => 'La empresa no trabaja en la zona de origen que elegiste.',
            50013 => 'La empresa no tiene una tarifa para esa ruta. Prueba con otra empresa o ruta.',
            50014 => 'La empresa no trabaja en la zona de destino que elegiste.',
            50015 => 'Esa empresa no está disponible en este momento.',
            50020 => 'La fecha de mudanza no puede ser anterior a hoy.',
            50021 => 'Esa proforma ya no está vigente o no pertenece a tu cuenta.',
            2627  => 'Ese registro ya existe (por ejemplo, la mudanza ya fue calificada).',
            2601  => 'Ese registro ya existe (por ejemplo, la mudanza ya fue calificada).',
        ];

        if (isset($mensajes[$codigo])) {
            return $mensajes[$codigo];
        }
        // Respaldo por si el driver no llena errorInfo con el codigo nativo
        foreach ($mensajes as $num => $texto) {
            if (str_contains($msg, (string) $num)) {
                return $texto;
            }
        }
        return 'Ocurrió un error al procesar la solicitud. Intenta de nuevo.';
    }
}
