<?php
/**
 * Vista: buscar.php
 * Modulo: Cliente
 * Responsable: [nombre del integrante]
 * Descripcion: Busqueda de empresas por ruta exacta (zona de origen y destino)
 *              para el cliente con sesion iniciada (RF-09). Muestra solo
 *              empresas con tarifa activa para esa ruta, con el monto
 *              estimado, y permite generar la proforma directamente.
 */

session_start();
require_once __DIR__ . '/../../../config/database.php';
require_once __DIR__ . '/../../controllers/ClienteController.php';

$idCliente = ClienteController::exigirCliente();
$cliente   = new ClienteController($pdo);

$zonas   = $cliente->listarZonas();
$origen  = (int) ($_GET['origen'] ?? 0);
$destino = (int) ($_GET['destino'] ?? 0);
$orden   = ($_GET['orden'] ?? '') === 'calificacion' ? 'calificacion' : 'precio';

$buscado    = $origen > 0 && $destino > 0;
$resultados = $buscado ? $cliente->buscarRutas($origen, $destino, $orden) : [];

// Agrupa las zonas por provincia para mostrarlas en <optgroup>
$zonasPorProvincia = [];
$nombresZona = [];
foreach ($zonas as $z) {
    $zonasPorProvincia[$z['provincia']][] = $z;
    $nombresZona[(int) $z['id_zona']] = $z['nombre'];
}

$csrf = ClienteController::tokenCsrf();
$paginaActual = 'buscar';
?>
<!DOCTYPE html>
<html lang="es">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Buscar por ruta — MudaConecta</title>
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
  <h1>Buscar por ruta</h1>
  <p class="intro">Elige de dónde a dónde te mudas y compara las empresas que tienen tarifa para esa ruta.</p>

  <form class="panel-oscuro mb-4" method="GET" action="buscar.php" id="formRuta" novalidate>
    <div class="row g-3 align-items-end">
      <?php foreach (['origen' => 'Desde', 'destino' => 'Hacia'] as $campo => $etiqueta):
        $seleccionada = $campo === 'origen' ? $origen : $destino; ?>
        <div class="col-md-4">
          <label class="form-label" for="<?= $campo ?>"><?= $etiqueta ?></label>
          <select class="form-select" id="<?= $campo ?>" name="<?= $campo ?>" required>
            <option value="">Elige una zona</option>
            <?php foreach ($zonasPorProvincia as $provincia => $lista): ?>
              <optgroup label="<?= h($provincia) ?>">
                <?php foreach ($lista as $z): ?>
                  <option value="<?= (int) $z['id_zona'] ?>" <?= (int) $z['id_zona'] === $seleccionada ? 'selected' : '' ?>>
                    <?= h($z['nombre']) ?>
                  </option>
                <?php endforeach; ?>
              </optgroup>
            <?php endforeach; ?>
          </select>
          <div class="invalid-feedback">Elige una zona.</div>
        </div>
      <?php endforeach; ?>
      <div class="col-md-2">
        <label class="form-label" for="orden">Ordenar por</label>
        <select class="form-select" id="orden" name="orden">
          <option value="precio" <?= $orden === 'precio' ? 'selected' : '' ?>>Menor precio</option>
          <option value="calificacion" <?= $orden === 'calificacion' ? 'selected' : '' ?>>Mejor calificación</option>
        </select>
      </div>
      <div class="col-md-2 d-grid">
        <button class="btn btn-mostaza" type="submit">Buscar</button>
      </div>
    </div>
  </form>

  <?php if (!$buscado): ?>
    <div class="vacio">
      <p>Elige la zona de origen y la de destino para ver precios.</p>
      <a href="../publico/directorio.php">O revisa el directorio completo de empresas</a>
    </div>
  <?php elseif (empty($resultados)): ?>
    <div class="vacio">
      <p>Ninguna empresa tiene tarifa para la ruta <?= h($nombresZona[$origen] ?? '') ?> a <?= h($nombresZona[$destino] ?? '') ?> todavía.</p>
      <a class="btn btn-contorno" href="../publico/directorio.php">Ver empresas por zona</a>
    </div>
  <?php else: ?>
    <p class="texto-suave">
      <?= count($resultados) ?> <?= count($resultados) === 1 ? 'empresa cubre' : 'empresas cubren' ?> esta ruta.
      Los montos son estimados; el precio exacto queda fijo al generar la proforma.
    </p>

    <?php foreach ($resultados as $r):
      $promedio = (float) $r['promedio'];
      $llenas   = (int) round($promedio); ?>
      <article class="boleto">
        <div class="row g-3 align-items-center">
          <div class="col-md-4">
            <a class="empresa" href="../publico/perfil_empresa.php?id=<?= (int) $r['id_empresa'] ?>"><?= h($r['nombre_comercial']) ?></a>
            <div class="estrellas">
              <?= str_repeat('★', $llenas) ?><span class="vacias"><?= str_repeat('★', 5 - $llenas) ?></span>
              <span class="texto-suave">
                <?= (int) $r['total_resenas'] > 0 ? number_format($promedio, 1) . ' (' . (int) $r['total_resenas'] . ')' : 'Sin reseñas' ?>
              </span>
            </div>
          </div>
          <div class="col-md-4">
            <div class="ruta">
              <span><?= h($nombresZona[$origen] ?? '') ?></span>
              <span class="punto"></span><span class="tramo"></span>
              <span class="km"><?= number_format((float) $r['distancia_km'], 1, ',', '.') ?> km</span>
              <span class="tramo"></span><span class="punto fin"></span>
              <span><?= h($nombresZona[$destino] ?? '') ?></span>
            </div>
          </div>
          <div class="col-6 col-md-2">
            <div class="meta">Estimado</div>
            <div class="monto"><?= colones($r['monto_estimado']) ?></div>
          </div>
          <div class="col-6 col-md-2 text-end">
            <form method="POST" action="proforma.php">
              <input type="hidden" name="csrf" value="<?= h($csrf) ?>">
              <input type="hidden" name="id_empresa" value="<?= (int) $r['id_empresa'] ?>">
              <input type="hidden" name="zona_origen" value="<?= $origen ?>">
              <input type="hidden" name="zona_destino" value="<?= $destino ?>">
              <button class="btn btn-mostaza" type="submit">Generar proforma</button>
            </form>
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
