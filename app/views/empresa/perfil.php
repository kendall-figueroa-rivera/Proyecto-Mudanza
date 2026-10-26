<?php
/**
 * Vista: perfil.php
 * Modulo: Empresa
 * Responsable: Santiago Correa Fallas
 * Descripcion: Consulta y edicion del perfil de la empresa. La identificacion
 *              (cedula juridica) y el estado solo se muestran, no se editan.
 */

session_start();
require_once __DIR__ . '/../../../config/database.php';
require_once __DIR__ . '/../../controllers/EmpresaController.php';

$idUsuario = EmpresaController::exigirEmpresa();
$controlador = new EmpresaController($pdo);
$errores   = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!EmpresaController::validarCsrf($_POST['csrf'] ?? null)) {
        $errores[] = 'La sesión del formulario expiró. Vuelve a intentarlo.';
    } else {
        $resultado = $controlador->actualizarPerfil($idUsuario, $_POST);
        if ($resultado['exito']) {
            EmpresaController::guardarAviso('success', 'Perfil actualizado correctamente.');
            header('Location: perfil.php');
            exit;
        }
        $errores = $resultado['errores'];
    }
}

$empresa = $controlador->obtenerEmpresaPorUsuario($idUsuario);
$aviso = EmpresaController::tomarAviso();
$csrf  = EmpresaController::tokenCsrf();
$paginaActual = 'perfil';
$estadosEmpresa = [
    'pendiente' => 'Pendiente', 'activa' => 'Activa', 'rechazada' => 'Rechazada',
    'inactiva' => 'Inactiva', 'suspendida' => 'Suspendida',
];
// Al reintentar un POST con errores se conservan los datos escritos
$valor = fn(string $campo) => $_POST[$campo] ?? ($empresa[$campo] ?? '');
?>
<!DOCTYPE html>
<html lang="es">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Perfil de la empresa — MudaConecta</title>
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

  <h1>Perfil de la empresa</h1>
  <p class="intro">Esta información es la que ven los clientes en el directorio.</p>

  <?php if (!$empresa): ?>
    <div class="alert alert-warning">Tu usuario no tiene una empresa asociada. Contacta a soporte.</div>
  <?php else: ?>
    <form class="panel" method="POST" action="perfil.php" id="formPerfil" novalidate>
      <input type="hidden" name="csrf" value="<?= h($csrf) ?>">

      <div class="row g-3 mb-3">
        <div class="col-md-6">
          <label class="form-label" for="identificacion">Identificación (cédula jurídica)</label>
          <input class="form-control" type="text" id="identificacion" value="<?= h($empresa['identificacion']) ?>" disabled>
        </div>
        <div class="col-md-6">
          <label class="form-label" for="estado_empresa">Estado de la cuenta</label>
          <input class="form-control" type="text" id="estado_empresa"
                 value="<?= h($estadosEmpresa[$empresa['estado']] ?? $empresa['estado']) ?>" disabled>
        </div>
      </div>
      <div class="mb-3">
        <label class="form-label" for="nombre_comercial">Nombre comercial</label>
        <input class="form-control" type="text" id="nombre_comercial" name="nombre_comercial" maxlength="150" required
               value="<?= h($valor('nombre_comercial')) ?>">
        <div class="invalid-feedback">Escribe el nombre comercial.</div>
      </div>
      <div class="row g-3 mb-3">
        <div class="col-md-5">
          <label class="form-label" for="telefono">Teléfono</label>
          <input class="form-control" type="text" id="telefono" name="telefono" maxlength="20"
                 value="<?= h($valor('telefono')) ?>">
          <div class="invalid-feedback">Usa solo números, espacios, + y guiones (8 a 20 caracteres).</div>
        </div>
        <div class="col-md-7">
          <label class="form-label" for="direccion">Dirección</label>
          <input class="form-control" type="text" id="direccion" name="direccion" maxlength="255"
                 value="<?= h($valor('direccion')) ?>">
        </div>
      </div>
      <div class="mb-4">
        <label class="form-label" for="descripcion">Descripción</label>
        <textarea class="form-control" id="descripcion" name="descripcion" rows="4" maxlength="500"
                  data-contador="contadorDescripcion"><?= h($valor('descripcion')) ?></textarea>
        <div class="form-text" id="contadorDescripcion"></div>
      </div>
      <button class="btn btn-mostaza" type="submit">Guardar cambios</button>
      <a class="btn btn-contorno" href="flota.php">Agregar o modificar flota</a>
      <a class="btn btn-contorno" href="../publico/home.php">Volver al inicio</a>
    </form>
  <?php endif; ?>
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
