<?php
/**
 * Vista: calificar.php
 * Modulo: Cliente
 * Responsable: [nombre del integrante]
 * Descripcion: Calificacion (1 a 5 estrellas) y resena de una mudanza
 *              completada (RF-17, RF-18). Con ?solicitud=N muestra el
 *              formulario; sin parametros lista las mudanzas por calificar y
 *              las resenas que el cliente ya escribio.
 */

session_start();
require_once __DIR__ . '/../../../config/database.php';
require_once __DIR__ . '/../../controllers/ClienteController.php';

$idCliente = ClienteController::exigirCliente();
$cliente   = new ClienteController($pdo);
$errores   = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!ClienteController::validarCsrf($_POST['csrf'] ?? null)) {
        $errores[] = 'La sesión del formulario expiró. Vuelve a intentarlo.';
    } else {
        $resultado = $cliente->registrarResena($idCliente, $_POST);
        if ($resultado['exito']) {
            ClienteController::guardarAviso('success', 'Gracias. Tu reseña ya aparece en el perfil de la empresa.');
            header('Location: calificar.php');
            exit;
        }
        $errores = $resultado['errores'];
    }
}

$idSolicitud = (int) ($_POST['id_solicitud'] ?? $_GET['solicitud'] ?? 0);
$solicitud   = $idSolicitud > 0 ? $cliente->obtenerSolicitudParaCalificar($idCliente, $idSolicitud) : null;

if (!$solicitud) {
    $pendientes = $cliente->listarPendientesDeCalificar($idCliente);
    $resenas    = $cliente->listarResenasCliente($idCliente);
}

