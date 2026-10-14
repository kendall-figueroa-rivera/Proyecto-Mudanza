<?php
/**
 * Vista: login.php
 * Modulo: Autenticacion
 * Responsable: Kendall Figueroa
 * Descripcion: Formulario de inicio de sesion, conectado a AuthController.
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
  <title>Iniciar sesión - Plataforma de Mudanzas</title>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
  <link rel="stylesheet" href="../../../public/assets/css/estilos.css">
</head>
<body class="bg-light">
<div class="container d-flex justify-content-center align-items-center" style="min-height:100vh;">
  <div class="card shadow-sm" style="max-width:420px; width:100%;">
    <div class="card-body p-4">
      <h4 class="mb-3 text-center">Iniciar sesión</h4>

      <?php if (isset($_GET['registrado'])): ?>
        <div class="alert alert-success py-2">Cuenta creada correctamente. Ya puedes iniciar sesión.</div>
      <?php endif; ?>

      <?php if ($resultado && !$resultado['exito']): ?>
        <div class="alert alert-danger py-2">
          <?php foreach ($resultado['errores'] as $e) echo htmlspecialchars($e) . '<br>'; ?>
        </div>
      <?php endif; ?>

      <form method="POST" id="formLogin" novalidate>
        <div class="mb-3">
          <label class="form-label">Correo electrónico</label>
          <input type="email" class="form-control" name="correo" id="correo" required>
          <div class="invalid-feedback">Ingresa un correo válido.</div>
        </div>
        <div class="mb-3">
          <label class="form-label">Contraseña</label>
          <input type="password" class="form-control" name="password" id="password" required minlength="8">
          <div class="invalid-feedback">La contraseña debe tener al menos 8 caracteres.</div>
        </div>
        <button type="submit" class="btn btn-primary w-100">Entrar</button>
      </form>

      <p class="text-center mt-3 mb-0">
        <a href="recuperar.php">¿Olvidaste tu contraseña?</a>
      </p>
      <p class="text-center mt-2 mb-0">
        ¿No tienes cuenta? <a href="registro.php">Regístrate</a>
      </p>
    </div>
  </div>
</div>

<script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>
<script src="../../../public/assets/js/validaciones.js"></script>
</body>
</html>
