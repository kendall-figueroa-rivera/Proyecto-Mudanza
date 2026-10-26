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
            default         => '../publico/home.php',
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

  .contenido{
    flex:1; display:grid; grid-template-columns:1fr 1fr; align-items:stretch;
  }
  .lado-visual{
    background:var(--ink); color:var(--paper); padding:56px; display:flex; flex-direction:column; justify-content:center;
  }
  .lado-visual h1{ font-size:clamp(2.2rem, 4vw, 3.2rem); max-width:11ch; }
  .lado-visual p{ color:#C9D1D8; max-width:34ch; margin-top:18px; font-size:1.02rem; }
  .ruta-puntos{ display:flex; align-items:center; gap:6px; margin-top:38px; color:#8FA0AC; font-size:.85rem; }
  .ruta-puntos .linea{
    flex:none; width:60px; height:1px;
    background-image:linear-gradient(to right, #8FA0AC 40%, transparent 0%);
    background-size:8px 1px; background-repeat:repeat-x;
  }

  .lado-form{ display:flex; align-items:center; justify-content:center; padding:48px 32px; }
  .card{ width:100%; max-width:380px; }
  .card h2{
    font-family:'Big Shoulders Display',sans-serif; font-weight:900; text-transform:uppercase;
    font-size:1.8rem; margin:0 0 6px;
  }
  .card .sub{ color:var(--muted); font-size:.95rem; margin:0 0 28px; }

  .campo{ margin-bottom:18px; }
  .campo label{ display:block; font-size:.82rem; font-weight:600; margin-bottom:6px; color:var(--ink); }
  .campo input{
    width:100%; padding:12px 14px; border:1.5px solid var(--line); border-radius:2px;
    font-family:'Inter',sans-serif; font-size:1rem; background:#fff; color:var(--ink);
  }
  .campo input:focus{ outline:none; border-color:var(--slate); }
  .campo input.is-invalid{ border-color:var(--error); }
  .campo .msg-error{ color:var(--error); font-size:.8rem; margin-top:5px; display:none; }
  .campo input.is-invalid ~ .msg-error{ display:block; }

  .btn-enviar{
    width:100%; padding:14px; background:var(--mustard); color:var(--ink); border:none;
    border-radius:2px; font-weight:700; font-size:1rem; cursor:pointer; margin-top:6px;
  }
  .btn-enviar:hover{ filter:brightness(0.95); }

  .alerta{
    padding:12px 14px; border-radius:2px; font-size:.9rem; margin-bottom:20px;
  }
  .alerta-error{ background:#F6E4E2; color:var(--error); border:1px solid #E6B9B4; }
  .alerta-ok{ background:#E3EBE4; color:#3D6B40; border:1px solid #BBD4BD; }

  .enlaces{ margin-top:22px; font-size:.9rem; color:var(--muted); text-align:center; }
  .enlaces a{ color:var(--slate); font-weight:600; border-bottom:2px solid var(--mustard); padding-bottom:1px; }
  .enlaces p{ margin:8px 0; }

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
