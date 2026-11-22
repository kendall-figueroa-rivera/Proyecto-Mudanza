<?php
/**
 * Vista: mi_cuenta.php
 * Modulo: Autenticacion
 * Responsable: Kendall Figueroa
 * Descripcion: Pagina "Mi cuenta" para cliente, empresa y administrador:
 *              editar nombre y telefono, y cambiar la contrasena. Muestra el
 *              menu del panel que corresponde al rol de la sesion.
 */

session_start();
require_once __DIR__ . '/../../../config/database.php';
require_once __DIR__ . '/../../controllers/AuthController.php';

$idUsuario = AuthController::exigirSesion();

// Menu del panel segun el rol de quien inicio sesion
$navPorRol = [
    'cliente'       => 'nav_cliente.php',
    'empresa'       => 'nav_empresa.php',
    'administrador' => 'nav_admin.php',
];
$nombresRol = ['cliente' => 'Cliente', 'empresa' => 'Empresa', 'administrador' => 'Administrador'];
$rolSesion  = $_SESSION['rol'] ?? '';
if (!isset($navPorRol[$rolSesion])) {
    header('Location: login.php');
    exit;
}

$auth = new AuthController($pdo);
$erroresPerfil = [];
$erroresContrasena = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!AuthController::validarCsrf($_POST['csrf'] ?? null)) {
        $erroresPerfil[] = 'La sesión del formulario expiró. Vuelve a intentarlo.';
    } elseif (($_POST['accion'] ?? '') === 'perfil') {
        $resultado = $auth->actualizarPerfil($idUsuario, $_POST);
        if ($resultado['exito']) {
            AuthController::guardarAviso('success', 'Tus datos se actualizaron correctamente.');
            header('Location: mi_cuenta.php');
            exit;
        }
        $erroresPerfil = $resultado['errores'];
    } elseif (($_POST['accion'] ?? '') === 'contrasena') {
        $resultado = $auth->cambiarContrasena($idUsuario, $_POST);
        if ($resultado['exito']) {
            AuthController::guardarAviso('success', 'Tu contraseña se cambió correctamente.');
            header('Location: mi_cuenta.php');
            exit;
        }
        $erroresContrasena = $resultado['errores'];
    }
}

$usuario = $auth->obtenerUsuario($idUsuario);
if (!$usuario) {
    header('Location: login.php');
    exit;
}

$aviso = AuthController::tomarAviso();
$csrf  = AuthController::tokenCsrf();
$paginaActual = 'mi_cuenta';

// Si un POST con errores vuelve a mostrar el formulario, se conservan los datos escritos
$valor = fn(string $campo) => ($_POST['accion'] ?? '') === 'perfil' ? ($_POST[$campo] ?? '') : ($usuario[$campo] ?? '');
// La fecha puede llegar como texto o como objeto DateTime segun el driver
$registro = $usuario['fecha_registro'] ?? null;
$desde = $registro instanceof DateTimeInterface
    ? $registro->format('d/m/Y')
    : ($registro ? date('d/m/Y', strtotime((string) $registro)) : '');
?>
<!DOCTYPE html>
<html lang="es">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Mi cuenta — MudaConecta</title>
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=Big+Shoulders+Display:wght@700;900&family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
  <link rel="stylesheet" href="../../../public/assets/css/estilos.css">
  <link rel="stylesheet" href="../../../public/assets/css/cliente.css">
</head>
<body>
<?php require __DIR__ . '/../partials/' . $navPorRol[$rolSesion]; ?>

