<?php
/**
 * Vista: flota.php
 * Modulo: Empresa
 * Responsable: Santiago Correa Fallas
 * Descripcion: CRUD de la flota de la empresa. Lista los vehiculos activos y
 *              permite agregar, editar (con ?editar=N) y eliminar
 *              logicamente (activo = 0) vehiculos.
 */

session_start();
require_once __DIR__ . '/../../../config/database.php';
require_once __DIR__ . '/../../controllers/EmpresaController.php';

$idUsuario   = EmpresaController::exigirEmpresa();
$controlador = new EmpresaController($pdo);
$empresa     = $controlador->obtenerEmpresaPorUsuario($idUsuario);
$errores     = [];

if (!$empresa) {
    EmpresaController::guardarAviso('danger', 'Tu usuario no tiene una empresa asociada.');
    header('Location: perfil.php');
    exit;
}
$idEmpresa = (int) $empresa['id_empresa'];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $accion = $_POST['accion'] ?? '';
    $idVehiculo = (int) ($_POST['id_vehiculo'] ?? 0);

    if (!EmpresaController::validarCsrf($_POST['csrf'] ?? null)) {
        $errores[] = 'La sesión del formulario expiró. Vuelve a intentarlo.';
    } elseif ($accion === 'agregar') {
        $resultado = $controlador->agregarVehiculo($idEmpresa, $_POST);
        if ($resultado['exito']) {
            EmpresaController::guardarAviso('success', 'Vehículo agregado a la flota.');
            header('Location: flota.php');
            exit;
        }
        $errores = $resultado['errores'];
    } elseif ($accion === 'editar') {
        $resultado = $controlador->editarVehiculo($idEmpresa, $idVehiculo, $_POST);
        if ($resultado['exito']) {
            EmpresaController::guardarAviso('success', 'Vehículo actualizado.');
            header('Location: flota.php');
            exit;
        }
        $errores = $resultado['errores'];
    } elseif ($accion === 'eliminar') {
        $resultado = $controlador->eliminarVehiculo($idEmpresa, $idVehiculo);
        if ($resultado['exito']) {
            EmpresaController::guardarAviso('success', 'Vehículo eliminado de la flota.');
        } else {
            EmpresaController::guardarAviso('danger', implode(' ', $resultado['errores']));
        }
        header('Location: flota.php');
        exit;
    }
}

// Vehiculo que se esta editando (por GET o al reintentar el POST de edicion)
$idEditar = (int) ($_POST['id_vehiculo'] ?? $_GET['editar'] ?? 0);
$vehiculoEditar = $idEditar > 0 ? $controlador->obtenerVehiculo($idEmpresa, $idEditar) : null;
$editando = $vehiculoEditar !== null;

// Datos del formulario: los del POST si hubo error, o los del vehiculo a editar
$form = $_SERVER['REQUEST_METHOD'] === 'POST' ? $_POST : ($vehiculoEditar ?? []);

$flota = $controlador->listarFlota($idEmpresa);
$aviso = EmpresaController::tomarAviso();
$csrf  = EmpresaController::tokenCsrf();
$paginaActual = 'flota';
?>
<!DOCTYPE html>
<html lang="es">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Flota — MudaConecta</title>
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=Big+Shoulders+Display:wght@700;900&family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
  <link rel="stylesheet" href="../../../public/assets/css/estilos.css">
  <link rel="stylesheet" href="../../../public/assets/css/cliente.css">
</head>
<body>
<?php require __DIR__ . '/../partials/nav_empresa.php'; ?>

