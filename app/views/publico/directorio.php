<?php
/**
 * Vista: directorio.php
 * Modulo: Cliente (vista publica)
 * Responsable: [nombre del integrante]
 * Descripcion: Directorio publico de empresas activas con busqueda y filtros
 *              por nombre, zona de origen, zona de destino y calificacion
 *              minima (RF-09). Recibe tambien los campos origen/destino del
 *              buscador del home.
 */

session_start();
require_once __DIR__ . '/../../../config/database.php';
require_once __DIR__ . '/../../controllers/ClienteController.php';

$cliente = new ClienteController($pdo);

$filtros = [
    'nombre'       => trim($_GET['nombre'] ?? ''),
    'origen'       => trim($_GET['origen'] ?? ''),
    'destino'      => trim($_GET['destino'] ?? ''),
    'calificacion' => (int) ($_GET['calificacion'] ?? 0),
];
$empresas    = $cliente->buscarEmpresas($filtros);
$hayFiltros  = $filtros['nombre'] !== '' || $filtros['origen'] !== ''
            || $filtros['destino'] !== '' || $filtros['calificacion'] > 0;
$esCliente   = ClienteController::esCliente();
$paginaActual = 'directorio';
?>
<!DOCTYPE html>
<html lang="es">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Empresas de mudanza — MudaConecta</title>
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
  <h1>Empresas de mudanza</h1>
  <p class="intro">Busca por zona, nombre o calificación. Todas las empresas de la lista fueron revisadas y aprobadas por la plataforma.</p>

  <form class="panel-oscuro mb-4" method="GET" action="directorio.php">
    <div class="row g-3 align-items-end">
      <div class="col-md-3">
        <label class="form-label" for="origen">Desde</label>
        <input class="form-control" type="text" id="origen" name="origen" placeholder="Zona o provincia"
               value="<?= h($filtros['origen']) ?>">
      </div>
      <div class="col-md-3">
        <label class="form-label" for="destino">Hacia</label>
        <input class="form-control" type="text" id="destino" name="destino" placeholder="Zona o provincia"
               value="<?= h($filtros['destino']) ?>">
      </div>
      <div class="col-md-2">
        <label class="form-label" for="nombre">Nombre</label>
        <input class="form-control" type="text" id="nombre" name="nombre" value="<?= h($filtros['nombre']) ?>">
      </div>
      <div class="col-md-2">
        <label class="form-label" for="calificacion">Calificación</label>
        <select class="form-select" id="calificacion" name="calificacion">
          <option value="0">Cualquiera</option>
          <?php for ($i = 4; $i >= 1; $i--): ?>
            <option value="<?= $i ?>" <?= $filtros['calificacion'] === $i ? 'selected' : '' ?>><?= $i ?> o más</option>
          <?php endfor; ?>
        </select>
      </div>
      <div class="col-md-2 d-grid">
        <button class="btn btn-mostaza" type="submit">Buscar</button>
      </div>
    </div>
  </form>

  <div class="d-flex justify-content-between align-items-center flex-wrap gap-2 mb-3">
    <p class="texto-suave mb-0">
      <?= count($empresas) ?> <?= count($empresas) === 1 ? 'empresa encontrada' : 'empresas encontradas' ?>
    </p>
    <?php if ($hayFiltros): ?>
      <a href="directorio.php">Quitar filtros</a>
    <?php endif; ?>
  </div>

  <?php if (empty($empresas)): ?>
    <div class="vacio">
      <p>Ninguna empresa coincide con esa búsqueda.</p>
      <a class="btn btn-contorno" href="directorio.php">Ver todas las empresas</a>
    </div>
  <?php else: ?>
    <div class="row g-3">
      <?php foreach ($empresas as $emp):
        $promedio = (float) $emp['promedio'];
        $llenas   = (int) round($promedio);
      ?>
        <div class="col-sm-6 col-lg-4">
          <article class="empresa-ficha">
            <h3><a href="perfil_empresa.php?id=<?= (int) $emp['id_empresa'] ?>"><?= h($emp['nombre_comercial']) ?></a></h3>
            <div class="estrellas mb-1" aria-label="Calificación <?= number_format($promedio, 1) ?> de 5">
              <?= str_repeat('★', $llenas) ?><span class="vacias"><?= str_repeat('★', 5 - $llenas) ?></span>
              <span class="texto-suave">
                <?= (int) $emp['total_resenas'] > 0
                    ? number_format($promedio, 1) . ' (' . (int) $emp['total_resenas'] . ')'
                    : 'Sin reseñas' ?>
              </span>
            </div>
            <div class="zonas"><?= $emp['zonas'] ? 'Trabaja en: ' . h($emp['zonas']) : 'Zonas por definir' ?></div>
            <p><?= h($emp['descripcion'] ?: 'Esta empresa aún no agregó una descripción.') ?></p>
            <div class="d-flex gap-2 flex-wrap">
              <a class="btn btn-sm btn-contorno" href="perfil_empresa.php?id=<?= (int) $emp['id_empresa'] ?>">Ver perfil</a>
              <a class="btn btn-sm btn-mostaza"
                 href="<?= $esCliente ? '../cliente/proforma.php?empresa=' . (int) $emp['id_empresa'] : '../auth/login.php' ?>">Cotizar</a>
            </div>
          </article>
        </div>
      <?php endforeach; ?>
    </div>
  <?php endif; ?>
</main>

<footer class="pie">
  <div class="container d-flex justify-content-between flex-wrap gap-2">
    <span>MudaConecta</span>
    <a href="contacto.php">¿Necesitas ayuda? Escríbenos</a>
  </div>
</footer>

<script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script src="../../../public/assets/js/cliente.js"></script>
</body>
</html>
