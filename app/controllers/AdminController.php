<?php
/**
 * Archivo: AdminController.php
 * Modulo: Administrador
 * Responsable: Josue Azofeifa
 * Descripcion: Gestiona las empresas y el catalogo de zonas.
 */

// Escapa texto para imprimirlo de forma segura dentro del HTML
if (!function_exists('h')) {
    function h($valor): string
    {
        return htmlspecialchars((string) ($valor ?? ''), ENT_QUOTES, 'UTF-8');
    }
}

class AdminController
{
    private PDO $pdo;

    public function __construct(PDO $pdo)
    {
        $this->pdo = $pdo;
    }

    // Redirige al login si la persona no tiene una sesion de administrador
    public static function exigirAdministrador(): int
    {
        if (!isset($_SESSION['id_usuario']) || ($_SESSION['rol'] ?? '') !== 'administrador') {
            header('Location: ../auth/login.php');
            exit;
        }

        return (int) $_SESSION['id_usuario'];
    }

    // Genera una vez por sesion el token que protege los formularios POST
    public static function tokenCsrf(): string
    {
        if (empty($_SESSION['csrf'])) {
            $_SESSION['csrf'] = bin2hex(random_bytes(32));
        }

        return $_SESSION['csrf'];
    }

    // Compara el token del formulario con el token de la sesion
    public static function validarCsrf(?string $token): bool
    {
        return is_string($token)
            && hash_equals($_SESSION['csrf'] ?? '', $token);
    }

    public static function guardarAviso(string $tipo, string $texto): void
    {
        $_SESSION['aviso'] = [
            'tipo' => $tipo,
            'texto' => $texto,
        ];
    }

    // Retorna y borra el mensaje pendiente, si existe
    public static function tomarAviso(): ?array
    {
        $aviso = $_SESSION['aviso'] ?? null;
        unset($_SESSION['aviso']);

        return $aviso;
    }

    // Lista empresas, su estado y los datos de contacto de su propietario - arraylist
    public function listarEmpresas(): array
    {
        $stmt = $this->pdo->query(
            "SELECT e.id_empresa, e.nombre_comercial, e.identificacion,
                    e.estado, e.fecha_registro, e.motivo_rechazo,
                    u.nombre AS nombre_propietario,
                    u.correo, u.telefono
             FROM empresas e
             INNER JOIN usuarios u ON u.id_usuario = e.id_usuario
             ORDER BY
                CASE e.estado
                    WHEN 'pendiente' THEN 1
                    WHEN 'activa' THEN 2
                    WHEN 'suspendida' THEN 3
                    WHEN 'rechazada' THEN 4
                    ELSE 5
                END,
                e.fecha_registro DESC"
        );

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    // Aprueba una empresa pendiente mediante el procedimiento almacenado
    public function aprobarEmpresa(int $idEmpresa, int $idAdmin): array
    {
        try {
            $stmt = $this->pdo->prepare(
                "EXEC sp_revisar_empresa
                    @id_empresa = :id_empresa,
                    @id_admin = :id_admin,
                    @decision = :decision,
                    @motivo = :motivo"
            );

            $stmt->execute([
                ':id_empresa' => $idEmpresa,
                ':id_admin' => $idAdmin,
                ':decision' => 'activa',
                ':motivo' => null,
            ]);

            return ['exito' => true, 'errores' => []];
        } catch (PDOException $e) {
            return [
                'exito' => false,
                'errores' => [$this->mensajeAmigable($e)],
            ];
        }
    }

    // Suspende una empresa activa y notifica a su propietario
    public function suspenderEmpresa(int $idEmpresa): array
    {
        try {
            $this->pdo->beginTransaction();

            $stmt = $this->pdo->prepare(
                "SELECT id_usuario
                 FROM empresas
                 WHERE id_empresa = :id_empresa
                   AND estado = 'activa'"
            );
            $stmt->execute([':id_empresa' => $idEmpresa]);
            $empresa = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$empresa) {
                $this->pdo->rollBack();

                return [
                    'exito' => false,
                    'errores' => ['La empresa no existe o no está activa.'],
                ];
            }

            $actualizar = $this->pdo->prepare(
                "UPDATE empresas
                 SET estado = 'suspendida'
                 WHERE id_empresa = :id_empresa
                   AND estado = 'activa'"
            );
            $actualizar->execute([':id_empresa' => $idEmpresa]);

            $notificar = $this->pdo->prepare(
                "INSERT INTO notificaciones (id_usuario, mensaje)
                 VALUES (:id_usuario, :mensaje)"
            );
            $notificar->execute([
                ':id_usuario' => (int) $empresa['id_usuario'],
                ':mensaje' => 'Tu empresa fue suspendida por un administrador.',
            ]);

            $this->pdo->commit();

            return ['exito' => true, 'errores' => []];
        } catch (PDOException $e) {
            if ($this->pdo->inTransaction()) {
                $this->pdo->rollBack();
            }

            return [
                'exito' => false,
                'errores' => [$this->mensajeAmigable($e)],
            ];
        }
    }

