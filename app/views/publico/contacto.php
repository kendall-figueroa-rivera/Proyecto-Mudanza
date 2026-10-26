<?php
/**
 * Vista: contacto.php
 * Modulo: Cliente (vista publica)
 * Responsable: [nombre del integrante]
 * Descripcion: Formulario de contacto a soporte. Envia el mensaje por correo
 *              con ClienteController::enviarContacto y no guarda nada en la
 *              base de datos (RF-25).
 */

session_start();
require_once __DIR__ . '/../../../config/database.php';
require_once __DIR__ . '/../../controllers/ClienteController.php';

$cliente = new ClienteController($pdo);
$errores = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!ClienteController::validarCsrf($_POST['csrf'] ?? null)) {
        $errores[] = 'La sesión del formulario expiró. Vuelve a intentarlo.';
    } else {
        $resultado = $cliente->enviarContacto($_POST);
        if ($resultado['exito']) {
            ClienteController::guardarAviso('success', 'Mensaje enviado. Te respondemos al correo que indicaste.');
            header('Location: contacto.php');
            exit;
        }
        $errores = $resultado['errores'];
    }
}

// Si hay sesion se prellenan nombre y correo
$correoSesion = isset($_SESSION['id_usuario'])
    ? $cliente->obtenerCorreoUsuario((int) $_SESSION['id_usuario'])
    : '';
$valores = [
    'nombre'  => $_POST['nombre'] ?? ($_SESSION['nombre'] ?? ''),
    'correo'  => $_POST['correo'] ?? $correoSesion,
    'asunto'  => $_POST['asunto'] ?? '',
    'mensaje' => $_POST['mensaje'] ?? '',
];

$aviso = ClienteController::tomarAviso();
$csrf  = ClienteController::tokenCsrf();
$paginaActual = 'contacto';
?>
<!DOCTYPE html>
<html lang="es">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Contacto — MudaConecta</title>
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=Big+Shoulders+Display:wght@700;900&family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
  <link rel="stylesheet" href="../../../public/assets/css/estilos.css">
  <link rel="stylesheet" href="../../../public/assets/css/cliente.css">
</head>
<body>
<?php require __DIR__ . '/../partials/nav_cliente.php'; ?>

<main class="container">
  <div class="row g-4">
    <div class="col-lg-7">
      <h1>Contacto</h1>
      <p class="intro">¿Problemas con tu cuenta, una empresa o una solicitud? Escríbenos y te respondemos por correo.</p>

      <?php if ($aviso): ?>
        <div class="alert alert-<?= h($aviso['tipo']) ?>"><?= h($aviso['texto']) ?></div>
      <?php endif; ?>
      <?php if (!empty($errores)): ?>
        <div class="alert alert-danger">
          <?php foreach ($errores as $e): ?><?= h($e) ?><br><?php endforeach; ?>
        </div>
      <?php endif; ?>

      <form class="panel" method="POST" action="contacto.php" id="formContacto" novalidate>
        <input type="hidden" name="csrf" value="<?= h($csrf) ?>">
        <div class="row g-3 mb-3">
          <div class="col-sm-6">
            <label class="form-label" for="nombre">Nombre</label>
            <input class="form-control" type="text" id="nombre" name="nombre" maxlength="100" required value="<?= h($valores['nombre']) ?>">
            <div class="invalid-feedback">Escribe tu nombre.</div>
          </div>
          <div class="col-sm-6">
            <label class="form-label" for="correo">Correo</label>
            <input class="form-control" type="email" id="correo" name="correo" required value="<?= h($valores['correo']) ?>">
            <div class="invalid-feedback">Escribe un correo válido.</div>
          </div>
        </div>
        <div class="mb-3">
          <label class="form-label" for="asunto">Asunto</label>
          <input class="form-control" type="text" id="asunto" name="asunto" maxlength="120" required value="<?= h($valores['asunto']) ?>">
          <div class="invalid-feedback">Escribe el asunto.</div>
        </div>
        <div class="mb-4">
          <label class="form-label" for="mensaje">Mensaje</label>
          <textarea class="form-control" id="mensaje" name="mensaje" rows="6" maxlength="2000" required
                    data-contador="contadorMensaje"><?= h($valores['mensaje']) ?></textarea>
          <div class="form-text" id="contadorMensaje"></div>
          <div class="invalid-feedback">El mensaje debe tener al menos 10 caracteres.</div>
        </div>
        <button class="btn btn-mostaza" type="submit">Enviar mensaje</button>
      </form>
    </div>

    <aside class="col-lg-5">
      <div class="panel">
        <h2>Otras formas de contacto</h2>
        <p class="mb-2">Correo de soporte:<br>
          <a href="mailto:<?= h(ClienteController::CORREO_SOPORTE) ?>"><?= h(ClienteController::CORREO_SOPORTE) ?></a>
        </p>
        <p class="texto-suave mb-0">Si tu consulta es sobre una mudanza, incluye el número de solicitud para ayudarte más rápido.</p>
      </div>
    </aside>
  </div>
</main>

<footer class="pie">
  <div class="container d-flex justify-content-between flex-wrap gap-2">
    <span>MudaConecta</span>
    <a href="home.php">Volver al inicio</a>
  </div>
</footer>

<script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script src="../../../public/assets/js/cliente.js"></script>
</body>
</html>
