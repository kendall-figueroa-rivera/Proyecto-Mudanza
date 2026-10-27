<?php
/**
 * Vista: zonas.php
 * Modulo: Administrador
 * Responsable: Josué Azofeifa
 * Descripcion: Permite crear, editar y eliminar zonas geográficas.
 */

session_start();

require_once __DIR__ . '/../../../config/database.php';
require_once __DIR__ . '/../../controllers/AdminController.php';

AdminController::exigirAdministrador();
$admin = new AdminController($pdo);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!AdminController::validarCsrf($_POST['csrf'] ?? null)) {
        AdminController::guardarAviso('danger', 'La solicitud no es válida. Inténtalo de nuevo.');
    } else {
        $accion = $_POST['accion'] ?? '';
        $idZona = (int) ($_POST['id_zona'] ?? 0);

        if ($accion === 'crear') {
            $resultado = $admin->crearZona($_POST);
            $mensajeExito = 'La zona se agregó correctamente.';
        } elseif ($accion === 'actualizar') {
            $resultado = $admin->actualizarZona($idZona, $_POST);
            $mensajeExito = 'La zona se actualizó correctamente.';
        } elseif ($accion === 'eliminar') {
            $resultado = $admin->eliminarZona($idZona);
            $mensajeExito = 'La zona se eliminó correctamente.';
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

    header('Location: zonas.php');
    exit;
}

$zonaEditar = null;
$idEditar = (int) ($_GET['editar'] ?? 0);

if ($idEditar > 0) {
    $zonaEditar = $admin->obtenerZona($idEditar);

    if ($zonaEditar === null) {
        AdminController::guardarAviso('danger', 'No se encontró la zona seleccionada.');
        header('Location: zonas.php');
        exit;
    }
}

$zonas = $admin->listarZonas();
$aviso = AdminController::tomarAviso();
$csrf = AdminController::tokenCsrf();
$paginaActual = 'zonas';

$valorNombre = $zonaEditar['nombre'] ?? '';
$valorProvincia = $zonaEditar['provincia'] ?? '';
$valorCanton = $zonaEditar['canton'] ?? '';
$accionFormulario = $zonaEditar ? 'actualizar' : 'crear';
?>
<!DOCTYPE html>
<html lang="es">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Zonas — Administración</title>

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
  <h1>Catálogo de zonas</h1>
  <p class="intro">
    Agrega y mantén las zonas geográficas con su provincia y cantón.
  </p>

  <?php if ($aviso): ?>
    <div class="alert alert-<?= h($aviso['tipo']) ?> alert-dismissible fade show" role="alert">
      <?= h($aviso['texto']) ?>
      <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Cerrar"></button>
    </div>
  <?php endif; ?>

  <section class="panel mb-4">
    <h2><?= $zonaEditar ? 'Editar zona' : 'Agregar zona' ?></h2>

    <form method="POST" action="zonas.php">
      <input type="hidden" name="csrf" value="<?= h($csrf) ?>">
      <input type="hidden" name="accion" value="<?= h($accionFormulario) ?>">

      <?php if ($zonaEditar): ?>
        <input type="hidden" name="id_zona" value="<?= (int) $zonaEditar['id_zona'] ?>">
      <?php endif; ?>

      <div class="row g-3">
        <div class="col-md-4">
          <label class="form-label" for="nombre">Nombre de la zona</label>
          <input
            class="form-control"
            type="text"
            id="nombre"
            name="nombre"
            maxlength="100"
            value="<?= h($valorNombre) ?>"
            required
          >
        </div>

        <div class="col-md-4">
          <label class="form-label" for="provincia">Provincia</label>
          <input
            class="form-control"
            type="text"
            id="provincia"
            name="provincia"
            maxlength="100"
            value="<?= h($valorProvincia) ?>"
            required
          >
        </div>

        <div class="col-md-4">
          <label class="form-label" for="canton">Cantón</label>
          <input
            class="form-control"
            type="text"
            id="canton"
            name="canton"
            maxlength="100"
            value="<?= h($valorCanton ?? '') ?>"
          >
        </div>
      </div>

      <div class="d-flex gap-2 mt-3">
        <button class="btn btn-mostaza" type="submit">
          <?= $zonaEditar ? 'Guardar cambios' : 'Agregar zona' ?>
        </button>

        <?php if ($zonaEditar): ?>
          <a class="btn btn-contorno" href="zonas.php">Cancelar</a>
        <?php endif; ?>
      </div>
    </form>
  </section>

  <section class="panel">
    <h2>Zonas registradas</h2>

    <?php if (empty($zonas)): ?>
      <p class="mb-0">Todavía no hay zonas registradas.</p>
    <?php else: ?>
      <div class="table-responsive">
        <table class="table align-middle">
          <thead>
            <tr>
              <th>Nombre</th>
              <th>Provincia</th>
              <th>Cantón</th>
              <th>Acciones</th>
            </tr>
          </thead>
          <tbody>
            <?php foreach ($zonas as $zona): ?>
              <tr>
                <td><?= h($zona['nombre']) ?></td>
                <td><?= h($zona['provincia']) ?></td>
                <td><?= h($zona['canton'] ?? '') ?></td>
                <td>
                  <div class="d-flex flex-wrap gap-2">
                    <a
                      class="btn btn-sm btn-contorno"
                      href="zonas.php?editar=<?= (int) $zona['id_zona'] ?>"
                    >
                      Editar
                    </a>

                    <form
                      method="POST"
                      action="zonas.php"
                      onsubmit="return confirm('¿Eliminar esta zona?');"
                    >
                      <input type="hidden" name="csrf" value="<?= h($csrf) ?>">
                      <input type="hidden" name="accion" value="eliminar">
                      <input type="hidden" name="id_zona" value="<?= (int) $zona['id_zona'] ?>">
                      <button class="btn btn-sm btn-peligro" type="submit">
                        Eliminar
                      </button>
                    </form>
                  </div>
                </td>
              </tr>
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