    // Lista las zonas ordenadas por provincia, canton y nombre
    public function listarZonas(): array
    {
        $stmt = $this->pdo->query(
            "SELECT id_zona, nombre, provincia, canton
             FROM zonas_cobertura
             ORDER BY provincia, canton, nombre"
        );

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    // localiza zona por idZona (identificador)
    public function obtenerZona(int $idZona): ?array
    {
        $stmt = $this->pdo->prepare(
            "SELECT id_zona, nombre, provincia, canton
             FROM zonas_cobertura
             WHERE id_zona = :id_zona"
        );
        $stmt->execute([':id_zona' => $idZona]);

        $zona = $stmt->fetch(PDO::FETCH_ASSOC);

        return $zona ?: null;
    }

    private function validarZona(array $datos): array
    {
        $zona = [
            'nombre' => trim($datos['nombre'] ?? ''),
            'provincia' => trim($datos['provincia'] ?? ''),
            'canton' => trim($datos['canton'] ?? ''),
        ];
        $errores = [];

        if ($zona['nombre'] === '' || mb_strlen($zona['nombre']) > 100) {
            $errores[] = 'Escribe el nombre de la zona (máximo 100 caracteres).';
        }

        if ($zona['provincia'] === '' || mb_strlen($zona['provincia']) > 100) {
            $errores[] = 'Escribe la provincia (máximo 100 caracteres).';
        }

        if (mb_strlen($zona['canton']) > 100) {
            $errores[] = 'El cantón no puede pasar de 100 caracteres.';
        }

        return [
            'datos' => $zona,
            'errores' => $errores,
        ];
    }

    // Crea una zona nueva en el catalogo
    public function crearZona(array $datos): array
    {
        $validacion = $this->validarZona($datos);

        if ($validacion['errores']) {
            return ['exito' => false, 'errores' => $validacion['errores']];
        }

        try {
            $stmt = $this->pdo->prepare(
                "INSERT INTO zonas_cobertura (nombre, provincia, canton)
                 VALUES (:nombre, :provincia, :canton)"
            );
            $stmt->execute([
                ':nombre' => $validacion['datos']['nombre'],
                ':provincia' => $validacion['datos']['provincia'],
                ':canton' => $validacion['datos']['canton'] !== ''
                    ? $validacion['datos']['canton']
                    : null,
            ]);

            return ['exito' => true, 'errores' => []];
        } catch (PDOException $e) {
            return [
                'exito' => false,
                'errores' => [$this->mensajeAmigable($e)],
            ];
        }
    }

    public function actualizarZona(int $idZona, array $datos): array
    {
        $validacion = $this->validarZona($datos);

        if ($validacion['errores']) {
            return ['exito' => false, 'errores' => $validacion['errores']];
        }

        try {
            $stmt = $this->pdo->prepare(
                "UPDATE zonas_cobertura
                 SET nombre = :nombre,
                     provincia = :provincia,
                     canton = :canton
                 WHERE id_zona = :id_zona"
            );
            $stmt->execute([
                ':nombre' => $validacion['datos']['nombre'],
                ':provincia' => $validacion['datos']['provincia'],
                ':canton' => $validacion['datos']['canton'] !== ''
                    ? $validacion['datos']['canton']
                    : null,
                ':id_zona' => $idZona,
            ]);

            if ($stmt->rowCount() === 0 && !$this->obtenerZona($idZona)) {
                return [
                    'exito' => false,
                    'errores' => ['La zona que intentas modificar no existe.'],
                ];
            }

            return ['exito' => true, 'errores' => []];
        } catch (PDOException $e) {
            return [
                'exito' => false,
                'errores' => [$this->mensajeAmigable($e)],
            ];
        }
    }

    // Elimina una zona si no está relacionada con empresas o tarifas
    public function eliminarZona(int $idZona): array
    {
        try {
            $stmt = $this->pdo->prepare(
                "DELETE FROM zonas_cobertura
                 WHERE id_zona = :id_zona"
            );
            $stmt->execute([':id_zona' => $idZona]);

            if ($stmt->rowCount() === 0) {
                return [
                    'exito' => false,
                    'errores' => ['La zona no existe o ya fue eliminada.'],
                ];
            }

            return ['exito' => true, 'errores' => []];
        } catch (PDOException $e) {
            return [
                'exito' => false,
                'errores' => [$this->mensajeAmigable($e)],
            ];
        }
    }

    // Traduce errores frecuentes de SQL Server a mensajes claros
    private function mensajeAmigable(PDOException $e): string
    {
        $codigoSql = (int) ($e->errorInfo[1] ?? 0);

        if ($codigoSql === 2601 || $codigoSql === 2627) {
            return 'Ya existe una zona con ese nombre en esa provincia.';
        }

        if ($codigoSql === 547) {
            return 'No se puede eliminar esa zona porque está asociada a empresas o tarifas.';
        }

        return 'Lo sentimos, no se pudo completar la solicitud. Revisa los datos y vuelve a intentarlo.';
    }
}
