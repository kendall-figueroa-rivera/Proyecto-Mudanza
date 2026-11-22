<?php
/**
 * Vista: recuperar.php
 * Modulo: Autenticacion
 * Responsable: Kendall Figueroa
 * Descripcion: Formulario para pedir el enlace de recuperacion de contrasena.
 *              Siempre muestra el mismo mensaje, exista o no el correo.
 */

session_start();
require_once __DIR__ . '/../../../config/database.php';
require_once __DIR__ . '/../../controllers/AuthController.php';

$auth = new AuthController($pdo);
$resultado = null;
$errores = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!AuthController::validarCsrf($_POST['csrf'] ?? null)) {
        $errores[] = 'La sesión del formulario expiró. Volvé a intentarlo.';
    } else {
        $resultado = $auth->solicitarRecuperacion($_POST['correo'] ?? '');
        if (!$resultado['exito']) {
            $errores = $resultado['errores'];
        }
    }
}

$enviado = $resultado !== null && $resultado['exito'];
$csrf = AuthController::tokenCsrf();
?>
<!DOCTYPE html>
<html lang="es">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Recuperar contraseña — MudaConecta</title>
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
    <h1>¿Olvidaste tu contraseña?</h1>
    <p>Escribí el correo de tu cuenta y te enviamos un enlace para crear una nueva. El enlace dura 30 minutos.</p>
    <div class="ruta-puntos">
      <span>Origen</span><span class="linea"></span><span>Destino</span>
    </div>
  </div>

  <div class="lado-form">
    <div class="card">
      <h2>Recuperar contraseña</h2>
      <p class="sub">Te mandamos el enlace por correo.</p>

      <?php if (!empty($errores)): ?>
        <div class="alerta alerta-error">
          <?php foreach ($errores as $e) echo h($e) . '<br>'; ?>
        </div>
      <?php endif; ?>

      <?php if ($enviado): ?>
        <div class="alerta alerta-ok">
          Si el correo está registrado, te enviamos un enlace para restablecer tu contraseña.
          Revisá tu bandeja de entrada (y la carpeta de spam). El enlace vence en 30 minutos.
        </div>
        <?php if (!empty($resultado['enlace_local'])): ?>
          <div class="alerta alerta-aviso">
            <strong>Modo local: no se pudo enviar el correo.</strong>
            Como estás en este mismo equipo, usá este enlace para continuar:
            <a class="enlace-local" href="<?= h($resultado['enlace_local']) ?>"><?= h($resultado['enlace_local']) ?></a>
          </div>
        <?php endif; ?>
      <?php else: ?>
        <form method="POST" id="formRecuperar" novalidate>
          <input type="hidden" name="csrf" value="<?= h($csrf) ?>">
          <div class="campo">
            <label for="correo">Correo electrónico</label>
            <input type="email" name="correo" id="correo" required autocomplete="email"
                   value="<?= h($_POST['correo'] ?? '') ?>">
            <div class="msg-error">Ingresá un correo válido.</div>
          </div>
          <button type="submit" class="btn-enviar">Enviar enlace</button>
        </form>
      <?php endif; ?>

      <div class="enlaces">
        <p><a href="login.php">Volver a iniciar sesión</a></p>
      </div>
    </div>
  </div>
</div>

<script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>
<script src="../../../public/assets/js/validaciones.js"></script>
</body>
</html>
