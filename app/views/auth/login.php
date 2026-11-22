<?php
/**
 * Vista: login.php
 * Modulo: Autenticacion
 * Responsable: Kendall Figueroa
 * Descripcion: Formulario de inicio de sesion, conectado a AuthController.
 *              Estilo visual alineado al Home (identidad MudaConecta).
 */

session_start();
require_once __DIR__ . '/../../../config/database.php';
require_once __DIR__ . '/../../controllers/AuthController.php';

$auth = new AuthController($pdo);
$resultado = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $resultado = $auth->iniciarSesion($_POST['correo'] ?? '', $_POST['password'] ?? '');

    if ($resultado['exito']) {
        $destino = match ($resultado['rol']) {
            'empresa'       => '../empresa/dashboard.php',
            'administrador' => '../admin/dashboard.php',
            default         => '../cliente/dashboard.php',
        };
        header("Location: $destino");
        exit;
    }
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Iniciar sesión — MudaConecta</title>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Big+Shoulders+Display:wght@700;900&family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
<link rel="stylesheet" href="../../../public/assets/css/login.css">
</head>
<body>

<div class="nav">
  <a href="../publico/home.php" class="brand">Muda<span>Conecta</span></a>
</div>

<div class="contenido">
  <div class="lado-visual">
    <h1>Bienvenido de vuelta</h1>
    <p>Entrá a tu cuenta para seguir tus solicitudes, revisar tus proformas o gestionar tu flota.</p>
    <div class="ruta-puntos">
      <span>Origen</span><span class="linea"></span><span>Destino</span>
    </div>
  </div>

  <div class="lado-form">
    <div class="card">
      <h2>Iniciar sesión</h2>
      <p class="sub">Ingresá con tu correo y contraseña.</p>

      <?php if (isset($_GET['registrado'])): ?>
        <div class="alerta alerta-ok">Cuenta creada correctamente. Ya podés iniciar sesión.</div>
      <?php endif; ?>

      <?php if (isset($_GET['restablecida'])): ?>
        <div class="alerta alerta-ok">Tu contraseña se cambió correctamente. Ya podés iniciar sesión.</div>
      <?php endif; ?>

      <?php if ($resultado && !$resultado['exito']): ?>
        <div class="alerta alerta-error">
          <?php foreach ($resultado['errores'] as $e) echo htmlspecialchars($e) . '<br>'; ?>
        </div>
      <?php endif; ?>

      <form method="POST" id="formLogin" novalidate>
        <div class="campo">
          <label for="correo">Correo electrónico</label>
          <input type="email" name="correo" id="correo" required>
          <div class="msg-error">Ingresá un correo válido.</div>
        </div>
        <div class="campo">
          <label for="password">Contraseña</label>
          <input type="password" name="password" id="password" required minlength="8">
          <div class="msg-error">La contraseña debe tener al menos 8 caracteres.</div>
        </div>
        <button type="submit" class="btn-enviar">Entrar</button>
      </form>

      <div class="enlaces">
        <p><a href="recuperar.php">¿Olvidaste tu contraseña?</a></p>
        <p>¿No tenés cuenta? <a href="registro.php">Registrate</a></p>
      </div>
    </div>
  </div>
</div>

<script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>
<script src="../../../public/assets/js/validaciones.js"></script>
</body>
</html>