$calificacionPrevia = (int) ($_POST['calificacion'] ?? 0);
$aviso = ClienteController::tomarAviso();
$csrf  = ClienteController::tokenCsrf();
$paginaActual = 'calificar';
$textosEstrellas = [1 => 'Muy mala', 2 => 'Mala', 3 => 'Regular', 4 => 'Buena', 5 => 'Excelente'];
?>
<!DOCTYPE html>
<html lang="es">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title><?= $solicitud ? 'Calificar servicio' : 'Mis reseñas' ?> — MudaConecta</title>
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
  <?php if ($aviso): ?>
    <div class="alert alert-<?= h($aviso['tipo']) ?>"><?= h($aviso['texto']) ?></div>
  <?php endif; ?>
  <?php if (!empty($errores)): ?>
    <div class="alert alert-danger">
      <?php foreach ($errores as $e): ?><?= h($e) ?><br><?php endforeach; ?>
    </div>
  <?php endif; ?>

  <?php if ($solicitud): ?>
    <div class="row justify-content-center">
      <div class="col-lg-7">
        <h1>Calificar servicio</h1>
        <p class="intro">
          Mudanza del <?= date('d/m/Y', strtotime($solicitud['fecha_mudanza'])) ?> con
          <strong><?= h($solicitud['nombre_comercial']) ?></strong>,
          de <?= h($solicitud['zona_origen']) ?> a <?= h($solicitud['zona_destino']) ?>.
          Tu reseña se publica en el perfil de la empresa con tu nombre.
        </p>

        <form class="panel" method="POST" action="calificar.php" id="formResena" novalidate>
          <input type="hidden" name="csrf" value="<?= h($csrf) ?>">
          <input type="hidden" name="id_solicitud" value="<?= (int) $solicitud['id_solicitud'] ?>">

          <fieldset class="mb-4">
            <legend class="form-label fs-6">¿Cómo fue el servicio?</legend>
            <div class="d-flex align-items-center gap-3 flex-wrap">
              <div class="selector-estrellas">
                <?php for ($i = 5; $i >= 1; $i--): ?>
                  <input type="radio" name="calificacion" id="estrella<?= $i ?>" value="<?= $i ?>"
                         <?= $calificacionPrevia === $i ? 'checked' : '' ?>>
                  <label for="estrella<?= $i ?>" title="<?= $textosEstrellas[$i] ?>">★<span class="visually-hidden"><?= $i ?> de 5, <?= $textosEstrellas[$i] ?></span></label>
                <?php endfor; ?>
              </div>
              <span class="texto-estrellas" id="textoEstrellas" aria-live="polite"><?= $textosEstrellas[$calificacionPrevia] ?? '' ?></span>
            </div>
            <div class="text-danger small mt-1 d-none" id="errorEstrellas">Elige de 1 a 5 estrellas.</div>
          </fieldset>

          <div class="mb-4">
            <label class="form-label" for="comentario">Comentario (opcional)</label>
            <textarea class="form-control" id="comentario" name="comentario" rows="5" maxlength="500"
                      data-contador="contadorComentario"
                      placeholder="¿Llegaron a tiempo? ¿Cuidaron tus cosas? ¿Las recomendarías?"><?= h($_POST['comentario'] ?? '') ?></textarea>
            <div class="form-text" id="contadorComentario"></div>
            <div class="invalid-feedback">Máximo 500 caracteres.</div>
          </div>

          <div class="d-flex gap-2 flex-wrap">
            <button class="btn btn-mostaza" type="submit">Publicar reseña</button>
            <a class="btn btn-contorno" href="calificar.php">Ahora no</a>
          </div>
        </form>
      </div>
    </div>

  <?php else: ?>
    <?php if ($idSolicitud > 0): ?>
      <div class="alert alert-warning">Esa mudanza no se puede calificar: todavía no está completada o ya tiene reseña.</div>
    <?php endif; ?>

    <h1>Mis reseñas</h1>
    <p class="intro">Puedes calificar cada mudanza una sola vez, después de que la empresa la marque como completada.</p>

    <section class="mb-5">
      <h2>Por calificar</h2>
      <?php if (empty($pendientes)): ?>
        <div class="vacio"><p class="mb-0">No tienes mudanzas pendientes de calificar.</p></div>
      <?php else: ?>
        <?php foreach ($pendientes as $p): ?>
          <article class="boleto completada">
            <div class="row g-3 align-items-center">
              <div class="col-md-4">
                <div class="meta">Solicitud #<?= (int) $p['id_solicitud'] ?>, <?= date('d/m/Y', strtotime($p['fecha_mudanza'])) ?></div>
                <div class="empresa"><?= h($p['nombre_comercial']) ?></div>
              </div>
              <div class="col-md-5">
                <div class="ruta">
                  <span><?= h($p['zona_origen']) ?></span>
                  <span class="punto"></span><span class="tramo"></span><span class="punto fin"></span>
                  <span><?= h($p['zona_destino']) ?></span>
                </div>
              </div>
              <div class="col-md-3 text-md-end">
                <a class="btn btn-mostaza" href="calificar.php?solicitud=<?= (int) $p['id_solicitud'] ?>">Calificar</a>
              </div>
            </div>
          </article>
        <?php endforeach; ?>
      <?php endif; ?>
    </section>

    <section>
      <h2>Reseñas publicadas</h2>
      <?php if (empty($resenas)): ?>
        <p class="texto-suave">Todavía no has escrito reseñas.</p>
      <?php else: ?>
        <div class="panel">
          <?php foreach ($resenas as $r): ?>
            <article class="resena">
              <div class="d-flex justify-content-between flex-wrap gap-2">
                <span class="autor"><?= h($r['nombre_comercial']) ?></span>
                <span class="estrellas" aria-label="<?= (int) $r['calificacion'] ?> de 5">
                  <?= str_repeat('★', (int) $r['calificacion']) ?><span class="vacias"><?= str_repeat('★', 5 - (int) $r['calificacion']) ?></span>
                </span>
              </div>
              <?php if ($r['comentario']): ?>
                <p class="mb-1 mt-2"><?= nl2br(h($r['comentario'])) ?></p>
              <?php endif; ?>
              <span class="texto-suave small">
                <?= date('d/m/Y', strtotime($r['fecha'])) ?>
                <?= $r['estado'] === 'oculta' ? '(oculta por moderación)' : '' ?>
              </span>
            </article>
          <?php endforeach; ?>
        </div>
      <?php endif; ?>
    </section>
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
