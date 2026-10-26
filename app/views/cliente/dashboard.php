<?php
/**
 * Vista: dashboard.php
 * Modulo: Cliente
 * Responsable: [nombre del integrante]
 * Descripcion: Panel principal del cliente: proxima mudanza, contadores de
 *              proformas vigentes, solicitudes activas y mudanzas por
 *              calificar, y notificaciones recientes.
 */

session_start();
require_once __DIR__ . '/../../../config/database.php';
require_once __DIR__ . '/../../controllers/ClienteController.php';

$idCliente = ClienteController::exigirCliente();
$cliente   = new ClienteController($pdo);

if ($_SERVER['REQUEST_METHOD'] === 'POST'
    && ($_POST['accion'] ?? '') === 'leer_notificaciones'
    && ClienteController::validarCsrf($_POST['csrf'] ?? null)) {
    $cliente->marcarNotificacionesLeidas($idCliente);
    header('Location: dashboard.php');
    exit;
}

$resumen        = $cliente->obtenerResumen($idCliente);
$proxima        = $cliente->obtenerProximaMudanza($idCliente);
$notificaciones = $cliente->listarNotificaciones($idCliente);
$hayNoLeidas    = in_array(0, array_map(fn($n) => (int) $n['leida'], $notificaciones), true);

$primerNombre = explode(' ', trim($_SESSION['nombre'] ?? ''))[0];
$csrf = ClienteController::tokenCsrf();
$paginaActual = 'dashboard';
?>
<!DOCTYPE html>
<html lang="es">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Mi panel — MudaConecta</title>
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
  <h1>Hola, <?= h($primerNombre) ?></h1>
  <p class="intro">Desde aquí sigues tus cotizaciones y mudanzas.</p>

  <div class="row g-4 mb-5">
    <div class="col-lg-7">
      <?php if ($proxima): ?>
        <article class="boleto <?= h($proxima['estado']) ?> h-100">
          <div class="d-flex justify-content-between align-items-start flex-wrap gap-2 mb-3">
            <div>
              <div class="meta">Tu próxima mudanza</div>
              <div class="monto"><?= date('d/m/Y', strtotime($proxima['fecha_mudanza'])) ?></div>
            </div>
            <span class="estado <?= h($proxima['estado']) ?>"><?= h(ClienteController::ESTADOS_SOLICITUD[$proxima['estado']] ?? $proxima['estado']) ?></span>
          </div>
          <div class="empresa mb-2"><?= h($proxima['nombre_comercial']) ?></div>
          <div class="ruta mb-3">
            <span><?= h($proxima['zona_origen']) ?></span>
            <span class="punto"></span><span class="tramo"></span><span class="punto fin"></span>
            <span><?= h($proxima['zona_destino']) ?></span>
          </div>
          <a href="solicitudes.php#solicitud-<?= (int) $proxima['id_solicitud'] ?>">Ver detalle de la solicitud</a>
        </article>
      <?php else: ?>
        <div class="panel-oscuro h-100 d-flex flex-column justify-content-center">
          <h2 class="mb-2">¿Te vas a mudar?</h2>
          <p class="mb-3">Compara empresas que cubren tu ruta y genera una proforma con el precio fijo.</p>
          <div><a class="btn btn-mostaza" href="buscar.php">Buscar por ruta</a></div>
        </div>
      <?php endif; ?>
    </div>

    <div class="col-lg-5">
      <div class="row g-4">
        <div class="col-4 col-lg-12">
          <a class="contador" href="historial_proformas.php?estado=vigente">
            <span class="numero"><?= (int) $resumen['proformas_vigentes'] ?></span>
            <span class="etiqueta d-block">Proformas vigentes</span>
          </a>
        </div>
        <div class="col-4 col-lg-12">
          <a class="contador" href="solicitudes.php">
            <span class="numero"><?= (int) $resumen['solicitudes_activas'] ?></span>
            <span class="etiqueta d-block">Solicitudes en curso</span>
          </a>
        </div>
        <div class="col-4 col-lg-12">
          <a class="contador" href="calificar.php">
            <span class="numero"><?= (int) $resumen['por_calificar'] ?></span>
            <span class="etiqueta d-block">Por calificar</span>
          </a>
        </div>
      </div>
    </div>
  </div>

  <section class="panel">
    <div class="d-flex justify-content-between align-items-center flex-wrap gap-2 mb-2">
      <h2 class="mb-0">Avisos</h2>
      <?php if ($hayNoLeidas): ?>
        <form method="POST" action="dashboard.php">
          <input type="hidden" name="csrf" value="<?= h($csrf) ?>">
          <input type="hidden" name="accion" value="leer_notificaciones">
          <button class="btn btn-sm btn-contorno" type="submit">Marcar todo como leído</button>
        </form>
      <?php endif; ?>
    </div>
    <?php if (empty($notificaciones)): ?>
      <p class="texto-suave mb-0">Aquí te avisamos cuando una empresa responda tus solicitudes.</p>
    <?php else: ?>
      <ul class="notificaciones">
        <?php foreach ($notificaciones as $n): ?>
          <li class="<?= (int) $n['leida'] === 0 ? 'nueva' : '' ?>">
            <?= h($n['mensaje']) ?>
            <time datetime="<?= h(date('c', strtotime($n['fecha']))) ?>"><?= date('d/m/Y H:i', strtotime($n['fecha'])) ?></time>
          </li>
        <?php endforeach; ?>
      </ul>
    <?php endif; ?>
  </section>
</main>

<footer class="pie">
  <div class="container d-flex justify-content-between flex-wrap gap-2">
    <span>MudaConecta</span>
    <a href="../publico/contacto.php">¿Necesitas ayuda? Escríbenos</a>
  </div>
</footer>

<script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script src="../../../public/assets/js/cliente.js"></script>
</body>
</html>
