<?php
/**
 * Vista: proforma.php
 * Modulo: Cliente
 * Responsable: [nombre del integrante]
 * Descripcion: Generacion de proformas conectada a sp_generar_proforma (RF-11).
 *              Sin parametros muestra el formulario (empresa, zona de origen y
 *              destino); con ?id=N muestra el detalle de una proforma del cliente.
 *              Acepta ?empresa=N para dejar la empresa preseleccionada.
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
        $resultado = $cliente->generarProforma($idCliente, $_POST);
        if ($resultado['exito']) {
            ClienteController::guardarAviso('success', 'Proforma generada. El monto queda fijo mientras esté vigente.');
            header('Location: proforma.php?id=' . $resultado['id_proforma']);
            exit;
        }
        $errores = $resultado['errores'];
    }
}

$idProforma = (int) ($_GET['id'] ?? 0);
$proforma   = $idProforma > 0 ? $cliente->obtenerProforma($idCliente, $idProforma) : null;
$aviso      = ClienteController::tomarAviso();

// Datos para el formulario (solo cuando no se muestra un detalle)
if (!$proforma) {
    $empresas = $cliente->listarEmpresasActivas();
    $zonas    = $cliente->listarZonas();
    $zonasPorProvincia = [];
    foreach ($zonas as $z) {
        $zonasPorProvincia[$z['provincia']][] = $z;
    }
    $empresaElegida = (int) ($_POST['id_empresa'] ?? $_GET['empresa'] ?? 0);
    $origenElegido  = (int) ($_POST['zona_origen'] ?? $_GET['origen'] ?? 0);
    $destinoElegido = (int) ($_POST['zona_destino'] ?? $_GET['destino'] ?? 0);
}

$csrf = ClienteController::tokenCsrf();
$paginaActual = 'proforma';
?>
<!DOCTYPE html>
<html lang="es">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title><?= $proforma ? 'Proforma #' . (int) $proforma['id_proforma'] : 'Cotizar mudanza' ?> — MudaConecta</title>
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

  <?php if ($proforma):
    $estado = $proforma['estado']; ?>
    <a class="d-inline-block mb-3" href="historial_proformas.php">Volver a mis proformas</a>
    <div class="row justify-content-center">
      <div class="col-lg-8">
        <article class="boleto proforma-detalle <?= h($estado) ?>">
          <div class="d-flex justify-content-between align-items-start flex-wrap gap-2 mb-3">
            <div>
              <div class="meta">Proforma #<?= (int) $proforma['id_proforma'] ?></div>
              <a class="empresa" href="../publico/perfil_empresa.php?id=<?= (int) $proforma['id_empresa'] ?>"><?= h($proforma['nombre_comercial']) ?></a>
            </div>
            <span class="estado <?= h($estado) ?>"><?= h(ClienteController::ESTADOS_PROFORMA[$estado] ?? $estado) ?></span>
          </div>

          <div class="ruta mb-4">
            <span><?= h($proforma['zona_origen']) ?></span>
            <span class="punto"></span><span class="tramo"></span>
            <span class="km"><?= number_format((float) $proforma['distancia_km'], 1, ',', '.') ?> km</span>
            <span class="tramo"></span><span class="punto fin"></span>
            <span><?= h($proforma['zona_destino']) ?></span>
          </div>

          <div class="meta">Monto total</div>
          <div class="monto"><?= colones($proforma['monto_total']) ?></div>

          <dl class="row desglose mb-0">
            <dt class="col-sm-5">Tarifa base</dt>
            <dd class="col-sm-7"><?= colones($proforma['precio_base']) ?></dd>
            <dt class="col-sm-5">Precio por kilómetro</dt>
            <dd class="col-sm-7"><?= colones($proforma['precio_por_km']) ?> × <?= number_format((float) $proforma['distancia_km'], 1, ',', '.') ?> km</dd>
            <dt class="col-sm-5">Generada</dt>
            <dd class="col-sm-7"><?= date('d/m/Y H:i', strtotime($proforma['fecha_generacion'])) ?></dd>
            <dt class="col-sm-5"><?= $estado === 'vencida' ? 'Venció' : 'Vigente hasta' ?></dt>
            <dd class="col-sm-7"><?= date('d/m/Y', strtotime($proforma['fecha_vence'])) ?></dd>
          </dl>
        </article>

        <div class="d-flex gap-2 flex-wrap">
          <?php if ($estado === 'vigente'): ?>
            <a class="btn btn-mostaza" href="solicitudes.php?proforma=<?= (int) $proforma['id_proforma'] ?>">Solicitar el servicio</a>
          <?php elseif ($proforma['id_solicitud']): ?>
            <a class="btn btn-tinta" href="solicitudes.php#solicitud-<?= (int) $proforma['id_solicitud'] ?>">Ver la solicitud #<?= (int) $proforma['id_solicitud'] ?></a>
          <?php else: ?>
            <a class="btn btn-mostaza" href="proforma.php?empresa=<?= (int) $proforma['id_empresa'] ?>">Generar una nueva proforma</a>
          <?php endif; ?>
          <a class="btn btn-contorno" href="historial_proformas.php">Ver todas mis proformas</a>
        </div>
      </div>
    </div>

  <?php else: ?>
    <?php if ($idProforma > 0): ?>
      <div class="alert alert-warning">No encontramos esa proforma en tu cuenta. Puedes generar una nueva aquí.</div>
    <?php endif; ?>

    <div class="row g-4">
      <div class="col-lg-7">
        <h1>Cotizar mudanza</h1>
        <p class="intro">Elige la empresa y la ruta. El monto se calcula con la tarifa vigente de la empresa y queda fijo durante <?= ClienteController::DIAS_VIGENCIA_PROFORMA ?> días.</p>

        <?php if (!empty($errores)): ?>
          <div class="alert alert-danger">
            <?php foreach ($errores as $e): ?><?= h($e) ?><br><?php endforeach; ?>
          </div>
        <?php endif; ?>

        <?php if (empty($empresas)): ?>
          <div class="vacio"><p>Todavía no hay empresas activas en la plataforma.</p></div>
        <?php else: ?>
          <form class="panel" method="POST" action="proforma.php" id="formProforma" novalidate>
            <input type="hidden" name="csrf" value="<?= h($csrf) ?>">
            <div class="mb-3">
              <label class="form-label" for="id_empresa">Empresa</label>
              <select class="form-select" id="id_empresa" name="id_empresa" required>
                <option value="">Elige una empresa</option>
                <?php foreach ($empresas as $emp): ?>
                  <option value="<?= (int) $emp['id_empresa'] ?>" <?= (int) $emp['id_empresa'] === $empresaElegida ? 'selected' : '' ?>>
                    <?= h($emp['nombre_comercial']) ?>
                  </option>
                <?php endforeach; ?>
              </select>
              <div class="invalid-feedback">Elige la empresa.</div>
            </div>
            <div class="row g-3 mb-4">
              <?php foreach (['zona_origen' => ['Desde', $origenElegido], 'zona_destino' => ['Hacia', $destinoElegido]] as $campo => [$etiqueta, $valor]): ?>
                <div class="col-sm-6">
                  <label class="form-label" for="<?= $campo ?>"><?= $etiqueta ?></label>
                  <select class="form-select" id="<?= $campo ?>" name="<?= $campo ?>" required>
                    <option value="">Elige una zona</option>
                    <?php foreach ($zonasPorProvincia as $provincia => $lista): ?>
                      <optgroup label="<?= h($provincia) ?>">
                        <?php foreach ($lista as $z): ?>
                          <option value="<?= (int) $z['id_zona'] ?>" <?= (int) $z['id_zona'] === $valor ? 'selected' : '' ?>><?= h($z['nombre']) ?></option>
                        <?php endforeach; ?>
                      </optgroup>
                    <?php endforeach; ?>
                  </select>
                  <div class="invalid-feedback">Elige una zona.</div>
                </div>
              <?php endforeach; ?>
            </div>
            <button class="btn btn-mostaza" type="submit">Generar proforma</button>
          </form>
        <?php endif; ?>
      </div>

      <aside class="col-lg-5">
        <div class="panel">
          <h2>¿No sabes qué empresa elegir?</h2>
          <p class="texto-suave">Busca por ruta para ver solo las empresas que cubren tu trayecto, con el precio estimado de cada una.</p>
          <a class="btn btn-contorno" href="buscar.php">Comparar por ruta</a>
        </div>
      </aside>
    </div>
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
