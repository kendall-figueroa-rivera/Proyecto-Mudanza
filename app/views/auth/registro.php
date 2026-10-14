<?php
/**
 * Vista: registro.php
 * Modulo: Autenticacion
 * Responsable: Kendall Figueroa
 * Descripcion: Formulario de registro (cliente/empresa), conectado a AuthController.
 */

session_start();
require_once __DIR__ . '/../../../config/database.php';
require_once __DIR__ . '/../../controllers/AuthController.php';

$auth = new AuthController($pdo);
$resultado = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $resultado = $auth->registrar($_POST);

    if ($resultado['exito']) {
        header('Location: login.php?registrado=1');
        exit;
    }
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
  <meta charset="UTF-8">
  <title>Crear cuenta - Plataforma de Mudanzas</title>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
  <link rel="stylesheet" href="../../../public/assets/css/estilos.css">
</head>
<body class="bg-light">
<div class="container d-flex justify-content-center align-items-center py-5" style="min-height:100vh;">
  <div class="card shadow-sm" style="max-width:480px; width:100%;">
    <div class="card-body p-4">
      <h4 class="mb-3 text-center">Crear cuenta</h4>

      <?php if ($resultado && !$resultado['exito']): ?>
        <div class="alert alert-danger py-2">
          <?php foreach ($resultado['errores'] as $e) echo htmlspecialchars($e) . '<br>'; ?>
        </div>
      <?php endif; ?>

      <form method="POST" id="formRegistro" novalidate>
        <div class="mb-3">
          <label class="form-label">Tipo de cuenta</label>
          <select class="form-select" name="tipo_cuenta" id="tipo_cuenta" required>
            <option value="cliente">Cliente</option>
            <option value="empresa">Empresa de transporte</option>
          </select>
        </div>

        <div class="mb-3">
          <label class="form-label">Nombre completo</label>
          <input type="text" class="form-control" name="nombre" required>
        </div>

        <div id="camposEmpresa" style="display:none;">
          <div class="mb-3">
            <label class="form-label">Nombre comercial</label>
            <input type="text" class="form-control" name="nombre_comercial">
          </div>
          <div class="mb-3">
            <label class="form-label">Cédula jurídica</label>
            <input type="text" class="form-control" name="identificacion">
          </div>
        </div>

        <div class="mb-3">
          <label class="form-label">Correo electrónico</label>
          <input type="email" class="form-control" name="correo" id="correo" required>
          <div class="invalid-feedback">Ingresa un correo válido.</div>
        </div>
        <div class="mb-3">
          <label class="form-label">Teléfono</label>
          <input type="text" class="form-control" name="telefono">
        </div>
        <div class="mb-3">
          <label class="form-label">Contraseña</label>
          <input type="password" class="form-control" name="password" id="password" required minlength="8">
          <div class="invalid-feedback">Debe tener al menos 8 caracteres.</div>
        </div>
        <div class="mb-3">
          <label class="form-label">Confirmar contraseña</label>
          <input type="password" class="form-control" name="password_confirm" id="password_confirm" required>
          <div class="invalid-feedback">Las contraseñas no coinciden.</div>
        </div>
        <button type="submit" class="btn btn-primary w-100">Crear cuenta</button>
      </form>

      <p class="text-center mt-3 mb-0">
        ¿Ya tienes cuenta? <a href="login.php">Inicia sesión</a>
      </p>
    </div>
  </div>
</div>

<script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>
<script src="../../../public/assets/js/validaciones.js"></script>
</body>
</html>
