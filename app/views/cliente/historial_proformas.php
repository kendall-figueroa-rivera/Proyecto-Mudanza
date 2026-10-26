<?php
/**
 * Vista: historial_proformas.php
 * Modulo: Cliente
 * Responsable: [nombre del integrante]
 * Descripcion: Consulta del historial de proformas del cliente con su estado
 *              (vigente, reservada o expirada) y filtro por estado (RF-12, RF-13).
 *              En la BD los estados se guardan como vigente/aceptada/vencida.
 */

session_start();
require_once __DIR__ . '/../../../config/database.php';
require_once __DIR__ . '/../../controllers/ClienteController.php';

$idCliente = ClienteController::exigirCliente();
$cliente   = new ClienteController($pdo);

$estado = $_GET['estado'] ?? '';
if (!array_key_exists($estado, ClienteController::ESTADOS_PROFORMA)) {
    $estado = '';
}
$proformas = $cliente->listarProformas($idCliente, $estado);
$paginaActual = 'historial_proformas';
?>
<!DOCTYPE html>
<html lang="es">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Mis proformas — MudaConecta</title>
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
  <div class="d-flex justify-content-between align-items-end flex-wrap gap-3 mb-3">
    <div>
      <h1>Mis proformas</h1>
      <p class="intro mb-0">Una proforma vigente se puede convertir en solicitud. Pasados <?= ClienteController::DIAS_VIGENCIA_PROFORMA ?> días expira y hay que generar otra.</p>
    </div>
    <a class="btn btn-mostaza" href="proforma.php">Nueva proforma</a>
  </div>

  <nav class="filtros" aria-label="Filtrar por estado">
    <a href="historial_proformas.php" class="<?= $estado === '' ? 'activo' : '' ?>">Todas</a>
    <?php foreach (ClienteController::ESTADOS_PROFORMA as $clave => $texto): ?>
      <a href="historial_proformas.php?estado=<?= $clave ?>" class="<?= $estado === $clave ? 'activo' : '' ?>"><?= $texto ?></a>
    <?php endforeach; ?>
  </nav>

  <?php if (empty($proformas)): ?>
    <div class="vacio">
      <p><?= $estado === '' ? 'Todavía no has generado proformas.' : 'No tienes proformas en ese estado.' ?></p>
      <a class="btn btn-contorno" href="buscar.php">Buscar empresas por ruta</a>
    </div>
  <?php else: ?>
    <?php foreach ($proformas as $p):
      $est = $p['estado']; ?>
      <article class="boleto <?= h($est) ?>">
        <div class="row g-3 align-items-center">
          <div class="col-md-3">
            <div class="meta">Proforma #<?= (int) $p['id_proforma'] ?>, <?= date('d/m/Y', strtotime($p['fecha_generacion'])) ?></div>
            <div class="empresa"><?= h($p['nombre_comercial']) ?></div>
          </div>
          <div class="col-md-4">
            <div class="ruta">
              <span><?= h($p['zona_origen']) ?></span>
              <span class="punto"></span><span class="tramo"></span><span class="punto fin"></span>
              <span><?= h($p['zona_destino']) ?></span>
            </div>
          </div>
          <div class="col-6 col-md-2">
            <div class="monto"><?= colones($p['monto_total']) ?></div>
          </div>
          <div class="col-6 col-md-3 text-end">
            <span class="estado <?= h($est) ?> mb-2"><?= h(ClienteController::ESTADOS_PROFORMA[$est] ?? $est) ?></span>
            <div class="meta mb-2">
              <?php if ($est === 'vigente'): ?>
                Vence el <?= date('d/m/Y', strtotime($p['fecha_vence'])) ?>
              <?php elseif ($est === 'aceptada' && $p['id_solicitud']): ?>
                Solicitud #<?= (int) $p['id_solicitud'] ?>
              <?php elseif ($est === 'vencida'): ?>
                Venció el <?= date('d/m/Y', strtotime($p['fecha_vence'])) ?>
              <?php endif; ?>
            </div>
            <div class="d-flex gap-2 justify-content-end flex-wrap">
              <a class="btn btn-sm btn-contorno" href="proforma.php?id=<?= (int) $p['id_proforma'] ?>">Ver</a>
              <?php if ($est === 'vigente'): ?>
                <a class="btn btn-sm btn-mostaza" href="solicitudes.php?proforma=<?= (int) $p['id_proforma'] ?>">Solicitar</a>
              <?php endif; ?>
            </div>
          </div>
        </div>
      </article>
    <?php endforeach; ?>
  <?php endif; ?>
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
