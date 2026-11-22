<?php
/**
 * Vista: registro.php
 * Modulo: Autenticacion
 * Responsable: Kendall Figueroa
 * Descripcion: Formulario de registro (cliente/empresa), conectado a AuthController.
 *              Estilo visual alineado al Home (identidad MudaConecta).
 */

session_start();
require_once __DIR__ . '/../../../config/database.php';
require_once __DIR__ . '/../../controllers/AuthController.php';

$auth = new AuthController($pdo);
$resultado = null;
$tipoPreseleccionado = ($_GET['tipo'] ?? '') === 'empresa' ? 'empresa' : 'cliente';

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
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Crear cuenta — MudaConecta</title>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Big+Shoulders+Display:wght@700;900&family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
<link rel="stylesheet" href="../../../public/assets/css/registro.css">
</head>
<body>

<div class="nav">
  <a href="../publico/home.php" class="brand">Muda<span>Conecta</span></a>
</div>

<div class="contenido">
  <div class="lado-visual">
    <h1>Empezá en minutos</h1>
    <p>Ya sea que busques mudarte o que tengas una flota para ofrecer, tu cuenta te lleva directo a lo que necesitás.</p>
    <div class="pasos-mini">
      <div><span class="n">1</span> Elegí si sos cliente o empresa</div>
      <div><span class="n">2</span> Completá tus datos</div>
      <div><span class="n">3</span> Ya podés buscar o publicar</div>
    </div>
  </div>

  <div class="lado-form">
    <div class="card">
      <h2>Crear cuenta</h2>
      <p class="sub">Elegí el tipo de cuenta para empezar.</p>

      <?php if ($resultado && !$resultado['exito']): ?>
        <div class="alerta alerta-error">
          <?php foreach ($resultado['errores'] as $e) echo htmlspecialchars($e) . '<br>'; ?>
        </div>
      <?php endif; ?>

      <form method="POST" id="formRegistro" novalidate>
        <div class="tipo-cuenta">
          <label>
            <input type="radio" name="tipo_cuenta" class="radio-tipo-cuenta" value="cliente" <?= $tipoPreseleccionado === 'cliente' ? 'checked' : '' ?>>
            <span>Soy cliente</span>
          </label>
          <label>
            <input type="radio" name="tipo_cuenta" class="radio-tipo-cuenta" value="empresa" <?= $tipoPreseleccionado === 'empresa' ? 'checked' : '' ?>>
            <span>Soy empresa</span>
          </label>
        </div>

        <div class="campo">
          <label for="nombre">Nombre completo</label>
          <input type="text" name="nombre" id="nombre" required>
        </div>

        <div id="camposEmpresa" style="<?= $tipoPreseleccionado === 'empresa' ? 'display:block;' : '' ?>">
          <div class="campo">
            <label for="nombre_comercial">Nombre comercial</label>
            <input type="text" name="nombre_comercial" id="nombre_comercial">
          </div>
          <div class="campo">
            <label for="identificacion">Cédula jurídica</label>
            <input type="text" name="identificacion" id="identificacion">
          </div>
        </div>

        <div class="campo">
          <label for="correo">Correo electrónico</label>
          <input type="email" name="correo" id="correo" required>
          <div class="msg-error">Ingresá un correo válido.</div>
        </div>
        <div class="campo">
          <label for="telefono">Teléfono</label>
          <input type="text" name="telefono" id="telefono">
        </div>
        <div class="campo">
          <label for="password">Contraseña</label>
          <input type="password" name="password" id="password" required minlength="8">
          <div class="msg-error">Debe tener al menos 8 caracteres.</div>
        </div>
        <div class="campo">
          <label for="password_confirm">Confirmar contraseña</label>
          <input type="password" name="password_confirm" id="password_confirm" required>
          <div class="msg-error">Las contraseñas no coinciden.</div>
        </div>
        <button type="submit" class="btn-enviar">Crear cuenta</button>
      </form>

      <div class="enlaces">¿Ya tenés cuenta? <a href="login.php">Iniciá sesión</a></div>
    </div>
  </div>
</div>

<script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>
<script src="../../../public/assets/js/validaciones.js"></script>
</body>
</html>
