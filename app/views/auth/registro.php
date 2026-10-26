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
<style>
  :root{
    --ink:#1B2A41; --paper:#F1EEE4; --paper-2:#E7E1D2;
    --mustard:#C98A2B; --slate:#3F6774; --line:#D8D0BC; --muted:#6B6A5F;
    --error:#B3433D;
  }
  *{ box-sizing:border-box; }
  body{
    margin:0; min-height:100vh; background:var(--paper); color:var(--ink);
    font-family:'Inter',sans-serif; line-height:1.5;
    display:flex; flex-direction:column;
  }
  a{ color:inherit; }
  h1{
    font-family:'Big Shoulders Display',sans-serif; font-weight:900;
    text-transform:uppercase; letter-spacing:.01em; line-height:.95; margin:0;
  }
  .nav{ display:flex; align-items:center; justify-content:space-between; padding:22px 28px; }
  .brand{ font-family:'Big Shoulders Display',sans-serif; font-weight:900; font-size:1.3rem; }
  .brand span{ color:var(--mustard); }

  .contenido{ flex:1; display:grid; grid-template-columns:1fr 1fr; align-items:stretch; }
  .lado-visual{ background:var(--ink); color:var(--paper); padding:56px; display:flex; flex-direction:column; justify-content:center; }
  .lado-visual h1{ font-size:clamp(2.2rem, 4vw, 3.2rem); max-width:12ch; }
  .lado-visual p{ color:#C9D1D8; max-width:34ch; margin-top:18px; font-size:1.02rem; }
  .lado-visual .pasos-mini{ margin-top:34px; }
  .lado-visual .pasos-mini div{ display:flex; gap:12px; align-items:baseline; margin-bottom:12px; color:#C9D1D8; font-size:.92rem; }
  .lado-visual .pasos-mini .n{ color:var(--mustard); font-weight:900; font-family:'Big Shoulders Display',sans-serif; font-size:1.3rem; }

  .lado-form{ display:flex; align-items:center; justify-content:center; padding:48px 32px; }
  .card{ width:100%; max-width:420px; }
  .card h2{ font-family:'Big Shoulders Display',sans-serif; font-weight:900; text-transform:uppercase; font-size:1.8rem; margin:0 0 6px; }
  .card .sub{ color:var(--muted); font-size:.95rem; margin:0 0 24px; }

  .tipo-cuenta{ display:flex; gap:10px; margin-bottom:22px; }
  .tipo-cuenta label{
    flex:1; text-align:center; padding:12px 10px; border:1.5px solid var(--line); border-radius:2px;
    font-size:.9rem; font-weight:600; cursor:pointer; color:var(--muted);
  }
  .tipo-cuenta input{ display:none; }
  .tipo-cuenta input:checked + span{ color:var(--ink); }
  .tipo-cuenta label:has(input:checked){ border-color:var(--mustard); background:var(--paper-2); color:var(--ink); }

  .campo{ margin-bottom:16px; }
  .campo label{ display:block; font-size:.82rem; font-weight:600; margin-bottom:6px; }
  .campo input{
    width:100%; padding:11px 14px; border:1.5px solid var(--line); border-radius:2px;
    font-family:'Inter',sans-serif; font-size:.98rem; background:#fff; color:var(--ink);
  }
  .campo input:focus{ outline:none; border-color:var(--slate); }
  .campo input.is-invalid{ border-color:var(--error); }
  .campo .msg-error{ color:var(--error); font-size:.8rem; margin-top:5px; display:none; }
  .campo input.is-invalid ~ .msg-error{ display:block; }

  #camposEmpresa{ display:none; background:var(--paper-2); padding:14px 16px 2px; border-radius:2px; margin-bottom:16px; }

  .btn-enviar{
    width:100%; padding:14px; background:var(--mustard); color:var(--ink); border:none;
    border-radius:2px; font-weight:700; font-size:1rem; cursor:pointer; margin-top:4px;
  }

  .alerta{ padding:12px 14px; border-radius:2px; font-size:.9rem; margin-bottom:18px; }
  .alerta-error{ background:#F6E4E2; color:var(--error); border:1px solid #E6B9B4; }

  .enlaces{ margin-top:20px; font-size:.9rem; color:var(--muted); text-align:center; }
  .enlaces a{ color:var(--slate); font-weight:600; border-bottom:2px solid var(--mustard); padding-bottom:1px; }

  @media (max-width:860px){
    .contenido{ grid-template-columns:1fr; }
    .lado-visual{ padding:40px 28px; }
  }
</style>
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