<main class="container">
  <?php if ($aviso): ?>
    <div class="alert alert-<?= h($aviso['tipo']) ?>"><?= h($aviso['texto']) ?></div>
  <?php endif; ?>
  <?php if (!empty($errores)): ?>
    <div class="alert alert-danger">
      <?php foreach ($errores as $e): ?><?= h($e) ?><br><?php endforeach; ?>
    </div>
  <?php endif; ?>

  <h1>Mi flota</h1>
  <p class="intro">Administra los vehículos de tu empresa.</p>

  <div class="row g-4">
    <div class="col-lg-4">
      <form class="panel" method="POST" action="flota.php" id="formVehiculo" novalidate>
        <h2 class="h5 mb-3"><?= $editando ? 'Editar vehículo' : 'Agregar vehículo' ?></h2>
        <input type="hidden" name="csrf" value="<?= h($csrf) ?>">
        <input type="hidden" name="accion" value="<?= $editando ? 'editar' : 'agregar' ?>">
        <?php if ($editando): ?>
          <input type="hidden" name="id_vehiculo" value="<?= (int) $vehiculoEditar['id_vehiculo'] ?>">
        <?php endif; ?>

        <div class="mb-3">
          <label class="form-label" for="placa">Placa</label>
          <input class="form-control" type="text" id="placa" name="placa" maxlength="15" required
                 value="<?= h($form['placa'] ?? '') ?>">
          <div class="invalid-feedback">Entre 3 y 15 letras, números o guiones.</div>
        </div>
        <div class="mb-3">
          <label class="form-label" for="tipo_vehiculo">Tipo de vehículo</label>
          <input class="form-control" type="text" id="tipo_vehiculo" name="tipo_vehiculo" maxlength="50" required
                 placeholder="Ej. Camión cerrado 4 toneladas" value="<?= h($form['tipo_vehiculo'] ?? '') ?>">
          <div class="invalid-feedback">Escribe el tipo de vehículo.</div>
        </div>
        <div class="mb-3">
          <label class="form-label" for="capacidad_kg">Capacidad (kg)</label>
          <input class="form-control" type="text" inputmode="decimal" id="capacidad_kg" name="capacidad_kg" required
                 value="<?= h($form['capacidad_kg'] ?? '') ?>">
          <div class="invalid-feedback">Escribe un número mayor que cero.</div>
        </div>
        <div class="mb-4">
          <label class="form-label" for="estado">Estado</label>
          <select class="form-select" id="estado" name="estado" required>
            <?php foreach (EmpresaController::ESTADOS_VEHICULO as $clave => $texto): ?>
              <option value="<?= h($clave) ?>" <?= ($form['estado'] ?? 'disponible') === $clave ? 'selected' : '' ?>><?= h($texto) ?></option>
            <?php endforeach; ?>
          </select>
        </div>
        <div class="d-flex gap-2 flex-wrap">
          <button class="btn btn-mostaza" type="submit"><?= $editando ? 'Guardar cambios' : 'Agregar vehículo' ?></button>
          <?php if ($editando): ?>
            <a class="btn btn-contorno" href="flota.php">Cancelar</a>
          <?php endif; ?>
        </div>
      </form>
    </div>

    <div class="col-lg-8">
      <?php if (empty($flota)): ?>
        <div class="vacio"><p>Todavía no tienes vehículos registrados.</p></div>
      <?php else: ?>
        <?php foreach ($flota as $v): ?>
          <article class="boleto <?= $v['estado'] === 'disponible' ? 'aceptada' : ($v['estado'] === 'en_servicio' ? 'pendiente' : 'cancelada') ?>">
            <div class="row g-3 align-items-center">
              <div class="col-md-8">
                <div class="meta">Placa <?= h($v['placa']) ?></div>
                <div class="empresa"><?= h($v['tipo_vehiculo']) ?></div>
                <div class="meta">
                  Capacidad: <?= h(number_format((float) $v['capacidad_kg'], 2, ',', '.')) ?> kg ·
                  <?= h(EmpresaController::ESTADOS_VEHICULO[$v['estado']] ?? $v['estado']) ?>
                </div>
              </div>
              <div class="col-md-4 text-md-end d-flex gap-2 justify-content-md-end flex-wrap">
                <a class="btn btn-sm btn-contorno" href="flota.php?editar=<?= (int) $v['id_vehiculo'] ?>">Editar</a>
                <form method="POST" action="flota.php" class="form-eliminar" data-placa="<?= h($v['placa']) ?>">
                  <input type="hidden" name="csrf" value="<?= h($csrf) ?>">
                  <input type="hidden" name="accion" value="eliminar">
                  <input type="hidden" name="id_vehiculo" value="<?= (int) $v['id_vehiculo'] ?>">
                  <button class="btn btn-sm btn-peligro" type="submit">Eliminar</button>
                </form>
              </div>
            </div>
          </article>
        <?php endforeach; ?>
      <?php endif; ?>
    </div>
  </div>
</main>

<footer class="pie">
  <div class="container d-flex justify-content-between flex-wrap gap-2">
    <span>MudaConecta</span>
  </div>
</footer>

<script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script src="../../../public/assets/js/empresa.js"></script>
</body>
</html>
