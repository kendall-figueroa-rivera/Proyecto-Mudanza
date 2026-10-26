<?php
/**
 * Vista: solicitudes.php
 * Modulo: Cliente
 * Responsable: [nombre del integrante]
 * Descripcion: Solicitud de servicio a partir de una proforma vigente usando
 *              sp_crear_solicitud (RF-14), y consulta y cancelacion de las
 *              solicitudes del cliente (RF-15).
 *              Con ?proforma=N muestra el formulario para crear la solicitud.
 */

session_start();
require_once __DIR__ . '/../../../config/database.php';
require_once __DIR__ . '/../../controllers/ClienteController.php';

$idCliente = ClienteController::exigirCliente();
$cliente   = new ClienteController($pdo);
$errores   = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $accion = $_POST['accion'] ?? '';

    if (!ClienteController::validarCsrf($_POST['csrf'] ?? null)) {
        $errores[] = 'La sesión del formulario expiró. Vuelve a intentarlo.';
    } elseif ($accion === 'crear') {
        $resultado = $cliente->crearSolicitud($idCliente, $_POST);
        if ($resultado['exito']) {
            ClienteController::guardarAviso('success',
                'Solicitud #' . $resultado['id_solicitud'] . ' enviada. La empresa te responderá por aquí.');
            header('Location: solicitudes.php#solicitud-' . $resultado['id_solicitud']);
            exit;
        }
        $errores = $resultado['errores'];
    } elseif ($accion === 'cancelar') {
        $idSolicitud = (int) ($_POST['id_solicitud'] ?? 0);
        $resultado = $cliente->cancelarSolicitud($idCliente, $idSolicitud);
        if ($resultado['exito']) {
            ClienteController::guardarAviso('success', "Solicitud #$idSolicitud cancelada.");
        } else {
            ClienteController::guardarAviso('danger', implode(' ', $resultado['errores']));
        }
        header('Location: solicitudes.php');
        exit;
    }
}

// Proforma de la que se quiere crear la solicitud (por GET o al reintentar el POST)
$idProforma = (int) ($_POST['id_proforma'] ?? $_GET['proforma'] ?? 0);
$proforma   = $idProforma > 0 ? $cliente->obtenerProforma($idCliente, $idProforma) : null;
$puedeSolicitar = $proforma && $proforma['estado'] === 'vigente';

