<?php
/**
 * Vista: empresas.php
 * Modulo: Administrador
 * Responsable: Josué Azofeifa
 * Descripcion: Lista empresas y permite aprobar o suspender.
 */

session_start();

require_once __DIR__ . '/../../../config/database.php';
require_once __DIR__ . '/../../controllers/AdminController.php';

$idAdmin = AdminController::exigirAdministrador();
$admin = new AdminController($pdo);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!AdminController::validarCsrf($_POST['csrf'] ?? null)) {
        AdminController::guardarAviso('danger', 'La solicitud no es válida. Inténtalo de nuevo.');
    } else {
        $accion = $_POST['accion'] ?? '';
        $idEmpresa = (int) ($_POST['id_empresa'] ?? 0);

        if ($accion === 'aprobar') {
            $resultado = $admin->aprobarEmpresa($idEmpresa, $idAdmin);
            $mensajeExito = 'La empresa fue aprobada correctamente.';
        } elseif ($accion === 'suspender') {
            $resultado = $admin->suspenderEmpresa($idEmpresa);
            $mensajeExito = 'La empresa fue suspendida correctamente.';
        } else {
            $resultado = [
                'exito' => false,
                'errores' => ['La acción indicada no es válida.']
            ];
            $mensajeExito = '';
        }

        if ($resultado['exito']) {
            AdminController::guardarAviso('success', $mensajeExito);
        } else {
            $errores = $resultado['errores'] ?? ['No se pudo completar la acción.'];
            AdminController::guardarAviso('danger', implode(' ', $errores));
        }
    }

    header('Location: empresas.php');
    exit;
}

$empresas = $admin->listarEmpresas();
$aviso = AdminController::tomarAviso();
$csrf = AdminController::tokenCsrf();
$paginaActual = 'empresas';

$etiquetasEstado = [
    'pendiente' => 'Pendiente',
    'activa' => 'Activa',
    'suspendida' => 'Suspendida',
    'rechazada' => 'Rechazada',
    'inactiva' => 'Inactiva'
];

$clasesEstado = [
    'pendiente' => 'text-bg-warning',
    'activa' => 'text-bg-success',
    'suspendida' => 'text-bg-danger',
    'rechazada' => 'text-bg-secondary',
    'inactiva' => 'text-bg-secondary'
];
?>
<!DOCTYPE html>
<html lang="es">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Empresas — Administración</title>

  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=Big+Shoulders+Display:wght@700;900&family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">

  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
  <link rel="stylesheet" href="../../../public/assets/css/estilos.css">
  <link rel="stylesheet" href="../../../public/assets/css/cliente.css">
</head>
<body>

<?php require __DIR__ . '/../partials/nav_admin.php'; ?>

<main class="container">
  <h1>Empresas</h1>
  <p class="intro">
    Revisa las empresas registradas y gestiona su aprobación o suspensión.
  </p>

  <?php if ($aviso): ?>
    <div class="alert alert-<?= h($aviso['tipo']) ?> alert-dismissible fade show" role="alert">
      <?= h($aviso['texto']) ?>
      <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Cerrar"></button>
    </div>
  <?php endif; ?>

  <section class="panel">
    <?php if (empty($empresas)): ?>
      <p class="mb-0">Todavía no hay empresas registradas.</p>
    <?php else: ?>
      <div class="table-responsive">
        <table class="table align-middle">
          <thead>
            <tr>
              <th>Empresa</th>
              <th>Identificación</th>
              <th>Propietario</th>
              <th>Contacto</th>
              <th>Estado</th>
              <th>Registro</th>
              <th>Acciones</th>
            </tr>
          </thead>
          <tbody>
            <?php foreach ($empresas as $empresa): ?>
              <?php
              $estado = $empresa['estado'];
              $etiqueta = $etiquetasEstado[$estado] ?? $estado;
              $clase = $clasesEstado[$estado] ?? 'text-bg-secondary';
              ?>
              <tr>
                <td><?= h($empresa['nombre_comercial']) ?></td>
                <td><?= h($empresa['identificacion']) ?></td>
                <td><?= h($empresa['nombre_propietario']) ?></td>
                <td>
                  <div><?= h($empresa['correo']) ?></div>
                  <small><?= h($empresa['telefono'] ?? '') ?></small>
                </td>
                <td>
                  <span class="badge <?= h($clase) ?>">
                    <?= h($etiqueta) ?>
                  </span>
                </td>
                <td><?= h(date('d/m/Y', strtotime($empresa['fecha_registro']))) ?></td>
                <td>
                  <div class="d-flex flex-wrap gap-2">
                    <?php if ($estado === 'pendiente'): ?>
                      <form method="POST" action="empresas.php">
                        <input type="hidden" name="csrf" value="<?= h($csrf) ?>">
                        <input type="hidden" name="accion" value="aprobar">
                        <input type="hidden" name="id_empresa" value="<?= (int) $empresa['id_empresa'] ?>">
                        <button class="btn btn-sm btn-mostaza" type="submit">
                          Aprobar
                        </button>
                      </form>
                    <?php endif; ?>

                    <?php if ($estado === 'activa'): ?>
                      <form method="POST" action="empresas.php"
                            onsubmit="return confirm('¿Suspender esta empresa?');">
                        <input type="hidden" name="csrf" value="<?= h($csrf) ?>">
                        <input type="hidden" name="accion" value="suspender">
                        <input type="hidden" name="id_empresa" value="<?= (int) $empresa['id_empresa'] ?>">
                        <button class="btn btn-sm btn-peligro" type="submit">
                          Suspender
                        </button>
                      </form>
                    <?php endif; ?>

                    <?php if (!in_array($estado, ['pendiente', 'activa'], true)): ?>
                      <span class="text-muted">Sin acciones disponibles</span>
                    <?php endif; ?>
                  </div>
                </td>
              </tr>

              <?php if (!empty($empresa['motivo_rechazo'])): ?>
                <tr>
                  <td colspan="7">
                    <small>
                      Motivo registrado: <?= h($empresa['motivo_rechazo']) ?>
                    </small>
                  </td>
                </tr>
              <?php endif; ?>
            <?php endforeach; ?>
          </tbody>
        </table>
      </div>
    <?php endif; ?>
  </section>
</main>

<footer class="pie">
  <div class="container">
    <span>MudaConecta</span>
  </div>
</footer>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
