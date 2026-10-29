<?php
/**
 * Vista: tarifas.php
 * Modulo: Empresa
 * Responsable: Santiago Correa Fallas
 * Descripcion: Gestion de las rutas y tarifas de la empresa. Permite crear
 *              rutas (origen y destino), editar distancia y precios (con
 *              ?editar=N) y eliminarlas logicamente (activo = 0).
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
    $accion   = $_POST['accion'] ?? '';
    $idTarifa = (int) ($_POST['id_tarifa'] ?? 0);

    if (!EmpresaController::validarCsrf($_POST['csrf'] ?? null)) {
        $errores[] = 'La sesión del formulario expiró. Vuelve a intentarlo.';
    } elseif ($accion === 'agregar') {
        $resultado = $controlador->agregarTarifa($idEmpresa, $_POST);
        if ($resultado['exito']) {
            EmpresaController::guardarAviso('success', 'Ruta agregada.');
            header('Location: tarifas.php');
            exit;
        }
        $errores = $resultado['errores'];
    } elseif ($accion === 'editar') {
        $resultado = $controlador->editarTarifa($idEmpresa, $idTarifa, $_POST);
        if ($resultado['exito']) {
            EmpresaController::guardarAviso('success', 'Ruta actualizada.');
            header('Location: tarifas.php');
            exit;
        }
        $errores = $resultado['errores'];
    } elseif ($accion === 'eliminar') {
        $resultado = $controlador->eliminarTarifa($idEmpresa, $idTarifa);
        if ($resultado['exito']) {
            EmpresaController::guardarAviso('success', 'Ruta eliminada.');
        } else {
            EmpresaController::guardarAviso('danger', implode(' ', $resultado['errores']));
        }
        header('Location: tarifas.php');
        exit;
    }
}

// Ruta que se esta editando (por GET o al reintentar el POST de edicion)
$idEditar = (int) ($_POST['id_tarifa'] ?? $_GET['editar'] ?? 0);
$tarifaEditar = $idEditar > 0 ? $controlador->obtenerTarifa($idEmpresa, $idEditar) : null;
$editando = $tarifaEditar !== null;

// Datos del formulario: los del POST si hubo error, o los de la ruta a editar
$form = $_SERVER['REQUEST_METHOD'] === 'POST' ? $_POST : ($tarifaEditar ?? []);

$tarifas = $controlador->listarTarifas($idEmpresa);
$aviso = EmpresaController::tomarAviso();
$csrf  = EmpresaController::tokenCsrf();
$paginaActual = 'tarifas';
$colones = fn($n) => '₡' . number_format((float) $n, 2, ',', '.');
?>
<!DOCTYPE html>
<html lang="es">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Rutas y tarifas — MudaConecta</title>
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

  <h1>Rutas y tarifas</h1>
  <p class="intro">Define las rutas que cubre tu empresa. El precio final se calcula como tarifa base + precio por kilómetro × distancia.</p>

  <div class="row g-4">
    <div class="col-lg-4">
      <form class="panel" method="POST" action="tarifas.php" id="formTarifa" novalidate>
        <h2 class="h5 mb-3"><?= $editando ? 'Editar ruta' : 'Agregar ruta' ?></h2>
        <input type="hidden" name="csrf" value="<?= h($csrf) ?>">
        <input type="hidden" name="accion" value="<?= $editando ? 'editar' : 'agregar' ?>">

        <?php if ($editando): ?>
          <input type="hidden" name="id_tarifa" value="<?= (int) $tarifaEditar['id_tarifa'] ?>">
          <p class="mb-3"><strong><?= h($tarifaEditar['zona_origen']) ?> a <?= h($tarifaEditar['zona_destino']) ?></strong></p>
        <?php else: ?>
          <div class="mb-3">
            <label class="form-label" for="origen">Origen</label>
            <input class="form-control" type="text" id="origen" name="origen" maxlength="100" required
                   placeholder="Ej. Curridabat" value="<?= h($form['origen'] ?? '') ?>">
            <div class="invalid-feedback">Escribe el origen.</div>
          </div>
          <div class="mb-3">
            <label class="form-label" for="provincia_origen">Provincia del origen</label>
            <select class="form-select" id="provincia_origen" name="provincia_origen" required>
              <option value="">Elige una provincia</option>
              <?php foreach (EmpresaController::PROVINCIAS as $p): ?>
                <option value="<?= h($p) ?>" <?= ($form['provincia_origen'] ?? '') === $p ? 'selected' : '' ?>><?= h($p) ?></option>
              <?php endforeach; ?>
            </select>
            <div class="invalid-feedback">Elige una provincia.</div>
          </div>
          <div class="mb-3">
            <label class="form-label" for="destino">Destino</label>
            <input class="form-control" type="text" id="destino" name="destino" maxlength="100" required
                   placeholder="Ej. Heredia Centro" value="<?= h($form['destino'] ?? '') ?>">
            <div class="invalid-feedback">Escribe el destino.</div>
          </div>
          <div class="mb-3">
            <label class="form-label" for="provincia_destino">Provincia del destino</label>
            <select class="form-select" id="provincia_destino" name="provincia_destino" required>
              <option value="">Elige una provincia</option>
              <?php foreach (EmpresaController::PROVINCIAS as $p): ?>
                <option value="<?= h($p) ?>" <?= ($form['provincia_destino'] ?? '') === $p ? 'selected' : '' ?>><?= h($p) ?></option>
              <?php endforeach; ?>
            </select>
            <div class="invalid-feedback">Elige una provincia.</div>
          </div>
        <?php endif; ?>

        <div class="mb-3">
          <label class="form-label" for="distancia_km">Distancia (km)</label>
          <input class="form-control" type="text" inputmode="decimal" id="distancia_km" name="distancia_km" required
                 value="<?= h($form['distancia_km'] ?? '') ?>">
          <div class="invalid-feedback">Escribe un número mayor que cero.</div>
        </div>
        <div class="mb-3">
          <label class="form-label" for="precio_base">Tarifa base (₡)</label>
          <input class="form-control" type="text" inputmode="decimal" id="precio_base" name="precio_base" required
                 value="<?= h($form['precio_base'] ?? '') ?>">
          <div class="invalid-feedback">Escribe un número igual o mayor que cero.</div>
        </div>
        <div class="mb-4">
          <label class="form-label" for="precio_por_km">Precio por km (₡)</label>
          <input class="form-control" type="text" inputmode="decimal" id="precio_por_km" name="precio_por_km" required
                 value="<?= h($form['precio_por_km'] ?? '') ?>">
          <div class="invalid-feedback">Escribe un número igual o mayor que cero.</div>
        </div>
        <div class="d-flex gap-2 flex-wrap">
          <button class="btn btn-mostaza" type="submit"><?= $editando ? 'Guardar cambios' : 'Agregar ruta' ?></button>
          <?php if ($editando): ?>
            <a class="btn btn-contorno" href="tarifas.php">Cancelar</a>
          <?php endif; ?>
        </div>
      </form>
    </div>

    <div class="col-lg-8">
      <?php if (empty($tarifas)): ?>
        <div class="vacio"><p>Todavía no tienes rutas registradas.</p></div>
      <?php else: ?>
        <?php foreach ($tarifas as $t): ?>
          <article class="boleto aceptada">
            <div class="row g-3 align-items-center">
              <div class="col-md-8">
                <div class="empresa"><?= h($t['zona_origen']) ?> a <?= h($t['zona_destino']) ?></div>
                <div class="meta">
                  <?= h($t['provincia_origen']) ?> → <?= h($t['provincia_destino']) ?> ·
                  <?= h(number_format((float) $t['distancia_km'], 2, ',', '.')) ?> km
                </div>
                <div class="meta">
                  Base <?= h($colones($t['precio_base'])) ?> ·
                  Por km <?= h($colones($t['precio_por_km'])) ?> ·
                  <strong>Estimado <?= h($colones($t['monto_estimado'])) ?></strong>
                </div>
              </div>
              <div class="col-md-4 text-md-end d-flex gap-2 justify-content-md-end flex-wrap">
                <a class="btn btn-sm btn-contorno" href="tarifas.php?editar=<?= (int) $t['id_tarifa'] ?>">Editar</a>
                <form method="POST" action="tarifas.php" class="form-eliminar-ruta"
                      data-ruta="<?= h($t['zona_origen']) ?> a <?= h($t['zona_destino']) ?>">
                  <input type="hidden" name="csrf" value="<?= h($csrf) ?>">
                  <input type="hidden" name="accion" value="eliminar">
                  <input type="hidden" name="id_tarifa" value="<?= (int) $t['id_tarifa'] ?>">
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