<main class="container">
  <?php if ($aviso): ?>
    <div class="alert alert-<?= h($aviso['tipo']) ?>"><?= h($aviso['texto']) ?></div>
  <?php endif; ?>

  <h1>Mi cuenta</h1>
  <p class="intro">Actualiza tus datos y cambia tu contraseña.</p>

  <div class="row g-4">
    <div class="col-lg-6">
      <form class="panel" method="POST" action="mi_cuenta.php" id="formPerfil" novalidate>
        <input type="hidden" name="csrf" value="<?= h($csrf) ?>">
        <input type="hidden" name="accion" value="perfil">
        <h2>Mis datos</h2>

        <?php if (!empty($erroresPerfil)): ?>
          <div class="alert alert-danger">
            <?php foreach ($erroresPerfil as $e): ?><?= h($e) ?><br><?php endforeach; ?>
          </div>
        <?php endif; ?>

        <div class="row g-3 mb-3">
          <div class="col-md-6">
            <label class="form-label" for="rol">Tipo de cuenta</label>
            <input class="form-control" type="text" id="rol" value="<?= h($nombresRol[$usuario['rol']] ?? $usuario['rol']) ?>" disabled>
          </div>
          <div class="col-md-6">
            <label class="form-label" for="desde">Miembro desde</label>
            <input class="form-control" type="text" id="desde" value="<?= h($desde) ?>" disabled>
          </div>
        </div>
        <div class="mb-3">
          <label class="form-label" for="correo">Correo electrónico</label>
          <input class="form-control" type="email" id="correo" value="<?= h($usuario['correo']) ?>" disabled>
          <div class="form-text">El correo es tu usuario y no se puede cambiar.</div>
        </div>
        <div class="mb-3">
          <label class="form-label" for="nombre">Nombre</label>
          <input class="form-control" type="text" id="nombre" name="nombre" maxlength="100" required
                 value="<?= h($valor('nombre')) ?>">
          <div class="invalid-feedback">Escribe tu nombre.</div>
        </div>
        <div class="mb-4">
          <label class="form-label" for="telefono">Teléfono</label>
          <input class="form-control" type="text" id="telefono" name="telefono" maxlength="20"
                 value="<?= h($valor('telefono')) ?>">
          <div class="invalid-feedback">Usa solo números, espacios, + y guiones (8 a 20 caracteres).</div>
        </div>
        <button class="btn btn-mostaza" type="submit">Guardar cambios</button>
      </form>
    </div>

    <div class="col-lg-6">
      <form class="panel" method="POST" action="mi_cuenta.php" id="formContrasena" novalidate>
        <input type="hidden" name="csrf" value="<?= h($csrf) ?>">
        <input type="hidden" name="accion" value="contrasena">
        <h2>Cambiar contraseña</h2>

        <?php if (!empty($erroresContrasena)): ?>
          <div class="alert alert-danger">
            <?php foreach ($erroresContrasena as $e): ?><?= h($e) ?><br><?php endforeach; ?>
          </div>
        <?php endif; ?>

        <div class="mb-3">
          <label class="form-label" for="password_actual">Contraseña actual</label>
          <input class="form-control" type="password" id="password_actual" name="password_actual"
                 autocomplete="current-password" required>
          <div class="invalid-feedback">Escribe tu contraseña actual.</div>
        </div>
        <div class="mb-3">
          <label class="form-label" for="password_nueva">Contraseña nueva</label>
          <input class="form-control" type="password" id="password_nueva" name="password_nueva"
                 autocomplete="new-password" minlength="8" required>
          <div class="form-text">Mínimo 8 caracteres.</div>
          <div class="invalid-feedback">La contraseña nueva debe tener al menos 8 caracteres.</div>
        </div>
        <div class="mb-4">
          <label class="form-label" for="password_confirmar">Confirmar contraseña nueva</label>
          <input class="form-control" type="password" id="password_confirmar" name="password_confirmar"
                 autocomplete="new-password" required>
          <div class="invalid-feedback">La confirmación no coincide con la contraseña nueva.</div>
        </div>
        <button class="btn btn-mostaza" type="submit">Cambiar contraseña</button>
      </form>
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
<script src="../../../public/assets/js/mi_cuenta.js"></script>
</body>
</html>
