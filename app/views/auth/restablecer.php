<?php
/**
 * Vista: restablecer.php
 * Modulo: Autenticacion
 * Responsable: Kendall Figueroa
 * Descripcion: Formulario para crear una contrasena nueva con el enlace enviado
 *              por correo. El enlace vence a los 30 minutos y sirve una sola vez.
 */

session_start();
require_once __DIR__ . '/../../../config/database.php';
require_once __DIR__ . '/../../controllers/AuthController.php';

$auth = new AuthController($pdo);
$token = (string) ($_POST['token'] ?? $_GET['token'] ?? '');
$errores = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!AuthController::validarCsrf($_POST['csrf'] ?? null)) {
        $errores[] = 'La sesión del formulario expiró. Volvé a intentarlo.';
    } else {
        $resultado = $auth->restablecerContrasena(
            $token,
            (string) ($_POST['password'] ?? ''),
            (string) ($_POST['password_confirm'] ?? '')
        );
        if ($resultado['exito']) {
            header('Location: login.php?restablecida=1');
            exit;
        }
        $errores = $resultado['errores'];
    }
}

// El enlace se revisa en cada carga: si ya se uso o vencio, no se muestra el formulario
$enlaceValido = $auth->validarTokenRecuperacion($token) !== null;
$csrf = AuthController::tokenCsrf();
?>
<!DOCTYPE html>
<html lang="es">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<meta name="referrer" content="no-referrer">
<meta name="robots" content="noindex">
<title>Nueva contraseña — MudaConecta</title>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Big+Shoulders+Display:wght@700;900&family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
<link rel="stylesheet" href="../../../public/assets/css/login.css">
<link rel="stylesheet" href="../../../public/assets/css/recuperacion.css">
</head>
<body>

<div class="nav">
  <a href="../publico/home.php" class="brand">Muda<span>Conecta</span></a>
</div>

<div class="contenido">
  <div class="lado-visual">
    <h1>Creá tu nueva contraseña</h1>
    <p>Elegí una contraseña de al menos 8 caracteres. Después vas a poder iniciar sesión con ella.</p>
    <div class="ruta-puntos">
      <span>Origen</span><span class="linea"></span><span>Destino</span>
    </div>
  </div>

  <div class="lado-form">
    <div class="card">
      <h2>Nueva contraseña</h2>

      <?php if (!$enlaceValido): ?>
        <p class="sub">No pudimos usar este enlace.</p>
        <div class="alerta alerta-error">
          El enlace no es válido o ya venció. Los enlaces duran 30 minutos y se pueden usar una sola vez.
        </div>
        <div class="enlaces">
          <p><a href="recuperar.php">Pedir un enlace nuevo</a></p>
          <p><a href="login.php">Volver a iniciar sesión</a></p>
        </div>
      <?php else: ?>
        <p class="sub">Escribila dos veces para confirmar.</p>

        <?php if (!empty($errores)): ?>
          <div class="alerta alerta-error">
            <?php foreach ($errores as $e) echo h($e) . '<br>'; ?>
          </div>
        <?php endif; ?>

        <form method="POST" id="formRestablecer" novalidate>
          <input type="hidden" name="csrf" value="<?= h($csrf) ?>">
          <input type="hidden" name="token" value="<?= h($token) ?>">
          <div class="campo">
            <label for="password">Contraseña nueva</label>
            <input type="password" name="password" id="password" required minlength="8" autocomplete="new-password">
            <div class="msg-error">La contraseña debe tener al menos 8 caracteres.</div>
          </div>
          <div class="campo">
            <label for="password_confirm">Confirmar contraseña</label>
            <input type="password" name="password_confirm" id="password_confirm" required autocomplete="new-password">
            <div class="msg-error">Las contraseñas no coinciden.</div>
          </div>
          <button type="submit" class="btn-enviar">Cambiar contraseña</button>
        </form>
      <?php endif; ?>
    </div>
  </div>
</div>

<script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>
<script src="../../../public/assets/js/validaciones.js"></script>
</body>
</html>