$solicitudes = $cliente->listarSolicitudes($idCliente);
$aviso = ClienteController::tomarAviso();
$csrf  = ClienteController::tokenCsrf();
$paginaActual = 'solicitudes';
?>
<!DOCTYPE html>
<html lang="es">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title><?= $puedeSolicitar ? 'Solicitar servicio' : 'Mis solicitudes' ?> — MudaConecta</title>
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

  <?php if ($idProforma > 0 && !$puedeSolicitar): ?>
    <div class="alert alert-warning">
      Esa proforma ya no está vigente o no pertenece a tu cuenta.
      <a href="historial_proformas.php?estado=vigente">Ver mis proformas vigentes</a>
    </div>
  <?php endif; ?>

  <?php if ($puedeSolicitar): ?>
    <section class="mb-5">
      <h1>Solicitar servicio</h1>
      <p class="intro">Completa los datos de la mudanza. La empresa recibe un aviso y te responde si acepta o no.</p>

      <div class="row g-4">
        <div class="col-lg-7">
          <form class="panel" method="POST" action="solicitudes.php" id="formSolicitud" novalidate>
            <input type="hidden" name="csrf" value="<?= h($csrf) ?>">
            <input type="hidden" name="accion" value="crear">
            <input type="hidden" name="id_proforma" value="<?= (int) $proforma['id_proforma'] ?>">

            <div class="mb-3">
              <label class="form-label" for="direccion_origen">Dirección exacta de origen</label>
              <input class="form-control" type="text" id="direccion_origen" name="direccion_origen" maxlength="255" required
                     placeholder="Ej. 200 m norte de la iglesia, casa esquinera portón negro"
                     value="<?= h($_POST['direccion_origen'] ?? '') ?>">
              <div class="invalid-feedback">Escribe la dirección de origen.</div>
            </div>
            <div class="mb-3">
              <label class="form-label" for="direccion_destino">Dirección exacta de destino</label>
              <input class="form-control" type="text" id="direccion_destino" name="direccion_destino" maxlength="255" required
                     value="<?= h($_POST['direccion_destino'] ?? '') ?>">
              <div class="invalid-feedback">Escribe la dirección de destino.</div>
            </div>
            <div class="mb-3">
              <label class="form-label" for="fecha_mudanza">Fecha de la mudanza</label>
              <input class="form-control" type="date" id="fecha_mudanza" name="fecha_mudanza" required
                     min="<?= date('Y-m-d') ?>" value="<?= h($_POST['fecha_mudanza'] ?? '') ?>">
              <div class="invalid-feedback">Elige una fecha de hoy en adelante.</div>
            </div>
            <div class="mb-4">
              <label class="form-label" for="descripcion">Qué hay que mover (opcional)</label>
              <textarea class="form-control" id="descripcion" name="descripcion" rows="4" maxlength="500"
                        data-contador="contadorDescripcion"
                        placeholder="Ej. Apartamento de 2 cuartos, refrigeradora, lavadora, 15 cajas. Segundo piso sin ascensor."><?= h($_POST['descripcion'] ?? '') ?></textarea>
              <div class="form-text" id="contadorDescripcion"></div>
              <div class="invalid-feedback">Máximo 500 caracteres.</div>
            </div>
            <div class="d-flex gap-2 flex-wrap">
              <button class="btn btn-mostaza" type="submit">Enviar solicitud</button>
              <a class="btn btn-contorno" href="proforma.php?id=<?= (int) $proforma['id_proforma'] ?>">Volver a la proforma</a>
            </div>
          </form>
        </div>

        <aside class="col-lg-5">
          <article class="boleto vigente">
            <div class="meta">Proforma #<?= (int) $proforma['id_proforma'] ?></div>
            <div class="empresa mb-3"><?= h($proforma['nombre_comercial']) ?></div>
            <div class="ruta mb-3">
              <span><?= h($proforma['zona_origen']) ?></span>
              <span class="punto"></span><span class="tramo"></span><span class="punto fin"></span>
              <span><?= h($proforma['zona_destino']) ?></span>
            </div>
            <div class="meta">Monto total</div>
            <div class="monto"><?= colones($proforma['monto_total']) ?></div>
          </article>
        </aside>
      </div>
    </section>
  <?php endif; ?>

  <section>
    <?php if ($puedeSolicitar): ?>
      <h2>Mis solicitudes</h2>
    <?php else: ?>
      <h1>Mis solicitudes</h1>
      <p class="intro">Aquí ves el estado de cada mudanza. Puedes cancelar las que estén pendientes o aceptadas antes de la fecha.</p>
    <?php endif; ?>

    <?php if (empty($solicitudes)): ?>
      <div class="vacio">
        <p>Todavía no has hecho solicitudes. Primero genera una proforma y luego conviértela en solicitud.</p>
        <a class="btn btn-contorno" href="historial_proformas.php?estado=vigente">Ver mis proformas vigentes</a>
      </div>
    <?php else: ?>
      <?php foreach ($solicitudes as $s):
        $est = $s['estado']; ?>
        <article class="boleto <?= h($est) ?>" id="solicitud-<?= (int) $s['id_solicitud'] ?>">
          <div class="row g-3">
            <div class="col-md-8">
              <div class="d-flex align-items-center gap-2 flex-wrap mb-1">
                <span class="meta">Solicitud #<?= (int) $s['id_solicitud'] ?></span>
                <span class="estado <?= h($est) ?>"><?= h(ClienteController::ESTADOS_SOLICITUD[$est] ?? $est) ?></span>
              </div>
              <div class="empresa mb-2"><?= h($s['nombre_comercial']) ?></div>
              <div class="ruta mb-2">
                <span><?= h($s['zona_origen']) ?></span>
                <span class="punto"></span><span class="tramo"></span><span class="punto fin"></span>
                <span><?= h($s['zona_destino']) ?></span>
              </div>
              <dl class="row small mb-0">
                <dt class="col-sm-3">Fecha</dt>
                <dd class="col-sm-9"><?= date('d/m/Y', strtotime($s['fecha_mudanza'])) ?></dd>
                <dt class="col-sm-3">Origen</dt>
                <dd class="col-sm-9"><?= h($s['direccion_origen']) ?></dd>
                <dt class="col-sm-3">Destino</dt>
                <dd class="col-sm-9"><?= h($s['direccion_destino']) ?></dd>
                <?php if ($s['motivo_respuesta']): ?>
                  <dt class="col-sm-3">Respuesta</dt>
                  <dd class="col-sm-9"><?= h($s['motivo_respuesta']) ?></dd>
                <?php endif; ?>
                <?php if ($est === 'aceptada' && $s['telefono_empresa']): ?>
                  <dt class="col-sm-3">Teléfono</dt>
                  <dd class="col-sm-9"><?= h($s['telefono_empresa']) ?></dd>
                <?php endif; ?>
              </dl>
            </div>
            <div class="col-md-4 text-md-end">
              <div class="monto mb-3"><?= colones($s['monto_total']) ?></div>
              <?php if (ClienteController::sePuedeCancelar($s)): ?>
                <form method="POST" action="solicitudes.php" class="form-cancelar" data-solicitud="<?= (int) $s['id_solicitud'] ?>">
                  <input type="hidden" name="csrf" value="<?= h($csrf) ?>">
                  <input type="hidden" name="accion" value="cancelar">
                  <input type="hidden" name="id_solicitud" value="<?= (int) $s['id_solicitud'] ?>">
                  <button class="btn btn-sm btn-peligro" type="submit">Cancelar solicitud</button>
                </form>
              <?php elseif ($est === 'completada' && !$s['id_resena']): ?>
                <a class="btn btn-sm btn-mostaza" href="calificar.php?solicitud=<?= (int) $s['id_solicitud'] ?>">Calificar a la empresa</a>
              <?php elseif ($est === 'completada'): ?>
                <span class="meta">Ya calificaste este servicio</span>
              <?php endif; ?>
            </div>
          </div>
        </article>
      <?php endforeach; ?>
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
